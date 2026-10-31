<div
    x-data="{
        channel: @js($channel),
        url: @js($url),
        strings: @js(['title' => __('hibira-support::support.toast_title'), 'handoff' => __('hibira-support::support.toast_handoff'), 'open' => __('hibira-support::support.open')]),
        init() {
            const listen = () => {
                if (! window.Echo) return false
                window.Echo.private(this.channel).listen('.conversation', (event) => this.notify(event))
                return true
            }
            if (! listen()) window.addEventListener('EchoLoaded', listen, { once: true })
        },
        notify(event) {
            const wanted = event.reason === 'handoff' || (event.reason === 'visitor' && event.handled_by === 'human')
            // The inbox shows the conversation already.
            if (! wanted || window.hibiraSupportActive === event.id || typeof FilamentNotification === 'undefined') return
            const notification = new FilamentNotification()
                .title(event.reason === 'handoff' ? this.strings.handoff.replace(':name', event.name) : this.strings.title.replace(':name', event.name))
                .body(event.preview)
                .icon('heroicon-o-lifebuoy')
            if (typeof FilamentNotificationAction !== 'undefined') {
                notification.actions([new FilamentNotificationAction('open').label(this.strings.open).button().url(this.url + '?c=' + encodeURIComponent(event.id))])
            }
            notification.send()
        },
    }"
    hidden
></div>
