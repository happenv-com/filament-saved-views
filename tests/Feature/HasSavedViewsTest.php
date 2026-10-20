<?php

declare(strict_types=1);

use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
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
function bootSavedViewsComponent(?string $savedViewId = null, ?SavedViewsListComponent $component = null): SavedViewsListComponent
{
    $component ??= new SavedViewsListComponent;
    $component->savedView = $savedViewId;

    $component->mountInteractsWithTable();
    $component->bootedInteractsWithTable();
    $component->bootedHasSavedViews();

    return $component;
}

/** The fixture list with a second filter that starts at a value of its own. */
function listWithDefaultFilter(): SavedViewsListComponent
{
    return new class extends SavedViewsListComponent
    {
        public function table(Table $table): Table
        {
            return parent::table($table)->pushFilters([
                SelectFilter::make('kind')->options(['retail' => 'Retail', 'trade' => 'Trade'])->default('retail'),
            ]);
        }
    };
}

/** The fixture list grouped by name until somebody changes it. */
function listGroupedByDefault(): SavedViewsListComponent
{
    return new class extends SavedViewsListComponent
    {
        public function table(Table $table): Table
        {
            return parent::table($table)->groups(['name', 'email'])->defaultGroup('name');
        }
    };
}

/** The fixture list that can be grouped, and is not until somebody does. */
function listWithGroups(): SavedViewsListComponent
{
    return new class extends SavedViewsListComponent
    {
        public function table(Table $table): Table
        {
            return parent::table($table)->groups(['name', 'email']);
        }
    };
}

/** The fixture list with filters whose defaults are a number and `false`. */
function listWithNumberAndFalseDefaults(): SavedViewsListComponent
{
    return new class extends SavedViewsListComponent
    {
        public function table(Table $table): Table
        {
            return parent::table($table)->pushFilters([
                SelectFilter::make('priority')->options([1 => 'Low', 2 => 'High'])->default(1),
                TernaryFilter::make('archived')->default(false),
            ]);
        }
    };
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

describe('getSavedViewsNavigationItems', function (): void {
    // On a Livewire request (sort, filter, paginate) Filament's original_request() is rebuilt from
    // the page path alone — no query string — so reading `savedView` from it dropped the open
    // view's highlight on every table interaction. The page's own #[Url] property survives them.
    it('keeps the open view active on Livewire requests, which carry no query string', function (): void {
        $open = storeSavedView([]);
        $open->submenu_visible = true;
        $open->save();

        $other = storeSavedView([]);
        $other->label = 'Other view';
        $other->submenu_visible = true;
        $other->save();

        $component = bootSavedViewsComponent((string) $open->id);

        $active = collect($component->getSavedViewsNavigationItems())
            ->mapWithKeys(fn ($item): array => [$item->getLabel() => $item->isActive()]);

        expect($active->all())->toBe(['My view' => true, 'Other view' => false]);
    });
});

describe('hasSavableTableState', function (): void {
    it('has nothing to save on the list as it opens', function (): void {
        expect(bootSavedViewsComponent()->hasSavableTableState())->toBeFalse();
    });

    it('has something to save once the table is arranged', function (Closure $arrange): void {
        $component = bootSavedViewsComponent(component: listWithDefaultFilter());
        $arrange($component);

        expect($component->hasSavableTableState())->toBeTrue();
    })->with([
        'a filter' => [static fn (SavedViewsListComponent $component): array => $component->tableFilters = ['status' => ['value' => 'open'], 'kind' => ['value' => 'retail']]],
        'a filter moved off its default' => [static fn (SavedViewsListComponent $component): array => $component->tableFilters = ['status' => ['value' => null], 'kind' => ['value' => 'trade']]],
        'a search' => [static fn (SavedViewsListComponent $component): string => $component->tableSearch = 'widget'],
        'a column search' => [static fn (SavedViewsListComponent $component): array => $component->tableColumnSearches = ['name' => 'ada']],
        'a sort' => [static fn (SavedViewsListComponent $component): string => $component->tableSort = 'name:desc'],
        'a page size' => [static fn (SavedViewsListComponent $component): int => $component->tableRecordsPerPage = 50],
        'a hidden column' => [static fn (SavedViewsListComponent $component) => $component->applyTableColumnManager([
            [...$component->tableColumns[0], 'isToggled' => false],
            $component->tableColumns[1],
        ])],
        'columns moved' => [static fn (SavedViewsListComponent $component) => $component->applyTableColumnManager(array_reverse($component->tableColumns), wasReordered: true)],
    ]);

    it('does not count a filter left at its default', function (): void {
        $component = bootSavedViewsComponent(component: listWithDefaultFilter());

        expect($component->tableFilters['kind'] ?? null)->toBe(['value' => 'retail'])
            ->and($component->hasSavableTableState())->toBeFalse();
    });

    it('does not count a number default restored from the URL as a string', function (): void {
        $component = bootSavedViewsComponent(component: listWithNumberAndFalseDefaults());
        $component->tableFilters = [...$component->tableFilters, 'priority' => ['value' => '1'], 'archived' => ['value' => '0']];

        expect($component->hasSavableTableState())->toBeFalse();
    });

    it('counts a filter cleared from its false default', function (): void {
        $component = bootSavedViewsComponent(component: listWithNumberAndFalseDefaults());

        expect($component->tableFilters['archived']['value'] ?? null)->not->toBeNull()
            ->and($component->hasSavableTableState())->toBeFalse();

        $component = bootSavedViewsComponent(component: listWithNumberAndFalseDefaults());
        $component->tableFilters = [...$component->tableFilters, 'archived' => ['value' => null]];

        expect($component->hasSavableTableState())->toBeTrue();
    });

    it('does not count the table default group', function (): void {
        $component = bootSavedViewsComponent(component: listGroupedByDefault());

        expect($component->tableGrouping)->toStartWith('name:')
            ->and($component->hasSavableTableState())->toBeFalse();
    });

    it('counts a grouping away from the default', function (?string $grouping): void {
        $component = bootSavedViewsComponent(component: listGroupedByDefault());
        $component->tableGrouping = $grouping;

        expect($component->hasSavableTableState())->toBeTrue();
    })->with([
        'another group' => ['email:asc'],
        'the other direction' => ['name:desc'],
        'no group at all' => [null],
    ]);

    it('counts a grouping on a table that opens ungrouped', function (): void {
        $component = bootSavedViewsComponent(component: listWithGroups());

        expect($component->hasSavableTableState())->toBeFalse();

        $component = bootSavedViewsComponent(component: listWithGroups());
        $component->tableGrouping = 'name:asc';

        expect($component->hasSavableTableState())->toBeTrue();
    });

    it('leaves the filters as they were after reading their defaults', function (): void {
        $component = bootSavedViewsComponent(component: listWithDefaultFilter());
        $component->tableFilters = ['status' => ['value' => 'open'], 'kind' => ['value' => 'trade']];

        $component->hasSavableTableState();

        expect($component->tableFilters)->toBe(['status' => ['value' => 'open'], 'kind' => ['value' => 'trade']]);
    });

    it('ignores what a host adds to the state', function (): void {
        $component = bootSavedViewsComponent(component: new class extends SavedViewsListComponent
        {
            public function getCurrentTableStateForSavedView(): array
            {
                return [...parent::getCurrentTableStateForSavedView(), 'tab' => 'archived'];
            }
        });

        expect($component->hasSavableTableState())->toBeFalse();
    });
});
