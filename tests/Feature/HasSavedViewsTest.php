<?php

declare(strict_types=1);

use Happenv\FilamentSavedViews\Models\SavedView;
use Happenv\FilamentSavedViews\Tests\Fixtures\SavedViewsListComponent;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Session;

/**
 * Boot the fixture the way Livewire would, but without rendering it.
 *
 * `livewire()` cannot be used here: every Livewire render in this package's testbench setup dies
 * in Livewire's validation hook, independently of what is being rendered. The trait's two entry
 * points are lifecycle hooks, so driving them directly tests the same thing.
 */
function bootSavedViewsComponent(?string $savedViewId = null): SavedViewsListComponent
{
    $component = new SavedViewsListComponent;
    $component->savedView = $savedViewId;

    $component->mountInteractsWithTable();
    $component->bootedInteractsWithTable();
    $component->bootedHasSavedViews();

    return $component;
}

function storeSavedView(array $savedData): SavedView
{
    $view = new SavedView;
    $view->user_id = 7;
    $view->class = SavedViewsListComponent::getResource();
    $view->label = 'My view';
    $view->saved_data = $savedData;
    $view->save();

    return $view;
}

beforeEach(function (): void {
    $this->actingAs(new GenericUser(['id' => 7]));
});

describe('saveCurrentView', function (): void {
    it('captures the complete table state, not just the parts that fit in a URL', function (): void {
        $component = bootSavedViewsComponent();

        Session::put($component->getHasReorderedTableColumnsSessionKey(), true);

        $component->tableFilters = ['status' => ['value' => 'open']];
        $component->tableSearch = 'widget';
        $component->tableColumnSearches = ['name' => 'ada'];
        $component->tableSort = 'name';

        $component->saveCurrentView('My view');

        expect(SavedView::query()->sole()->saved_data)
            ->toHaveKey('filters', ['status' => ['value' => 'open']])
            ->toHaveKey('search', 'widget')
            ->toHaveKey('column_searches', ['name' => 'ada'])
            ->toHaveKey('sort', 'name')
            ->toHaveKey('columns_reordered', true)
            ->toHaveKeys(['grouping', 'per_page', 'columns']);
    });
});

describe('updateCurrentView', function (): void {
    it('writes the current table state over the open view', function (): void {
        $view = storeSavedView(['search' => 'old', 'per_page' => 10]);
        $component = bootSavedViewsComponent((string) $view->id);

        $component->tableSearch = 'new';
        $component->tableColumnSearches = ['name' => 'ada'];
        $component->updateCurrentView();

        expect($view->refresh()->saved_data)
            ->toHaveKey('search', 'new')
            ->toHaveKey('column_searches', ['name' => 'ada']);
    });

    it('captures exactly what saving a new view captures', function (): void {
        // The two paths must not drift, or updating a view silently drops a slice that creating
        // one keeps.
        $view = storeSavedView([]);
        $component = bootSavedViewsComponent((string) $view->id);

        $component->updateCurrentView();
        $updated = array_keys($view->refresh()->saved_data);

        $component->saveCurrentView('Another');
        $created = array_keys(SavedView::query()->where('label', 'Another')->sole()->saved_data);

        sort($updated);
        sort($created);

        expect($updated)->toBe($created);
    });

    it('does nothing when no view is open', function (): void {
        $view = storeSavedView(['search' => 'untouched']);
        $component = bootSavedViewsComponent();

        $component->tableSearch = 'changed';
        $component->updateCurrentView();

        expect($view->refresh()->saved_data['search'])->toBe('untouched');
    });

    it('will not write to a view belonging to somebody else', function (): void {
        $someoneElse = new SavedView;
        $someoneElse->user_id = 99;
        $someoneElse->class = SavedViewsListComponent::getResource();
        $someoneElse->label = 'Theirs';
        $someoneElse->saved_data = ['search' => 'theirs'];
        $someoneElse->save();

        $component = bootSavedViewsComponent((string) $someoneElse->id);
        $component->tableSearch = 'mine';
        $component->updateCurrentView();

        expect($someoneElse->refresh()->saved_data['search'])->toBe('theirs');
    });
});

describe('bootedHasSavedViews', function (): void {
    it('restores the slices that do not travel in the URL', function (): void {
        $columns = bootSavedViewsComponent()->getDefaultTableColumnState();
        $columns[1]['isToggled'] = false;

        $view = storeSavedView([
            'columns' => $columns,
            'columns_reordered' => true,
            'column_searches' => ['name' => 'ada'],
            'per_page' => 25,
        ]);

        $component = bootSavedViewsComponent((string) $view->id);

        expect($component->tableColumnSearches)->toBe(['name' => 'ada'])
            ->and($component->tableRecordsPerPage)->toBe(25)
            ->and(Session::get($component->getHasReorderedTableColumnsSessionKey()))->toBeTrue();
    });

    it('still reads the page size from a view saved under the old key', function (): void {
        // Rows written before the rename must keep working; nobody migrates saved views.
        $view = storeSavedView(['perPage' => 50]);

        $component = bootSavedViewsComponent((string) $view->id);

        expect($component->tableRecordsPerPage)->toBe(50);
    });

    it('leaves the table alone when no saved view is requested', function (): void {
        storeSavedView(['per_page' => 25]);

        expect(bootSavedViewsComponent()->tableRecordsPerPage)->not->toBe(25);
    });
});
