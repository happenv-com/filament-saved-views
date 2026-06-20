<?php

declare(strict_types=1);

namespace Happenv\FilamentSavedViews\Filament\Concerns;

use Filament\Navigation\NavigationItem;
use Filament\Resources\Pages\ListRecords;
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
     * @throws RuntimeException
     */
    public function getUrlForSavedView(SavedView $view): string
    {
        $resource = static::getResource();

        return $resource::getUrl('index') . '?' . \http_build_query([
            'filters' => $view->filters->all(),
            'savedView' => $view->id,
        ]);
    }
}
