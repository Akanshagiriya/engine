<?php

namespace Minds\Core\Analytics\UserStates;

class RewardFactor
{
    static $values = [
        UserState::STATE_CASUAL => 1.5,
        UserState::STATE_COLD => 0.5,
        UserState::STATE_CORE => 0.5,
        UserState::STATE_CURIOUS => 1,
        UserState::STATE_NEW => 2,
        UserState::STATE_RESURRECTED => 1.5,
        UserState::STATE_UNKNOWN => 1
    ];

    public static function getForUserState(?string $userState): float
    {
        return static::$values[$userState] ?? static::$values[UserState::STATE_UNKNOWN];
    }
}