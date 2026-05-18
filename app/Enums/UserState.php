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
    case ReturnSickWaitManualDate = 80;
    case ReturnSickWaitData = 90;
    case ScheduleWaitText = 100;
    case ScheduleConfirm = 110;
    case FMWaitType = 120;
    case FMWaitHours = 130;
    case FMWaitDays = 140;
    case FMWaitReason = 150;
    case FMConfirm = 160;

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
            self::ReturnSickWaitManualDate => 'ReturnSickWaitManualDate',
            self::ReturnSickWaitData => 'ReturnSickWaitData',
            self::ScheduleWaitText => 'ScheduleWaitText',
            self::ScheduleConfirm => 'ScheduleConfirm',
            self::FMWaitType => 'FMWaitType',
            self::FMWaitHours => 'FMWaitHours',
            self::FMWaitDays => 'FMWaitDays',
            self::FMWaitReason => 'FMWaitReason',
            self::FMConfirm => 'FMConfirm',
        };
    }
}
