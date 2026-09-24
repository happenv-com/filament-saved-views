<?php

declare(strict_types=1);

namespace Happenv\FilamentSavedViews\Tests\Fixtures;

use Happenv\FilamentSavedViews\Filament\Concerns\HasSavedViews;

/**
 * The one thing {@see HasSavedViews} asks of a
 * resource: a URL to build the saved view's link from.
 */
class SavedViewsStubResource
{
    public static function getUrl(string $name = 'index'): string
    {
        return '/stub/' . $name;
    }
}
