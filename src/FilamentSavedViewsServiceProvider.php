<?php

declare(strict_types=1);

namespace Happenv\FilamentSavedViews;

use Closure;
use Filament\Actions\Action;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Happenv\FilamentSavedViews\Filament\Enums\SavedViewManagerLayout;
use Happenv\FilamentSavedViews\Livewire\SavedViewsControl;
use Happenv\FilamentSavedViews\Models\SavedView;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use WeakMap;

class FilamentSavedViewsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-saved-views')
            ->hasConfigFile('filament-happenv-saved-views')
            ->hasViews('filament-saved-views')
            ->hasTranslations()
            ->hasMigration('create_saved_views_table');
    }

    public function packageRegistered(): void
    {
        // Swappable model — a host (e.g. the Filamerce connector) binds a
        // UUID-keyed subclass. bindIf so the host binding always wins.
        $this->app->bindIf(SavedView::class, SavedView::class);
    }

    public function packageBooted(): void
    {
        Livewire::component('filament-saved-views-control', SavedViewsControl::class);

        FilamentAsset::register([
            Css::make('filament-saved-views', __DIR__ . '/../resources/css/saved-views.css'),
        ], 'happenv-com/filament-saved-views');

        $this->registerTableMacros();
    }

    /**
     * Register the table configuration macros that let any table opt the
     * saved-views manager into a dropdown, modal or slide-over presentation —
     * mirroring Filament's own `columnManagerLayout()` /
     * `columnManagerTriggerAction()` API.
     *
     * Per-table state is kept in a {@see WeakMap} so it is automatically
     * released with the table instance (Octane-safe).
     */
    private function registerTableMacros(): void
    {
        /** @var WeakMap<Table, SavedViewManagerLayout|Closure|null> $layouts */
        $layouts = new WeakMap;

        /** @var WeakMap<Table, Closure|null> $triggerCallbacks */
        $triggerCallbacks = new WeakMap;

        /** @var WeakMap<Table, bool|Closure|null> $deferConditions */
        $deferConditions = new WeakMap;

        Table::macro('savedViewManagerLayout', function (SavedViewManagerLayout | Closure | null $layout) use ($layouts): Table {
            /** @var Table $this */
            // @phpstan-ignore varTag.nativeType
            $layouts[$this] = $layout;

            return $this;
        });

        Table::macro('getSavedViewManagerLayout', function () use ($layouts): SavedViewManagerLayout {
            /** @var Table $this */
            // @phpstan-ignore varTag.nativeType
            return $this->evaluate($layouts[$this] ?? null) ?? SavedViewManagerLayout::Dropdown;
        });

        Table::macro('savedViewManagerTriggerAction', function (?Closure $callback) use ($triggerCallbacks): Table {
            /** @var Table $this */
            // @phpstan-ignore varTag.nativeType
            $triggerCallbacks[$this] = $callback;

            return $this;
        });

        Table::macro('deferSavedViewManager', function (bool | Closure $condition = true) use ($deferConditions): Table {
            /** @var Table $this */
            // @phpstan-ignore varTag.nativeType
            $deferConditions[$this] = $condition;

            return $this;
        });

        Table::macro('getDeferSavedViewManager', function () use ($deferConditions): bool {
            /** @var Table $this */
            // @phpstan-ignore varTag.nativeType
            return (bool) ($this->evaluate($deferConditions[$this] ?? null) ?? false);
        });

        Table::macro('getSavedViewManagerTriggerAction', function () use ($triggerCallbacks): Action {
            /** @var Table $this */
            // @phpstan-ignore varTag.nativeType
            $action = Action::make('openSavedViewManager')
                ->label(__('filament-saved-views::saved-views.label'))
                ->iconButton()
                ->icon(config('filament-happenv-saved-views.icons.manager'))
                ->color('gray')
                // A dot on the trigger when the table has been arranged away from the view it is
                // showing. A closure, not a value: it is evaluated at render, so it follows the
                // table as the user works instead of freezing at whatever was true on page load.
                // The glyph is hidden by the package stylesheet — Filament will not render a badge
                // whose content is blank, and what is wanted here is the dot alone.
                ->badge(static function (HasTable $livewire): ?string {
                    return method_exists($livewire, 'hasUnsavedSavedViewChanges')
                        && $livewire->hasUnsavedSavedViewChanges()
                            ? '•'
                            : null;
                })
                ->badgeColor('danger')
                ->badgeTooltip(__('filament-saved-views::saved-views.unsaved_changes'))
                ->livewireClickHandlerEnabled(false)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('filament::components/modal.actions.close.label'))
                ->table($this)
                ->authorize(true);

            // @phpstan-ignore-next-line method.notFound (Table macro)
            if ($this->getDeferSavedViewManager()) {
                $action->extraModalFooterActions([
                    Action::make('applySavedViews')
                        ->label(__('filament-saved-views::saved-views.apply'))
                        ->button()
                        ->alpineClickHandler("\$dispatch('apply-saved-views'); close()"),
                ]);
            }

            $callback = $triggerCallbacks[$this] ?? null;

            if ($callback) {
                $action = $this->evaluate($callback, ['action' => $action]) ?? $action;
            }

            $action->extraAttributes(['class' => 'fi-force-enabled'], merge: true);

            return $action;
        });
    }
}
