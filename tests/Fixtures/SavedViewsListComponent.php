<?php

declare(strict_types=1);

namespace Happenv\FilamentSavedViews\Tests\Fixtures;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Happenv\FilamentSavedViews\Filament\Concerns\HasSavedViews;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Stands in for a resource List page.
 *
 * {@see HasSavedViews} is declared `@mixin ListRecords`, but everything it actually needs from one
 * is the table concern plus `getResource()` — so this fixture supplies exactly that, rather than
 * dragging a whole panel, resource and model into the test suite.
 */
class SavedViewsListComponent extends Component implements HasActions, HasSchemas, HasTable
{
    use HasSavedViews;
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public static function getResource(): string
    {
        return SavedViewsStubResource::class;
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => [])
            // Without this the reorder flag is never written, and a saved view's column ORDER
            // has nothing to restore.
            ->reorderableColumns()
            ->columns([
                TextColumn::make('name')->toggleable(),
                TextColumn::make('email')->toggleable(),
            ]);
    }

    public function render(): View
    {
        return view('saved-views-fixtures::list');
    }
}
