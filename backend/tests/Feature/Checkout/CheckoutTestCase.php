<?php

namespace Tests\Feature\Checkout;

use Tests\Feature\Cart\CartTestCase;

abstract class CheckoutTestCase extends CartTestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array{customer: array<string, string>, delivery: array<string, mixed>}
     */
    protected function checkoutPayload(array $overrides = []): array
    {
        $base = [
            'customer' => [
                'name' => 'Cliente Demo Rotor',
                'email' => 'cliente.demo@example.test',
                'phone' => '+570000000000',
                'document_type' => 'CC',
                'document_number' => '1000000000',
            ],
            'delivery' => [
                'mode' => 'PICKUP',
            ],
        ];

        if (isset($overrides['customer']) && is_array($overrides['customer'])) {
            $base['customer'] = array_merge($base['customer'], $overrides['customer']);
            unset($overrides['customer']);
        }
        if (isset($overrides['delivery']) && is_array($overrides['delivery'])) {
            $base['delivery'] = array_merge($base['delivery'], $overrides['delivery']);
            unset($overrides['delivery']);
        }

        return array_merge($base, $overrides);
    }
}
