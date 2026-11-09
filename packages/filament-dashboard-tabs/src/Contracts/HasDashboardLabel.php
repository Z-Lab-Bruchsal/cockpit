<?php

namespace AlexanderGabriel\FilamentDashboardTabs\Contracts;

/**
 * Implement on a widget to control how it is named in the "add widget" picker and in edit mode.
 */
interface HasDashboardLabel
{
    public static function getDashboardLabel(): string;
}
