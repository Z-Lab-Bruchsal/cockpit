<div class="mx-auto max-w-4xl p-4 sm:p-6">
    <form wire:submit="save" class="flex flex-col gap-6">
        <h1 class="text-xl font-bold tracking-tight">{{ $this->getTitle() }}</h1>

        {{ $this->form }}

        <div class="sticky bottom-0 flex justify-end gap-3 border-t border-gray-200 bg-white/90 py-3 backdrop-blur dark:border-white/10 dark:bg-gray-900/90">
            <x-filament::button color="gray" type="button" wire:click="cancel">Abbrechen</x-filament::button>
            <x-filament::button type="submit" keybindings="mod+s">Speichern</x-filament::button>
        </div>
    </form>

    <x-filament-actions::modals />
</div>
