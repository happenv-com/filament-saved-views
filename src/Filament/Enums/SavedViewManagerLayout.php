<?php

declare(strict_types=1);

namespace Happenv\FilamentSavedViews\Filament\Enums;

use Filament\Tables\Enums\ColumnManagerLayout;

/**
 * Controls how the saved-views manager is presented in a table toolbar,
 * mirroring Filament's own {@see ColumnManagerLayout}.
 */
enum SavedViewManagerLayout
{
    case Dropdown;

    case Modal;
}
