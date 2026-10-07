<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CreateRcaOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalizedText = [];
        foreach (['tax_id', 'county', 'vin', 'vehicle_identification_number', 'license_plate', 'trade_register'] as $field) {
            if ($this->filled($field)) {
                $normalizedText[$field] = Str::upper(trim((string) $this->input($field)));
            }
        }

        $numericFields = [
            'city_code',
            'year_of_construction',
            'engine_displacement',
            'engine_power',
            'total_weight',
            'seats',
            'term_time',
        ];
        $normalized = [];

        foreach ($numericFields as $field) {
            if ($this->filled($field)) {
                $normalized[$field] = (int) $this->input($field);
            }
        }

        if ($this->input('fuel_type') === 'electric') {
            $normalized['engine_displacement'] = 0;
        }

        $this->merge([...$normalizedText, ...$normalized]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'customer_type' => ['required', 'in:individual,company'],
            'last_name' => ['required_if:customer_type,individual', 'nullable', 'string', 'min:2', 'regex:/^[\p{L}\s-]+$/u'],
            'first_name' => ['required_if:customer_type,individual', 'nullable', 'string', 'min:2', 'regex:/^[\p{L}\s-]+$/u'],
            'company_name' => ['required_if:customer_type,company', 'nullable', 'string', 'min:3'],
            'trade_register' => ['nullable', 'string', 'regex:/^J\d{1,2}\/\d{1,6}\/\d{4}$/'],
            'contact_person' => ['required_if:customer_type,company', 'nullable', 'string', 'min:2'],
            'tax_id' => ['required', 'string', 'max:13'],
            'identification_type' => [
                Rule::requiredIf(fn (): bool => $this->input('customer_type') === 'individual'),
                'nullable',
                'in:CI,PASSPORT',
            ],
            'identification_number' => [
                Rule::requiredIf(fn (): bool => $this->input('customer_type') === 'individual'),
                'nullable',
                'string',
                'min:5',
                'max:20',
            ],
            'email' => ['required', 'email'],
            'mobile_number' => ['required', 'regex:/^(07\d{8}|\+407\d{8})$/'],
            'county' => ['required', 'string', 'size:2', 'uppercase'],
            'city' => ['required', 'string', 'min:2'],
            'city_code' => ['required', 'integer', 'min:1'],
            'street' => ['required', 'string', 'min:2'],
            'house_number' => ['required', 'string', 'max:20'],
            'postcode' => ['required', 'digits:6'],
            'registration_type' => ['required', 'in:registered,recorded,temporaryRegistered,temporaryRecorded'],
            'license_plate' => [
                Rule::requiredIf(fn (): bool => in_array($this->input('registration_type'), ['registered', 'recorded'], true)),
                'nullable',
                'string',
                Rule::when($this->input('registration_type') === 'registered', ['regex:/^(?:MAI|A|CD|TC|FA|ALA|CO)\d{1,6}$|^[A-Z]{1,2}\d{2,3}[A-Z]{3}$/']),
                Rule::when($this->input('registration_type') === 'recorded', ['regex:/^[A-Z]+\d+$/']),
            ],
            'vehicle_identification_number' => [
                Rule::requiredIf(fn (): bool => in_array($this->input('registration_type'), ['registered', 'recorded'], true)),
                'nullable',
                'regex:/^[A-Za-z]\d{6}$/',
            ],
            'vin' => ['required', 'regex:/^[A-HJ-NPR-Z0-9]{17}$/'],
            'vehicle_type' => ['required', 'in:M1,M1G,M2,M2G,M3,M3G,N1,N1G,N2,N2G,N3,N3G,O1,O2,O3,O4,L1e,L2e,L3e,L4e,L5e,L6e,L7e,T,C,R,S'],
            'brand' => ['required', 'string', 'min:2'],
            'model' => ['required', 'string', 'min:1'],
            'year_of_construction' => ['required', 'integer', 'between:1980,'.now()->year],
            'engine_displacement' => ['required', 'integer', 'between:0,8000'],
            'engine_power' => ['required', 'integer', 'between:1,500'],
            'total_weight' => ['required', 'integer', 'between:300,40000'],
            'seats' => ['required', 'integer', 'between:1,90'],
            'fuel_type' => ['required', 'in:diesel,petrol,electric,hybrid,lpg'],
            'first_registration' => ['required', 'date', 'before_or_equal:today'],
            'usage_type' => ['required', 'in:personal,passengerTransportation,taxi,carRental,drivingSchool,security,courier,cargoTransportation,distribution'],
            'insurer' => ['required', 'in:allianz,asirom,axeria,eazy_insure,generali,grawe,groupama,hellas_nextins,hellas_autonom,omniasig,dallbogg'],
            'bonus_malus' => ['required', 'in:B0,B1,B2,B3,B4,B5,B6,B7,B8,M1,M2,M3,M4,M5,M6,M7,M8'],
            'start_date' => [
                'required',
                'date',
                Rule::when(
                    in_array($this->input('registration_type'), ['registered', 'recorded'], true),
                    ['after_or_equal:tomorrow'],
                    ['after_or_equal:today'],
                ),
            ],
            'term_time' => ['required', 'integer', 'in:1,3,6,12'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $taxId = strtoupper((string) $this->input('tax_id'));

            if ($this->input('customer_type') === 'individual' && ! $this->validCnp($taxId)) {
                $validator->errors()->add('tax_id', 'CNP-ul introdus nu este valid.');
            }

            if ($this->input('customer_type') === 'company' && ! $this->validCui($taxId)) {
                $validator->errors()->add('tax_id', 'CUI-ul introdus nu este valid.');
            }

            $firstRegistration = $this->date('first_registration');
            $year = (int) $this->input('year_of_construction');

            if ($firstRegistration instanceof Carbon && $firstRegistration->year < $year) {
                $validator->errors()->add('first_registration', 'Prima înmatriculare nu poate fi anterioară anului de fabricație.');
            }
        }];
    }

    private function validCnp(string $cnp): bool
    {
        if (! preg_match('/^\d{13}$/', $cnp)) {
            return false;
        }

        $control = [2, 7, 9, 1, 4, 6, 3, 5, 8, 2, 7, 9];
        $sum = 0;

        foreach ($control as $index => $factor) {
            $sum += ((int) $cnp[$index]) * $factor;
        }

        $check = $sum % 11;

        return ($check === 10 ? 1 : $check) === (int) $cnp[12];
    }

    private function validCui(string $cui): bool
    {
        $cui = preg_replace('/^RO/', '', $cui) ?? '';

        if (! preg_match('/^\d{2,10}$/', $cui)) {
            return false;
        }

        $digits = str_pad($cui, 10, '0', STR_PAD_LEFT);
        $control = [7, 5, 3, 2, 1, 7, 5, 3, 2];
        $sum = 0;

        foreach ($control as $index => $factor) {
            $sum += ((int) $digits[$index]) * $factor;
        }

        return ((($sum * 10) % 11) % 10) === (int) $digits[9];
    }
}
