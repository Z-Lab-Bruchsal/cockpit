<?php

namespace App\Livewire\Homepage;

use App\Filament\Pages\Inline\EditFooterBlockInline;
use App\Filament\Pages\Inline\EditFooterLinkInline;
use App\Models\CompanySetting;
use App\Models\Section;
use App\Services\Homepage\ContentIds;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Arranges the footer grid directly on the homepage (arrange mode): drag elements to reorder, change their width,
 * add, edit and remove text elements. Every change is saved immediately.
 */
class FooterEditor extends Component
{
    public function mount(): void
    {
        $this->authorizeEditor();
    }

    /**
     * Grid classes for a width of the 6-column footer grid (literal, so that Tailwind picks them up).
     */
    public static function widthClass(mixed $width): string
    {
        return match ((int) $width) {
            3 => 'lg:col-span-3',
            4 => 'sm:col-span-2 lg:col-span-4',
            6 => 'sm:col-span-2 lg:col-span-6',
            default => 'lg:col-span-2',
        };
    }

    /**
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    #[Computed]
    public function blocks(): array
    {
        return CompanySetting::current()->footerBlocks();
    }

    public function sort(string $id, int $position): void
    {
        $this->update(function (array $blocks) use ($id, $position): array {
            $index = $this->indexOf($blocks, $id);
            $moved = $blocks[$index];
            array_splice($blocks, $index, 1);
            array_splice($blocks, max(0, min($position, count($blocks))), 0, [$moved]);

            return $blocks;
        });
    }

    public function resize(string $id, int $width): void
    {
        Validator::make(['width' => $width], ['width' => ['required', 'integer', Rule::in(array_keys(Section::TILE_WIDTHS))]])->validate();

        $this->update(function (array $blocks) use ($id, $width): array {
            $blocks[$this->indexOf($blocks, $id)]['data']['width'] = $width;

            return $blocks;
        });
    }

    /**
     * Adds an empty text element at the end and opens its editor.
     */
    public function addText(): void
    {
        $blockId = null;

        $this->update(function (array $blocks) use (&$blockId): array {
            $blocks[] = ['type' => 'text', 'data' => ['body' => null, 'width' => 2]];
            $blocks = ContentIds::ensure($blocks);
            $blockId = end($blocks)['data']['block_id'];

            return $blocks;
        });

        $this->dispatch('open-inline-editor', url: EditFooterBlockInline::getUrl(['block' => $blockId]));
    }

    public function remove(string $id): void
    {
        $this->update(function (array $blocks) use ($id): array {
            array_splice($blocks, $this->indexOf($blocks, $id), 1);

            return $blocks;
        });
    }

    /**
     * Columns of the company settings holding the entries of the "links" and "social" elements.
     *
     * @var array<string, string>
     */
    public const LIST_COLUMNS = ['links' => 'footer_links', 'social' => 'social_links'];

    public function sortLinks(string $item, int $position): void
    {
        $this->moveEntry('links', (int) $item, $position);
    }

    public function sortSocial(string $item, int $position): void
    {
        $this->moveEntry('social', (int) $item, $position);
    }

    public function removeEntry(string $kind, int $index): void
    {
        $this->updateList($kind, function (array $entries) use ($index): array {
            abort_unless(array_key_exists($index, $entries), 404);
            array_splice($entries, $index, 1);

            return $entries;
        });
    }

    public function addEntry(string $kind): void
    {
        abort_unless(array_key_exists($kind, self::LIST_COLUMNS), 404);
        $this->authorizeEditor();

        $this->dispatch('open-inline-editor', url: EditFooterLinkInline::getUrl(['kind' => $kind, 'index' => 'neu']));
    }

    protected function moveEntry(string $kind, int $from, int $position): void
    {
        $this->updateList($kind, function (array $entries) use ($from, $position): array {
            abort_unless(array_key_exists($from, $entries), 404);
            $moved = $entries[$from];
            array_splice($entries, $from, 1);
            array_splice($entries, max(0, min($position, count($entries))), 0, [$moved]);

            return $entries;
        });
    }

    /**
     * @param  callable(list<array<string, mixed>>): list<array<string, mixed>>  $change
     */
    protected function updateList(string $kind, callable $change): void
    {
        abort_unless(array_key_exists($kind, self::LIST_COLUMNS), 404);
        $this->authorizeEditor();

        $settings = CompanySetting::current();
        $column = self::LIST_COLUMNS[$kind];
        $settings->update([$column => array_values($change(array_values($settings->{$column} ?? [])))]);
    }

    public function render(): View
    {
        return view('livewire.homepage.footer-editor', [
            'settings' => CompanySetting::current(),
            'widths' => array_keys(Section::TILE_WIDTHS),
        ]);
    }

    /**
     * @param  callable(list<array<string, mixed>>): list<array<string, mixed>>  $change
     */
    protected function update(callable $change): void
    {
        $this->authorizeEditor();

        $settings = CompanySetting::current();
        $settings->update(['footer_blocks' => array_values($change($settings->footerBlocks()))]);

        unset($this->blocks);
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    protected function indexOf(array $blocks, string $id): int
    {
        $index = ContentIds::blockIndex($blocks, $id);

        abort_if($index === null, 404);

        return $index;
    }

    protected function authorizeEditor(): void
    {
        abort_unless((bool) Auth::guard('web')->user()?->can('View:CompanySettingsPage'), 403);
    }
}
