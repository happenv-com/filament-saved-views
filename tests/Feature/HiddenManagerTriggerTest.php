<?php

declare(strict_types=1);

use Filament\Tables\Table;
use Happenv\FilamentSavedViews\Filament\Enums\SavedViewManagerLayout;
use Happenv\FilamentSavedViews\Tests\Fixtures\SavedViewsListComponent;
use Illuminate\Auth\GenericUser;

beforeEach(function (): void {
    $this->actingAs(new GenericUser(['id' => 7]));

    // The control nests the manager's own Livewire component, whose snapshot is signed.
    config(['app.key' => 'base64:' . base64_encode(str_repeat('k', 32))]);

    $this->component = new SavedViewsListComponent;
    $this->component->setId('page');

    /**
     * The control the toolbar render hook would print for this page, for a table configured by `$configure`.
     */
    $this->renderControl = function (Closure $configure): string {
        $table = $configure(Table::make($this->component));

        return view('filament-saved-views::control', [
            'resourceClass' => SavedViewsListComponent::getResource(),
            'activeViewId' => null,
            'hasUnsavedChanges' => false,
            'livewireKey' => 'saved-views-control-page',
            // @phpstan-ignore-next-line method.notFound (Table macro)
            'layout' => $table->getSavedViewManagerLayout(),
            // @phpstan-ignore-next-line method.notFound (Table macro)
            'triggerAction' => $table->getSavedViewManagerTriggerAction(),
            // @phpstan-ignore-next-line method.notFound (Table macro)
            'deferred' => $table->getDeferSavedViewManager(),
            // @phpstan-ignore-next-line method.notFound (Table macro)
            'triggerHidden' => $table->isSavedViewManagerTriggerHidden(),
        ])->render();
    };
});

it('gives the host the id the manager modal is rendered under', function (): void {
    // The render hook keys the control `saved-views-control-<page id>` and the modal `<key>.manager`;
    // the page's answer has to name that same modal, or `open-modal` opens nothing.
    $html = ($this->renderControl)(fn (Table $table): Table => $table->savedViewManagerTriggerHidden());

    expect($this->component->getSavedViewManagerModalId())->toBe('saved-views-control-page.manager')
        ->and($html)->toContain('id="' . $this->component->getSavedViewManagerModalId() . '"');
});

it('renders the trigger with the modal by default', function (): void {
    // @phpstan-ignore-next-line method.notFound (Table macro)
    $html = ($this->renderControl)(fn (Table $table): Table => $table->savedViewManagerLayout(SavedViewManagerLayout::Modal));

    expect($html)->toContain('fi-modal-trigger')
        ->toContain('fi-force-enabled')
        ->toContain('id="saved-views-control-page.manager"');
});

it('renders only the modal, under the page id, when the trigger is hidden', function (): void {
    // @phpstan-ignore-next-line method.notFound (Table macro)
    $html = ($this->renderControl)(fn (Table $table): Table => $table->savedViewManagerLayout(SavedViewManagerLayout::Modal)->savedViewManagerTriggerHidden());

    expect($html)->not->toContain('fi-modal-trigger')
        // The toolbar button itself (the package marks it so a disabled table cannot grey it out).
        ->not->toContain('fi-force-enabled')
        ->toContain('id="saved-views-control-page.manager"')
        ->toContain('fi-fm-saved-view-manager-modal');

    // The modal is the only top-level element, and it starts shut — so the toolbar's flex row has
    // nothing to lay out until the host opens it.
    expect(trim($html))->toStartWith('<div')
        ->and($html)->toContain('x-show="isOpen"');
});

it('renders a modal rather than a dropdown when the trigger is hidden', function (): void {
    // A dropdown hangs from its button; without one there would be nothing to open.
    // @phpstan-ignore-next-line method.notFound (Table macro)
    $html = ($this->renderControl)(fn (Table $table): Table => $table->savedViewManagerTriggerHidden());

    expect($html)->not->toContain('fi-fm-saved-view-manager-dropdown')
        ->toContain('fi-fm-saved-view-manager-modal')
        ->toContain('id="saved-views-control-page.manager"');
});

it('keeps the slide-over configured on the trigger action when the trigger is hidden', function (): void {
    // @phpstan-ignore-next-line method.notFound (Table macro)
    $html = ($this->renderControl)(fn (Table $table): Table => $table
        ->savedViewManagerTriggerAction(fn ($action) => $action->slideOver())
        ->savedViewManagerTriggerHidden());

    expect($html)->toContain('fi-modal-slide-over')
        ->not->toContain('fi-modal-trigger');
});
