<?php

namespace Tests\Feature\Checkout;

use App\Models\StockReservation;
use App\Models\User;
use Illuminate\Support\Carbon;

class CheckoutValidationTest extends CheckoutTestCase
{
    public function test_valid_cart_returns_200_valid_true(): void
    {
        $this->actingAsCustomerWithProfile();
        $product = $this->createProduct(null, ['stock_quantity' => 5, 'price' => '10000.00', 'tax_rate' => '0.1900']);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $response = $this->postJson('/api/checkout/validate', $this->checkoutPayload());

        $response->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('validation_errors', [])
            ->assertJsonPath('totals.subtotal', '10000.00')
            ->assertJsonPath('totals.tax_total', '1900.00')
            ->assertJsonPath('totals.total', '11900.00')
            ->assertJsonPath('totals.currency', 'COP');
    }

    public function test_empty_cart_returns_200_valid_false_cart_empty(): void
    {
        $this->actingAsCustomerWithProfile();

        $response = $this->postJson('/api/checkout/validate', $this->checkoutPayload());

        $response->assertOk()
            ->assertJsonPath('valid', false)
            ->assertJsonPath('validation_errors.0.code', 'CART_EMPTY');
    }

    public function test_expired_reservation_returns_200_valid_false(): void
    {
        $this->actingAsCustomerWithProfile();
        $product = $this->createProduct(null, ['stock_quantity' => 5]);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        StockReservation::query()->where('status', 'ACTIVE')->update([
            'expires_at' => Carbon::now()->subMinute(),
        ]);

        $response = $this->postJson('/api/checkout/validate', $this->checkoutPayload());

        $response->assertOk()
            ->assertJsonPath('valid', false)
            ->assertJsonPath('validation_errors.0.code', 'RESERVATION_EXPIRED');
    }

    public function test_bad_customer_email_returns_422(): void
    {
        $this->actingAsCustomerWithProfile();

        $this->assertApiError(
            $this->postJson('/api/checkout/validate', $this->checkoutPayload([
                'customer' => ['email' => 'not-an-email'],
            ])),
            422,
            'VALIDATION_ERROR',
        );
    }

    public function test_shipping_missing_address_returns_422(): void
    {
        $this->actingAsCustomerWithProfile();

        $this->assertApiError(
            $this->postJson('/api/checkout/validate', $this->checkoutPayload([
                'delivery' => ['mode' => 'COORDINATED_SHIPPING'],
            ])),
            422,
            'VALIDATION_ERROR',
        );
    }

    public function test_pickup_address_normalized_in_payload_path(): void
    {
        $this->actingAsCustomerWithProfile();
        $product = $this->createProduct(null, ['stock_quantity' => 2]);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $response = $this->postJson('/api/checkout/validate', $this->checkoutPayload([
            'delivery' => ['mode' => 'PICKUP', 'address' => 'should-be-ignored'],
        ]));

        $response->assertOk()->assertJsonPath('valid', true);
    }

    public function test_guest_gets_401(): void
    {
        $this->assertApiError(
            $this->postJson('/api/checkout/validate', $this->checkoutPayload()),
            401,
            'UNAUTHENTICATED',
        );
    }

    public function test_admin_gets_403(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->assertApiError(
            $this->postJson('/api/checkout/validate', $this->checkoutPayload()),
            403,
            'FORBIDDEN',
        );
    }

    public function test_customer_without_profile_gets_409(): void
    {
        $this->actingAs(User::factory()->customer()->create());

        $this->assertApiError(
            $this->postJson('/api/checkout/validate', $this->checkoutPayload()),
            409,
            'CUSTOMER_PROFILE_REQUIRED',
        );
    }
}
