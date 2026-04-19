<?php

declare(strict_types=1);

class SensorHelper
{
    public static function GetState(int $varID): ?bool
    {
        if (!@IPS_VariableExists($varID)) {
            return null;
        }

        $value = GetValueBoolean($varID);
        $var = IPS_GetVariable($varID);
        $profile = $var['VariableProfile'];

        // Reversed Profile erkennen
        if (strpos($profile, 'Reversed') !== false) {
            return !$value;
        }

        return $value;
    }
}
