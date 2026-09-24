<?php

declare(strict_types=1);

use Filament\Tables\Table;
use Happenv\FilamentSavedViews\Models\SavedView;
use Happenv\FilamentSavedViews\Tests\Fixtures\SavedViewsListComponent;
use Happenv\FilamentSavedViews\Tests\Fixtures\SavedViewsTableComponent;
use Illuminate\Auth\GenericUser;

beforeEach(function (): void {
    $this->actingAs(new GenericUser(['id' => 7]));

    // @phpstan-ignore-next-line method.notFound (Table macro)
    $this->triggerFor = fn (SavedViewsListComponent $component) => Table::make($component)->getSavedViewManagerTriggerAction();
});

it('carries no badge while the table matches the view it is showing', function (): void {
    $component = new SavedViewsListComponent;
    $component->mountInteractsWithTable();
    $component->bootedInteractsWithTable();
    $component->bootedHasSavedViews();

    $view = new SavedView;
    $view->user_id = 7;
    $view->class = SavedViewsListComponent::getResource();
    $view->label = 'v';
    $view->saved_data = $component->getCurrentTableStateForSavedView();
    $view->save();

    $opened = new SavedViewsListComponent;
    $opened->savedView = (string) $view->id;
    $opened->mountInteractsWithTable();
    $opened->bootedInteractsWithTable();
    $opened->bootedHasSavedViews();

    expect(($this->triggerFor)($opened)->getBadge())->toBeNull();
});

it('carries a badge once the table is arranged away from the view', function (): void {
    $view = new SavedView;
    $view->user_id = 7;
    $view->class = SavedViewsListComponent::getResource();
    $view->label = 'v';
    $view->saved_data = ['search' => 'old'];
    $view->save();

    $opened = new SavedViewsListComponent;
    $opened->savedView = (string) $view->id;
    $opened->mountInteractsWithTable();
    $opened->bootedInteractsWithTable();
    $opened->bootedHasSavedViews();
    $opened->tableSearch = 'new';

    $trigger = ($this->triggerFor)($opened);

    // Non-blank on purpose: Filament refuses to render a badge whose content is blank, and the
    // stylesheet hides the glyph so only the dot shows.
    expect($trigger->getBadge())->not->toBeNull()
        ->and(filled($trigger->getBadge()))->toBeTrue()
        ->and($trigger->getBadgeColor())->toBe('danger')
        // On the ACTION, not on the badge. `badgeTooltip()` is settable on an Action and nothing
        // renders it for one — only the navigation components read it — so the explanation has to
        // hang off the button itself.
        ->and((string) $trigger->getTooltip())->toBe(__('filament-saved-views::saved-views.unsaved_changes'));
});

it('carries no tooltip while the table matches the view', function (): void {
    // The tooltip is the explanation for the dot, so it must not linger once the dot is gone.
    $component = new SavedViewsListComponent;
    $component->mountInteractsWithTable();
    $component->bootedInteractsWithTable();
    $component->bootedHasSavedViews();

    $view = new SavedView;
    $view->user_id = 7;
    $view->class = SavedViewsListComponent::getResource();
    $view->label = 'v';
    $view->saved_data = $component->getCurrentTableStateForSavedView();
    $view->save();

    $opened = new SavedViewsListComponent;
    $opened->savedView = (string) $view->id;
    $opened->mountInteractsWithTable();
    $opened->bootedInteractsWithTable();
    $opened->bootedHasSavedViews();

    expect(($this->triggerFor)($opened)->getTooltip())->toBeNull();
});

it('carries no badge on a page that has no saved views at all', function (): void {
    // The badge closure runs for every table in the panel, so it has to tolerate a component that
    // knows nothing about saved views.
    $plain = new SavedViewsTableComponent;
    $plain->mountInteractsWithTable();
    $plain->bootedInteractsWithTable();

    // @phpstan-ignore-next-line method.notFound (Table macro)
    expect(Table::make($plain)->getSavedViewManagerTriggerAction()->getBadge())->toBeNull();
});
