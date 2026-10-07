<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RcaAuditLogger
{
    /** @param array<string, mixed> $payload */
    public function log(Request $request, int $calculationId, string $event, array $payload): void
    {
        DB::table('rca_audit_events')->insert(['rca_calculation_id' => $calculationId, 'event' => $event, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function redactSecrets(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), ['password', 'token', 'refresh_token', 'taxid', 'idnumber', 'email', 'mobilenumber'], true)) {
                $payload[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $payload[$key] = $this->redactSecrets($value);
            }
        }

        return $payload;
    }
}
