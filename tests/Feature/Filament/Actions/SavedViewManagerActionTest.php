<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Filament\Tables\Table;
use Happenv\FilamentSavedViews\Filament\Actions\SavedViewManagerAction;
use Happenv\FilamentSavedViews\Tests\Fixtures\SavedViewsListComponent;
use Illuminate\Auth\GenericUser;

beforeEach(function (): void {
    $this->actingAs(new GenericUser(['id' => 7]));

    $this->component = new SavedViewsListComponent;
    $this->component->mountInteractsWithTable();
    $this->component->bootedInteractsWithTable();

    // @phpstan-ignore-next-line method.notFound (Table macro)
    $this->trigger = fn () => Table::make($this->component)->getSavedViewManagerTriggerAction();
});

it('is what the table hands back as its manager trigger', function (): void {
    expect(($this->trigger)())->toBeInstanceOf(SavedViewManagerAction::class)
        ->and(($this->trigger)()->getName())->toBe('openSavedViewManager');
});

it('carries its defaults', function (): void {
    expect(($this->trigger)()->getColor())->toBe('gray')
        ->and(($this->trigger)()->getLabel())->toBe(__('filament-saved-views::saved-views.label'));
});

it('lets a host override those defaults with configureUsing', function (): void {
    // The ordering that makes this work: ComponentManager::configure() runs setUp() at the position
    // of the class DECLARING it, then the configurations registered against that class. Reverse
    // them and the package would silently win over the host every time.
    SavedViewManagerAction::configureUsing(
        static fn (SavedViewManagerAction $action): SavedViewManagerAction => $action->color('primary')->badgeColor('warning'),
        during: function (): void {
            expect(($this->trigger)()->getColor())->toBe('primary')
                ->and(($this->trigger)()->getBadgeColor())->toBe('warning');
        },
    );
});

it('lets the package defaults win over a broad Action::configureUsing', function (): void {
    // A callback on the parent applies too, but earlier — so a panel-wide default does not quietly
    // repaint this button.
    Action::configureUsing(
        static fn (Action $action): Action => $action->color('danger'),
        during: function (): void {
            expect(($this->trigger)()->getColor())->toBe('gray');
        },
    );
});

it('still honours the per-table trigger callback, which runs last', function (): void {
    // Two seams, deliberately: configureUsing for every table, this one for a single table.
    SavedViewManagerAction::configureUsing(
        static fn (SavedViewManagerAction $action): SavedViewManagerAction => $action->color('primary'),
        during: function (): void {
            $table = Table::make($this->component)
                // @phpstan-ignore-next-line method.notFound (Table macro)
                ->savedViewManagerTriggerAction(static fn (Action $action): Action => $action->color('info'));

            // @phpstan-ignore-next-line method.notFound (Table macro)
            expect($table->getSavedViewManagerTriggerAction()->getColor())->toBe('info');
        },
    );
});
