<?php

declare(strict_types=1);

namespace Happenv\FilamentSavedViews;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\View\TablesRenderHook;
use Happenv\FilamentSavedViews\Filament\Concerns\HasSavedViews;
use Livewire\Livewire;

class FilamentSavedViewsPlugin implements Plugin
{
    public function getId(): string
    {
        return 'happenv-filament-saved-views';
    }

    public function register(Panel $panel): void
    {
        // Inject the saved-views control into the toolbar of every resource list
        // table on this panel, so saved views work for all resources without each
        // one registering its own action.
        $panel->renderHook(
            TablesRenderHook::TOOLBAR_END,
            static function (): string {
                $component = Livewire::current();

                // Only resource List pages get the control — not relation managers
                // or table widgets, which have no resource-scoped saved views.
                if (! $component instanceof ListRecords || ! in_array(HasSavedViews::class, \class_uses_recursive($component), strict: true)) {
                    return '';
                }

                $table = $component->getTable();

                return view('filament-saved-views::control', [
                    'resourceClass' => $component::getResource(),
                    'livewireKey' => 'saved-views-control-' . $component->getId(),
                    // @phpstan-ignore-next-line method.notFound (Table macro)
                    'layout' => $table->getSavedViewManagerLayout(),
                    // @phpstan-ignore-next-line method.notFound (Table macro)
                    'triggerAction' => $table->getSavedViewManagerTriggerAction(),
                    // @phpstan-ignore-next-line method.notFound (Table macro)
                    'deferred' => $table->getDeferSavedViewManager(),
                ])->render();
            },
        );
    }

    public function boot(Panel $panel): void {}

    public static function make(): static
    {
        return resolve(static::class);
    }
}
