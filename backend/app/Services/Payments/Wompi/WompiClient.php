<?php

declare(strict_types=1);

namespace App\Services\Payments\Wompi;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Http;

final class WompiClient
{
    public function __construct(
        private readonly WompiConfig $config,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getTransaction(string $transactionId): array
    {
        $this->config->assertSandboxKeysConfigured();

        $response = Http::withToken($this->config->privateKey())
            ->acceptJson()
            ->timeout(20)
            ->get($this->config->baseUrl().'/transactions/'.$transactionId);

        if (! $response->successful()) {
            throw ApiException::providerFailure('Wompi transaction lookup failed.', [
                'status' => $response->status(),
            ]);
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw ApiException::providerFailure('Wompi transaction lookup returned invalid JSON.');
        }

        $data = $json['data'] ?? $json;
        if (! is_array($data)) {
            throw ApiException::providerFailure('Wompi transaction payload missing data.');
        }

        return $data;
    }
}
