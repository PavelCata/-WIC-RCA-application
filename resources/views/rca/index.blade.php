<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Calculator RCA</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="wrap">
    <header class="page-header">
        <div>
            <p class="eyebrow">RCA · PLATFORMĂ DIGITALĂ</p>
            <h1>Calculator RCA</h1>
            <p class="muted">Obține rapid o ofertă, emite polița și păstrează documentele într-un singur loc.</p>
        </div>
        <div class="header-badge">
            <span class="status-dot"></span>
            Procesare securizată
        </div>
    </header>

    @if ($errors->any())
        <div id="form-errors" class="error" role="alert">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    @if (session('success'))<div class="ok">{{ session('success') }}</div>@endif

    <section class="card form-card">
        <div class="section-heading">
            <div>
                <p class="section-kicker">PASUL 1</p>
                <h2>Solicită ofertă</h2>
            </div>
            <span class="section-note">Completează datele de mai jos</span>
        </div>
        <form id="rca-offer-form" method="post" action="{{ route('rca.offer') }}" novalidate>
            @csrf

            <fieldset>
                <legend>A. Tip client</legend>
                <div class="choice-row">
                    <label><input type="radio" name="customer_type" value="individual" @checked(old('customer_type', 'individual') === 'individual')> Persoană fizică</label>
                    <label><input type="radio" name="customer_type" value="company" @checked(old('customer_type') === 'company')> Persoană juridică</label>
                </div>
            </fieldset>

            <fieldset>
                <legend>B. Date client</legend>
                <div class="grid">
                    <label data-individual>Nume<input name="last_name" value="{{ old('last_name') }}" minlength="2" pattern="[\p{L}\s-]+" required></label>
                    <label data-individual>Prenume<input name="first_name" value="{{ old('first_name') }}" minlength="2" pattern="[\p{L}\s-]+" required></label>
                    <label data-individual>Tip act identitate<select name="identification_type" required><option value="">Selectează tipul</option><option value="CI" @selected(old('identification_type') === 'CI')>Carte de identitate</option><option value="PASSPORT" @selected(old('identification_type') === 'PASSPORT')>Pașaport</option></select></label>
                    <label data-individual>Serie și număr act<input name="identification_number" value="{{ old('identification_number') }}" minlength="5" maxlength="20" required></label>
                    <label data-company class="hidden">Denumire firmă<input name="company_name" value="{{ old('company_name') }}" minlength="3" required></label>
                    <label data-company class="hidden">Persoană de contact<input name="contact_person" value="{{ old('contact_person') }}" minlength="2" required></label>
                    <label><span data-tax-id-label>CNP</span><input name="tax_id" value="{{ old('tax_id') }}" inputmode="numeric" minlength="13" maxlength="13" required></label>
                    <label data-company class="hidden">Nr. Registrul Comerțului<input name="trade_register" placeholder="J40/1234/2020" value="{{ old('trade_register') }}"></label>
                    <label>Email<input type="email" name="email" value="{{ old('email') }}" required></label>
                    <label>Telefon<input type="tel" name="mobile_number" placeholder="07xxxxxxxx" pattern="(07[0-9]{8}|\+407[0-9]{8})" value="{{ old('mobile_number') }}" required></label>
                </div>
            </fieldset>

            <fieldset>
                <legend>C. Adresa asiguratului</legend>
                <div class="grid">
                    <label>Județ
                        <select name="county" id="county">
                            <option value="">Selectează județul</option>
                            @foreach (['AB'=>'Alba','AR'=>'Arad','AG'=>'Argeș','BC'=>'Bacău','BH'=>'Bihor','BN'=>'Bistrița-Năsăud','BT'=>'Botoșani','BV'=>'Brașov','BR'=>'Brăila','BZ'=>'Buzău','CS'=>'Caraș-Severin','CL'=>'Călărași','CJ'=>'Cluj','CT'=>'Constanța','CV'=>'Covasna','DB'=>'Dâmbovița','DJ'=>'Dolj','GL'=>'Galați','GR'=>'Giurgiu','GJ'=>'Gorj','HR'=>'Harghita','HD'=>'Hunedoara','IL'=>'Ialomița','IS'=>'Iași','IF'=>'Ilfov','MM'=>'Maramureș','MH'=>'Mehedinți','MS'=>'Mureș','NT'=>'Neamț','OT'=>'Olt','PH'=>'Prahova','SM'=>'Satu Mare','SJ'=>'Sălaj','SB'=>'Sibiu','SV'=>'Suceava','TR'=>'Teleorman','TM'=>'Timiș','TL'=>'Tulcea','VS'=>'Vaslui','VL'=>'Vâlcea','VN'=>'Vrancea','B'=>'București'] as $code => $name)
                                <option value="{{ $code }}" @selected(old('county') === $code)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Localitate<input name="city" id="city" list="localities" value="{{ old('city') }}" required><datalist id="localities"><option data-county="MS" value="Targu Mures"><option data-county="CJ" value="Cluj-Napoca"><option data-county="B" value="București"></datalist></label>
                    <input type="hidden" name="city_code" id="city_code" value="{{ old('city_code') }}">
                    <label>Cod poștal<input name="postcode" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" value="{{ old('postcode') }}" required></label>
                    <label>Stradă<input name="street" value="{{ old('street') }}" required></label>
                    <label>Număr<input name="house_number" value="{{ old('house_number') }}" required></label>
                </div>
            </fieldset>

            <fieldset>
                <legend>D. Date vehicul</legend>
                <div class="grid">
                    <label>Stare înmatriculare<select name="registration_type" id="registration_type" required><option value="registered" @selected(old('registration_type', 'registered') === 'registered')>Înmatriculat</option><option value="recorded" @selected(old('registration_type') === 'recorded')>Înregistrat</option><option value="temporaryRegistered" @selected(old('registration_type') === 'temporaryRegistered')>Temporar înmatriculat</option><option value="temporaryRecorded" @selected(old('registration_type') === 'temporaryRecorded')>Temporar înregistrat</option></select></label>
                    <label>Număr înmatriculare<input name="license_plate" id="license_plate" placeholder="CJ01ABC" pattern="[A-Z]{1,2}[0-9]{2,3}[A-Z]{3}" value="{{ old('license_plate') }}"></label>
                    <label data-vehicle-identification>CIV (7 caractere)<input name="vehicle_identification_number" id="vehicle_identification_number" placeholder="C123456" pattern="[A-Za-z][0-9]{6}" maxlength="7" value="{{ old('vehicle_identification_number') }}"></label>
                    <label>VIN (17 caractere)<input name="vin" maxlength="17" minlength="17" pattern="[A-HJ-NPR-Z0-9]{17}" value="{{ old('vin') }}" required></label>
                    <label>Tip vehicul<select name="vehicle_type" required><option value="">Selectează tipul</option><option value="M1" @selected(old('vehicle_type') === 'M1')>M1 - autoturism</option><option value="N1" @selected(old('vehicle_type') === 'N1')>N1 - comercial ușor</option><option value="M2" @selected(old('vehicle_type') === 'M2')>M2 - autobuz</option><option value="M3" @selected(old('vehicle_type') === 'M3')>M3 - autobuz</option><option value="N2" @selected(old('vehicle_type') === 'N2')>N2 - camion</option><option value="N3" @selected(old('vehicle_type') === 'N3')>N3 - camion</option><option value="L3e" @selected(old('vehicle_type') === 'L3e')>L3 - motocicletă</option></select></label>
                    <label>Marcă<input name="brand" value="{{ old('brand') }}" required></label>
                    <label>Model<input name="model" value="{{ old('model') }}" required></label>
                    <label>An fabricație<input type="number" name="year_of_construction" min="1980" max="{{ now()->year }}" value="{{ old('year_of_construction') }}" required></label>
                    <label>Capacitate cilindrică (cmc)<input type="number" name="engine_displacement" id="engine_displacement" min="0" max="8000" value="{{ old('engine_displacement') }}" required></label>
                    <label>Putere motor (kW)<input type="number" name="engine_power" min="1" max="500" value="{{ old('engine_power') }}" required></label>
                    <label>Masă totală autorizată (kg)<input type="number" name="total_weight" min="300" max="40000" value="{{ old('total_weight') }}" required></label>
                    <label>Nr. locuri<input type="number" name="seats" min="1" max="90" value="{{ old('seats') }}" required></label>
                    <label>Combustibil<select name="fuel_type" id="fuel_type" required><option value="">Selectează combustibilul</option><option value="petrol" @selected(old('fuel_type') === 'petrol')>Benzină</option><option value="diesel" @selected(old('fuel_type') === 'diesel')>Motorină</option><option value="lpg" @selected(old('fuel_type') === 'lpg')>GPL</option><option value="hybrid" @selected(old('fuel_type') === 'hybrid')>Hibrid</option><option value="electric" @selected(old('fuel_type') === 'electric')>Electric</option></select></label>
                    <label>Prima înmatriculare<input type="date" name="first_registration" id="first_registration" max="{{ now()->toDateString() }}" value="{{ old('first_registration') }}" required></label>
                    <label>Utilizare<select name="usage_type" required><option value="">Selectează utilizarea</option><option value="personal" @selected(old('usage_type') === 'personal')>Personal</option><option value="taxi" @selected(old('usage_type') === 'taxi')>Taxi</option><option value="carRental" @selected(old('usage_type') === 'carRental')>Închiriere</option><option value="cargoTransportation" @selected(old('usage_type') === 'cargoTransportation')>Transport marfă</option><option value="courier" @selected(old('usage_type') === 'courier')>Curierat</option><option value="drivingSchool" @selected(old('usage_type') === 'drivingSchool')>Instruire auto</option><option value="security" @selected(old('usage_type') === 'security')>Uz specializat</option></select></label>
                </div>
            </fieldset>

            <fieldset>
                <legend>E. Detalii ofertă</legend>
                <div class="grid">
                    <label>Asigurător<select name="insurer" required><option value="">Selectează asigurătorul</option><option value="allianz" @selected(old('insurer') === 'allianz')>Allianz</option><option value="asirom" @selected(old('insurer') === 'asirom')>Asirom</option><option value="generali" @selected(old('insurer') === 'generali')>Generali</option><option value="groupama" @selected(old('insurer') === 'groupama')>Groupama</option><option value="omniasig" @selected(old('insurer') === 'omniasig')>Omniasig</option><option value="grawe" @selected(old('insurer') === 'grawe')>Grawe</option><option value="eazy_insure" @selected(old('insurer') === 'eazy_insure')>Eazy Insure</option><option value="dallbogg" @selected(old('insurer') === 'dallbogg')>DallBogg</option></select></label>
                    <label>Clasă Bonus-Malus<select name="bonus_malus" required><option value="">Selectează clasa</option><option value="B0" @selected(old('bonus_malus', 'B0') === 'B0')>B0 - fără reducere</option><option value="B1" @selected(old('bonus_malus') === 'B1')>B1 - reducere 5%</option><option value="B2" @selected(old('bonus_malus') === 'B2')>B2 - reducere 10%</option><option value="B3" @selected(old('bonus_malus') === 'B3')>B3 - reducere 15%</option><option value="B4" @selected(old('bonus_malus') === 'B4')>B4 - reducere 20%</option><option value="B5" @selected(old('bonus_malus') === 'B5')>B5 - reducere 25%</option><option value="B6" @selected(old('bonus_malus') === 'B6')>B6 - reducere 30%</option><option value="B7" @selected(old('bonus_malus') === 'B7')>B7 - reducere 40%</option><option value="B8" @selected(old('bonus_malus') === 'B8')>B8 - reducere 50%</option><option value="M1" @selected(old('bonus_malus') === 'M1')>M1 - malus</option><option value="M2" @selected(old('bonus_malus') === 'M2')>M2 - malus</option><option value="M3" @selected(old('bonus_malus') === 'M3')>M3 - malus</option><option value="M4" @selected(old('bonus_malus') === 'M4')>M4 - malus</option><option value="M5" @selected(old('bonus_malus') === 'M5')>M5 - malus</option><option value="M6" @selected(old('bonus_malus') === 'M6')>M6 - malus</option><option value="M7" @selected(old('bonus_malus') === 'M7')>M7 - malus</option><option value="M8" @selected(old('bonus_malus') === 'M8')>M8 - malus</option></select></label>
                    <label>Data început poliță<input type="date" name="start_date" data-current-date="{{ now()->toDateString() }}" data-future-date="{{ now()->addDay()->toDateString() }}" min="{{ now()->addDay()->toDateString() }}" value="{{ old('start_date', now()->addDay()->toDateString()) }}" required></label>
                    <label>Perioadă<select name="term_time" required><option value="">Selectează perioada</option><option value="1">1 lună</option><option value="3">3 luni</option><option value="6">6 luni</option><option value="12" @selected(old('term_time', 12) == 12)>12 luni</option></select></label>
                </div>
            </fieldset>
            <div class="form-actions">
                <p class="form-hint">Câmpurile marcate sunt necesare pentru calcularea ofertei.</p>
                <button type="submit" id="offer-submit">Obține oferta <span aria-hidden="true">→</span></button>
            </div>
        </form>
    </section>

    <section class="card history-card">
        <div class="section-heading">
            <div>
                <p class="section-kicker">ACTIVITATE</p>
                <h2>Istoric și documente</h2>
            </div>
            <span class="section-note">O evidență a ofertelor tale</span>
        </div>
        <div class="table-wrap">
            <table><thead><tr><th>ID</th><th>Asigurător</th><th>Ofertă</th><th>Stare</th><th>Acțiuni</th></tr></thead><tbody>@forelse($calculations as $calculation)<tr><td>{{ $calculation->id }}</td><td>{{ $calculation->insurer }}</td><td>{{ $calculation->offer_id ?? '—' }}</td><td><span class="status-pill">{{ $calculation->status }}</span></td><td>@if($calculation->offer_id)<a class="button button-secondary" href="{{ route('rca.pdf', [$calculation->id, 'offer']) }}">PDF ofertă</a>@endif @if($calculation->policy_id)<a class="button button-secondary" href="{{ route('rca.pdf', [$calculation->id, 'policy']) }}">PDF poliță</a>@elseif($calculation->offer_id)<form method="post" action="{{ route('rca.policy', $calculation->id) }}">@csrf<input type="number" step="0.01" name="amount" placeholder="Valoare RON" required><input name="document_number" placeholder="Nr. document" required><select name="payment_method"><option value="receipt">Chitanță</option><option value="payment order">Ordin plată</option><option value="pos">POS</option></select><button>Emite polița</button></form>@endif</td></tr>@empty<tr><td colspan="5" class="empty-state">Nu există calcule încă.</td></tr>@endforelse</tbody></table>
        </div>
    </section>
</main>
</body>
</html>
