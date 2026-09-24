<?php

declare(strict_types=1);

$template = fn (): string => (string) file_get_contents(dirname(__DIR__, 2) . '/resources/views/livewire/saved-views-control.blade.php');

it('names the drag handle after the view it moves', function () use ($template): void {
    // An icon-only button with no name is announced as just "button". Filament labels its own drag
    // handles with its translated "Move" / "Reorder column" plus what moves; this does the same.
    expect($template())->toContain('aria-label="{{ __(\'filament-forms::components.builder.actions.reorder.label\') }} {{ $view->label }}"');
});

it('names the sub-navigation checkbox after its view', function () use ($template): void {
    // The title alone read the same in every row.
    expect($template())->toContain('aria-label="{{ __(\'filament-saved-views::saved-views.submenu_visible\') }}: {{ $view->label }}"');
});

it('resolves the borrowed Filament label in every locale the package ships', function (): void {
    foreach (glob(dirname(__DIR__, 2) . '/resources/lang/*', GLOB_ONLYDIR) ?: [] as $directory) {
        $label = trans('filament-forms::components.builder.actions.reorder.label', [], basename($directory));

        expect($label)->toBeString()->not->toBe('filament-forms::components.builder.actions.reorder.label');
    }
});
