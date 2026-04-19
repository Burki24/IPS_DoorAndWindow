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

    public function Create(): void
    {
        parent::Create();

        // Properties
        $this->RegisterPropertyInteger('Mode', 0);
        $this->RegisterPropertyInteger('SensorTop', 0);
        $this->RegisterPropertyInteger('SensorBottom', 0);
        $this->RegisterPropertyInteger('SensorHandle', 0);
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

    // 2️⃣ Griff einlesen und mappen
    $handlePosition = null;

    if ($handleID > 0 && @IPS_VariableExists($handleID)) {
        $raw = GetValueString($handleID);

        $this->SendDebug('HandleRaw', $raw, 0);

        $handlePosition = $this->MapHandlePosition($raw);

        if ($handlePosition !== null) {
            $this->SetValue('HandlePosition', $handlePosition);
        } else {
            $this->SendDebug('HandleMapping', 'Unbekannter Wert: ' . $raw, 0);
        }
    }

    // 3️⃣ Prioritätslogik (Sensor > Griff)
    if ($handlePosition !== null) {

        $this->SendDebug('HandlePosition', (string)$handlePosition, 0);

        switch ($state) {

            case WindowStateHelper::STATE_CLOSED:
                // Sensor sagt zu → bleibt zu
                break;

            case WindowStateHelper::STATE_TILT:
                // Griff kann erweitern
                if ($handlePosition === 2 || $handlePosition === 3) {
                    $state = WindowStateHelper::STATE_OPEN;
                    $this->SendDebug('HandleRefine', 'Tilt → Open', 0);
                }
                break;

            case WindowStateHelper::STATE_OPEN:
                // offen bleibt offen
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
        $map = [
            'down' => 0,
            'runter' => 0,
    
            'up' => 1,
            'rauf' => 1,
    
            'left' => 2,
            'links' => 2,
    
            'right' => 3,
            'rechts' => 3
        ];
    
        $key = strtolower(trim($value));
    
        return $map[$key] ?? null;
    }
}
