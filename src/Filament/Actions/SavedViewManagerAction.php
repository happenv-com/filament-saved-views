<?php

declare(strict_types=1);

namespace Happenv\FilamentSavedViews\Filament\Actions;

use Filament\Actions\Action;
use Filament\Tables\Contracts\HasTable;
use Override;

/**
 * The toolbar button that opens the saved-views manager.
 *
 * A class of its own rather than a configured `Action::make()`, so that hosts get the seam Filament
 * users already reach for:
 *
 *     SavedViewManagerAction::configureUsing(fn (SavedViewManagerAction $action) => $action->color('primary'));
 *
 * The ordering works out because `ComponentManager::configure()` walks the class hierarchy and runs
 * `setUp()` at the position of the class that DECLARES it — so these defaults are applied first and
 * anything registered against this class overrides them, while a broad `Action::configureUsing()`
 * on the parent still runs before them. `savedViewManagerTriggerAction()` remains available for
 * changing one table rather than all of them.
 */
class SavedViewManagerAction extends Action
{
    #[Override]
    public static function getDefaultName(): ?string
    {
        return 'openSavedViewManager';
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('filament-saved-views::saved-views.label'))
            ->iconButton()
            ->icon(config('filament-happenv-saved-views.icons.manager'))
            ->color('gray')
            // A dot when the table has been arranged away from the view it is showing. Closures,
            // not values: they are evaluated at render, so they follow the table as the user works
            // instead of freezing at whatever was true when the action was built. The glyph is
            // hidden by the package stylesheet — Filament will not render a badge whose content is
            // blank, and what is wanted here is the dot alone.
            ->badge(static fn (HasTable $livewire): ?string => self::hasUnsavedChanges($livewire) ? '•' : null)
            ->badgeColor('danger')
            // The explanation goes on the whole button, not on the dot. `badgeTooltip()` is
            // settable on an Action but nothing renders it for one: it is read only by the
            // navigation components — sidebar, topbar, tabs — and never by the button blades.
            ->tooltip(static fn (HasTable $livewire): ?string => self::hasUnsavedChanges($livewire)
                ? __('filament-saved-views::saved-views.unsaved_changes')
                : null)
            ->livewireClickHandlerEnabled(false)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('filament::components/modal.actions.close.label'))
            ->authorize(true);
    }

    /**
     * Whether the page has been arranged away from the saved view it is showing.
     *
     * This action is built for EVERY table on the panel, including the many whose pages know
     * nothing about saved views, so the question has to tolerate a component that cannot answer it.
     */
    private static function hasUnsavedChanges(HasTable $livewire): bool
    {
        return method_exists($livewire, 'hasUnsavedSavedViewChanges')
            && $livewire->hasUnsavedSavedViewChanges();
    }
}
