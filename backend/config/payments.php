<?php

return [

    /*
    | Proveedor de pagos activo:
    |   simulated -> pasarela de práctica incluida (no mueve dinero, resultado según la tarjeta de prueba)
    |   wompi     -> Wompi sandbox/producción (requiere WOMPI_* en .env)
    */
    'provider' => env('PAYMENT_PROVIDER', 'simulated'),

];
