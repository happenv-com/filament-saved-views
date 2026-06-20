@php
    use Happenv\FilamentSavedViews\Filament\Enums\SavedViewManagerLayout;
@endphp

{{--
    Toolbar wrapper for the saved-views manager. The presentation (dropdown vs
    modal/slide-over) and the trigger button are driven by the table config set
    through `savedViewManagerLayout()` / `savedViewManagerTriggerAction()`,
    mirroring Filament's own column-manager toolbar. The interactive content
    (save input, list, delete) lives in the nested Livewire control.
--}}
@if ($layout === SavedViewManagerLayout::Modal || $triggerAction->isModalSlideOver())
    <x-filament::modal
        :alignment="$triggerAction->getModalAlignment()"
        :autofocus="$triggerAction->isModalAutofocused()"
        :close-button="$triggerAction->hasModalCloseButton()"
        :close-by-clicking-away="$triggerAction->isModalClosedByClickingAway()"
        :close-by-escaping="$triggerAction->isModalClosedByEscaping()"
        :description="$triggerAction->getModalDescription()"
        :extra-modal-window-attribute-bag="$triggerAction->getExtraModalWindowAttributeBag()"
        :extra-modal-overlay-attribute-bag="$triggerAction->getExtraModalOverlayAttributeBag()"
        :footer-actions="$triggerAction->getVisibleModalFooterActions()"
        :footer-actions-alignment="$triggerAction->getModalFooterActionsAlignment()"
        :heading="$triggerAction->getCustomModalHeading() ?? __('filament-saved-views::saved-views.label')"
        :icon="$triggerAction->getModalIcon()"
        :icon-color="$triggerAction->getModalIconColor()"
        :slide-over="$triggerAction->isModalSlideOver()"
        :slide-over-position="$triggerAction->getModalSlideOverPosition()"
        :sticky-footer="$triggerAction->isModalFooterSticky()"
        :sticky-header="$triggerAction->isModalHeaderSticky()"
        :width="$triggerAction->getModalWidth()"
        wire:key="{{ $livewireKey }}.modal"
        class="fi-fm-saved-view-manager-modal"
    >
        <x-slot name="trigger">
            {{ $triggerAction }}
        </x-slot>

        {{ $triggerAction->getModalContent() }}

        @livewire ('filament-saved-views-control', ['resourceClass' => $resourceClass, 'deferred' => $deferred], $livewireKey)

        {{ $triggerAction->getModalContentFooter() }}
    </x-filament::modal>
@else
    <x-filament::dropdown
        placement="bottom-end"
        width="xs"
        wire:key="{{ $livewireKey }}.dropdown"
        class="fi-fm-saved-view-manager-dropdown"
    >
        <x-slot name="trigger">
            {{ $triggerAction }}
        </x-slot>

        @livewire ('filament-saved-views-control', ['resourceClass' => $resourceClass, 'deferred' => $deferred], $livewireKey)
    </x-filament::dropdown>
@endif
