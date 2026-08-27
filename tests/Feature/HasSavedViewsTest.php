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

describe('hasUnsavedSavedViewChanges', function (): void {
    it('says no when nothing has been touched since the view was opened', function (): void {
        $view = storeSavedView([]);
        $component = bootSavedViewsComponent((string) $view->id);
        $component->updateCurrentView();

        expect(bootSavedViewsComponent((string) $view->id)->hasUnsavedSavedViewChanges())->toBeFalse();
    });

    it('says yes once the table is arranged away from the view', function (): void {
        $view = storeSavedView([]);
        $component = bootSavedViewsComponent((string) $view->id);
        $component->updateCurrentView();

        $component = bootSavedViewsComponent((string) $view->id);
        $component->tableSearch = 'something new';

        expect($component->hasUnsavedSavedViewChanges())->toBeTrue();
    });

    it('says no when no view is open at all', function (): void {
        $component = bootSavedViewsComponent();
        $component->tableSearch = 'anything';

        expect($component->hasUnsavedSavedViewChanges())->toBeFalse();
    });

    it('is not fooled by the key order the database hands back', function (): void {
        // saved_data is json: it does not preserve the key order of an object, so a comparison
        // that cared about order would report every view as edited the moment it was reopened.
        $component = bootSavedViewsComponent();
        $state = $component->getCurrentTableStateForSavedView();

        $view = storeSavedView(array_reverse($state, preserve_keys: true));

        expect(bootSavedViewsComponent((string) $view->id)->hasUnsavedSavedViewChanges())->toBeFalse();
    });

    it('treats the several spellings of nothing as the same nothing', function (): void {
        // An untouched filter form is not empty — Filament fills it with an entry per filter
        // holding null values — and a view saved before a slice existed simply omits it.
        $view = storeSavedView([
            'filters' => ['status' => ['value' => null]],
            'search' => '',
            'columns_reordered' => false,
        ]);

        expect(bootSavedViewsComponent((string) $view->id)->hasUnsavedSavedViewChanges())->toBeFalse();
    });

    it('does not call a view stale over a page size it never recorded', function (): void {
        // Every other slice has a meaningful empty value. This one does not — its "empty" is
        // whatever number the table chose — so a view that never stored it cannot differ on it.
        $view = storeSavedView(['search' => '']);

        expect(bootSavedViewsComponent((string) $view->id)->hasUnsavedSavedViewChanges())->toBeFalse();
    });

    it('still notices a page size the user actually changed', function (): void {
        $view = storeSavedView(['per_page' => 10]);
        $component = bootSavedViewsComponent((string) $view->id);
        $component->tableRecordsPerPage = 50;

        expect($component->hasUnsavedSavedViewChanges())->toBeTrue();
    });

    it('reads a legacy view through its old page-size key', function (): void {
        $component = bootSavedViewsComponent();
        $component->tableRecordsPerPage = 25;
        $view = storeSavedView(['perPage' => 25]);

        $reopened = bootSavedViewsComponent((string) $view->id);
        $reopened->tableRecordsPerPage = 25;

        expect($reopened->hasUnsavedSavedViewChanges())->toBeFalse();
    });

    it('notices a slice the view never recorded once the user fills it in', function (): void {
        // The counterpart to ignoring an unrecorded column layout: a slice whose empty value IS
        // blank must still go dirty when the user puts something in it, or an old view could never
        // learn anything new.
        $view = storeSavedView(['columns' => []]);
        $component = bootSavedViewsComponent((string) $view->id);
        $component->tableSearch = 'acme';

        expect($component->hasUnsavedSavedViewChanges())->toBeTrue();
    });

    it('goes quiet again the moment the view is updated', function (): void {
        $view = storeSavedView(['search' => 'old']);
        $component = bootSavedViewsComponent((string) $view->id);
        $component->tableSearch = 'new';

        expect($component->hasUnsavedSavedViewChanges())->toBeTrue();

        $component->updateCurrentView();

        expect($component->hasUnsavedSavedViewChanges())->toBeFalse();
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
