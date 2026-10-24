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

    /*
    |--------------------------------------------------------------------------
    | Cost tracking and monthly cap
    |--------------------------------------------------------------------------
    |
    | Token prices (toman per million tokens, as billed by your provider) turn
    | the stored token counts into an estimated cost on the usage dashboard.
    | A monthly cap (cost and/or tokens, 0 = off) stops new questions once it
    | is reached and warns the admins from 80%.
    |
    */

    'prompt_price_per_million_toman' => (float) env('AI_ASSISTANT_PROMPT_PRICE', 0),

    'completion_price_per_million_toman' => (float) env('AI_ASSISTANT_COMPLETION_PRICE', 0),

    'monthly_cost_cap_toman' => (int) env('AI_ASSISTANT_MONTHLY_COST_CAP', 0),

    'monthly_token_cap' => (int) env('AI_ASSISTANT_MONTHLY_TOKEN_CAP', 0),

    /*
    |--------------------------------------------------------------------------
    | Team chat: answer in parallel
    |--------------------------------------------------------------------------
    |
    | When true, the specialists of a team chat answer at the same time (each as
    | its own queue job) instead of one after another. It only shortens the wait
    | when two or more queue workers run; with a single worker it changes
    | nothing. The discussion rounds stay sequential.
    |
    */

    'team_chat_parallel' => (bool) env('AI_TEAM_CHAT_PARALLEL', false),

];
