<?php

declare(strict_types=1);

namespace Happenv\FilamentSavedViews\Livewire;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
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
class SavedViewsControl extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    /** @var class-string The resource the saved views are scoped to. */
    public string $resourceClass;

    /**
     * State for the "save current view" form (the name input).
     *
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * Id of the view currently being renamed. Captured when the edit action
     * mounts so the uniqueness rule can exclude it (public so it survives the
     * Livewire round-trip between mounting the modal and submitting it).
     */
    public string | int | null $editingViewId = null;

    public function mount(string $resourceClass): void
    {
        $this->resourceClass = $resourceClass;
        $this->form->fill();
    }

    /**
     * The "save current view" form: a single name input whose validation
     * (required + per-user/resource uniqueness) is handled by Filament.
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('label')
                    ->hiddenLabel()
                    ->placeholder(__('filament-saved-views::saved-views.name_placeholder'))
                    ->required()
                    ->rule(fn (): Closure => fn (string $attribute, mixed $value, Closure $fail) => $this->failIfDuplicateLabel($value, null, $fail)),
            ])
            ->statePath('data');
    }

    /**
     * Persist the current filter state (read from the browser query string) as a
     * named view, then open it. The name is validated by the Filament form.
     *
     * @throws RuntimeException
     */
    public function save(?string $queryString = null): void
    {
        $label = trim((string) $this->form->getState()['label']);

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
                    ->required()
                    ->rule(fn (): Closure => fn (string $attribute, mixed $value, Closure $fail) => $this->failIfDuplicateLabel($value, $this->editingViewId, $fail)),
            ])
            ->fillForm(function (array $arguments): array {
                // Capture the edited id so the uniqueness rule can exclude this
                // record. Set here (not in mountUsing) because fillForm reliably
                // runs with the action arguments on mount.
                $this->editingViewId = $arguments['id'] ?? null;

                return ['label' => $this->scopedQuery()->whereKey($arguments['id'] ?? null)->value('label')];
            })
            ->action(function (array $data, array $arguments): void {
                $this->scopedQuery()
                    ->whereKey($arguments['id'] ?? null)
                    ->update(['label' => $data['label']]);
            });
    }

    /**
     * Validation helper: fail when another saved view in the same scope already
     * uses the given label. Pass $exceptId to exclude the record being renamed.
     */
    private function failIfDuplicateLabel(mixed $value, string | int | null $exceptId, Closure $fail): void
    {
        $duplicate = $this->scopedQuery()
            ->where('label', trim((string) $value))
            ->when($exceptId !== null, fn (Builder $query): Builder => $query->whereKeyNot($exceptId))
            ->exists();

        if ($duplicate) {
            $fail(__('filament-saved-views::saved-views.duplicate_name'));
        }
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
