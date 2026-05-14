<?php

namespace App\Enums;

enum CommandType: string
{
    case None = 'none';
    case Start = 'start';
    case Delay = 'delay';
    case Sick = 'sick';
    case ReturnSick = 'return-sick';
    case Schedule = 'schedule';
    case ForceMajeure = 'force-majeure';
    case Other = 'other';

    public function toString(): string
    {
        return match ($this) {
            self::None => 'none',
            self::Start => 'start',
            self::Delay => 'delay',
            self::Sick => 'sick',
            self::ReturnSick => 'return-sick',
            self::Schedule => 'schedule',
            self::ForceMajeure => 'force-majeure',
            self::Other => 'other',
        };
    }
}
