<?php

return [

    /*
    | Where the chat widget's endpoints live: /hibira-support/widget.js and
    | the visitor API next to it.
    */
    'path' => env('HIBIRA_SUPPORT_PATH', 'hibira-support'),

    /*
    | Sites allowed to show the widget, as origins such as
    | https://www.example.com. Your app's own URL is always allowed. Add the
    | same origins to "Allowed origins" on your Hibira app page.
    */
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('HIBIRA_SUPPORT_ORIGINS', ''))))),

    /*
    | Limits that keep a visitor, or a script pretending to be one, from
    | running up your AI bill. When one is reached the conversation goes to
    | your team.
    */
    'limits' => [
        'conversations_per_ip_per_hour' => 10,
        'messages_per_minute' => 20,
        'ai_replies_per_conversation' => 8,
        'ai_replies_per_ip_per_10_minutes' => 6,
        'ai_replies_per_ip_per_day' => 30,
    ],

    /*
    | How much of the conversation and of your knowledge text is sent to the
    | AI with each question, in characters.
    */
    'history_characters' => 16000,

    'knowledge_characters' => 60000,

    'message_characters' => 2000,

    /*
    | Seconds to wait after a visitor's message before the AI answers, so
    | several quick messages get one answer. Used when a queue is running.
    */
    'answer_delay' => 2,

    /*
    | Shows a small "Powered by Hibira" link under the chat.
    */
    'powered_by' => (bool) env('HIBIRA_SUPPORT_POWERED_BY', true),

    'pusher_js' => env('HIBIRA_SUPPORT_PUSHER_JS', 'https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js'),

];
