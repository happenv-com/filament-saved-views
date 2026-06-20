<?php

declare(strict_types=1);

namespace Happenv\FilamentSavedViews\Filament\Concerns;

use Filament\Navigation\NavigationItem;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Facades\FilamentView;
use Happenv\FilamentSavedViews\Models\SavedView;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use RuntimeException;

use function Filament\Support\original_request;

/**
 * Opt-in saved-views navigation for Filament list pages.
 *
 * The save / open / delete control itself is injected globally into every
 * resource list toolbar by the saved-views plugin; this trait only adds the
 * saved views as page sub-navigation items for pages that want to surface them
 * in their left rail.
 *
 * @mixin ListRecords
 */
trait HasSavedViews
{
    #[Url(as: 'savedView')]
    public ?string $savedView = null;

    /** Guard so a saved view's columns are restored only once per component load. */
    public bool $savedViewColumnsApplied = false;

    /**
     * @return array<NavigationItem>
     *
     * @throws RuntimeException
     */
    public function getSavedViewsNavigationItems(): array
    {
        $views = resolve(SavedView::class)::query()
            ->where('user_id', Auth::guard(config('filament-happenv-saved-views.guard'))->id())
            // @phpstan-ignore staticMethod.notFound
            ->where('class', static::getResource())
            ->where('submenu_visible', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        $items = [];

        foreach ($views as $view) {
            $items[] = NavigationItem::make($view->label)
                ->icon(config('filament-happenv-saved-views.icons.item'))
                ->url($this->getUrlForSavedView($view))
                ->isActiveWhen(fn (): bool => (string) original_request()->query('savedView') === (string) $view->id);
        }

        return $items;
    }

    /**
     * Re-render the page (and so the saved-views sub-navigation) when the
     * saved-views control commits a change. The empty body is intentional:
     * receiving the Livewire event triggers a re-render.
     */
    #[On('saved-views-updated')]
    public function refreshSavedViews(): void {}

    /**
     * Capture the current table state and persist it as a named view. Triggered
     * by the saved-views control via the `save-current-view` event; the page is
     * the only place with native access to the live filters/search/sort/columns.
     *
     * @throws RuntimeException
     */
    #[On('save-current-view')]
    public function saveCurrentView(string $label): void
    {
        $view = resolve(SavedView::class);
        // @phpstan-ignore staticMethod.notFound
        $view->class = static::getResource();
        $view->user_id = Auth::guard(config('filament-happenv-saved-views.guard'))->id();
        $view->label = $label;
        $view->saved_data = [
            'filters' => $this->tableFilters ?? [],
            'search' => (string) $this->tableSearch,
            'sort' => $this->tableSort,
            'grouping' => $this->tableGrouping,
            // @phpstan-ignore method.notFound
            'perPage' => $this->getTableRecordsPerPage(),
            'columns' => $this->tableColumns,
        ];
        $view->save();

        $url = $this->getUrlForSavedView($view);
        $this->redirect($url, navigate: FilamentView::hasSpaMode($url));
    }

    /**
     * Restore toggled columns when a saved view is opened. Filters/search/sort
     * come back via the URL natively; columns are session-based, so apply them
     * explicitly. Runs in the `booted` hook (after the table is initialised) and
     * only once per component load (the guard survives Livewire round-trips).
     *
     * @throws RuntimeException
     */
    public function bootedHasSavedViews(): void
    {
        if ($this->savedViewColumnsApplied) {
            return;
        }

        $this->savedViewColumnsApplied = true;

        $savedViewId = $this->savedView ?? request()->query('savedView');

        if (blank($savedViewId)) {
            return;
        }

        $view = resolve(SavedView::class)::query()
            ->where('user_id', Auth::guard(config('filament-happenv-saved-views.guard'))->id())
            ->whereKey($savedViewId)
            ->first();

        $columns = $view?->saved_data['columns'] ?? null;

        if (is_array($columns) && $columns !== []) {
            // @phpstan-ignore method.notFound
            $this->applyTableColumnManager($columns);
        }

        // perPage is session-based (not URL-synced), so restore it explicitly.
        $perPage = $view?->saved_data['perPage'] ?? null;

        if ($perPage !== null) {
            $this->tableRecordsPerPage = $perPage;
        }
    }

    /**
     * @throws RuntimeException
     */
    public function getUrlForSavedView(SavedView $view): string
    {
        $resource = static::getResource();
        $data = $view->saved_data ?? [];

        return $resource::getUrl('index') . '?' . \http_build_query(array_filter([
            'filters' => $data['filters'] ?? [],
            'search' => $data['search'] ?? null,
            'sort' => $data['sort'] ?? null,
            'grouping' => $data['grouping'] ?? null,
            'savedView' => $view->id,
        ], static fn ($value): bool => $value !== null && $value !== '' && $value !== []));
    }
}
