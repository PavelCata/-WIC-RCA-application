# Diagrama de clase - Calculator RCA

Diagrama reflectă structura actuală a aplicației și fluxul de integrare cu RCA API:

`auth` → `offer` → `policy` → `PDF`

```mermaid
classDiagram
    direction LR

    class Controller {
        <<abstract>>
    }

    class RcaCalculatorController {
        -RcaCalculationRepository calculations
        -RcaAuditLogger audit
        +index() View
        +offer(CreateRcaOfferRequest, RcaApiClient, RcaQuotationPayloadFactory) RedirectResponse
        +policy(CreateRcaPolicyRequest, int, RcaApiClient, RcaQuotationPayloadFactory) RedirectResponse
        +pdf(Request, int, string, RcaApiClient) Response
    }

    Controller <|-- RcaCalculatorController

    class CreateRcaOfferRequest {
        +authorize() bool
        +rules() array
        +prepareForValidation() void
        +after() array
        -validCnp(string) bool
        -validCui(string) bool
    }

    class CreateRcaPolicyRequest {
        +authorize() bool
        +rules() array
    }

    class RcaQuotationPayloadFactory {
        +offer(array) array
        +policy(int, array) array
    }

    class RcaCalculationRepository {
        +latest() iterable
        +create(string, array) int
        +find(int) stdClass
        +saveOffer(int, array) void
        +markOfferFailed(int) void
        +savePolicy(int, array) void
        +markPolicyFailed(int) void
    }

    class RcaAuditLogger {
        +log(Request, int, string, array) void
        +redactSecrets(array) array
    }

    class RcaApiClient {
        +createOffer(array) array
        +createPolicy(array) array
        +offerPdf(int) array
        +policyPdf(int) array
        -request() PendingRequest
        -token() string
        -sslOptions() array
    }

    RcaCalculatorController --> CreateRcaOfferRequest : validates offer input
    RcaCalculatorController --> CreateRcaPolicyRequest : validates policy input
    RcaCalculatorController --> RcaQuotationPayloadFactory : builds API payload
    RcaCalculatorController --> RcaCalculationRepository : persists state
    RcaCalculatorController --> RcaAuditLogger : records traceability
    RcaCalculatorController --> RcaApiClient : calls external API

    class RcaCalculationsTable {
        +id int
        +insurer string
        +offer_id int?
        +policy_id int?
        +request_payload json
        +offer_response json?
        +policy_response json?
        +status string
        +created_at timestamp
        +updated_at timestamp
    }

    class RcaAuditEventsTable {
        +id int
        +rca_calculation_id int
        +event string
        +payload json?
        +ip_address string?
        +user_agent text?
        +created_at timestamp
        +updated_at timestamp
    }

    RcaCalculationRepository --> RcaCalculationsTable : reads and writes
    RcaAuditLogger --> RcaAuditEventsTable : inserts events
    RcaAuditEventsTable --> RcaCalculationsTable : belongs to

    class RcaApi {
        <<external service>>
        +POST /auth
        +PATCH /auth
        +DELETE /auth
        +POST /offer
        +POST /policy
        +GET /offer/{offerId}
        +GET /policy/{policyId}
    }

    class AuthToken {
        +token string
        +expires_at datetime
        +refresh_token string
    }

    class Offer {
        +offerId int
        +response json
    }

    class Policy {
        +policyId int
        +response json
    }

    RcaApiClient --> RcaApi : HTTPS + Token header
    RcaApi ..> AuthToken : auth response
    RcaApi ..> Offer : offer response
    RcaApi ..> Policy : policy response
    RcaApiClient ..> AuthToken : caches token
    RcaCalculationRepository ..> Offer : stores offer_id
    RcaCalculationRepository ..> Policy : stores policy_id

    class RcaIndexView {
        <<Blade view>>
        +offer form
        +policy form
        +history table
        +PDF links
    }

    RcaCalculatorController --> RcaIndexView : renders
```

## Trasabilitate

Fiecare calcul este înregistrat în `rca_calculations`, iar fiecare etapă importantă este jurnalizată în `rca_audit_events`:

- `offer_requested`
- `offer_received`
- `offer_failed`
- `policy_requested`
- `policy_received`
- `policy_failed`
- `offer_pdf_downloaded`
- `policy_pdf_downloaded`

Credentialele API sunt citite server-side din configurația Laravel și nu apar în frontend. Payload-urile salvate în jurnal trec prin `redactSecrets()` înainte de persistare.
