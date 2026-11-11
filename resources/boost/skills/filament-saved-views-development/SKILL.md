---
name: filament-saved-views-development
description: >-
  Add per-user saved table views (filters, search, sort, grouping, per-page, column layout) to
  Filament 4/5 resource list pages with happenv-com/filament-saved-views. Use when registering
  FilamentSavedViewsPlugin, adding the HasSavedViews trait to a ListRecords page,
  showing views in getSubNavigation() via getSavedViewsNavigationItems(), configuring the manager
  (savedViewManagerLayout(), savedViewManagerTriggerAction(), deferSavedViewManager(),
  savedViewManagerTriggerHidden(), SavedViewManagerAction::configureUsing()), opening it from your
  own button (getSavedViewManagerModalId(), hasSavableTableState()), swapping the SavedView model
  (UUID keys, tenants), the saved_views migration and config, or testing saved views.
---

# Filament Saved Views

Lets a user save how a resource's list table looks right now under a name, and reopen it later. A view is a row in `saved_views` owned by `(user_id, class)`, where `class` is the **resource class**. Opening a view is a plain URL: the list page with `?filters=…&search=…&sort=…&grouping=…&savedView={id}`; the page restores the column layout, page size and column searches from the row. A view never changes by itself: arranging the table while a view is open only shows an "unsaved changes" dot and an "Update this view" button.

## When to use this skill

- Enabling saved views on a panel or on one resource's list page.
- Putting saved views into the page sub-navigation, or opening the manager from your own button/tab.
- Restyling the manager or changing its layout per table.
- Swapping the model or table (UUID keys, multi-tenancy, a prefixed table name).
- Tests around saving and opening views.

## Installation

```bash
composer require happenv-com/filament-saved-views
php artisan vendor:publish --tag="filament-saved-views-migrations"
php artisan migrate
php artisan filament:assets
```

Add the views to the panel's custom theme so Tailwind sees their classes:

```css
@source '../../../../vendor/happenv-com/filament-saved-views/resources/**/*.blade.php';
```

Supports Filament 4 (`^4.11.2`) and 5 (`^5.6.2`), Laravel 11 to 13, PHP 8.3 to 8.5.

## Enabling it

Both steps are required: the plugin renders the control, and only on list pages that use the trait.

```php
use Happenv\FilamentSavedViews\FilamentSavedViewsPlugin;

$panel->plugin(FilamentSavedViewsPlugin::make());
```

```php
use Filament\Resources\Pages\ListRecords;
use Happenv\FilamentSavedViews\Filament\Concerns\HasSavedViews;

class ListOrders extends ListRecords
{
    use HasSavedViews;

    // Optional: views with "show in submenu" checked appear as sub-navigation items.
    public function getSubNavigation(): array
    {
        return $this->getSavedViewsNavigationItems();
    }
}
```

- Only resource `ListRecords` pages get the control. Relation managers, table widgets and custom Livewire tables do not, even with the trait.
- The control is injected through the `TablesRenderHook::TOOLBAR_END` render hook; there is no action to add to `headerActions()` or `toolbarActions()`.
- `ListRecords::getSubNavigation()` returns the cluster's sub-navigation for a resource in a cluster. Overriding it replaces that; keep both with `[...parent::getSubNavigation(), ...$this->getSavedViewsNavigationItems()]`.

## Configuring the manager

Per table, with `Table` macros (in the resource's `table()` or the page's):

```php
use Filament\Actions\Action;
use Filament\Tables\Table;
use Happenv\FilamentSavedViews\Filament\Enums\SavedViewManagerLayout;

return $table
    ->savedViewManagerLayout(SavedViewManagerLayout::Modal)          // Dropdown (default) | Modal
    ->savedViewManagerTriggerAction(fn (Action $action): Action => $action->slideOver())
    ->deferSavedViewManager()                                        // stage edits, "Apply saved views" commits
    ->savedViewManagerTriggerHidden();                               // no toolbar button, see below
```

These are macros, so PHPStan reports them as undefined methods unless an extension that understands Filament macros is installed (or the call is ignored).

For every table at once, configure the button class (runs after the package defaults, so it wins):

```php
use Happenv\FilamentSavedViews\Filament\Actions\SavedViewManagerAction;

SavedViewManagerAction::configureUsing(
    fn (SavedViewManagerAction $action) => $action->color('primary')->badgeColor('warning'),
);
```

Do not restyle it with a broad `Action::configureUsing()`; that runs before the package's own defaults and is overwritten.

### Opening the manager from your own button

```php
use Filament\Actions\Action;
use Illuminate\Support\Js;

// in table(): ->savedViewManagerTriggerHidden()

Action::make('savedViews')
    ->alpineClickHandler(fn (): string => "\$dispatch('open-modal', { id: " . Js::from($this->getSavedViewManagerModalId()) . ' })');

Action::make('saveView')
    ->visible(fn (): bool => $this->hasSavableTableState());   // the table differs from its untouched state
```

With the trigger hidden the manager is always a modal (or the slide-over set on the trigger action); a dropdown has nothing to hang from. Never hard-code the modal id; it contains the Livewire component id.

## What a view stores

`saved_data` (JSON): `filters`, `search`, `column_searches`, `sort`, `grouping`, `per_page`, `columns` (toggled + order) and `columns_reordered`. Filters, search, sort and grouping travel in the URL (Filament's own `#[Url]` properties on `ListRecords`); the rest is applied in `bootedHasSavedViews()` when the page opens with `?savedView=`.

- **Not stored:** the active tab of `getTabs()` (`?tab=`), the current page number, and anything outside the table. A view opened from a list with tabs lands on the default tab.
- A filter's `default()` matters: `hasSavableTableState()` and the unsaved-changes dot compare against the table's untouched state (defaults applied), not against empty.
- Renaming a resource class, or moving it to another namespace, orphans its views: they are keyed by the resource's FQCN. Migrate the `class` column when you do.

## Scoping, model and config

Views are scoped by `Auth::guard(config('filament-happenv-saved-views.guard'))->id()` and the resource class, nothing else. Names are unique per user and resource (validated in the form; there is no database unique index).

```php
// config/filament-happenv-saved-views.php (vendor:publish --tag="filament-saved-views-config")
return [
    'table' => 'saved_views',
    'guard' => null,              // set it when the panel authenticates with a non-default guard
    'icons' => [ /* manager, delete, save, update, item, edit, reorder: any Filament icon */ ],
];
```

The model is resolved from the container (`bindIf`), so the host can replace it:

```php
use Happenv\FilamentSavedViews\Models\SavedView;

$this->app->bind(SavedView::class, \App\Models\SavedView::class);   // a subclass of SavedView
```

- The subclass must stay a `SavedView` (it uses `spatie/eloquent-sortable` on `sort_order`, scoped by `user_id` + `class`).
- **UUID / ULID user ids:** the published migration uses `foreignId('user_id')` (bigint). Change it to `foreignUuid` / `foreignUlid` before migrating. For UUID view ids use `uuid('id')->primary()` in the migration and `HasUuids` on the subclass.
- **Multi-tenancy:** views are *not* separated per tenant. A user with several tenants sees the same views in all of them. To separate them, add a tenant column, a global scope on the subclass that filters by it and a `creating` hook that fills it.
- `table` can point at a prefixed table; the migration and the model both read it.

## Translations and views

`php artisan vendor:publish --tag="filament-saved-views-translations"` (every locale Filament ships) and `--tag="filament-saved-views-views"`. A published view must be re-published after upgrades.

## Testing

The page listens for Livewire events from the control, so tests can drive it without the UI:

```php
use Happenv\FilamentSavedViews\Models\SavedView;
use Livewire\Livewire;
use function Pest\Livewire\livewire;

livewire(ListOrders::class)
    ->set('tableSearch', 'acme')
    ->dispatch('save-current-view', label: 'Acme orders');

$view = SavedView::query()->where('label', 'Acme orders')->firstOrFail();
expect($view->class)->toBe(OrderResource::class)
    ->and($view->saved_data['search'])->toBe('acme');

Livewire::withQueryParams(['savedView' => (string) $view->getKey()])
    ->test(ListOrders::class)
    ->assertSet('savedView', (string) $view->getKey());
```

`update-current-view` overwrites the open view the same way. A view of another user is never opened: the lookup is always scoped to the signed-in user.
