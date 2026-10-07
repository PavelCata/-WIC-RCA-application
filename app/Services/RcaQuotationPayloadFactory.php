<?php

namespace App\Services;

use Carbon\Carbon;

class RcaQuotationPayloadFactory
{
    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function offer(array $data): array
    {
        $policyholder = ['taxId' => $data['tax_id'], 'bonusMalus' => $data['bonus_malus'], 'email' => $data['email'], 'mobileNumber' => $data['mobile_number'], 'address' => ['country' => 'RO', 'county' => $data['county'], 'city' => $data['city'], 'cityCode' => $data['city_code'], 'street' => $data['street'], 'houseNumber' => $data['house_number'], 'postcode' => $data['postcode']]];

        if ($data['customer_type'] === 'company') {
            $policyholder['businessName'] = $data['company_name'];
            $policyholder['contactPerson'] = $data['contact_person'];
            if (! empty($data['trade_register'])) {
                $policyholder['companyRegistryNumber'] = $data['trade_register'];
            }
        } else {
            $policyholder['lastName'] = $data['last_name'];
            $policyholder['firstName'] = $data['first_name'];
            $policyholder['identification'] = ['idType' => $data['identification_type'], 'idNumber' => $data['identification_number']];
        }

        $vehicle = ['licensePlate' => $data['license_plate'] ?? null, 'registrationType' => $data['registration_type'], 'vin' => $data['vin'], 'vehicleType' => $data['vehicle_type'], 'brand' => $data['brand'], 'model' => $data['model'], 'yearOfConstruction' => $data['year_of_construction'], 'engineDisplacement' => $data['engine_displacement'], 'enginePower' => $data['engine_power'], 'totalWeight' => $data['total_weight'], 'seats' => $data['seats'], 'fuelType' => $data['fuel_type'], 'firstRegistration' => Carbon::parse($data['first_registration'])->toDateString(), 'usageType' => $data['usage_type']];
        if (isset($data['vehicle_identification_number']) && $data['vehicle_identification_number'] !== null) {
            $vehicle['identification'] = ['idNumber' => $data['vehicle_identification_number']];
        }

        $payload = ['provider' => ['organization' => ['businessName' => $data['insurer']]], 'product' => ['motor' => ['startDate' => Carbon::parse($data['start_date'])->toDateString(), 'termTime' => $data['term_time'], 'installmentCount' => 1], 'policyholder' => $policyholder, 'vehicle' => $vehicle]];
        $credentials = array_filter(['account' => config('rca.provider_account'), 'password' => config('rca.provider_password'), 'code' => config('rca.provider_code')], static fn (mixed $value): bool => is_string($value) && $value !== '');
        if ($credentials !== []) {
            $payload['provider']['authentication'] = $credentials;
        }

        return $payload;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function policy(int $offerId, array $data): array
    {
        return ['offerId' => $offerId, 'payment' => ['method' => $data['payment_method'], 'currency' => 'RON', 'amount' => $data['amount'], 'date' => now()->toDateString(), 'documentNumber' => $data['document_number']]];
    }
}
