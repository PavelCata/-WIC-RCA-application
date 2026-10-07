# Diagrama de pachete - Calculator RCA

```mermaid
flowchart LR
    subgraph Frontend["Frontend"]
        Views["resources/views/rca<br/>Blade UI"]
        Assets["resources/css + resources/js<br/>Tailwind / JavaScript"]
        Views --> Assets
    end

    subgraph Http["HTTP Layer"]
        Routes["routes/web.php<br/>RCA routes"]
        Controller["app/Http/Controllers<br/>RcaCalculatorController"]
        Requests["app/Http/Requests<br/>Offer / Policy validation"]
        Routes --> Controller
        Controller --> Requests
    end

    subgraph Application["Application Services"]
        Payload["app/Services<br/>RcaQuotationPayloadFactory"]
        ApiClient["app/Services<br/>RcaApiClient"]
        Repository["app/Services<br/>RcaCalculationRepository"]
        Audit["app/Services<br/>RcaAuditLogger"]
    end

    subgraph Database["Database"]
        Calculations[("rca_calculations<br/>offers, policies, status")]
        AuditEvents[("rca_audit_events<br/>complete traceability")]
        Calculations --> AuditEvents
    end

    subgraph External["External RCA API"]
        Auth["POST /auth<br/>JWT token"]
        Offer["POST /offer<br/>offerId"]
        Policy["POST /policy<br/>policyId"]
        OfferPdf["GET /offer/{offerId}<br/>offer PDF"]
        PolicyPdf["GET /policy/{policyId}<br/>policy PDF"]
        Auth --> Offer
        Offer --> Policy
        Offer --> OfferPdf
        Policy --> PolicyPdf
    end

    Frontend --> Routes
    Controller --> Payload
    Controller --> ApiClient
    Controller --> Repository
    Controller --> Audit
    Payload --> ApiClient
    Repository --> Calculations
    Audit --> AuditEvents
    ApiClient --> Auth
    ApiClient --> Offer
    ApiClient --> Policy
    ApiClient --> OfferPdf
    ApiClient --> PolicyPdf

    classDef frontend fill:#eff6ff,stroke:#2563eb,color:#172554
    classDef http fill:#f5f3ff,stroke:#7c3aed,color:#2e1065
    classDef application fill:#ecfdf5,stroke:#059669,color:#064e3b
    classDef database fill:#fff7ed,stroke:#ea580c,color:#7c2d12
    classDef external fill:#fef2f2,stroke:#dc2626,color:#7f1d1d

    class Views,Assets frontend
    class Routes,Controller,Requests http
    class Payload,ApiClient,Repository,Audit application
    class Calculations,AuditEvents database
    class Auth,Offer,Policy,OfferPdf,PolicyPdf external
```

## Responsabilitatea pachetelor

| Pachet | Responsabilitate |
| --- | --- |
| `Frontend` | Colectează datele utilizatorului și afișează ofertele, polițele și documentele |
| `HTTP Layer` | Definește rutele, validează inputul și orchestrează cazurile de utilizare |
| `Application Services` | Construiește payload-uri, apelează API-ul și persistă rezultatele |
| `Database` | Păstrează calculele, identificatorii API, răspunsurile și jurnalul de audit |
| `External RCA API` | Autentifică aplicația, generează oferta, emite polița și furnizează PDF-urile |

## Regula de trasabilitate

Orice operație care trece prin backend trebuie să aibă un calcul asociat în `rca_calculations` și un eveniment în `rca_audit_events`. Credentialele pentru `auth` nu traversează frontend-ul.
