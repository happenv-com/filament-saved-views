<?php

declare(strict_types=1);

namespace Happenv\FilamentSavedViews;

use Closure;
use Filament\Actions\Action;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
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

        Table::macro('getSavedViewManagerTriggerAction', function () use ($triggerCallbacks): Action {
            /** @var Table $this */
            // @phpstan-ignore varTag.nativeType
            $action = Action::make('openSavedViewManager')
                ->label(__('filament-saved-views::saved-views.label'))
                ->iconButton()
                ->icon(config('filament-happenv-saved-views.icons.manager'))
                ->color('gray')
                ->livewireClickHandlerEnabled(false)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('filament::components/modal.actions.close.label'))
                ->table($this)
                ->authorize(true);

            $callback = $triggerCallbacks[$this] ?? null;

            if ($callback) {
                $action = $this->evaluate($callback, ['action' => $action]) ?? $action;
            }

            $action->extraAttributes(['class' => 'fi-force-enabled'], merge: true);

            return $action;
        });
    }
}
