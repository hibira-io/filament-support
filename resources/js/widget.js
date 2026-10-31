/*
 * Hibira Support chat widget. Served by hibira-io/filament-support at
 * /hibira-support/widget.js; add it to a page with
 * <script src="https://your-app.example/hibira-support/widget.js" async></script>
 *
 * Optional attributes on the script tag: data-name and data-email for
 * signed-in visitors, data-open to open the chat on load.
 */
(function () {
    'use strict'

    var script = document.currentScript || document.querySelector('script[src*="hibira-support/widget.js"]')
    if (! script || window.__hibiraSupport) return
    window.__hibiraSupport = true

    var base = script.src.replace(/\/widget\.js(\?.*)?$/, '')
    var storeKey = 'hibira-support:' + base
    var state = { open: false, config: null, id: null, token: null, last: 0, messages: [], pusher: null, typingTimer: null, poll: null, unread: 0, sending: false }

    function load() {
        try { return JSON.parse(localStorage.getItem(storeKey) || 'null') } catch (e) { return null }
    }

    function save() {
        try { localStorage.setItem(storeKey, JSON.stringify({ id: state.id, token: state.token })) } catch (e) {}
    }

    function forget() {
        try { localStorage.removeItem(storeKey) } catch (e) {}
    }

    function api(path, options) {
        options = options || {}
        var headers = { Accept: 'application/json' }
        if (options.body) headers['Content-Type'] = 'application/json'
        if (state.token) headers['X-Hibira-Support-Token'] = state.token
        return fetch(base + path, {
            method: options.method || 'GET',
            headers: headers,
            body: options.body ? JSON.stringify(options.body) : undefined,
            credentials: 'omit',
        }).then(function (response) {
            return response.json().catch(function () { return {} }).then(function (data) {
                if (! response.ok) {
                    var error = new Error(data.message || 'HTTP ' + response.status)
                    error.status = response.status
                    throw error
                }
                return data
            })
        })
    }

    // ---- UI ----------------------------------------------------------------

    var host = document.createElement('div')
    host.setAttribute('data-hibira-support', '')
    var root = host.attachShadow ? host.attachShadow({ mode: 'open' }) : host
    var el = {}

    function t(key) {
        return (state.config && state.config.strings && state.config.strings[key]) || key
    }

    function build() {
        var c = state.config
        root.innerHTML =
            '<style>' +
            ':host { all: initial }' +
            '* { box-sizing: border-box; font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Noto Sans", "Hiragino Sans", "PingFang TC", "Microsoft JhengHei", sans-serif }' +
            '.w { --c: ' + c.color + '; --bg: #fff; --ink: #111827; --muted: #6b7280; --soft: #f3f4f6; --line: #e5e7eb; position: fixed; z-index: 2147483000; inset-inline-end: 20px; bottom: 20px; color: var(--ink); font-size: 14px; line-height: 1.45 }' +
            '@media (prefers-color-scheme: dark) { .w { --bg: #18181b; --ink: #f4f4f5; --muted: #a1a1aa; --soft: #27272a; --line: #3f3f46 } }' +
            '.launch { position: relative; width: 56px; height: 56px; border: 0; border-radius: 9999px; background: var(--c); color: #fff; cursor: pointer; box-shadow: 0 6px 24px rgba(0,0,0,.2); display: flex; align-items: center; justify-content: center }' +
            '.launch:focus-visible, button:focus-visible, textarea:focus-visible { outline: 2px solid var(--c); outline-offset: 2px }' +
            '.launch svg { width: 26px; height: 26px }' +
            '.dot { position: absolute; top: 2px; inset-inline-end: 2px; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 9999px; background: #ef4444; color: #fff; font-size: 11px; font-weight: 700; line-height: 18px; text-align: center }' +
            '.panel { position: absolute; bottom: 72px; inset-inline-end: 0; width: 370px; height: min(560px, calc(100vh - 110px)); display: flex; flex-direction: column; background: var(--bg); border: 1px solid var(--line); border-radius: 16px; box-shadow: 0 12px 40px rgba(0,0,0,.18); overflow: hidden }' +
            '.panel[hidden] { display: none }' +
            '.head { display: flex; align-items: center; gap: 10px; padding: 14px 16px; background: var(--c); color: #fff }' +
            '.head strong { flex: 1; font-size: 15px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap }' +
            '.x { width: 32px; height: 32px; border: 0; border-radius: 8px; background: transparent; color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center }' +
            '.x:hover { background: rgba(255,255,255,.15) }' +
            '.x svg { width: 20px; height: 20px }' +
            '.list { flex: 1; overflow-y: auto; padding: 14px; display: flex; flex-direction: column; gap: 8px }' +
            '.m { max-width: 85%; align-self: flex-start }' +
            '.m.me { align-self: flex-end; text-align: end }' +
            '.b { display: inline-block; padding: 8px 12px; border-radius: 14px; background: var(--soft); white-space: pre-wrap; overflow-wrap: anywhere; text-align: start }' +
            '.me .b { background: var(--c); color: #fff }' +
            '.who { margin-top: 2px; font-size: 11px; color: var(--muted) }' +
            '.note { align-self: center; max-width: 90%; text-align: center; font-size: 12px; color: var(--muted) }' +
            '.typing { align-self: flex-start; padding: 8px 12px; border-radius: 14px; background: var(--soft); display: flex; gap: 4px }' +
            '.typing i { width: 6px; height: 6px; border-radius: 9999px; background: var(--muted); animation: hs 1s infinite ease-in-out }' +
            '.typing i:nth-child(2) { animation-delay: .15s } .typing i:nth-child(3) { animation-delay: .3s }' +
            '@keyframes hs { 0%, 80%, 100% { opacity: .3 } 40% { opacity: 1 } }' +
            '@media (prefers-reduced-motion: reduce) { .typing i { animation: none } }' +
            '.form { display: flex; gap: 8px; align-items: flex-end; padding: 10px; border-top: 1px solid var(--line) }' +
            'textarea { flex: 1; resize: none; max-height: 120px; min-height: 40px; padding: 9px 12px; border: 1px solid var(--line); border-radius: 12px; background: transparent; color: inherit; font-size: 14px }' +
            '.send { width: 40px; height: 40px; flex: none; border: 0; border-radius: 12px; background: var(--c); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center }' +
            '.send:disabled { opacity: .5; cursor: default }' +
            '.send svg { width: 18px; height: 18px }' +
            '.foot { padding: 0 10px 8px; font-size: 11px; color: var(--muted); text-align: center }' +
            '.foot a { color: inherit }' +
            '.err { padding: 0 12px 6px; font-size: 12px; color: #dc2626 }' +
            '@media (max-width: 480px) { .w { inset-inline-end: 12px; bottom: 12px } .panel { position: fixed; inset: 0; width: auto; height: auto; border-radius: 0; bottom: 0 } .w.is-open .launch { display: none } }' +
            '</style>' +
            '<div class="w">' +
            '<div class="panel" role="dialog" aria-modal="false" hidden>' +
            '<div class="head"><strong></strong><button type="button" class="x" aria-label=""><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>' +
            '<div class="list" aria-live="polite"></div>' +
            '<div class="err" hidden></div>' +
            '<form class="form"><textarea rows="1"></textarea><button type="submit" class="send" aria-label=""><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button></form>' +
            '<div class="foot"></div>' +
            '</div>' +
            '<button type="button" class="launch" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/></svg><span class="dot" hidden></span></button>' +
            '</div>'

        el.panel = root.querySelector('.panel')
        el.title = root.querySelector('.head strong')
        el.close = root.querySelector('.x')
        el.list = root.querySelector('.list')
        el.error = root.querySelector('.err')
        el.form = root.querySelector('.form')
        el.input = root.querySelector('textarea')
        el.send = root.querySelector('.send')
        el.launch = root.querySelector('.launch')
        el.dot = root.querySelector('.dot')
        el.foot = root.querySelector('.foot')

        el.title.textContent = c.title
        el.panel.setAttribute('aria-label', c.title)
        el.close.setAttribute('aria-label', t('close'))
        el.send.setAttribute('aria-label', t('send'))
        el.launch.setAttribute('aria-label', t('open'))
        el.input.placeholder = t('placeholder')
        el.foot.textContent = c.ai ? t('ai_notice') : ''
        if (c.powered_by) {
            if (c.ai) el.foot.appendChild(document.createTextNode(' · '))
            var powered = document.createElement('a')
            powered.href = 'https://hibira.io/?utm_source=support-widget&utm_medium=referral'
            powered.target = '_blank'
            powered.rel = 'noopener'
            powered.textContent = t('powered')
            el.foot.appendChild(powered)
        }
        el.foot.hidden = ! c.ai && ! c.powered_by

        el.launch.addEventListener('click', function () { toggle(! state.open) })
        el.close.addEventListener('click', function () { toggle(false) })
        el.panel.addEventListener('keydown', function (e) { if (e.key === 'Escape') toggle(false) })
        el.form.addEventListener('submit', function (e) { e.preventDefault(); send() })
        el.input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && ! e.shiftKey && ! e.isComposing) { e.preventDefault(); send() }
        })
        el.input.addEventListener('input', function () {
            el.input.style.height = 'auto'
            el.input.style.height = Math.min(el.input.scrollHeight, 120) + 'px'
        })

        document.body.appendChild(host)
        render()
    }

    function bubble(text, mine, who) {
        var wrap = document.createElement('div')
        wrap.className = 'm' + (mine ? ' me' : '')
        var b = document.createElement('div')
        b.className = 'b'
        b.textContent = text
        wrap.appendChild(b)
        if (who) {
            var w = document.createElement('div')
            w.className = 'who'
            w.textContent = who
            wrap.appendChild(w)
        }
        return wrap
    }

    function render() {
        el.list.textContent = ''
        el.list.appendChild(bubble(state.config.greeting, false, null))
        state.messages.forEach(function (m) {
            if (m.sender === 'system') {
                var note = document.createElement('p')
                note.className = 'note'
                note.textContent = t('handoff')
                el.list.appendChild(note)
            } else if (m.sender === 'visitor') {
                el.list.appendChild(bubble(m.body, true, null))
            } else {
                el.list.appendChild(bubble(m.body, false, m.sender === 'assistant' ? t('assistant') : m.author))
            }
        })
        if (state.typing) {
            var typing = document.createElement('div')
            typing.className = 'typing'
            typing.setAttribute('aria-label', t('typing'))
            typing.innerHTML = '<i></i><i></i><i></i>'
            el.list.appendChild(typing)
        }
        el.list.scrollTop = el.list.scrollHeight
        el.dot.hidden = state.unread === 0
        el.dot.textContent = state.unread > 9 ? '9+' : String(state.unread)
        el.send.disabled = state.sending
    }

    function toggle(open) {
        state.open = open
        el.panel.hidden = ! open
        el.launch.setAttribute('aria-expanded', String(open))
        el.panel.parentNode.classList.toggle('is-open', open)
        if (open) {
            state.unread = 0
            render()
            el.input.focus()
            if (state.id) fetchMessages()
        }
        poll()
    }

    function showError(text) {
        el.error.hidden = ! text
        el.error.textContent = text || ''
    }

    // ---- Messages ----------------------------------------------------------

    function add(list) {
        var fresh = false
        list.forEach(function (m) {
            // Events can arrive before the response to the visitor's own send.
            if (state.messages.some(function (known) { return known.id === m.id })) return
            state.messages.push(m)
            state.last = Math.max(state.last, m.id)
            fresh = true
            if (m.sender !== 'visitor') {
                state.typing = false
                if (! state.open) state.unread++
            }
        })
        if (fresh) {
            state.messages.sort(function (x, y) { return x.id - y.id })
            render()
        }
    }

    function fetchMessages() {
        return api('/conversations/' + state.id + '?after=' + state.last).then(function (data) {
            add(data.messages)
        }).catch(function (error) {
            // The conversation is gone; start over next time.
            if (error.status === 404) {
                forget()
                state.id = state.token = null
                state.messages = []
                state.last = 0
                render()
            }
        })
    }

    function send() {
        var body = el.input.value.trim()
        if (! body || state.sending) return
        state.sending = true
        showError('')
        render()

        var request = state.id
            ? api('/conversations/' + state.id + '/messages', { method: 'POST', body: { body: body } }).then(function (m) { add([m]) })
            : api('/conversations', {
                method: 'POST',
                body: {
                    body: body,
                    name: script.getAttribute('data-name') || undefined,
                    email: script.getAttribute('data-email') || undefined,
                    page_url: location.href.slice(0, 500),
                    locale: navigator.language,
                },
            }).then(function (data) {
                state.id = data.id
                state.token = data.token
                save()
                add(data.messages)
                connect()
            })

        request.then(function () {
            el.input.value = ''
            el.input.style.height = 'auto'
            if (state.config.ai) { state.typing = true; clearTimeout(state.typingTimer); state.typingTimer = setTimeout(stopTyping, 30000) }
        }).catch(function (error) {
            showError(error.status === 429 ? error.message : t('not_sent'))
        }).then(function () {
            state.sending = false
            render()
        })
    }

    function stopTyping() {
        state.typing = false
        render()
    }

    // ---- Realtime ----------------------------------------------------------

    function loadPusher(done) {
        if (window.Pusher) return done()
        var tag = document.createElement('script')
        tag.src = state.config.pusher_js
        tag.async = true
        tag.onload = done
        document.head.appendChild(tag)
    }

    function connect() {
        if (! state.id || state.pusher || ! state.config.key) return
        loadPusher(function () {
            var c = state.config
            state.pusher = new window.Pusher(c.key, {
                cluster: 'hkg',
                wsHost: c.host,
                wsPort: c.port,
                wssPort: c.port,
                forceTLS: c.tls,
                enabledTransports: ['ws', 'wss'],
                disableStats: true,
                channelAuthorization: {
                    endpoint: base + '/conversations/' + state.id + '/auth',
                    transport: 'ajax',
                    headers: { 'X-Hibira-Support-Token': state.token },
                },
            })
            var channel = state.pusher.subscribe('private-hibira-support.c.' + state.id)
            channel.bind('message', function (m) {
                if (m.fetch) fetchMessages()
                else add([m])
            })
            channel.bind('typing', function () {
                state.typing = true
                render()
                clearTimeout(state.typingTimer)
                state.typingTimer = setTimeout(stopTyping, 8000)
            })
            // Catch up on anything sent while the connection was down.
            state.pusher.connection.bind('connected', fetchMessages)
            state.pusher.connection.bind('state_change', poll)
        })
    }

    // Without a live connection, check for new messages while the chat is open.
    function poll() {
        var live = state.pusher && state.pusher.connection.state === 'connected'
        if (state.open && state.id && ! live) {
            if (! state.poll) state.poll = setInterval(fetchMessages, 5000)
        } else if (state.poll) {
            clearInterval(state.poll)
            state.poll = null
        }
    }

    // ---- Start -------------------------------------------------------------

    api('/config?locale=' + encodeURIComponent(navigator.language || 'en')).then(function (config) {
        state.config = config
        var saved = load()
        if (saved && saved.id && saved.token) {
            state.id = saved.id
            state.token = saved.token
        }
        build()
        if (state.id) fetchMessages().then(connect)
        if (script.hasAttribute('data-open')) toggle(true)
    }).catch(function () {})
})()
