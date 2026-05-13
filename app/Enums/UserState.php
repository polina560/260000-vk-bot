<?php

namespace App\Enums;

enum UserState: int
{
    case None = 0;
    case WaitReason = 10;
    case WaitTime = 20;
    case Confirm = 30;

    public function toString(): string
    {
        return match ($this) {
            self::None => 'None',
            self::WaitReason => 'WaitReason',
            self::WaitTime => 'WaitTime',
            self::Confirm => 'Confirm',
        };
    }
}
