<?php

namespace App\Enums;

enum TimeClockState: string
{
    case NotClockedIn = 'not_clocked_in';
    case Working = 'working';
}
