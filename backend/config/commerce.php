<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cart reservation window
    |--------------------------------------------------------------------------
    |
    | Active stock reservations expire after this many minutes. Mutating the
    | cart refreshes the reservation expiry for affected lines.
    |
    */

    'cart_reservation_minutes' => (int) env('CART_RESERVATION_MINUTES', 30),

];
