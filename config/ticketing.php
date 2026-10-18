<?php

return [
    /*
    | The unit every amount in the system is really stored in is toman (the
    | wallet, the pipeline and the ticket prices all use it), so orders are
    | tagged IRT. Set TICKETING_CURRENCY=IRR only if prices are entered in rials.
    */
    'currency' => env('TICKETING_CURRENCY', 'IRT'),

    /*
    | Carbon dayOfWeek values (0 = Sunday ... 6 = Saturday) that count as the
    | weekend for prices marked "weekend only": Thursday and Friday.
    */
    'weekend_days' => [4, 5],

    /*
    | How many days of sales decide which ticket types appear as one-tap
    | buttons on the cashier screen.
    */
    'quick_sale_days' => 30,
];
