<?php

declare(strict_types=1);

namespace Happenv\FilamentSavedViews\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentView;
use Happenv\FilamentSavedViews\Models\SavedView;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use RuntimeException;

/**
 * Self-contained saved-views control rendered into every resource list table's
 * toolbar (via the saved-views render hook). It reads the live table filter state
 * from the page URL, so it works for any resource without that resource declaring
 * its own action.
 */
class SavedViewsControl extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /** @var class-string The resource the saved views are scoped to. */
    public string $resourceClass;

    public function mount(string $resourceClass): void
    {
        $this->resourceClass = $resourceClass;
    }

    /**
     * Persist the current filter state (read from the browser query string) as a
     * named view, then open it.
     *
     * @throws RuntimeException
     */
    public function save(string $label, ?string $queryString = null): void
    {
        $label = trim($label);

        if ($label === '') {
            return;
        }

        parse_str(ltrim((string) $queryString, '?'), $params);
        $filters = $params['filters'] ?? [];

        $view = resolve(SavedView::class);
        $view->class = $this->resourceClass;
        $view->filters = collect(is_array($filters) ? $filters : []);
        $view->search_term = is_string($params['tableSearch'] ?? null) ? $params['tableSearch'] : null;
        $view->label = $label;
        $view->user_id = Auth::guard(config('filament-happenv-saved-views.guard'))->id();
        $view->save();

        $url = $this->urlFor($view);
        $this->redirect($url, navigate: FilamentView::hasSpaMode($url));
    }

    /**
     * Delete a saved view behind a Filament confirmation modal. The view id is
     * passed as an action argument from the blade trigger.
     *
     * @throws RuntimeException
     */
    public function deleteViewAction(): Action
    {
        return Action::make('deleteView')
            ->label(__('filament-saved-views::saved-views.delete'))
            ->icon(config('filament-happenv-saved-views.icons.delete'))
            ->iconButton()
            ->color('danger')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading(__('filament-saved-views::saved-views.delete_confirm'))
            ->action(function (array $arguments): void {
                $id = $arguments['id'] ?? null;

                if (! is_string($id) && ! is_int($id)) {
                    return;
                }

                $this->scopedQuery()->whereKey($id)->delete();

                $url = $this->resourceClass::getUrl('index');
                $this->redirect($url, navigate: FilamentView::hasSpaMode($url));
            });
    }

    /**
     * Persist the new order from a list of view ids (as produced by SortableJS
     * `toArray()`), scoped to the current user + resource.
     *
     * @param  array<int, string|int>  $orderedIds
     */
    public function reorderViews(array $orderedIds): void
    {
        foreach (array_values($orderedIds) as $index => $id) {
            $this->scopedQuery()->whereKey($id)->update(['sort_order' => $index]);
        }
    }

    /**
     * Flip whether a view appears in the page submenu.
     */
    public function toggleSubmenu(string | int $id): void
    {
        $view = $this->scopedQuery()->whereKey($id)->first();

        if ($view === null) {
            return;
        }

        $view->submenu_visible = ! $view->submenu_visible;
        $view->save();
    }

    /**
     * Inline rename behind a modal opened from the per-row gear button. The view
     * id is passed as an action argument from the blade trigger.
     */
    public function editViewAction(): Action
    {
        return Action::make('editView')
            ->label(__('filament-saved-views::saved-views.rename'))
            ->icon(config('filament-happenv-saved-views.icons.edit'))
            ->iconButton()
            ->color('gray')
            ->size('sm')
            ->modalHeading(__('filament-saved-views::saved-views.rename'))
            ->modalWidth(Width::Small)
            ->schema([
                TextInput::make('label')
                    ->hiddenLabel()
                    ->placeholder(__('filament-saved-views::saved-views.rename_placeholder'))
                    ->required(),
            ])
            ->fillForm(fn (array $arguments): array => [
                'label' => $this->scopedQuery()->whereKey($arguments['id'] ?? null)->value('label'),
            ])
            ->action(function (array $data, array $arguments): void {
                $this->scopedQuery()
                    ->whereKey($arguments['id'] ?? null)
                    ->update(['label' => $data['label']]);
            });
    }

    public function urlFor(SavedView $view): string
    {
        return $this->resourceClass::getUrl('index') . '?' . \http_build_query([
            'filters' => $view->filters->all(),
            'savedView' => $view->id,
        ]);
    }

    /**
     * Base query scoped to the current user + the resource this control is bound to.
     *
     * @return Builder<SavedView>
     */
    private function scopedQuery(): Builder
    {
        return resolve(SavedView::class)::query()
            ->where('user_id', Auth::guard(config('filament-happenv-saved-views.guard'))->id())
            ->where('class', $this->resourceClass);
    }

    /**
     * @return Collection<int, SavedView>
     *
     * @throws RuntimeException
     */
    public function getViewsProperty(): Collection
    {
        return $this->scopedQuery()
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();
    }

    public function render(): View
    {
        return view('filament-saved-views::livewire.saved-views-control');
    }
}
