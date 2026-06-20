<?php

declare(strict_types=1);

use Happenv\FilamentSavedViews\Models\SavedView;

it('uses an auto-increment id and stores on the configured table', function (): void {
    $view = SavedView::query()->create([
        'user_id' => 1,
        'class' => 'App\\Resources\\OrderResource',
        'label' => 'Orders',
        'saved_data' => ['filters' => ['status' => 'open']],
    ]);

    expect($view->getTable())->toBe('saved_views')
        ->and($view->getKeyType())->toBe('int')
        ->and($view->id)->toBeInt();
});

it('casts saved_data to an array', function (): void {
    $view = SavedView::query()->create([
        'user_id' => 1,
        'class' => 'App\\Resources\\OrderResource',
        'label' => 'With data',
        'saved_data' => ['filters' => ['status' => ['values' => ['open']]], 'search' => 'foo'],
    ]);

    expect($view->fresh()->saved_data)->toBe(['filters' => ['status' => ['values' => ['open']]], 'search' => 'foo']);
});

it('filters by user and class with plain where clauses', function (): void {
    SavedView::query()->create(['user_id' => 1, 'class' => 'A', 'label' => 'a1']);
    SavedView::query()->create(['user_id' => 2, 'class' => 'A', 'label' => 'a2']);
    SavedView::query()->create(['user_id' => 1, 'class' => 'B', 'label' => 'b1']);

    $result = SavedView::query()->where('user_id', 1)->where('class', 'A')->get();

    expect($result)->toHaveCount(1)
        ->and($result->first()->label)->toBe('a1');
});

it('honours a host-overridden table name', function (): void {
    config()->set('filament-happenv-saved-views.table', 'custom_views');

    expect((new SavedView)->getTable())->toBe('custom_views');
});

it('defaults sort_order to 0 and submenu_visible to true, cast to int/bool', function (): void {
    $view = SavedView::query()->create([
        'user_id' => 1,
        'class' => 'App\\Resources\\OrderResource',
        'label' => 'Defaults',
    ])->fresh();

    expect($view->sort_order)->toBe(0)
        ->and($view->submenu_visible)->toBeTrue();
});
