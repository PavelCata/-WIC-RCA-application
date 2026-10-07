# Documentație tehnică - Calculator RCA

## 1. Scopul aplicației

Aplicația este o interfață web Laravel pentru calcularea unei oferte RCA, emiterea poliței și obținerea documentelor PDF prin RCA API de la Life Is Hard.

Arhitectura este:

```text
Browser
  -> Laravel routes/controllers
      -> validare și normalizare input
      -> construire payload RCA API
      -> salvare calcul + audit
      -> RCA API extern
  -> afișare rezultat și descărcare PDF
```

Credentialele RCA API sunt folosite exclusiv în backend. Browserul trimite doar datele formularului către Laravel; nu primește contul, parola sau tokenul JWT.

## 2. Fluxul funcțional cerut

Fluxul conform documentației RCA API este:

1. **Auth** - backend-ul apelează `POST /auth` cu `account` și `password`.
2. **Offer** - backend-ul apelează `POST /offer` cu datele clientului, vehiculului, adresei și produsului.
3. **Policy** - utilizatorul furnizează datele de plată, iar backend-ul apelează `POST /policy` cu `offerId`.
4. **Documents** - backend-ul apelează `GET /offer/{offerId}` sau `GET /policy/{policyId}` și returnează PDF-ul.

În mediul QA, exemplul de credentiale indicat este `test` / `test`. Acestea se pun în `.env`, niciodată în Blade, JavaScript sau în payload-ul transmis din browser.

## 3. Structura proiectului

### Frontend

- `resources/views/rca/index.blade.php`
  - afișează formularul pentru ofertă;
  - permite alegerea persoană fizică / persoană juridică;
  - afișează câmpurile pentru client, adresă, vehicul și ofertă;
  - afișează istoricul calculelor;
  - afișează acțiunile pentru PDF și emiterea poliței.
- `resources/css/app.css`
  - Tailwind CSS v4;
  - layout responsive, carduri, input-uri, tabel și stări vizuale.
- `resources/js/app.js`
  - schimbă dinamic câmpurile PF/PJ;
  - activează/dezactivează câmpurile vehiculului în funcție de tipul de înmatriculare;
  - setează `engineDisplacement` la `0` pentru vehicule electrice;
  - calculează `city_code` pentru localitățile configurate;
  - aplică reguli de dată în browser;
  - verifică validitatea HTML înainte de trimitere.

Validarea din JavaScript este doar pentru experiența utilizatorului. Validarea obligatorie și de securitate se face în backend prin Form Requests.

### HTTP Layer

`routes/web.php` definește următoarele rute:

| Metodă | URL | Nume | Rol |
| --- | --- | --- | --- |
| `GET` | `/` | `rca.index` | afișează calculatorul și ultimele calcule |
| `POST` | `/oferte` | `rca.offer` | validează și solicită oferta |
| `POST` | `/calculatii/{calculation}/polita` | `rca.policy` | transformă oferta în poliță |
| `GET` | `/calculatii/{calculation}/pdf/{type}` | `rca.pdf` | descarcă PDF-ul ofertei sau poliței |

`app/Http/Controllers/RcaCalculatorController.php` orchestrează requestul, dar nu construiește manual payload-ul și nu face direct operații SQL complexe.

Metodele controllerului:

- `index()` cere ultimele 10 calculări din repository și returnează view-ul;
- `offer()`:
  1. primește `CreateRcaOfferRequest`;
  2. construiește payload-ul prin `RcaQuotationPayloadFactory`;
  3. creează o înregistrare cu status `submitted`;
  4. înregistrează `offer_requested`;
  5. apelează RCA API;
  6. salvează `offer_id`, răspunsul și statusul `offered`;
  7. înregistrează `offer_received`;
  8. la eroare marchează `offer_failed` și înregistrează `offer_failed`.
- `policy()`:
  1. caută calculul local;
  2. verifică existența `offer_id`;
  3. construiește payload-ul de plată;
  4. înregistrează `policy_requested`;
  5. apelează `POST /policy`;
  6. salvează `policy_id`, răspunsul și statusul `issued`;
  7. înregistrează `policy_received`;
  8. la eroare marchează `policy_failed`.
- `pdf()`:
  1. verifică tipul `offer` sau `policy`;
  2. găsește ID-ul extern salvat;
  3. apelează endpointul PDF corespunzător;
  4. decodează conținutul Base64;
  5. returnează `application/pdf`;
  6. înregistrează descărcarea.

### Validarea ofertei

`CreateRcaOfferRequest` normalizează înainte de validare:

- textul identificatorilor la uppercase și fără spații inutile;
- câmpurile numerice la integer;
- capacitatea motorului electric la `0`.

Regulile acoperă:

- PF sau PJ;
- CNP valid pentru persoană fizică;
- CUI valid pentru persoană juridică;
- identificare CI/PASSPORT pentru PF;
- email și telefon românesc;
- județ, localitate, cod SIRUTA și adresă;
- tipul de înmatriculare;
- număr de înmatriculare condiționat de tip;
- CIV și VIN;
- tip, marcă, model, an, motor, masă și număr de locuri;
- combustibil și utilizare;
- asigurător, Bonus-Malus, perioadă și data de început.

Regula importantă pentru date:

- `registered` și `recorded`: începutul poliței trebuie să fie cel puțin mâine;
- `temporaryRegistered` și `temporaryRecorded`: începutul poate fi astăzi.

### Validarea poliței

`CreateRcaPolicyRequest` validează:

- `amount`: număr mai mare decât `0.01`;
- `payment_method`: una dintre valorile acceptate de API;
- `document_number`: text obligatoriu, maximum 50 caractere.

API-ul acceptă metodele `receipt`, `broker receipt`, `payment order`, `broker payment order` și `pos`. Pentru card, documentația API indică folosirea valorii `pos`.

## 4. Construirea requestului RCA API

`app/Services/RcaQuotationPayloadFactory.php` transformă câmpurile plate ale formularului în structura ierarhică cerută de API.

Payload-ul de ofertă are forma:

```json
{
  "provider": {
    "organization": {
      "businessName": "allianz"
    }
  },
  "product": {
    "motor": {
      "startDate": "YYYY-MM-DD",
      "termTime": 12,
      "installmentCount": 1
    },
    "policyholder": {},
    "vehicle": {}
  }
}
```

### Policyholder

Pentru PF sunt trimise:

- `taxId`;
- `bonusMalus`;
- `email`;
- `mobileNumber`;
- `address`;
- `lastName`, `firstName`;
- `identification.idType`, `identification.idNumber`.

Pentru PJ sunt trimise:

- `taxId`;
- `businessName`;
- `contactPerson`;
- opțional `companyRegistryNumber`;
- aceleași date de contact și adresă.

### Address

Maparea este:

| Formular | API |
| --- | --- |
| `county` | `county` |
| `city` | `city` |
| `city_code` | `cityCode` |
| `street` | `street` |
| `house_number` | `houseNumber` |
| `postcode` | `postcode` |
| implicit | `country = RO` |

### Vehicle

Maparea este:

| Formular | API |
| --- | --- |
| `registration_type` | `registrationType` |
| `license_plate` | `licensePlate` |
| `vin` | `vin` |
| `vehicle_type` | `vehicleType` |
| `brand` | `brand` |
| `model` | `model` |
| `year_of_construction` | `yearOfConstruction` |
| `engine_displacement` | `engineDisplacement` |
| `engine_power` | `enginePower` |
| `total_weight` | `totalWeight` |
| `seats` | `seats` |
| `fuel_type` | `fuelType` |
| `first_registration` | `firstRegistration` |
| `usage_type` | `usageType` |
| `vehicle_identification_number` | `identification.idNumber` |

Datele sunt convertite la format ISO `YYYY-MM-DD`.

### Provider authentication

Credentialele specifice asigurătorului sunt adăugate numai dacă există în configurație:

- `RCA_PROVIDER_ACCOUNT`;
- `RCA_PROVIDER_PASSWORD`;
- `RCA_PROVIDER_CODE`.

Acestea sunt diferite de credentialele contului operatorului folosite la `/auth`.

### Payload pentru poliță

`RcaQuotationPayloadFactory::policy()` produce:

```json
{
  "offerId": 123,
  "payment": {
    "method": "receipt",
    "currency": "RON",
    "amount": 450,
    "date": "YYYY-MM-DD",
    "documentNumber": "CH-001"
  }
}
```

Documentația API spune că un al doilea request cu același `offerId` returnează polița salvată la prima cerere, fără emiterea unei polițe noi. Backend-ul trebuie să păstreze această idempotency în modelul de stare și să nu ofere butonul de emitere după statusul `issued`.

## 5. Clientul RCA API

`app/Services/RcaApiClient.php` este singura componentă care comunică HTTP cu API-ul extern.

Responsabilități:

- configurează `baseUrl`;
- aplică timeout de conectare și timeout total;
- face retry pentru erori tranzitorii;
- trimite `Accept: application/json`;
- trimite `Content-Language: ro`;
- obține tokenul din `POST /auth`;
- trimite tokenul în header-ul `Token`;
- cache-uiește tokenul;
- verifică certificatul TLS;
- expune metodele de domeniu:
  - `createOffer()`;
  - `createPolicy()`;
  - `offerPdf()`;
  - `policyPdf()`.

Tokenul este obținut lazy: autentificarea nu se face la încărcarea paginii, ci la primul apel RCA API. Cache-ul este local și are durata de 50 de minute. Documentația API mai oferă `PATCH /auth` pentru reînnoirea tokenului; implementarea curentă nu folosește încă refresh token-ul și trebuie extinsă dacă expirarea tokenului devine o problemă.

## 6. Persistență și trasabilitate

### `rca_calculations`

Tabelul păstrează starea agregată a unei operații:

- `id`: identificator local;
- `insurer`: asigurătorul selectat;
- `offer_id`: identificator extern al ofertei;
- `policy_id`: identificator extern al poliței;
- `request_payload`: payload-ul de ofertă redactat;
- `offer_response`: răspunsul complet al API-ului;
- `policy_response`: răspunsul complet al API-ului;
- `status`: starea curentă;
- timestamps.

Stările actuale sunt:

```text
draft -> submitted -> offered -> issued
                  |          |
                  v          v
             offer_failed  policy_failed
```

### `rca_audit_events`

Pentru fiecare operație sunt păstrate:

- `rca_calculation_id`;
- numele evenimentului;
- payload JSON;
- IP-ul clientului;
- user-agent-ul;
- timestamps.

Evenimentele existente sunt:

- `offer_requested`;
- `offer_received`;
- `offer_failed`;
- `policy_requested`;
- `policy_received`;
- `policy_failed`;
- `offer_pdf_downloaded`;
- `policy_pdf_downloaded`.

`RcaAuditLogger::redactSecrets()` elimină sau maschează date sensibile înainte de logare. Sunt mascate credentiale, tokenuri, CNP/CUI, identificatori și date de contact. Implementarea actuală trebuie revizuită periodic pentru a acoperi toate datele personale pe care politica de retenție nu permite să fie păstrate în clar.

## 7. Configurație și medii

`config/rca.php` citește din `.env`:

```dotenv
RCA_API_BASE_URL=https://rca-qa.api.lifeishard.ro
RCA_API_ACCOUNT=test
RCA_API_PASSWORD=test
RCA_PROVIDER_ACCOUNT=
RCA_PROVIDER_PASSWORD=
RCA_PROVIDER_CODE=
RCA_API_TIMEOUT=15
RCA_API_CONNECT_TIMEOUT=5
RCA_API_VERIFY_SSL=true
```

În producție:

- se schimbă URL-ul către mediul de producție;
- se folosesc credentiale de producție distincte;
- `APP_DEBUG=false`;
- `RCA_API_VERIFY_SSL=true`;
- IP-ul serverului trebuie whitelistat de furnizor;
- `.env` nu se commit-uiește și nu se expune prin frontend.

Documentația API menționează că accesul la medii este restricționat prin whitelist de IP și că credentialele QA nu funcționează în producție.

## 8. Reguli API care trebuie respectate în UI/backend

Documentația Life Is Hard descrie reguli generale și reguli specifice asigurătorului. Formularul curent acoperă subsetul comun, dar pentru o integrare completă trebuie adăugate reguli dinamice pe asigurător.

Reguli generale importante:

- `provider.organization.businessName` este obligatoriu;
- `product.motor.startDate` și `termTime` sunt obligatorii;
- `termTime` este între 1 și 12 luni, dar valorile exacte pot depinde de asigurător;
- `product.policyholder` și `product.vehicle` sunt obligatorii;
- `cityCode` trebuie să fie cod SIRUTA valid;
- `VIN` poate avea reguli suplimentare în funcție de anul mașinii;
- CIV este necesar pentru vehicule `registered` și `recorded`;
- numărul de înmatriculare are format diferit pentru `registered`, `recorded` și tipurile temporare;
- răspunsul API are structura comună `error`, `status`, `data`, `message`;
- `Content-Language: ro` cere mesaje de validare în limba română.

Exemple de particularități:

- Allianz poate impune perioadă de 12 luni pentru persoane fizice și are reguli pentru rate, CASCO, CAEN și vehicule;
- Groupama are reguli pentru rate, utilizarea vehiculului, șoferi și combinația vehicul/proprietar;
- DallBogg impune reguli speciale pentru rate, VIN, date și tipuri de înmatriculare;
- Hellas Autonom și Hellas NextIns au reguli comune și pot genera link de plată;
- Axeria face `houseNumber` obligatoriu.

Concluzia este că validarea locală trebuie să aibă două niveluri:

1. reguli comune, aplicate tuturor asigurătorilor;
2. reguli specifice asigurătorului, aplicate după alegerea `insurer`.

## 9. Împărțirea prezentării în două părți

### Partea 1: Auth, Offer și frontend

Se prezintă:

1. documentația Swagger și endpointul `POST /auth`;
2. utilizarea credentialelor QA `test` / `test`;
3. tokenul JWT și headerul `Token`;
4. endpointul `POST /offer`;
5. structura `provider`, `product.motor`, `policyholder`, `vehicle`;
6. formularul web și validarea câmpurilor;
7. separarea frontend/backend;
8. faptul că requestul este salvat și auditat înainte de apelul extern.

### Partea 2: Integrarea completă

Se prezintă:

1. folosirea `offerId`;
2. endpointul `POST /policy`;
3. datele de plată și currency `RON`;
4. idempotency pentru același `offerId`;
5. salvarea `policyId`;
6. `GET /offer/{offerId}` și `GET /policy/{policyId}`;
7. decodarea PDF-ului Base64;
8. istoricul și trasabilitatea completă;
9. gestionarea erorilor și stările `offer_failed` / `policy_failed`.

## 10. Verificare și teste

`tests/Feature/RcaCalculatorTest.php` verifică:

- pagina calculatorului;
- validarea câmpurilor obligatorii;
- CNP invalid;
- tip de înmatriculare invalid;
- prima înmatriculare anterioară anului de fabricație;
- formatul ISO al datelor trimise la API;
- payload pentru persoană juridică;
- autentificarea backend și transmiterea headerului `Token`;
- fluxul complet ofertă → poliță → PDF;
- persistarea ID-urilor și statusurilor;
- faptul că parolele și datele sensibile nu sunt salvate în audit.

Testul `test_real_rca_api_accepts_an_offer` este dezactivat implicit și se activează doar prin:

```dotenv
RUN_RCA_INTEGRATION_TESTS=true
```

Testele obișnuite folosesc `Http::fake()`, deci nu depind de disponibilitatea API-ului extern.

## 11. Observații pentru etapa următoare

Înainte de integrarea finală trebuie verificat direct în Swagger:

- schema exactă pentru `POST /auth`;
- schema completă pentru `POST /offer`;
- răspunsul cu una sau mai multe oferte;
- schema exactă pentru `POST /policy`;
- endpointurile PDF și conținutul Base64;
- nomenclatoarele pentru județe și localități;
- regulile specifice fiecărui asigurător ales;
- mecanismul de refresh prin `PATCH /auth`;
- endpointul opțional `GET /policy?series={series}&number={number}`.

Documentația curentă descrie implementarea existentă și evidențiază locurile unde aplicația trebuie extinsă pentru acoperire completă a Swagger-ului.
