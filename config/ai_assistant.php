<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tool output retention
    |--------------------------------------------------------------------------
    |
    | The raw result of every assistant tool call (attendance, wallets, deals...)
    | is stored with the conversation. After this many days it is replaced by a
    | short note; the questions and answers themselves are kept. Set to 0 to
    | keep tool output forever.
    |
    */

    'tool_output_retention_days' => (int) env('AI_ASSISTANT_TOOL_RETENTION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Tool routing
    |--------------------------------------------------------------------------
    |
    | When a question clearly concerns some modules, only their tools are sent
    | to the model (a large cut in tokens per question). Set to false to always
    | send every tool the user may use.
    |
    */

    'tool_routing' => (bool) env('AI_ASSISTANT_TOOL_ROUTING', true),

];
