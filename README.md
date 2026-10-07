# RCA Quote and Policy Calculator

A Laravel web application for creating Romanian motor third-party liability (RCA) insurance offers through the Life Is Hard RCA API. The workflow covers offer creation, policy issuance, PDF retrieval, and an auditable local calculation history.

## Features

- Responsive quote form for individuals and companies.
- Server-side validation and API payload construction.
- Backend-only API authentication and credential handling.
- Offer, policy, and PDF API workflows.
- Persistent calculation status and audit events with sensitive data redaction.
- Automated feature tests using mocked HTTP responses.

## Workflow

```text
Quote form -> validation -> RCA API offer -> policy issuance -> PDF download
                         \-> calculation history and audit events
```

The browser communicates with Laravel only. Laravel handles API credentials, authentication, payload mapping, persistence, and communication with the external provider.

## Requirements

- PHP 8.3 or later
- Composer
- Node.js and npm
- SQLite (or another database supported by Laravel)

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm ci
npm run build
php artisan serve
```

Configure `RCA_API_BASE_URL`, `RCA_API_ACCOUNT`, and `RCA_API_PASSWORD` in `.env` with credentials for an authorized RCA API environment. Provider-specific credentials can be set with `RCA_PROVIDER_ACCOUNT`, `RCA_PROVIDER_PASSWORD`, and `RCA_PROVIDER_CODE`. Never commit `.env` or production credentials.

## Tests

```bash
php artisan test --compact
```

The automated tests mock external HTTP requests and do not require access to the RCA API. Live integration tests are opt-in and require explicitly configured credentials.

## Project structure

- `app/Http` — request validation and web controller
- `app/Services` — API client, payload factory, persistence, and audit logging
- `database/migrations` — calculation and audit-event schemas
- `resources` — Blade interface, CSS, and JavaScript
- `routes/web.php` — application endpoints
- `tests/Feature` — calculator and API workflow coverage
- `docs` — application, class, package, and state diagrams

See [the technical documentation](docs/rca-application-documentation.md) for API details, architecture, and workflow behavior.
