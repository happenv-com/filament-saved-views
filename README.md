# Filament Saved Views

Reusable, per-user **saved views** for Filament list tables. Let users capture the current table
state (filters, search, sort, grouping, per-page and toggled columns) as a named view, then reopen it
in one click — with a management UI styled to match Filament's own column manager.

The package is framework-agnostic: it ships an auto-increment-keyed model and binds everything through
the service container, so a host application can swap in its own model (e.g. UUID keys, multi-tenant)
without touching the package.

## Features

- **Save the current view** — capture the complete table state of any resource list table: filters,
  search, per-column searches, sort, grouping, per-page, and the column layout including its order.
- **Update the open view** — arranging a table while a view is open does not change the view; an
  explicit "Update this view" button in the control writes the current state over it.
- **Saved-views manager** — a toolbar control (dropdown / modal / slide-over) that lists a user's
  views, mirroring the look and spacing of Filament's column manager.
- **Reorder** views by drag-and-drop, **rename** them inline, and **delete** them.
- **Submenu visibility** — a per-view checkbox controls whether the view appears in the page's
  sub-navigation (via the optional `HasSavedViews` trait).
- **Deferred mode** — opt in with `deferSavedViewManager()` to stage changes and commit them with an
  "Apply saved views" action, just like `deferColumnManager()`.
- **Per-user scoping** and unique view names (per user + resource).
- **Configurable icons** (Heroicon by default) and a fully swappable model.

## Requirements

- PHP 8.3+
- Filament 4 or 5
- Livewire 3 or 4

## Installation

```bash
composer require happenv-com/filament-saved-views
```

Run the migration that creates the `saved_views` table:

```bash
php artisan migrate
```

Publish the package assets (registers the manager stylesheet):

```bash
php artisan filament:assets
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="filament-saved-views-config"
```

## Usage

### 1. Register the plugin on a panel

```php
use Happenv\FilamentSavedViews\FilamentSavedViewsPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel->plugin(FilamentSavedViewsPlugin::make());
}
```

The plugin injects the saved-views control into the toolbar of every resource list page that uses the
`HasSavedViews` trait.

### 2. Add the trait to your list pages

```php
use Happenv\FilamentSavedViews\Filament\Concerns\HasSavedViews;

class ListOrders extends ListRecords
{
    use HasSavedViews;
}
```

Surface the saved views in the page sub-navigation:

```php
public function getSubNavigation(): array
{
    return $this->getSavedViewsNavigationItems();
}
```

### 3. Configure the manager per table (optional)

```php
use Happenv\FilamentSavedViews\Filament\Enums\SavedViewManagerLayout;
use Filament\Actions\Action;
use Filament\Tables\Table;

public function table(Table $table): Table
{
    return $table
        // Dropdown (default) or Modal / slide-over presentation.
        ->savedViewManagerLayout(SavedViewManagerLayout::Modal)
        // Customise the trigger button.
        ->savedViewManagerTriggerAction(fn (Action $action): Action => $action->slideOver())
        // Stage changes and require an "Apply saved views" click (default: false = immediate).
        ->deferSavedViewManager();
}
```

## Configuration

`config/filament-happenv-saved-views.php`:

```php
return [
    'table' => 'saved_views', // backing table
    'guard' => null,          // auth guard used to scope views (null = default)
    'icons' => [              // Heroicon enums by default
        'manager' => Heroicon::Eye,
        'delete'  => Heroicon::Trash,
        'save'    => Heroicon::ArrowDownTray,
        'item'    => Heroicon::Bookmark,
        'edit'    => Heroicon::Cog6Tooth,
        'reorder' => Heroicon::Bars2,
    ],
];
```

### Swapping the model

Every model reference is resolved through the container and bound with `bindIf`, so a host app can
provide its own model (different keys, traits, connection):

```php
use Happenv\FilamentSavedViews\Models\SavedView;

$this->app->bind(SavedView::class, \App\Models\MySavedView::class);
```

Point the package at the host table via `config('filament-happenv-saved-views.table')` and override
icons the same way (any value Filament accepts as an icon, including other icon-set enums).

## License

MIT.
