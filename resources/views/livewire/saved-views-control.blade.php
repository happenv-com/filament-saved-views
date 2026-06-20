<div>
    @php ($activeView = request()->query('savedView'))

    <div class="fi-fm-sv flex flex-col gap-3">
        {{-- Capture the current filter/search state as a new named view. --}}
        <div class="flex items-center gap-2" x-data="{ label: '' }">
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

        {{-- Manager list — mirrors Filament's table column-manager markup/classes. --}}
        @if ($this->views->isEmpty())
            <div class="px-2 py-1.5 text-sm text-gray-400 dark:text-gray-500">
                {{ __('filament-saved-views::saved-views.empty') }}
            </div>
        @else
            <div
                x-sortable
                x-on:end.stop="$wire.reorderViews($event.target.sortable.toArray())"
                data-sortable-animation-duration="300"
                class="fi-fm-sv-items"
            >
                @foreach ($this->views as $view)
                    <div x-sortable-item="{{ $view->id }}" wire:key="saved-view-{{ $view->id }}">
                        <div class="fi-fm-sv-item">
                            <div class="fi-fm-sv-label">
                                <input
                                    type="checkbox"
                                    class="fi-checkbox-input"
                                    @checked($view->submenu_visible)
                                    wire:change="toggleSubmenu('{{ $view->id }}')"
                                    :title="__('filament-saved-views::saved-views.submenu_visible')"
                                />

                                <a
                                    href="{{ $this->urlFor($view) }}"
                                    wire:navigate
                                    @class([
                                        'fi-fm-sv-link',
                                        'font-semibold text-primary-600 dark:text-primary-400' => (string) $activeView === (string) $view->id,
                                    ])
                                >{{ $view->label }}</a>
                            </div>

                            {{ ($this->editViewAction)(['id' => $view->id]) }}
                            {{ ($this->deleteViewAction)(['id' => $view->id]) }}

                            <button
                                x-sortable-handle
                                x-on:click.stop
                                class="fi-fm-sv-handle"
                                type="button"
                            >
                                {{ \Filament\Support\generate_icon_html(config('filament-happenv-saved-views.icons.reorder')) }}
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Taken out of flow: the modals wrapper is a height:0 block element that
         otherwise sits in the toolbar's flex row as an empty extra item. The
         modal overlay itself is fixed-positioned, so it still renders correctly. --}}
    <div class="absolute">
        <x-filament-actions::modals />
    </div>
</div>
