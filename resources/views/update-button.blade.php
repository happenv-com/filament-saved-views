@if ($visible)
    <div class="fi-fm-sv-update">
        <x-filament::button
            wire:click="updateCurrentView"
            :icon="config('filament-happenv-saved-views.icons.update')"
            color="success"
            size="sm"
            class="w-full"
        >
            {{ __('filament-saved-views::saved-views.update') }}
        </x-filament::button>
    </div>
@endif
