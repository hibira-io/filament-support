<x-filament-panels::page>
    <style>
        .hs-form { display: grid; gap: 1.5rem; max-width: 48rem }
        .hs-field { display: grid; gap: 0.375rem }
        .hs-field > label { font-size: 0.875rem; font-weight: 500 }
        .hs-help { font-size: 0.8125rem; color: #6b7280 }
        .dark .hs-help { color: #a1a1aa }
        .hs-error { font-size: 0.8125rem; color: #dc2626 }
        .hs-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)) }
        .hs-textarea { width: 100%; padding: 0.5rem 0.75rem; border: 0; background: transparent; color: inherit; font-size: 0.875rem; resize: vertical }
        .hs-modes { display: grid; gap: 0.5rem; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)) }
        .hs-mode { display: grid; gap: 0.25rem; padding: 0.75rem; border: 1px solid #e5e7eb; border-radius: 0.75rem; cursor: pointer; font-size: 0.8125rem }
        .dark .hs-mode { border-color: #3f3f46 }
        .hs-mode strong { display: flex; align-items: center; gap: 0.375rem; font-size: 0.875rem }
        .hs-mode:has(input:checked) { border-color: var(--hs-primary, #d97706); box-shadow: 0 0 0 1px var(--hs-primary, #d97706) }
        .hs-mode input { position: absolute; opacity: 0; pointer-events: none }
        .hs-code { display: block; padding: 0.75rem; border-radius: 0.5rem; background: #f3f4f6; font-size: 0.8125rem; overflow-x: auto; white-space: pre }
        .dark .hs-code { background: #27272a }
        .hs-answer { padding: 0.75rem; border-radius: 0.5rem; background: #ede9fe; color: #5b21b6; font-size: 0.875rem; white-space: pre-wrap }
        .dark .hs-answer { background: #2e1065; color: #ddd6fe }
        .hs-row { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center }
        .hs-swatch { width: 2.5rem; height: 2.25rem; padding: 0; border: 0; background: none; cursor: pointer }
    </style>

    <form
        wire:submit="save"
        class="hs-form"
        x-data
        x-init="(() => { const p = getComputedStyle(document.documentElement).getPropertyValue('--primary-600').trim(); if (p) $el.style.setProperty('--hs-primary', p.includes('(') || p.startsWith('#') ? p : `rgb(${p})`) })()"
    >
        @unless ($hibiraReady)
            <x-filament::section>
                <x-slot name="heading">{{ __('hibira-support::settings.hibira_missing') }}</x-slot>
                <p class="hs-help">{!! __('hibira-support::settings.hibira_missing_help', ['url' => '<a class="underline" href="https://hibira.io/portal" target="_blank" rel="noopener">hibira.io/portal</a>']) !!}</p>
            </x-filament::section>
        @endunless

        <x-filament::section>
            <x-slot name="heading">{{ __('hibira-support::settings.mode') }}</x-slot>
            <x-slot name="description">{{ __('hibira-support::settings.mode_help') }}</x-slot>

            <div class="hs-modes">
                @foreach (['off' => 'heroicon-o-power', 'draft' => 'heroicon-o-pencil-square', 'auto' => 'heroicon-o-bolt'] as $mode => $icon)
                    <label class="hs-mode">
                        <input type="radio" wire:model.live="ai_mode" value="{{ $mode }}">
                        <strong><x-filament::icon :icon="$icon" class="h-5 w-5" /> {{ __('hibira-support::settings.mode_'.$mode) }}</strong>
                        <span class="hs-help">{{ __('hibira-support::settings.mode_'.$mode.'_help') }}</span>
                    </label>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">{{ __('hibira-support::settings.provider_section') }}</x-slot>
            <x-slot name="description">{{ __('hibira-support::settings.provider_help') }}</x-slot>

            <div class="hs-form">
                <div class="hs-grid">
                    <div class="hs-field">
                        <label for="hs-provider">{{ __('hibira-support::settings.provider') }}</label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select id="hs-provider" wire:model.live="provider">
                                @foreach ($providers as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>

                    <div class="hs-field">
                        <label for="hs-model">{{ __('hibira-support::settings.model') }}</label>
                        <x-filament::input.wrapper :valid="! $errors->has('model')">
                            <x-filament::input id="hs-model" type="text" wire:model="model" :placeholder="$suggestedModel" />
                        </x-filament::input.wrapper>
                        @error('model') <p class="hs-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="hs-field">
                    <label for="hs-key">{{ __('hibira-support::settings.api_key') }}</label>
                    <div class="hs-row">
                        <div style="flex: 1; min-width: 14rem">
                            <x-filament::input.wrapper :valid="! $errors->has('api_key')">
                                <x-filament::input id="hs-key" type="password" autocomplete="off" wire:model="api_key" :placeholder="$keyHint ? __('hibira-support::settings.key_saved', ['hint' => $keyHint]) : 'sk-…'" />
                            </x-filament::input.wrapper>
                        </div>
                        @if ($keyHint)
                            <x-filament::button color="gray" size="sm" wire:click="forgetKey" icon="heroicon-o-trash">
                                {{ __('hibira-support::settings.forget_key') }}
                            </x-filament::button>
                        @endif
                    </div>
                    <p class="hs-help">{{ __('hibira-support::settings.api_key_help') }}</p>
                    @error('api_key') <p class="hs-error">{{ $message }}</p> @enderror
                </div>

                @if ($needsUrl || $base_url !== '')
                    <div class="hs-field">
                        <label for="hs-url">{{ __('hibira-support::settings.base_url') }}</label>
                        <x-filament::input.wrapper :valid="! $errors->has('base_url')">
                            <x-filament::input id="hs-url" type="url" wire:model="base_url" placeholder="http://localhost:11434/v1" />
                        </x-filament::input.wrapper>
                        <p class="hs-help">{{ __('hibira-support::settings.base_url_help') }}</p>
                        @error('base_url') <p class="hs-error">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="hs-field">
                    <label for="hs-limit">{{ __('hibira-support::settings.daily_limit') }}</label>
                    <div style="max-width: 12rem">
                        <x-filament::input.wrapper :valid="! $errors->has('daily_limit')">
                            <x-filament::input id="hs-limit" type="number" min="0" wire:model="daily_limit" />
                        </x-filament::input.wrapper>
                    </div>
                    <p class="hs-help">{{ __('hibira-support::settings.daily_limit_help') }}</p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">{{ __('hibira-support::settings.knowledge_section') }}</x-slot>
            <x-slot name="description">{{ __('hibira-support::settings.knowledge_help') }}</x-slot>

            <div class="hs-form">
                <div class="hs-field">
                    <label for="hs-instructions">{{ __('hibira-support::settings.instructions') }}</label>
                    <x-filament::input.wrapper>
                        <textarea id="hs-instructions" rows="3" class="hs-textarea" wire:model="instructions" placeholder="{{ __('hibira-support::settings.instructions_placeholder') }}"></textarea>
                    </x-filament::input.wrapper>
                </div>

                <div class="hs-field">
                    <label for="hs-knowledge">{{ __('hibira-support::settings.knowledge') }}</label>
                    <x-filament::input.wrapper :valid="! $errors->has('knowledge')">
                        <textarea id="hs-knowledge" rows="12" class="hs-textarea" wire:model="knowledge" placeholder="{{ __('hibira-support::settings.knowledge_placeholder') }}"></textarea>
                    </x-filament::input.wrapper>
                    @error('knowledge') <p class="hs-error">{{ $message }}</p> @enderror
                </div>

                <div class="hs-field">
                    <label for="hs-question">{{ __('hibira-support::settings.test') }}</label>
                    <div class="hs-row">
                        <div style="flex: 1; min-width: 14rem">
                            <x-filament::input.wrapper>
                                <x-filament::input id="hs-question" type="text" wire:model="question" wire:keydown.enter.prevent="test" :placeholder="__('hibira-support::settings.test_default')" />
                            </x-filament::input.wrapper>
                        </div>
                        <x-filament::button color="gray" wire:click="test" icon="heroicon-o-chat-bubble-left-ellipsis">
                            {{ __('hibira-support::settings.test_button') }}
                        </x-filament::button>
                    </div>
                    @if ($answer !== null)
                        <div class="hs-answer">{{ str_contains($answer, '[HANDOFF]') ? __('hibira-support::settings.test_handoff') : $answer }}</div>
                    @endif
                    @if ($error)
                        <p class="hs-error">{{ $error }}</p>
                    @endif
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">{{ __('hibira-support::settings.widget_section') }}</x-slot>
            <x-slot name="description">{{ __('hibira-support::settings.widget_help') }}</x-slot>

            <div class="hs-form">
                <div class="hs-grid">
                    <div class="hs-field">
                        <label for="hs-title">{{ __('hibira-support::settings.widget_title') }}</label>
                        <x-filament::input.wrapper>
                            <x-filament::input id="hs-title" type="text" wire:model="widget_title" :placeholder="config('app.name')" />
                        </x-filament::input.wrapper>
                    </div>
                    <div class="hs-field">
                        <label for="hs-color">{{ __('hibira-support::settings.color') }}</label>
                        <div class="hs-row">
                            <span class="hs-swatch" style="border-radius: 0.5rem; background: {{ preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#d97706' }}" aria-hidden="true"></span>
                            <div style="flex: 1">
                                <x-filament::input.wrapper :valid="! $errors->has('color')">
                                    <x-filament::input id="hs-color" type="text" wire:model.live="color" />
                                </x-filament::input.wrapper>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="hs-field">
                    <label for="hs-greeting">{{ __('hibira-support::settings.greeting') }}</label>
                    <x-filament::input.wrapper>
                        <x-filament::input id="hs-greeting" type="text" wire:model="greeting" :placeholder="__('hibira-support::widget.greeting')" />
                    </x-filament::input.wrapper>
                </div>

                <div class="hs-field">
                    <label>{{ __('hibira-support::settings.snippet') }}</label>
                    <code class="hs-code">{{ $snippet }}</code>
                    <p class="hs-help">{{ __('hibira-support::settings.snippet_help') }}</p>
                </div>
            </div>
        </x-filament::section>

        <div class="hs-row">
            <x-filament::button type="submit" icon="heroicon-o-check">
                {{ __('hibira-support::settings.save') }}
            </x-filament::button>
            <x-filament::button tag="a" color="gray" :href="$inboxUrl" icon="heroicon-o-lifebuoy">
                {{ __('hibira-support::support.inbox') }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
