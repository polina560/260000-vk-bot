<?php

namespace App\Enums;

enum UserState: int
{
    case None = 0;
    case WaitReason = 10;
    case WaitTime = 20;
    case Confirm = 30;
    case SickWaitDays = 40;
    case SickWaitComment = 50;
    case SickConfirm = 60;
    case SickWaitRemote = 70;

    public function toString(): string
    {
        return match ($this) {
            self::None => 'None',
            self::WaitReason => 'WaitReason',
            self::WaitTime => 'WaitTime',
            self::Confirm => 'Confirm',
            self::SickWaitDays => 'SickWaitDays',
            self::SickWaitComment => 'SickWaitComment',
            self::SickConfirm => 'SickConfirm',
            self::SickWaitRemote => 'SickWaitRemote',
        };
    }
}
