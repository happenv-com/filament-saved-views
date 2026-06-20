<?php

declare(strict_types=1);

use Happenv\FilamentSavedViews\Models\SavedView;
use Illuminate\Support\Collection;

it('uses an auto-increment id and stores on the configured table', function (): void {
    $view = SavedView::query()->create([
        'user_id' => 1,
        'class' => 'App\\Resources\\OrderResource',
        'label' => 'Orders',
        'filters' => collect(['status' => 'open']),
    ]);

    expect($view->getTable())->toBe('saved_views')
        ->and($view->getKeyType())->toBe('int')
        ->and($view->id)->toBeInt();
});

it('casts the filters column to a collection', function (): void {
    $view = SavedView::query()->create([
        'user_id' => 1,
        'class' => 'App\\Resources\\OrderResource',
        'label' => 'With filters',
        'filters' => collect(['status' => ['values' => ['open']]]),
    ]);

    expect($view->fresh()->filters)->toBeInstanceOf(Collection::class)
        ->and($view->fresh()->filters->all())->toBe(['status' => ['values' => ['open']]]);
});

it('filters by user and class with plain where clauses', function (): void {
    SavedView::query()->create(['user_id' => 1, 'class' => 'A', 'label' => 'a1', 'filters' => collect()]);
    SavedView::query()->create(['user_id' => 2, 'class' => 'A', 'label' => 'a2', 'filters' => collect()]);
    SavedView::query()->create(['user_id' => 1, 'class' => 'B', 'label' => 'b1', 'filters' => collect()]);

    $result = SavedView::query()->where('user_id', 1)->where('class', 'A')->get();

    expect($result)->toHaveCount(1)
        ->and($result->first()->label)->toBe('a1');
});

it('honours a host-overridden table name', function (): void {
    config()->set('saved-views.table', 'custom_views');

    expect((new SavedView)->getTable())->toBe('custom_views');
});
