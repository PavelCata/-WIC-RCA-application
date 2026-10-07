<?php

namespace Tests\Feature;

use App\Services\RcaApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RcaCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculator_page_is_available(): void
    {
        $this->get(route('rca.index'))
            ->assertOk()
            ->assertSee('Calculator RCA');
    }

    public function test_offer_requires_required_data(): void
    {
        $this->post(route('rca.offer'))
            ->assertSessionHasErrors(['insurer', 'start_date', 'vin']);
    }

    public function test_offer_rejects_invalid_cnp(): void
    {
        $data = $this->offerData();
        $data['tax_id'] = '1960101123457';

        $this->post(route('rca.offer'), $data)
            ->assertSessionHasErrors(['tax_id']);
    }

    public function test_offer_rejects_license_plate_for_invalid_registration_state(): void
    {
        $data = $this->offerData();
        $data['registration_type'] = 'invalid';

        $this->post(route('rca.offer'), $data)
            ->assertSessionHasErrors(['registration_type']);
    }

    public function test_offer_rejects_first_registration_before_manufacturing_year(): void
    {
        $data = $this->offerData();
        $data['year_of_construction'] = 2020;
        $data['first_registration'] = '2019-12-31';

        $this->post(route('rca.offer'), $data)
            ->assertSessionHasErrors(['first_registration']);
    }

    public function test_offer_sends_future_dates_to_api_in_iso_format(): void
    {
        config(['rca.base_url' => 'https://rca-qa.example.test', 'rca.account' => 'test', 'rca.password' => 'test']);
        Cache::flush();
        Http::fake([
            'https://rca-qa.example.test/auth*' => Http::response(['data' => ['token' => 'jwt-token']]),
            'https://rca-qa.example.test/offer' => Http::response(['data' => ['offers' => [['offerId' => 123]]]]),
            'https://rca-qa.example.test/offer/123' => Http::response(['data' => ['files' => [['name' => 'offer.pdf', 'content' => base64_encode('offer-pdf')]]]]),
        ]);

        $this->post(route('rca.offer'), $this->offerData())->assertRedirect(route('rca.index'));
        $calculationId = DB::table('rca_calculations')->value('id');
        $this->get(route('rca.pdf', [$calculationId, 'offer']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertSee('offer-pdf');

        Http::assertSent(function (ClientRequest $request): bool {
            if ($request->url() !== 'https://rca-qa.example.test/offer') {
                return false;
            }

            return data_get($request->data(), 'product.policyholder.identification') === ['idType' => 'CI', 'idNumber' => 'CJ123456']
                && data_get($request->data(), 'product.policyholder.address.cityCode') === 54975
                && data_get($request->data(), 'product.vehicle.identification') === ['idNumber' => 'C123456']
                && data_get($request->data(), 'product.motor.startDate') === now()->addDay()->toDateString()
                && data_get($request->data(), 'product.vehicle.firstRegistration') === '2020-05-10';
        });
    }

    public function test_company_offer_sends_cui_identification(): void
    {
        config(['rca.base_url' => 'https://rca-qa.example.test', 'rca.account' => 'test', 'rca.password' => 'test']);
        Cache::flush();
        Http::fake([
            'https://rca-qa.example.test/auth*' => Http::response(['data' => ['token' => 'jwt-token']]),
            'https://rca-qa.example.test/offer' => Http::response(['data' => ['offers' => [['offerId' => 123]]]]),
        ]);

        $data = $this->offerData();
        $data['customer_type'] = 'company';
        $data['tax_id'] = '18547290';
        $data['company_name'] = 'Exemplu SRL';
        $data['contact_person'] = 'Marius Pop';
        unset($data['last_name'], $data['first_name'], $data['identification_type'], $data['identification_number']);

        $this->post(route('rca.offer'), $data)->assertRedirect(route('rca.index'));

        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://rca-qa.example.test/offer'
            && data_get($request->data(), 'product.policyholder.taxId') === '18547290'
            && data_get($request->data(), 'product.policyholder.identification') === null);
    }

    public function test_real_rca_api_accepts_an_offer(): void
    {
        if (! filter_var(env('RUN_RCA_INTEGRATION_TESTS', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Testul de integrare RCA este dezactivat. Setează RUN_RCA_INTEGRATION_TESTS=true.');
        }

        if (! is_string(config('rca.account')) || config('rca.account') === '' || ! is_string(config('rca.password')) || config('rca.password') === '') {
            $this->markTestSkipped('Credențialele API RCA nu sunt configurate.');
        }

        Cache::flush();

        $this->post(route('rca.offer'), $this->offerData())
            ->assertRedirect(route('rca.index'));
    }

    public function test_api_client_authenticates_on_backend_and_sends_token_to_offer(): void
    {
        config(['rca.base_url' => 'https://rca-qa.example.test', 'rca.account' => 'test', 'rca.password' => 'test']);
        Cache::flush();
        Http::fake([
            'https://rca-qa.example.test/auth*' => Http::response(['data' => ['token' => 'jwt-token']]),
            'https://rca-qa.example.test/offer' => Http::response(['data' => ['offers' => [['offerId' => 123]]]]),
        ]);

        app(RcaApiClient::class)->createOffer(['product' => []]);

        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://rca-qa.example.test/auth'
            && $request->data() === ['account' => 'test', 'password' => 'test']);
        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://rca-qa.example.test/offer' && $request->hasHeader('Token', 'jwt-token'));
    }

    public function test_complete_offer_policy_and_pdf_flow_is_audited(): void
    {
        config([
            'rca.base_url' => 'https://rca-qa.example.test', 'rca.account' => 'test', 'rca.password' => 'test',
            'rca.provider_account' => 'provider-test', 'rca.provider_password' => 'provider-secret', 'rca.provider_code' => 'provider-code',
        ]);
        Cache::flush();
        Http::fake([
            'https://rca-qa.example.test/auth*' => Http::response(['data' => ['token' => 'jwt-token']]),
            'https://rca-qa.example.test/offer' => Http::response(['data' => ['offers' => [['offerId' => 123]]]]),
            'https://rca-qa.example.test/policy' => Http::response(['data' => ['policies' => [['policyId' => 456]]]]),
            'https://rca-qa.example.test/policy/456' => Http::response(['data' => ['files' => [['name' => 'policy.pdf', 'content' => base64_encode('pdf-content')]]]]),
        ]);

        $this->post(route('rca.offer'), $this->offerData())->assertRedirect(route('rca.index'));
        $calculationId = DB::table('rca_calculations')->value('id');
        $this->assertDatabaseHas('rca_calculations', ['id' => $calculationId, 'offer_id' => 123, 'status' => 'offered']);
        $this->assertDatabaseMissing('rca_calculations', ['request_payload' => 'provider-secret']);

        $this->post(route('rca.policy', $calculationId), ['amount' => 450, 'payment_method' => 'receipt', 'document_number' => 'CH-001'])->assertRedirect(route('rca.index'));
        $this->assertDatabaseHas('rca_calculations', ['id' => $calculationId, 'policy_id' => 456, 'status' => 'issued']);
        $this->get(route('rca.pdf', [$calculationId, 'policy']))->assertOk()->assertHeader('content-type', 'application/pdf')->assertSee('pdf-content');
        $this->assertDatabaseCount('rca_audit_events', 5);
        $this->assertFalse(DB::table('rca_audit_events')->where('rca_calculation_id', $calculationId)->where('payload', 'like', '%1960101123456%')->exists());
        $this->assertFalse(DB::table('rca_audit_events')->where('rca_calculation_id', $calculationId)->where('payload', 'like', '%provider-secret%')->exists());
    }

    /** @return array<string, mixed> */
    private function offerData(): array
    {
        return ['customer_type' => 'individual', 'insurer' => 'allianz', 'start_date' => now()->addDay()->toDateString(), 'term_time' => 12, 'last_name' => 'Popescu', 'first_name' => 'Ion', 'identification_type' => 'CI', 'identification_number' => 'CJ123456', 'tax_id' => '1960101123456', 'bonus_malus' => 'B8', 'email' => 'ion@example.test', 'mobile_number' => '0712345678', 'county' => 'CJ', 'city' => 'Cluj-Napoca', 'city_code' => 54975, 'street' => 'Memorandumului', 'house_number' => '1', 'postcode' => '400114', 'registration_type' => 'registered', 'license_plate' => 'CJ01ABC', 'vehicle_identification_number' => 'C123456', 'vin' => 'WVWZZZ1JZXW000001', 'vehicle_type' => 'M1', 'brand' => 'Volkswagen', 'model' => 'Golf', 'year_of_construction' => 2020, 'engine_displacement' => 1598, 'engine_power' => 85, 'total_weight' => 1850, 'seats' => 5, 'fuel_type' => 'diesel', 'first_registration' => '2020-05-10', 'usage_type' => 'personal'];
    }
}
