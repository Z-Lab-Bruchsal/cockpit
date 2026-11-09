<?php

namespace App\Filament\Clusters\Settings;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

/**
 * Global settings of the cockpit and the homepage, plus master data like users, terminals or belts.
 */
class SettingsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Einstellungen';

    protected static ?string $clusterBreadcrumb = 'Einstellungen';

    protected static ?string $title = 'Einstellungen';

    protected static ?string $slug = 'einstellungen';

    protected static ?int $navigationSort = 100;
}
