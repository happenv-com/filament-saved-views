# Filament Saved Views

[![Latest Version](https://img.shields.io/github/v/release/happenv-com/filament-saved-views?style=flat-square&label=version)](https://github.com/happenv-com/filament-saved-views/releases)
[![Tests](https://img.shields.io/github/actions/workflow/status/happenv-com/filament-saved-views/tests.yml?label=tests&style=flat-square)](https://github.com/happenv-com/filament-saved-views/actions/workflows/tests.yml)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/happenv-com/filament-saved-views/phpstan.yml?label=phpstan&style=flat-square)](https://github.com/happenv-com/filament-saved-views/actions/workflows/phpstan.yml)
[![Quality](https://img.shields.io/github/actions/workflow/status/happenv-com/filament-saved-views/quality.yml?label=code%20quality&style=flat-square)](https://github.com/happenv-com/filament-saved-views/actions/workflows/quality.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/happenv-com/filament-saved-views.svg?style=flat-square)](https://packagist.org/packages/happenv-com/filament-saved-views)
[![License](https://img.shields.io/github/license/happenv-com/filament-saved-views.svg?style=flat-square)](LICENSE.md)

Reusable, per-user **saved views** for Filament list tables. Let users capture the current table
state (filters, search, sort, grouping, per-page and toggled columns) as a named view, then reopen it
in one click — with a management UI styled to match Filament's own column manager.

The package is framework-agnostic: it ships an auto-increment-keyed model and binds everything through
the service container, so a host application can swap in its own model (e.g. UUID keys, multi-tenant)
without touching the package.

```php
use Happenv\FilamentSavedViews\FilamentSavedViewsPlugin;

$panel->plugin(FilamentSavedViewsPlugin::make());
```

## Key features

- **Save the current view.** Capture the complete table state of any resource list table — filters, search, per-column searches, sort, grouping, per-page, and the column layout including its order.
- **Update the open view.** Arranging a table while a view is open does not change the view; an explicit "Update this view" button, shown only when there is something to write, writes the current state over it, and a dot on the manager trigger says the table has unsaved changes.
- **A saved-views manager.** A toolbar control (dropdown / modal / slide-over) lists a user's views, mirroring the look and spacing of Filament's column manager — see [Configure the manager per table](#4-configure-the-manager-per-table-optional).
- **Reorder, rename and delete.** Views are reordered by drag-and-drop, renamed inline and deleted from the manager.
- **Submenu visibility.** A per-view checkbox controls whether the view appears in the page's sub-navigation (via the optional `HasSavedViews` trait) — see [Add the trait to your list pages](#2-add-the-trait-to-your-list-pages).
- **Deferred mode.** Opt in with `deferSavedViewManager()` to stage changes and commit them with an "Apply saved views" action, just like `deferColumnManager()`.
- **Per-user scoping.** Views belong to the signed-in user, and view names are unique per user and resource.
- **64 languages.** The manager is translated into every locale Filament ships — see [Translations](#translations).
- **Configurable icons and a swappable model.** Heroicons by default, any icon Filament accepts instead, and a model resolved from the container — see [Swapping the model](#swapping-the-model).

## Requirements

| Package  | Versions                               |
|----------|----------------------------------------|
| PHP      | 8.3 – 8.5                              |
| Laravel  | 11, 12, 13 (CI runs 12 and 13)         |
| Filament | 4 (`^4.11.2`), 5 (`^5.6.2`)            |
| Livewire | 3, 4                                   |

## Installation

Install the package via Composer:

```bash
composer require happenv-com/filament-saved-views
```

Publish and run the migration that creates the `saved_views` table:

```bash
php artisan vendor:publish --tag="filament-saved-views-migrations"
php artisan migrate
```

Publish the package assets (registers the manager stylesheet):

```bash
php artisan filament:assets
```

> [!IMPORTANT]
> If you have not set up a custom theme and are using Filament Panels, follow the instructions in the [Filament docs](https://filamentphp.com/docs/5.x/styling/overview#creating-a-custom-theme) first.

Add the package's views to your theme's CSS file, so Tailwind generates the classes they use:

```css
@source '../../../../vendor/happenv-com/filament-saved-views/resources/**/*.blade.php';
```

Register the plugin in your panel provider — see [Usage](#usage).

## Configuration

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="filament-saved-views-config"
```

`config/filament-happenv-saved-views.php`:

```php
return [
    'table' => 'saved_views', // backing table
    'guard' => null,          // auth guard used to scope views (null = default)
    'icons' => [              // Heroicon enums by default
        'manager' => Heroicon::Eye,
        'delete'  => Heroicon::Trash,
        'save'    => Heroicon::ArrowDownTray,
        'update'  => Heroicon::ArrowPath,
        'item'    => Heroicon::Bookmark,
        'edit'    => Heroicon::Cog6Tooth,
        'reorder' => Heroicon::Bars2,
    ],
];
```

Optionally, publish the views and translations:

```bash
php artisan vendor:publish --tag="filament-saved-views-views"
php artisan vendor:publish --tag="filament-saved-views-translations"
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

### 3. Restyle the manager button (optional)

The toolbar button is a `SavedViewManagerAction`, so it configures the way any Filament component
does — once, for every table:

```php
use Happenv\FilamentSavedViews\Filament\Actions\SavedViewManagerAction;

SavedViewManagerAction::configureUsing(
    fn (SavedViewManagerAction $action) => $action->color('primary')->badgeColor('warning'),
);
```

Your callback runs after the package's own defaults, so anything you set wins. A broad
`Action::configureUsing()` registered against the parent still runs earlier, so a panel-wide default
does not quietly repaint this one button.

### 4. Configure the manager per table (optional)

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

## Translations

The manager ships in every locale Filament ships:

`am` `ar` `az` `bg` `bn` `bs` `ca` `ckb` `cs` `da` `de` `el` `en` `es` `et` `eu` `fa` `fi` `fil` `fr` `he` `hi` `hr` `hu` `hy` `id` `it` `ja` `ka` `km` `ko` `ku` `lt` `lus` `lv` `mk` `mn` `ms` `my` `nb` `ne` `nl` `pl` `pt` `pt_BR` `ro` `ru` `sk` `sl` `sq` `sr_Cyrl` `sr_Latn` `sv` `sw` `tg` `th` `tr` `uk` `ur` `uz` `vi` `zh_CN` `zh_HK` `zh_TW`

Publish them with `php artisan vendor:publish --tag="filament-saved-views-translations"` to change the
wording or add a language. `tests/Unit/TranslationsTest.php` checks that every locale has exactly the
keys English has, and that every locale Filament ships has a translation.

## Development

```bash
composer test          # unit and feature tests
composer phpstan       # static analysis
composer cs            # fix code style: composer normalize, Rector, Pint
composer ci            # everything CI checks, locally
```

The stylesheet (`resources/css/saved-views.css`) is plain CSS registered with `FilamentAsset` — there
is no build step.

## Upgrading

Breaking changes and how to migrate are described in [UPGRADING](UPGRADING.md) for every major version.

## Changelog

See [CHANGELOG](CHANGELOG.md) and [GitHub releases](https://github.com/happenv-com/filament-saved-views/releases) for what has changed recently.

## Contributing

See [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Happenv sp. z o.o.](https://happenv.com)
- [webard](https://github.com/webard)
- [All contributors](../../contributors)

## License

The MIT License (MIT). See [License File](LICENSE.md) for more information.

---

<p align="center">
    <a href="https://happenv.com">
        <img src="art/happenv-logo.png" alt="Happenv" width="400">
    </a>
</p>
