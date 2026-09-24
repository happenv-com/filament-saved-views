<?php

declare(strict_types=1);

use Filament\Panel;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Happenv\FilamentSavedViews\FilamentSavedViewsPlugin;

it('registers on a panel under its id', function (): void {
    $panel = Panel::make()->id('admin')->plugin(FilamentSavedViewsPlugin::make());

    expect($panel->getPlugin('happenv-filament-saved-views'))
        ->toBeInstanceOf(FilamentSavedViewsPlugin::class);
});

it('registers the manager stylesheet as a Filament asset', function (): void {
    expect(FilamentAsset::getStyles(['happenv-com/filament-saved-views']))
        ->toHaveCount(1)
        ->each->toBeInstanceOf(Css::class);
});
