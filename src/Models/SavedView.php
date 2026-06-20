<?php

declare(strict_types=1);

namespace Happenv\FilamentSavedViews\Models;

use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * @property array<string, mixed>|null $saved_data
 */
class SavedView extends Model
{
    protected $guarded = [];

    #[Override]
    public function getTable(): string
    {
        return $this->table ?? config('filament-happenv-saved-views.table', 'saved_views');
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'saved_data' => 'array',
            'sort_order' => 'integer',
            'submenu_visible' => 'boolean',
        ];
    }
}
