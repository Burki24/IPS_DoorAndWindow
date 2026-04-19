<?php

declare(strict_types=1);

class WindowStateHelper
{
    public const STATE_CLOSED = 0;
    public const STATE_TILT   = 1;
    public const STATE_OPEN   = 2;

    public static function Evaluate(bool $top, bool $bottom): int
    {
        if ($top && $bottom) {
            return self::STATE_OPEN;
        }

        if ($top && !$bottom) {
            return self::STATE_TILT;
        }

        if (!$top && $bottom) {
            return self::STATE_OPEN; // Sonderfall
        }

        return self::STATE_CLOSED;
    }
}
