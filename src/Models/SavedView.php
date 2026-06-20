<?php

declare(strict_types=1);

namespace Happenv\FilamentSavedViews\Models;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Override;

/**
 * @property Collection<string, mixed> $filters
 */
class SavedView extends Model
{
    protected $guarded = [];

    #[Override]
    public function getTable(): string
    {
        return $this->table ?? config('saved-views.table', 'saved_views');
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'filters' => AsCollection::class,
        ];
    }
}
