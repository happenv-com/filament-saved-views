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
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use RuntimeException;

/**
 * Self-contained saved-views control rendered into every resource list table's
 * toolbar (via the saved-views render hook). It reads the live table filter state
 * from the page URL, so it works for any resource without that resource declaring
 * its own action.
 *
 * @property-read Schema $form
 */
class SavedViewsControl extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    /** @var class-string The resource the saved views are scoped to. */
    public string $resourceClass;

    /** Whether reorder/visibility/rename/delete are staged until "Apply". */
    public bool $deferred = false;

    /**
     * The view the page is currently showing, if any.
     *
     * Passed in from the render hook rather than read from the query string: this control is its
     * own Livewire component, and its update requests carry no query string at all — so a control
     * that asked the request would lose track of the open view the moment anyone clicked it.
     */
    public string | int | null $activeViewId = null;

    /** @var array<int, string|int>|null Ordered ids staged for reorder (deferred mode). */
    public ?array $draftOrder = null;

    /** @var array<string|int, bool> Staged submenu-visibility overrides (deferred mode). */
    public array $draftVisible = [];

    /** @var array<string|int, string> Staged label renames (deferred mode). */
    public array $draftLabels = [];

    /** @var array<int, string|int> Ids staged for deletion (deferred mode). */
    public array $draftDeleted = [];

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

    public function mount(string $resourceClass, bool $deferred = false, string | int | null $activeViewId = null): void
    {
        $this->resourceClass = $resourceClass;
        $this->deferred = $deferred;
        $this->activeViewId = $activeViewId;
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
     * Validate the name, then hand off to the page (which owns the live table
     * state) to capture + persist the view via the `save-current-view` event.
     */
    public function save(): void
    {
        $label = trim((string) $this->form->getState()['label']);

        $this->dispatch('save-current-view', label: $label);
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
            ->requiresConfirmation(! $this->deferred)
            ->modalHeading(__('filament-saved-views::saved-views.delete_confirm'))
            ->action(function (array $arguments): void {
                $id = $arguments['id'] ?? null;

                if (! is_string($id) && ! is_int($id)) {
                    return;
                }

                if ($this->deferred) {
                    $this->draftDeleted[] = $id;

                    return;
                }

                $this->scopedQuery()->whereKey($id)->delete();

                $this->dispatch('saved-views-updated');
            });
    }

    /**
     * Persist (or, in deferred mode, stage) the new order from a list of view
     * ids (as produced by SortableJS `toArray()`), scoped to the current user.
     *
     * @param  array<int, string|int>  $orderedIds
     */
    public function reorderViews(array $orderedIds): void
    {
        if ($this->deferred) {
            $this->draftOrder = array_values($orderedIds);

            return;
        }

        resolve(SavedView::class)::setNewOrder(array_values($orderedIds), 0);

        $this->dispatch('saved-views-updated');
    }

    /**
     * Flip whether a view appears in the page submenu (staged in deferred mode).
     */
    public function toggleSubmenu(string | int $id): void
    {
        if ($this->deferred) {
            $this->draftVisible[(string) $id] = ! $this->effectiveVisible($id);

            return;
        }

        $view = $this->scopedQuery()->whereKey($id)->first();

        if ($view === null) {
            return;
        }

        $view->submenu_visible = ! $view->submenu_visible;
        $view->save();

        $this->dispatch('saved-views-updated');
    }

    /**
     * Commit every staged change (delete + rename + visibility + order) in one
     * transaction, then refresh the page. Invoked by the modal-footer Apply
     * action via the `apply-saved-views` browser event.
     *
     * @throws RuntimeException
     */
    public function applySavedViews(): void
    {
        DB::transaction(function (): void {
            if ($this->draftDeleted !== []) {
                $this->scopedQuery()->whereKey($this->draftDeleted)->delete();
            }

            foreach ($this->draftLabels as $id => $label) {
                $this->scopedQuery()->whereKey($id)->update(['label' => $label]);
            }

            foreach ($this->draftVisible as $id => $visible) {
                $this->scopedQuery()->whereKey($id)->update(['submenu_visible' => $visible]);
            }

            if ($this->draftOrder !== null) {
                resolve(SavedView::class)::setNewOrder(array_values($this->draftOrder), 0);
            }
        });

        $this->resetDraft();
        $this->dispatch('saved-views-updated');

        $url = $this->resourceClass::getUrl('index');
        $this->redirect($url, navigate: FilamentView::hasSpaMode($url));
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
                $id = $arguments['id'] ?? null;

                if ($this->deferred) {
                    $this->draftLabels[(string) $id] = $data['label'];

                    return;
                }

                $this->scopedQuery()->whereKey($id)->update(['label' => $data['label']]);

                $this->dispatch('saved-views-updated');
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
        $data = $view->saved_data ?? [];

        return $this->resourceClass::getUrl('index') . '?' . \http_build_query(array_filter([
            'filters' => $data['filters'] ?? [],
            'search' => $data['search'] ?? null,
            'sort' => $data['sort'] ?? null,
            'grouping' => $data['grouping'] ?? null,
            'savedView' => $view->id,
        ], static fn ($value): bool => ! in_array($value, [null, '', []], true)));
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
     * Current (possibly staged) submenu visibility for a view.
     */
    private function effectiveVisible(string | int $id): bool
    {
        if (array_key_exists((string) $id, $this->draftVisible)) {
            return $this->draftVisible[(string) $id];
        }

        return (bool) $this->scopedQuery()->whereKey($id)->value('submenu_visible');
    }

    private function resetDraft(): void
    {
        $this->draftOrder = null;
        $this->draftVisible = [];
        $this->draftLabels = [];
        $this->draftDeleted = [];
    }

    /**
     * @return Collection<int, SavedView>
     *
     * @throws RuntimeException
     */
    public function getViewsProperty(): Collection
    {
        $views = $this->scopedQuery()
            ->ordered()
            ->orderBy('label')
            ->get();

        if (! $this->deferred) {
            return $views;
        }

        // Drop staged deletions, apply staged renames/visibility, then re-order
        // by the staged order (falling back to the persisted order).
        $views = $views
            ->reject(fn (SavedView $view): bool => in_array((string) $view->id, array_map(strval(...), $this->draftDeleted), strict: true))
            ->each(function (SavedView $view): void {
                if (array_key_exists((string) $view->id, $this->draftLabels)) {
                    $view->label = $this->draftLabels[(string) $view->id];
                }

                if (array_key_exists((string) $view->id, $this->draftVisible)) {
                    $view->submenu_visible = $this->draftVisible[(string) $view->id];
                }
            });

        if ($this->draftOrder !== null) {
            $order = array_flip(array_map(strval(...), $this->draftOrder));
            $views = $views
                ->sortBy(fn (SavedView $view): int => $order[(string) $view->id] ?? PHP_INT_MAX)
                ->values();
        }

        return $views;
    }

    public function render(): View
    {
        return view('filament-saved-views::livewire.saved-views-control');
    }
}
