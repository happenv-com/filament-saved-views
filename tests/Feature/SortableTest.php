<?php

declare(strict_types=1);

use Happenv\FilamentSavedViews\Models\SavedView;

function makeSortableView(string $label, ?int $sort = null): SavedView
{
    return SavedView::query()->create(array_filter([
        'user_id' => 1,
        'class' => 'App\\Resources\\OrderResource',
        'label' => $label,
        'sort_order' => $sort,
    ], static fn ($value): bool => $value !== null));
}

it('reorders views with setNewOrder (0-based)', function (): void {
    $a = makeSortableView('A', 0);
    $b = makeSortableView('B', 1);
    $c = makeSortableView('C', 2);

    SavedView::setNewOrder([$b->id, $c->id, $a->id], 0);

    expect($b->fresh()->sort_order)->toBe(0)
        ->and($c->fresh()->sort_order)->toBe(1)
        ->and($a->fresh()->sort_order)->toBe(2);
});

it('returns views in order via the ordered scope', function (): void {
    makeSortableView('B', 2);
    makeSortableView('A', 1);
    makeSortableView('C', 0);

    $labels = SavedView::query()->ordered()->pluck('label')->all();

    expect($labels)->toBe(['C', 'A', 'B']);
});

it('appends a new view to the end of its user+class group', function (): void {
    makeSortableView('A', 0);
    makeSortableView('B', 1);

    $new = new SavedView;
    $new->user_id = 1;
    $new->class = 'App\\Resources\\OrderResource';
    $new->label = 'C';
    $new->setHighestOrderNumber();
    $new->save();

    expect($new->fresh()->sort_order)->toBe(2);
});

it('scopes the append per user and class', function (): void {
    makeSortableView('A', 0);
    makeSortableView('B', 1);

    // A different class starts its own ordering from scratch.
    $other = new SavedView;
    $other->user_id = 1;
    $other->class = 'App\\Resources\\CaseResource';
    $other->label = 'Other';
    $other->setHighestOrderNumber();
    $other->save();

    expect($other->fresh()->sort_order)->toBe(1);
});
