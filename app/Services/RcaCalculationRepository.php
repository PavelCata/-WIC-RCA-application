<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use stdClass;

class RcaCalculationRepository
{
    /** @return iterable<int, stdClass> */
    public function latest(): iterable
    {
        return DB::table('rca_calculations')->latest()->limit(10)->get();
    }

    /** @param array<string, mixed> $payload */
    public function create(string $insurer, array $payload): int
    {
        return DB::table('rca_calculations')->insertGetId(['insurer' => $insurer, 'request_payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'status' => 'submitted', 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array<string, mixed> $response */
    public function saveOffer(int $id, array $response): void
    {
        DB::table('rca_calculations')->where('id', $id)->update(['offer_id' => data_get($response, 'data.offers.0.offerId'), 'offer_response' => json_encode($response, JSON_THROW_ON_ERROR), 'status' => 'offered', 'updated_at' => now()]);
    }

    public function markOfferFailed(int $id): void
    {
        DB::table('rca_calculations')->where('id', $id)->update(['status' => 'offer_failed', 'updated_at' => now()]);
    }

    public function markPolicyFailed(int $id): void
    {
        DB::table('rca_calculations')->where('id', $id)->update(['status' => 'policy_failed', 'updated_at' => now()]);
    }

    /** @param array<string, mixed> $response */
    public function savePolicy(int $id, array $response): void
    {
        DB::table('rca_calculations')->where('id', $id)->update(['policy_id' => data_get($response, 'data.policies.0.policyId'), 'policy_response' => json_encode($response, JSON_THROW_ON_ERROR), 'status' => 'issued', 'updated_at' => now()]);
    }

    public function find(int $id): ?stdClass
    {
        return DB::table('rca_calculations')->find($id);
    }
}
