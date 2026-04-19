<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/SensorHelper.php';
require_once __DIR__ . '/../libs/WindowStateHelper.php';
require_once __DIR__ . '/../libs/VariableProfileHelper.php';

class DoorWindowState extends IPSModuleStrict
{
    use VariableProfileHelper;

    // Modes
    private const MODE_BINARY = 0;
    private const MODE_TILT   = 1;
    private const HANDLE_IGNORE   = 0;
    private const HANDLE_OVERRIDE = 1;
    private const HANDLE_REFINE   = 2;

    public function Create(): void
    {
        parent::Create();

        // Properties
        $this->RegisterPropertyInteger('Mode', 0);
        $this->RegisterPropertyInteger('SensorTop', 0);
        $this->RegisterPropertyInteger('SensorBottom', 0);
        $this->RegisterPropertyInteger('SensorHandle', 0);
        $this->RegisterPropertyInteger('HandleMode', 2);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->MaintainVariable(
            'HandlePosition',
            'Griffposition',
            VARIABLETYPE_INTEGER,
            'DWS.HandlePosition',
            2,
            $this->ReadPropertyInteger('SensorHandle') > 0
        );
        
        // Optional Debug / Rohwert
        $this->MaintainVariable(
            'HandleRaw',
            'Griff Rohwert',
            VARIABLETYPE_STRING,
            '',
            3,
            false // optional später aktivieren
        );
        
        // Profil anlegen (WICHTIG: vor MaintainVariable!)
        $this->RegisterProfiles();

        $mode = $this->ReadPropertyInteger('Mode');

        // Variablen
        $this->MaintainVariable(
            'Open',
            'Fenster offen',
            VARIABLETYPE_BOOLEAN,
            '~Window.Reversed',
            1,
            true
        );

        $this->MaintainVariable(
            'State',
            'Fensterstatus',
            VARIABLETYPE_INTEGER,
            'DWS.State',
            0,
            $mode === self::MODE_TILT
        );

        // Sensoren überwachen
        $this->RegisterSensorMessages();

        // Initial berechnen
        $this->UpdateState();
    }

    /**
     * Registriert alle benötigten Profile
     */
    private function RegisterProfiles(): void
    {
        $this->RegisterProfileIntegerEx(
            'DWS.State',
            'Window',
            '',
            '',
            [
                [0, 'Geschlossen', '', 0x00FF00],
                [1, 'Gekippt', '', 0xFFFF00],
                [2, 'Offen', '', 0xFF0000]
            ]
        );
        
        $this->RegisterProfileIntegerEx(
            'DWS.HandlePosition',
            'Move',
            '',
            '',
            [
                [0, 'Unten', '', 0x00FF00],
                [1, 'Oben', '', 0xFFFF00],
                [2, 'Links', '', 0x0000FF],
                [3, 'Rechts', '', 0x0000FF]
            ]
        );
    }

    private function RegisterSensorMessages(): void
    {
        $ids = [
            $this->ReadPropertyInteger('SensorTop'),
            $this->ReadPropertyInteger('SensorBottom')
        ];

        foreach ($ids as $id) {
            if ($id > 0 && @IPS_VariableExists($id)) {
                $this->RegisterMessage($id, VM_UPDATE);
            }
        }
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($Message === VM_UPDATE) {
            $this->SendDebug(__FUNCTION__, 'Sensor Update empfangen', 0);
            $this->UpdateState();
        }
    }

private function UpdateState(): void
{
    $this->SendDebug(__FUNCTION__, 'Start UpdateState', 0);

    $topID = $this->ReadPropertyInteger('SensorTop');
    $bottomID = $this->ReadPropertyInteger('SensorBottom');
    $handleID = $this->ReadPropertyInteger('SensorHandle');

    if (!@IPS_VariableExists($topID) || !@IPS_VariableExists($bottomID)) {
        $this->SendDebug('Error', 'Sensor nicht vorhanden', 0);
        return;
    }

    $top = SensorHelper::GetState($topID);
    $bottom = SensorHelper::GetState($bottomID);

    if ($top === null || $bottom === null) {
        $this->SendDebug('Error', 'Sensor liefert NULL', 0);
        return;
    }

    $this->SendDebug(
        'SensorValues',
        json_encode([
            'TopID' => $topID,
            'BottomID' => $bottomID,
            'Top' => $top,
            'Bottom' => $bottom
        ]),
        0
    );

    // 1️⃣ Basiszustand aus Sensoren
    $state = WindowStateHelper::Evaluate($top, $bottom);

    // 2️⃣ Griff einlesen und direkt in Zustand mappen
    $handleState = null;

    if ($handleID > 0 && @IPS_VariableExists($handleID)) {
        $raw = GetValueString($handleID);

        $this->SendDebug('HandleRaw', $raw, 0);

        $handleState = $this->MapHandlePosition($raw);

        if ($handleState !== null) {
            $this->SetValue('HandlePosition', $handleState);
        } else {
            $this->SendDebug('HandleMapping', 'Unbekannter Wert: ' . $raw, 0);
        }
    }

    // 3️⃣ HandleMode berücksichtigen
    $handleMode = $this->ReadPropertyInteger('HandleMode');

    if ($handleState !== null) {

        $this->SendDebug('HandleMode', (string)$handleMode, 0);
        $this->SendDebug('HandleState', (string)$handleState, 0);

        switch ($handleMode) {

            case self::HANDLE_IGNORE:
                // Griff komplett ignorieren
                break;

            case self::HANDLE_OVERRIDE:
                // Griff bestimmt Zustand komplett
                $state = $handleState;
                $this->SendDebug('HandleOverride', (string)$state, 0);
                break;

            case self::HANDLE_REFINE:
                // Sensor ist Basis, Griff verfeinert sinnvoll

                switch ($state) {

                    case WindowStateHelper::STATE_CLOSED:
                        // geschlossen bleibt geschlossen
                        break;

                    case WindowStateHelper::STATE_TILT:
                        // Griff kann erweitern
                        if ($handleState === WindowStateHelper::STATE_OPEN) {
                            $state = WindowStateHelper::STATE_OPEN;
                            $this->SendDebug('HandleRefine', 'Tilt → Open', 0);
                        }
                        break;

                    case WindowStateHelper::STATE_OPEN:
                        // offen bleibt offen
                        break;
                }
                break;
        }
    }

    $this->SendDebug('StateResult', (string)$state, 0);

    // Sonderfall sichtbar machen
    if (!$top && $bottom) {
        $this->SendDebug('Warning', 'Ungewöhnlicher Zustand: unten offen, oben zu', 0);
    }

    // 4️⃣ Werte setzen

    // Bool (~Window.Reversed)
    $this->SetValue('Open', $state === WindowStateHelper::STATE_CLOSED);

    // State nur im Tilt-Mode
    if ($this->ReadPropertyInteger('Mode') === self::MODE_TILT) {
        $this->SetValue('State', $state);
    }
}

    private function MapHandlePosition(string $value): ?int
    {
        $value = strtolower(trim($value));
    
        // Normalisierte Mapping-Tabelle
        $map = [
    
            // 🔴 Geschlossen
            'down' => 0,
            'runter' => 0,
            'closed' => 0,
            'close' => 0,
            'zu' => 0,
            'geschlossen' => 0,
            '0' => 0,
            'false' => 0,
    
            // 🟡 Gekippt
            'up' => 1,
            'rauf' => 1,
            'tilt' => 1,
            'tilted' => 1,
            'kippen' => 1,
            'gekippt' => 1,
            'ventilation' => 1,
    
            // 🟢 Offen
            'left' => 2,
            'links' => 2,
            'right' => 2,
            'rechts' => 2,
            'open' => 2,
            'opening' => 2,
            'offen' => 2,
            'opened' => 2,
            'true' => 2,
            '1' => 2
        ];
    
        if (isset($map[$value])) {
            return $map[$value];
        }
    
        // Fallback: Teilstring-Erkennung (sehr wichtig!)
        if (strpos($value, 'tilt') !== false || strpos($value, 'kipp') !== false) {
            return 1;
        }
    
        if (strpos($value, 'open') !== false || strpos($value, 'offen') !== false) {
            return 2;
        }
    
        if (strpos($value, 'close') !== false || strpos($value, 'zu') !== false) {
            return 0;
        }
    
        return null;
    }
}
