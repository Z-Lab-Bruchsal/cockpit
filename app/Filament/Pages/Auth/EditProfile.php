<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Resources\Users\Schemas\TeamProfileFields;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Profile page where every team member maintains their own business card for the homepage.
 */
class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
                Section::make('Mein Teamprofil')
                    ->description('So erscheinst du auf der Homepage, sobald die Leitung dich freischaltet.')
                    ->schema(TeamProfileFields::selfEditable())
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
