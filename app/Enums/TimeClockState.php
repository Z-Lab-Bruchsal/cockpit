<?php

namespace App\Enums;

enum TimeClockState: string
{
    case NotClockedIn = 'not_clocked_in';
    case Working = 'working';
    case OnBreak = 'on_break';
    case ClockedOut = 'clocked_out';
}
