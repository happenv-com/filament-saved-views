<?php

declare(strict_types=1);

namespace Happenv\FilamentSavedViews\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Facades\FilamentView;
use Happenv\FilamentSavedViews\Models\SavedView;
use Illuminate\Contracts\View\View;
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
            ->extraAttributes(['class' => 'shrink-0 opacity-0 transition group-hover:opacity-100'])
            ->action(function (array $arguments): void {
                $id = $arguments['id'] ?? null;

                if (! is_string($id) && ! is_int($id)) {
                    return;
                }

                resolve(SavedView::class)::query()
                    ->where('user_id', Auth::guard(config('filament-happenv-saved-views.guard'))->id())
                    ->where('class', $this->resourceClass)
                    ->whereKey($id)
                    ->delete();

                $url = $this->resourceClass::getUrl('index');
                $this->redirect($url, navigate: FilamentView::hasSpaMode($url));
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
     * @return Collection<int, SavedView>
     *
     * @throws RuntimeException
     */
    public function getViewsProperty(): Collection
    {
        return resolve(SavedView::class)::query()
            ->where('user_id', Auth::guard(config('filament-happenv-saved-views.guard'))->id())
            ->where('class', $this->resourceClass)
            ->orderBy('label')
            ->get();
    }

    public function render(): View
    {
        return view('filament-saved-views::livewire.saved-views-control');
    }
}
