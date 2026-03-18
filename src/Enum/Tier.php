<?php

declare(strict_types=1);

namespace ItpContext\Enum;

enum Tier: int
{
    case Critical = 1;
    case Important = 2;
    case Standard = 3;

    public function requiresProof(): bool
    {
        return $this === self::Critical;
    }

    public function isEnforcedStrictly(): bool
    {
        return $this === self::Critical;
    }
}
