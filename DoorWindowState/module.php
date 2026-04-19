<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/SensorHelper.php';
require_once __DIR__ . '/../libs/WindowStateHelper.php';

class DoorWindowState extends IPSModuleStrict
{
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
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

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
            'Window.State',
            0,
            $mode === self::MODE_TILT
        );

        // Sensoren überwachen
        $this->RegisterSensorMessages();

        // Initial berechnen
        $this->UpdateState();
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
            $this->UpdateState();
        }
    }

    private function UpdateState(): void
    {
        $topID = $this->ReadPropertyInteger('SensorTop');
        $bottomID = $this->ReadPropertyInteger('SensorBottom');

        if (!@IPS_VariableExists($topID) || !@IPS_VariableExists($bottomID)) {
            return;
        }

        $top = SensorHelper::GetState($topID);
        $bottom = SensorHelper::GetState($bottomID);

        if ($top === null || $bottom === null) {
            return;
        }

        $state = WindowStateHelper::Evaluate($top, $bottom);

        // Bool immer setzen (~Window.Reversed)
        $this->SetValue('Open', $state === WindowStateHelper::STATE_CLOSED);

        // State nur im Tilt-Mode
        if ($this->ReadPropertyInteger('Mode') === self::MODE_TILT) {
            $this->SetValue('State', $state);
        }
    }
}
