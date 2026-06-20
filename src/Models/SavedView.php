<?php

declare(strict_types=1);

namespace Happenv\FilamentSavedViews\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Override;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

/**
 * @property array<string, mixed>|null $saved_data
 */
class SavedView extends Model implements Sortable
{
    use SortableTrait;

    protected $guarded = [];

    /**
     * eloquent-sortable configuration. Ordering uses the `sort_order` column.
     * `sort_when_creating` is off so an explicitly-set order is never overwritten;
     * new views are appended on demand via {@see setHighestOrderNumber()}.
     *
     * @var array<string, mixed>
     */
    public array $sortable = [
        'order_column_name' => 'sort_order',
        'sort_when_creating' => false,
    ];

    #[Override]
    public function getTable(): string
    {
        return $this->table ?? config('filament-happenv-saved-views.table', 'saved_views');
    }

    /**
     * Order is scoped per owner + resource, so a new view is appended to the end
     * of its own (user, class) group rather than the whole table.
     */
    public function buildSortQuery(): Builder
    {
        return static::query()
            ->where('user_id', $this->user_id)
            ->where('class', $this->class);
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
