<?php

namespace App\Enums;

enum CommandType: int
{
    case None = 0;
    case Start = 10;
    case Delay = 20;
    case Sick = 30;
    case ReturnSick = 40;
    case Schedule = 50;
    case ForceMajeure = 60;
    case Other = 70;

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
