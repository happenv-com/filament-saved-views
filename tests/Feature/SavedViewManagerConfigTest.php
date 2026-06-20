<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Filament\Tables\Table;
use Happenv\FilamentSavedViews\Filament\Enums\SavedViewManagerLayout;
use Happenv\FilamentSavedViews\Tests\Fixtures\SavedViewsTableComponent;

function makeSavedViewsTestTable(): Table
{
    return Table::make(new SavedViewsTableComponent);
}

it('defaults the saved-view manager layout to a dropdown', function (): void {
    expect(makeSavedViewsTestTable()->getSavedViewManagerLayout())
        ->toBe(SavedViewManagerLayout::Dropdown);
});

it('stores the configured saved-view manager layout', function (): void {
    $table = makeSavedViewsTestTable()->savedViewManagerLayout(SavedViewManagerLayout::Modal);

    expect($table->getSavedViewManagerLayout())->toBe(SavedViewManagerLayout::Modal);
});

it('resolves a closure-based saved-view manager layout', function (): void {
    $table = makeSavedViewsTestTable()
        ->savedViewManagerLayout(fn (): SavedViewManagerLayout => SavedViewManagerLayout::Modal);

    expect($table->getSavedViewManagerLayout())->toBe(SavedViewManagerLayout::Modal);
});

it('builds a default saved-view manager trigger action', function (): void {
    $action = makeSavedViewsTestTable()->getSavedViewManagerTriggerAction();

    expect($action)->toBeInstanceOf(Action::class)
        ->and($action->getName())->toBe('openSavedViewManager')
        ->and($action->isModalSlideOver())->toBeFalse();
});

it('applies the saved-view manager trigger action customization closure', function (): void {
    $table = makeSavedViewsTestTable()->savedViewManagerTriggerAction(
        fn (Action $action): Action => $action->slideOver()->modalFooterActions([]),
    );

    expect($table->getSavedViewManagerTriggerAction()->isModalSlideOver())->toBeTrue();
});

it('keeps saved-view manager config isolated per table instance', function (): void {
    $configured = makeSavedViewsTestTable()->savedViewManagerLayout(SavedViewManagerLayout::Modal);
    $untouched = makeSavedViewsTestTable();

    expect($configured->getSavedViewManagerLayout())->toBe(SavedViewManagerLayout::Modal)
        ->and($untouched->getSavedViewManagerLayout())->toBe(SavedViewManagerLayout::Dropdown);
});
