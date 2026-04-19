<?php

declare(strict_types=1);

class DoorWindowState extends IPSModule
{
    private const PROFILE_NAME = 'DWS.WindowState';

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('Sensors', '[]');
        $this->RegisterAttributeString('RegisteredSensors', '[]');

        $this->RegisterVariableInteger('State', 'State', self::PROFILE_NAME, 10);
        $this->DisableAction('State');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->EnsureProfile();
        $this->UpdateMessageRegistrations();
        $this->UpdateState();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

        if ($Message === VM_UPDATE) {
            $this->UpdateState();
        }
    }

    private function EnsureProfile(): void
    {
        if (!IPS_VariableProfileExists(self::PROFILE_NAME)) {
            IPS_CreateVariableProfile(self::PROFILE_NAME, VARIABLETYPE_INTEGER);
            IPS_SetVariableProfileAssociation(self::PROFILE_NAME, 0, 'Unknown', '', 0x808080);
            IPS_SetVariableProfileAssociation(self::PROFILE_NAME, 1, 'Closed', '', 0x00AA00);
            IPS_SetVariableProfileAssociation(self::PROFILE_NAME, 2, 'Open', '', 0xCC0000);
        }
    }

    private function UpdateMessageRegistrations(): void
    {
        $sensorConfig = $this->ReadSensorConfig();
        $newSensorIds = array_values(array_unique(array_column($sensorConfig, 'VariableID')));
        $registeredSensorIds = json_decode($this->ReadAttributeString('RegisteredSensors'), true);

        if (!is_array($registeredSensorIds)) {
            $registeredSensorIds = [];
        }

        foreach (array_diff($registeredSensorIds, $newSensorIds) as $variableId) {
            if (@IPS_VariableExists($variableId)) {
                $this->UnregisterMessage($variableId, VM_UPDATE);
            }
        }

        foreach (array_diff($newSensorIds, $registeredSensorIds) as $variableId) {
            if (@IPS_VariableExists($variableId)) {
                $this->RegisterMessage($variableId, VM_UPDATE);
            }
        }

        $this->WriteAttributeString('RegisteredSensors', json_encode($newSensorIds));
    }

    private function UpdateState(): void
    {
        $sensorConfig = $this->ReadSensorConfig();

        if ($sensorConfig === []) {
            $this->SetValue('State', 0);
            return;
        }

        $hasKnownSensorValue = false;
        foreach ($sensorConfig as $sensor) {
            $sensorState = $this->IsSensorOpen($sensor['VariableID'], (string) $sensor['OpenValue']);

            if ($sensorState === null) {
                continue;
            }

            $hasKnownSensorValue = true;

            if ($sensorState) {
                $this->SetValue('State', 2);
                return;
            }
        }

        $this->SetValue('State', $hasKnownSensorValue ? 1 : 0);
    }

    /**
     * @return array<int, array{VariableID:int, OpenValue:string}>
     */
    private function ReadSensorConfig(): array
    {
        $decoded = json_decode($this->ReadPropertyString('Sensors'), true);
        if (!is_array($decoded)) {
            return [];
        }

        $result = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }

            $enabled = $row['Enabled'] ?? true;
            if (!$enabled) {
                continue;
            }

            $variableId = (int) ($row['VariableID'] ?? 0);
            if ($variableId <= 0) {
                continue;
            }

            $result[] = [
                'VariableID' => $variableId,
                'OpenValue' => (string) ($row['OpenValue'] ?? '1')
            ];
        }

        return $result;
    }

    private function IsSensorOpen(int $variableId, string $openValue): ?bool
    {
        if (!@IPS_VariableExists($variableId)) {
            return null;
        }

        $value = GetValue($variableId);

        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (is_int($value) || is_float($value)) {
            if (is_numeric($openValue)) {
                return ((float) $value) === ((float) $openValue);
            }

            $value = (string) $value;
        } else {
            $value = trim((string) $value);
        }

        return strcasecmp((string) $value, trim($openValue)) === 0;
    }
}
