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
            // @phpstan-ignore method.notFound
            ->ordered()
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
        $view->saved_data = $this->getCurrentTableStateForSavedView();
        // Append the new view to the end of the user's list for this resource.
        $view->setHighestOrderNumber();
        $view->save();

        $url = $this->getUrlForSavedView($view);
        $this->redirect($url, navigate: FilamentView::hasSpaMode($url));
    }

    /**
     * Write the current table state over the saved view the page is showing.
     *
     * A view is a named thing with an identity of its own, so it changes only when asked: arranging
     * columns while one is open does NOT touch it. This is the asking. Triggered by the
     * saved-views control, which is the only place that knows a view is open and offers the button.
     *
     * @throws RuntimeException
     */
    #[On('update-current-view')]
    public function updateCurrentView(): void
    {
        $view = $this->getOpenSavedView();

        if ($view === null) {
            return;
        }

        $view->saved_data = $this->getCurrentTableStateForSavedView();
        $view->save();

        $url = $this->getUrlForSavedView($view);
        $this->redirect($url, navigate: FilamentView::hasSpaMode($url));
    }

    /**
     * Everything worth remembering about how this table looks right now.
     *
     * Shared by saving a new view and updating the open one, so the two can never drift into
     * capturing different things — the failure mode being a view that loses a slice on update.
     *
     * @return array<string, mixed>
     */
    protected function getCurrentTableStateForSavedView(): array
    {
        return [
            'filters' => $this->tableFilters ?? [],
            'search' => (string) $this->tableSearch,
            'column_searches' => $this->tableColumnSearches,
            'sort' => $this->tableSort,
            'grouping' => $this->tableGrouping,
            // @phpstan-ignore method.notFound
            'per_page' => $this->getTableRecordsPerPage(),
            'columns' => $this->tableColumns,
            // @phpstan-ignore method.notFound
            'columns_reordered' => (bool) session()->get($this->getHasReorderedTableColumnsSessionKey(), false),
        ];
    }

    /**
     * The saved view this page is showing, if any — scoped to its owner, so a guessed id in the
     * query string cannot reach somebody else's view.
     *
     * @throws RuntimeException
     */
    protected function getOpenSavedView(): ?SavedView
    {
        $savedViewId = $this->savedView ?? request()->query('savedView');

        if (blank($savedViewId)) {
            return null;
        }

        return resolve(SavedView::class)::query()
            ->where('user_id', Auth::guard(config('filament-happenv-saved-views.guard'))->id())
            ->whereKey($savedViewId)
            ->first();
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

        $data = $this->getOpenSavedView()?->saved_data ?? [];

        $columns = $data['columns'] ?? null;

        if (is_array($columns) && $columns !== []) {
            // The reorder flag has to travel WITH the columns, not after them:
            // applyTableColumnManager() branches on it to decide whether to sync the saved order
            // or fall back to the table's declared order. Restoring the columns without it turns
            // a hand-ordered view back into the default order.
            // @phpstan-ignore method.notFound
            $this->applyTableColumnManager($columns, (bool) ($data['columns_reordered'] ?? false));
        }

        // Everything below is session-based rather than URL-synced, so it has to be restored by
        // hand — unlike filters, search, sort and grouping, which travel in the view's URL.
        $perPage = $data['per_page'] ?? $data['perPage'] ?? null;

        if ($perPage !== null) {
            $this->tableRecordsPerPage = $perPage;
        }

        $columnSearches = $data['column_searches'] ?? null;

        if (is_array($columnSearches) && $columnSearches !== []) {
            $this->tableColumnSearches = $columnSearches;

            // bootedInteractsWithTable() has already written the session from the pre-restore
            // value, so without this the session and the component disagree from here on.
            // @phpstan-ignore method.notFound
            if ($this->getTable()->persistsColumnSearchesInSession()) {
                // @phpstan-ignore method.notFound
                session()->put($this->getTableColumnSearchesSessionKey(), $columnSearches);
            }
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
