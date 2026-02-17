<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Orderstatus;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bestellt_single')
                ->icon(Heroicon::ShoppingCart)->label("Bestellt")->action(function(Model $record) {
                    $orderstatusBestellt = Orderstatus::where("name", "bestellt")->first();
                    $record->orderstatus_id = $orderstatusBestellt->id;
                    $record->save();
                })->visible(function(Model $record) {$orderstatusErfasst = Orderstatus::where("name", "erfasst")->first();return ($orderstatusErfasst->id == $record->orderstatus_id);}),
            Action::make('angekommen_single')
                ->icon(Heroicon::BuildingOffice)->label("Angekommen")->action(function(Model $record) {
                    $orderstatusAngekommen = Orderstatus::where("name", "angekommen")->first();
                    $record->orderstatus_id = $orderstatusAngekommen->id;
                    $record->save();
                })->visible(function(Model $record) {$orderstatusBestellt = Orderstatus::where("name", "bestellt")->first();return ($orderstatusBestellt->id == $record->orderstatus_id);}),
            Action::make('genommen_single')
                ->icon(Heroicon::Check)->label("Genommen")->action(function(Model $record) {
                    $orderstatusGenommen = Orderstatus::where("name", "genommen")->first();
                    $record->orderstatus_id = $orderstatusGenommen->id;
                    $record->save();
                })->visible(function(Model $record) {$orderstatusAngekommen = Orderstatus::where("name", "angekommen")->first();return ($orderstatusAngekommen->id == $record->orderstatus_id);}),
            Action::make("url_oeffnen")->icon(Heroicon::Link)->label("URL öffnen")->url(function (Model $record) { return $record->url;}, true),
            DeleteAction::make(),
        ];
    }
}
