<x-filament-panels::page>
    <style>
        .hs-inbox { --hs-line: #e5e7eb; --hs-muted: #6b7280; --hs-soft: #f3f4f6; --hs-panel: #fff; --hs-ai: #ede9fe; --hs-ai-ink: #5b21b6; display: grid; grid-template-columns: 20rem 1fr; height: max(30rem, calc(100vh - 13rem)); border: 1px solid var(--hs-line); border-radius: 0.75rem; overflow: hidden; background: var(--hs-panel) }
        .dark .hs-inbox { --hs-line: #3f3f46; --hs-muted: #a1a1aa; --hs-soft: #27272a; --hs-panel: #18181b; --hs-ai: #2e1065; --hs-ai-ink: #ddd6fe }
        .hs-side { display: flex; flex-direction: column; min-height: 0; border-inline-end: 1px solid var(--hs-line) }
        .hs-tabs { display: flex; gap: 0.25rem; padding: 0.5rem; border-bottom: 1px solid var(--hs-line) }
        .hs-tab { flex: 1; padding: 0.375rem 0.5rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 500; color: var(--hs-muted) }
        .hs-tab[aria-selected=true] { background: var(--hs-soft); color: inherit }
        .hs-list { flex: 1; overflow-y: auto }
        .hs-item { display: grid; grid-template-columns: 1fr auto; gap: 0.125rem 0.5rem; width: 100%; padding: 0.625rem 0.75rem; text-align: start; border-bottom: 1px solid var(--hs-line) }
        .hs-item:hover, .hs-item[aria-current=true] { background: var(--hs-soft) }
        .hs-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.875rem; font-weight: 600 }
        .hs-time { font-size: 0.75rem; color: var(--hs-muted) }
        .hs-preview { grid-column: 1 / -1; display: flex; align-items: center; gap: 0.375rem; min-width: 0; font-size: 0.8125rem; color: var(--hs-muted) }
        .hs-preview span:first-of-type { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1 }
        .hs-badge { min-width: 1.25rem; height: 1.25rem; padding: 0 0.375rem; border-radius: 9999px; background: #ef4444; color: #fff; font-size: 0.75rem; font-weight: 600; line-height: 1.25rem; text-align: center }
        .hs-pill { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.0625rem 0.5rem; border-radius: 9999px; font-size: 0.6875rem; font-weight: 600; background: var(--hs-soft); color: var(--hs-muted); white-space: nowrap }
        .hs-pill.is-ai { background: var(--hs-ai); color: var(--hs-ai-ink) }
        .hs-main { display: flex; flex-direction: column; min-width: 0; min-height: 0 }
        .hs-head { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem 0.75rem; padding: 0.75rem 1rem; border-bottom: 1px solid var(--hs-line) }
        .hs-head-who { flex: 1; min-width: 10rem }
        .hs-sub { font-size: 0.75rem; color: var(--hs-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap }
        .hs-actions { display: flex; gap: 0.375rem }
        .hs-messages { flex: 1; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: 0.5rem }
        .hs-msg { max-width: min(36rem, 80%); align-self: flex-start }
        .hs-msg.is-team { align-self: flex-end; text-align: end }
        .hs-bubble { display: inline-block; padding: 0.5rem 0.75rem; border-radius: 1rem; background: var(--hs-soft); white-space: pre-wrap; overflow-wrap: anywhere; text-align: start; font-size: 0.875rem }
        .is-team .hs-bubble { background: var(--hs-primary, #d97706); color: #fff }
        .is-ai .hs-bubble { background: var(--hs-ai); color: var(--hs-ai-ink) }
        .hs-meta { margin-top: 0.125rem; font-size: 0.6875rem; color: var(--hs-muted) }
        .hs-note { align-self: center; font-size: 0.75rem; color: var(--hs-muted) }
        .hs-draft { margin: 0 0.75rem; padding: 0.625rem 0.75rem; border-radius: 0.75rem; background: var(--hs-ai); color: var(--hs-ai-ink); font-size: 0.8125rem; display: grid; gap: 0.5rem }
        .hs-draft p { white-space: pre-wrap }
        .hs-compose { display: flex; gap: 0.5rem; align-items: flex-end; padding: 0.75rem; border-top: 1px solid var(--hs-line) }
        .hs-input { flex: 1; resize: none; max-height: 10rem; padding: 0.5rem 0.75rem; border: 1px solid var(--hs-line); border-radius: 0.5rem; background: transparent; color: inherit; font-size: 0.875rem }
        .hs-empty { margin: auto; padding: 2rem; text-align: center; color: var(--hs-muted); font-size: 0.875rem; display: grid; gap: 0.75rem; justify-items: center }
        .hs-banner { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 1rem; padding: 0.75rem 1rem; border: 1px solid var(--hs-line, #e5e7eb); border-radius: 0.75rem; font-size: 0.875rem }
        .hs-back { display: none }
        @media (max-width: 767px) {
            .hs-inbox { grid-template-columns: 1fr; height: calc(100dvh - 14rem) }
            .hs-inbox.has-active .hs-side, .hs-inbox:not(.has-active) .hs-main { display: none }
            .hs-back { display: inline-flex }
        }
    </style>

    @if ($aiMode === 'off' && $settingsUrl)
        <div class="hs-banner">
            <span>{{ __('hibira-support::support.ai_off') }}</span>
            <x-filament::button tag="a" :href="$settingsUrl" size="sm" icon="heroicon-o-sparkles">
                {{ __('hibira-support::support.set_up_ai') }}
            </x-filament::button>
        </div>
    @endif

    <div
        x-data="{
            channel: @js($channel),
            conversations: @js($conversations),
            filter: 'open',
            active: null,
            messages: [],
            draft: '',
            sending: false,
            typingAt: 0,
            timer: null,
            listener: null,
            strings: @js(trans('hibira-support::support')),
            init() {
                const primary = getComputedStyle(document.documentElement).getPropertyValue('--primary-600').trim()
                if (primary) this.$el.style.setProperty('--hs-primary', primary.includes('(') || primary.startsWith('#') ? primary : `rgb(${primary})`)

                this.listener = (event) => this.changed(event)
                const listen = () => {
                    if (! window.Echo) return false
                    window.Echo.private(this.channel).listen('.conversation', this.listener)
                    return true
                }
                if (! listen()) window.addEventListener('EchoLoaded', listen, { once: true })

                // Without a connection, look for new messages now and then.
                this.timer = setInterval(() => {
                    if (window.Echo?.connector?.pusher?.connection?.state !== 'connected') this.refresh()
                }, 10000)

                const wanted = new URLSearchParams(location.search).get('c')
                if (wanted) this.open(wanted)
            },
            destroy() {
                clearInterval(this.timer)
                window.hibiraSupportActive = null
                window.Echo?.private(this.channel).stopListening('.conversation', this.listener)
            },
            get current() {
                return this.conversations.find((c) => c.id === this.active) ?? null
            },
            async refresh() {
                this.conversations = await $wire.loadConversations(this.filter)
                if (this.active) {
                    const data = await $wire.openConversation(this.active)
                    if (data) this.show(data)
                }
            },
            async setFilter(filter) {
                this.filter = filter
                this.conversations = await $wire.loadConversations(filter)
            },
            async changed(event) {
                this.conversations = await $wire.loadConversations(this.filter)
                if (event.id === this.active) {
                    const data = await $wire.openConversation(this.active)
                    if (data) this.show(data)
                }
            },
            async open(id) {
                this.active = id
                window.hibiraSupportActive = id
                const data = await $wire.openConversation(id)
                if (! data) return
                this.show(data)
                const item = this.conversations.find((c) => c.id === id)
                if (item) item.unread = 0
                this.$nextTick(() => this.$refs.input?.focus())
            },
            show(data) {
                const index = this.conversations.findIndex((c) => c.id === data.conversation.id)
                if (index >= 0) this.conversations[index] = data.conversation
                else this.conversations.unshift(data.conversation)
                this.messages = data.messages
                this.$nextTick(() => this.$refs.messages && (this.$refs.messages.scrollTop = this.$refs.messages.scrollHeight))
            },
            close() {
                this.active = null
                window.hibiraSupportActive = null
            },
            async send() {
                const body = this.draft.trim()
                if (! body || this.sending || ! this.active) return
                this.sending = true
                const message = await $wire.reply(this.active, body)
                this.sending = false
                if (! message) return
                this.draft = ''
                const data = await $wire.openConversation(this.active)
                if (data) this.show(data)
            },
            typed() {
                if (! this.active || Date.now() - this.typingAt < 3000) return
                this.typingAt = Date.now()
                $wire.typing(this.active)
            },
            useDraft() {
                this.draft = this.current.ai_draft
                this.$nextTick(() => this.$refs.input?.focus())
            },
            async act(method) {
                await $wire[method](this.active)
                if (method === 'close') this.close()
                await this.refresh()
            },
            label(message) {
                if (message.sender === 'visitor') return this.current?.name
                if (message.sender === 'assistant') return this.strings.assistant
                return message.author
            },
            time(at) {
                if (! at) return ''
                const date = new Date(at)
                return date.toDateString() === new Date().toDateString()
                    ? date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                    : date.toLocaleDateString([], { month: 'short', day: 'numeric' })
            },
        }"
        class="hs-inbox"
        :class="{ 'has-active': active }"
    >
        <aside class="hs-side">
            <div class="hs-tabs" role="tablist">
                @foreach (['open', 'mine', 'closed'] as $tab)
                    <button type="button" class="hs-tab" role="tab" :aria-selected="filter === @js($tab)" x-on:click="setFilter(@js($tab))">
                        {{ __('hibira-support::support.filter_'.$tab) }}
                    </button>
                @endforeach
            </div>

            <div class="hs-list">
                <template x-for="c in conversations" :key="c.id">
                    <button type="button" class="hs-item" :aria-current="active === c.id" x-on:click="open(c.id)">
                        <span class="hs-name" x-text="c.name"></span>
                        <span class="hs-time" x-text="time(c.at)"></span>
                        <span class="hs-preview">
                            <span x-text="c.preview"></span>
                            <span class="hs-pill is-ai" x-show="c.handled_by === 'assistant' && c.status === 'open'">AI</span>
                            <span class="hs-badge" x-show="c.unread > 0" x-text="c.unread"></span>
                        </span>
                    </button>
                </template>

                <p class="hs-empty" x-show="conversations.length === 0">
                    {{ __('hibira-support::support.empty') }}
                </p>
            </div>
        </aside>

        <section class="hs-main">
            <template x-if="current">
                <div class="hs-head">
                    <x-filament::icon-button class="hs-back" icon="heroicon-o-arrow-left" color="gray" x-on:click="close()" :label="__('hibira-support::support.back')" />
                    <div class="hs-head-who">
                        <div class="hs-name" x-text="current.name"></div>
                        <div class="hs-sub">
                            <span x-text="current.email"></span>
                            <span x-show="current.email && current.page_url"> · </span>
                            <a x-show="current.page_url" :href="current.page_url" target="_blank" rel="noopener" class="underline" x-text="current.page_url"></a>
                        </div>
                    </div>
                    <span class="hs-pill" :class="{ 'is-ai': current.handled_by === 'assistant' }" x-text="current.status === 'closed' ? strings.closed : (current.handled_by === 'assistant' ? strings.handled_ai : strings.handled_human)"></span>
                    <div class="hs-actions">
                        <x-filament::icon-button icon="heroicon-o-user" color="gray" x-show="current.handled_by === 'assistant'" x-on:click="act('takeOver')" :tooltip="__('hibira-support::support.take_over')" :label="__('hibira-support::support.take_over')" />
                        <x-filament::icon-button icon="heroicon-o-sparkles" color="gray" x-show="current.handled_by === 'human' && current.status === 'open'" x-on:click="act('handBack')" :tooltip="__('hibira-support::support.hand_back')" :label="__('hibira-support::support.hand_back')" />
                        <x-filament::icon-button icon="heroicon-o-check-circle" color="gray" x-show="current.status === 'open'" x-on:click="act('close')" :tooltip="__('hibira-support::support.close')" :label="__('hibira-support::support.close')" />
                    </div>
                </div>
            </template>

            <div class="hs-messages" x-ref="messages" x-show="current">
                <template x-for="m in messages" :key="m.id">
                    <div>
                        <template x-if="m.sender === 'system'">
                            <p class="hs-note" x-text="strings.handed_off"></p>
                        </template>
                        <template x-if="m.sender !== 'system'">
                            <div class="hs-msg" :class="{ 'is-team': m.sender !== 'visitor', 'is-ai': m.sender === 'assistant' }">
                                <div class="hs-bubble" x-text="m.body"></div>
                                <div class="hs-meta"><span x-text="label(m)"></span> · <span x-text="time(m.at)"></span></div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <template x-if="current && current.ai_draft">
                <div class="hs-draft">
                    <strong>{{ __('hibira-support::support.suggested') }}</strong>
                    <p x-text="current.ai_draft"></p>
                    <div>
                        <x-filament::button size="xs" color="gray" x-on:click="useDraft()" icon="heroicon-o-pencil-square">
                            {{ __('hibira-support::support.use_suggestion') }}
                        </x-filament::button>
                    </div>
                </div>
            </template>

            <form class="hs-compose" x-show="current" x-on:submit.prevent="send()">
                <textarea
                    x-ref="input"
                    x-model="draft"
                    rows="2"
                    class="hs-input"
                    placeholder="{{ __('hibira-support::support.placeholder') }}"
                    x-on:input="typed()"
                    x-on:keydown.enter="if (! $event.shiftKey && ! $event.isComposing) { $event.preventDefault(); send() }"
                ></textarea>
                <x-filament::button type="submit" icon="heroicon-o-paper-airplane" x-bind:disabled="sending || ! draft.trim()">
                    {{ __('hibira-support::support.send') }}
                </x-filament::button>
            </form>

            <div class="hs-empty" x-show="! current">
                <x-filament::icon icon="heroicon-o-lifebuoy" class="h-10 w-10" />
                <span>{{ __('hibira-support::support.pick') }}</span>
            </div>
        </section>
    </div>
</x-filament-panels::page>
