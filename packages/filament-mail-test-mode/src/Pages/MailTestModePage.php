<?php

namespace AlexanderGabriel\FilamentMailTestMode\Pages;

use AlexanderGabriel\FilamentMailTestMode\FilamentMailTestModePlugin;
use AlexanderGabriel\FilamentMailTestMode\MailTestMode;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use UnitEnum;

class MailTestModePage extends Page
{
    protected string $view = 'filament-mail-test-mode::page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $slug = 'mail-test-mode';

    /**
     * Set by the plugin, see {@see FilamentMailTestModePlugin::cluster()}.
     */
    protected static ?string $configuredCluster = null;

    public static function useCluster(?string $cluster): void
    {
        static::$configuredCluster = $cluster;
    }

    public static function getCluster(): ?string
    {
        return static::$configuredCluster;
    }

    public static function canAccess(): bool
    {
        return parent::canAccess() && FilamentMailTestModePlugin::get()->isAuthorized();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return FilamentMailTestModePlugin::get()->getNavigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-mail-test-mode::messages.title');
    }

    public static function getNavigationBadge(): ?string
    {
        return app(MailTestMode::class)->isEnabled() ? __('filament-mail-test-mode::messages.badge_active') : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public function getTitle(): string|Htmlable
    {
        return __('filament-mail-test-mode::messages.title');
    }

    public function mailTestMode(): MailTestMode
    {
        return app(MailTestMode::class);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('enable')
                ->label(__('filament-mail-test-mode::messages.actions.enable'))
                ->icon(Heroicon::OutlinedPlay)
                ->color('danger')
                ->visible(fn (): bool => ! $this->mailTestMode()->isEnabled())
                ->modalDescription(__('filament-mail-test-mode::messages.actions.enable_description'))
                ->fillForm(fn (): array => [
                    'recipient' => $this->mailTestMode()->setting()->recipient ?? filament()->auth()->user()?->email,
                ])
                ->schema([
                    TextInput::make('recipient')
                        ->label(__('filament-mail-test-mode::messages.recipient'))
                        ->email()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->mailTestMode()->enable($data['recipient'], filament()->auth()->user());

                    Notification::make()
                        ->title(__('filament-mail-test-mode::messages.notifications.enabled', ['recipient' => $data['recipient']]))
                        ->success()
                        ->send();
                }),
            Action::make('changeRecipient')
                ->label(__('filament-mail-test-mode::messages.actions.change_recipient'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('gray')
                ->visible(fn (): bool => $this->mailTestMode()->isEnabled() && ! $this->mailTestMode()->isForcedByEnv())
                ->modalDescription(__('filament-mail-test-mode::messages.actions.change_recipient_description'))
                ->fillForm(fn (): array => [
                    'recipient' => $this->mailTestMode()->recipient(),
                ])
                ->schema([
                    TextInput::make('recipient')
                        ->label(__('filament-mail-test-mode::messages.recipient'))
                        ->email()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->mailTestMode()->changeRecipient($data['recipient']);

                    Notification::make()
                        ->title(__('filament-mail-test-mode::messages.notifications.recipient_changed', ['recipient' => $data['recipient']]))
                        ->success()
                        ->send();
                }),
            Action::make('disable')
                ->label(__('filament-mail-test-mode::messages.actions.disable'))
                ->icon(Heroicon::OutlinedStop)
                ->color('gray')
                ->visible(fn (): bool => $this->mailTestMode()->isEnabled() && ! $this->mailTestMode()->isForcedByEnv())
                ->requiresConfirmation()
                ->modalDescription(__('filament-mail-test-mode::messages.actions.disable_description'))
                ->action(function (): void {
                    $this->mailTestMode()->disable();

                    Notification::make()
                        ->title(__('filament-mail-test-mode::messages.notifications.disabled'))
                        ->success()
                        ->send();
                }),
            Action::make('sendTestMail')
                ->label(__('filament-mail-test-mode::messages.actions.send_test_mail'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('gray')
                ->visible(fn (): bool => $this->mailTestMode()->isEnabled() && $this->mailTestMode()->recipient() !== null)
                ->action(function (): void {
                    $originalRecipient = filament()->auth()->user()?->email ?? (string) config('mail.from.address');

                    Mail::raw(__('filament-mail-test-mode::messages.test_mail.body'), function (Message $message) use ($originalRecipient): void {
                        $message->to($originalRecipient)->subject(__('filament-mail-test-mode::messages.test_mail.subject'));
                    });

                    Notification::make()
                        ->title(__('filament-mail-test-mode::messages.notifications.test_mail_sent', ['recipient' => $this->mailTestMode()->recipient()]))
                        ->success()
                        ->send();
                }),
        ];
    }
}
