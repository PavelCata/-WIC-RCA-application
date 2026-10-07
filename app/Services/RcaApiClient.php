<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RcaApiClient
{
    /** @return array<string, mixed> */
    public function createOffer(array $payload): array
    {
        return $this->request()->post('/offer', $payload)->throw()->json();
    }

    /** @return array<string, mixed> */
    public function createPolicy(array $payload): array
    {
        return $this->request()->post('/policy', $payload)->throw()->json();
    }

    /** @return array<string, mixed> */
    public function offerPdf(int $offerId): array
    {
        return $this->request()->get("/offer/{$offerId}")->throw()->json();
    }

    /** @return array<string, mixed> */
    public function policyPdf(int $policyId): array
    {
        return $this->request()->get("/policy/{$policyId}")->throw()->json();
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl((string) config('rca.base_url'))
            ->timeout((int) config('rca.timeout', 15))
            ->connectTimeout((int) config('rca.connect_timeout', 5))
            ->retry([100, 300], throw: false)
            ->withOptions($this->sslOptions())
            ->acceptJson()
            ->withHeaders(['Content-Language' => 'ro'])
            ->withHeader('Token', $this->token());
    }

    private function token(): string
    {
        $cacheKey = 'rca-api-token-'.sha1((string) config('rca.base_url').':'.(string) config('rca.account'));

        return Cache::remember($cacheKey, now()->addMinutes(50), function (): string {
            $response = Http::baseUrl((string) config('rca.base_url'))
                ->timeout((int) config('rca.timeout', 15))
                ->connectTimeout((int) config('rca.connect_timeout', 5))
                ->retry([100, 300], throw: false)
                ->withOptions($this->sslOptions())
                ->acceptJson()
                ->withHeaders(['Content-Language' => 'ro'])
                ->asForm()
                ->post('/auth', [
                    'account' => config('rca.account'),
                    'password' => config('rca.password'),
                ])
                ->throw()
                ->json();

            $token = data_get($response, 'data.token') ?? data_get($response, 'token');

            if (! is_string($token) || $token === '') {
                throw new RuntimeException('RCA API nu a returnat un token valid.');
            }

            return $token;
        });
    }

    /** @return array{verify: bool|string} */
    private function sslOptions(): array
    {
        $caBundle = config('rca.ca_bundle');

        if (is_string($caBundle) && $caBundle !== '') {
            return ['verify' => $caBundle];
        }

        return ['verify' => filter_var(config('rca.verify_ssl', true), FILTER_VALIDATE_BOOL)];
    }
}
