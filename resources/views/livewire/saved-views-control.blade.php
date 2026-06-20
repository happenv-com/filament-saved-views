<div>
    @php ($activeView = request()->query('savedView'))

    {{-- Content only. The dropdown / modal / slide-over wrapper and the trigger
         button are rendered by the toolbar wrapper view (control.blade.php),
         driven by the table's saved-view manager config. --}}
    <div class="flex flex-col gap-3 p-3" x-data="{ label: '' }">
        <div class="flex items-center gap-2">
            <x-filament::input.wrapper class="flex-1">
                <x-filament::input
                    type="text"
                    x-model="label"
                    :placeholder="__('filament-saved-views::saved-views.name_placeholder')"
                    x-on:keydown.enter.prevent="
                        $wire.save(label, window.location.search)
                        label = ''
                    "
                />
            </x-filament::input.wrapper>

            <x-filament::button
                :icon="config('filament-happenv-saved-views.icons.save')"
                color="primary"
                :title="__('filament-saved-views::saved-views.save')"
                x-on:click="
                    $wire.save(label, window.location.search)
                    label = ''
                "
                x-bind:disabled="!label.trim()"
                class="shrink-0"
            />
        </div>

        <div class="-mx-1 border-t border-gray-100 dark:border-white/10"></div>

        <ul class="flex flex-col gap-0.5">
            @forelse ($this->views as $view)
                <li
                    @class ([
                        'group flex items-center justify-between gap-2 rounded-lg px-2 py-1.5 text-sm',
                        'bg-gray-50 dark:bg-white/5' => (string) $activeView === (string) $view->id,
                        'hover:bg-gray-50 dark:hover:bg-white/5' => (string) $activeView !== (string) $view->id,
                    ])
                >
                    <a
                        href="{{ $this->urlFor($view) }}"
                        wire:navigate
                        class="flex min-w-0 flex-1 items-center gap-2 text-gray-700 dark:text-gray-200"
                    >
                        <x-filament::icon :icon="config('filament-happenv-saved-views.icons.item')" class="h-4 w-4 shrink-0 text-gray-400" />
                        <span class="truncate">{{ $view->label }}</span>
                    </a>

                    {{ ($this->deleteViewAction)(['id' => $view->id]) }}
                </li>
            @empty
                <li class="px-2 py-1.5 text-sm text-gray-400 dark:text-gray-500">
                    {{ __('filament-saved-views::saved-views.empty') }}
                </li>
            @endforelse
        </ul>
    </div>

    {{-- Taken out of flow: the modals wrapper is a height:0 block element that
         otherwise sits in the toolbar's flex row as an empty extra item. The
         modal overlay itself is fixed-positioned, so it still renders correctly. --}}
    <div class="absolute">
        <x-filament-actions::modals />
    </div>
</div>
