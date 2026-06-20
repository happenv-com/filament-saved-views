<?php

declare(strict_types=1);

use Filament\Support\Icons\Heroicon;

return [
    // Table backing the saved-views model. A host may point this at its own
    // table (the Filamerce connector uses `fm_saved_views`).
    'table' => 'saved_views',

    // Auth guard used to scope views to the current user (null = default guard).
    'guard' => null,

    // Icons used across the control. Heroicon enums by default; a host may
    // override these with its own icon set's string names (e.g. `phosphor-eye`).
    'icons' => [
        'manager' => Heroicon::Eye,
        'delete' => Heroicon::Trash,
        'save' => Heroicon::ArrowDownTray,
        'item' => Heroicon::Bookmark,
    ],
];
