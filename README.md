# Inventory API

A REST API for tracking **contracts** and the **devices** attached to them. A device type (e.g. a laptop model) can be attached to any number of contracts — and even to the same contract multiple times — where each attachment represents one physical unit identified by a globally unique serial number.

Built with **Laravel 13** on **PHP 8.5** and **MySQL 8**, shipped as a Docker Compose stack based on the [serversideup/php](https://serversideup.net/open-source/docker-php/) `fpm-nginx` image.

## Features

- Full CRUD for devices and contracts (list + single item)
- Attach / detach device units to and from contracts by serial number
- Contract detail includes its devices with serial numbers; the contract list deliberately does not
- Unified JSON error handling (`{"message": ...}` envelope, `errors` map on validation failures)
- Every API request/response is logged to the database (`api_request_logs` table), with sensitive headers redacted, oversized bodies truncated, and rows pruned after 30 days
- OpenAPI 3.1 documentation generated from the code by [Scramble](https://scramble.dedoc.co/), served behind HTTP Basic Auth

## Quickstart (Docker)

Requirements: Docker with the Compose plugin. Nothing else — PHP and Composer run inside the container.

```bash
cp .env.example .env

# Install dependencies and generate the app key (AUTORUN must stay off before vendor/ exists):
docker compose run --rm --no-deps -e AUTORUN_ENABLED=false php composer install
docker compose run --rm --no-deps -e AUTORUN_ENABLED=false php php artisan key:generate

# Start the stack — database migrations run automatically on boot:
docker compose up -d

# Optional: seed demo data (devices, contracts, attached units, log samples):
docker compose exec php php artisan db:seed
```

The API is now available at `http://localhost:8080`:

```bash
curl http://localhost:8080/api/v1/devices -H 'Accept: application/json'
```

> **Linux hosts:** the image maps the container user to UID/GID 1000 by default. If your user has a different ID, build with `USER_ID=$(id -u) GROUP_ID=$(id -g) docker compose up -d --build` so the bind-mounted `storage/` directory stays writable.

## API documentation

Interactive OpenAPI docs (generated from the FormRequests and API Resources — always in sync with the code):

| URL | Content |
|---|---|
| `http://localhost:8080/docs/api` | Documentation UI |
| `http://localhost:8080/docs/api.json` | Raw OpenAPI 3.1 document |

Both are protected by HTTP Basic Auth. Credentials come from `DOCS_BASIC_AUTH_USERNAME` / `DOCS_BASIC_AUTH_PASSWORD` (`docs` / `secret` in `.env.example`). The guard **fails closed**: if the credentials are not configured, the docs return 401 for everyone. The docs route is also rate-limited (30 requests/minute) to blunt brute-force attempts.

## Endpoints

All endpoints live under `/api/v1` and speak JSON.

| Method | URI | Description |
|---|---|---|
| GET | `/api/v1/devices` | List devices (paginated, filterable, sortable) |
| POST | `/api/v1/devices` | Create a device |
| GET | `/api/v1/devices/{uuid}` | Show a device |
| PATCH/PUT | `/api/v1/devices/{uuid}` | Update a device (partial updates supported) |
| DELETE | `/api/v1/devices/{uuid}` | Delete a device — `409 Conflict` while units of it are attached to any contract |
| GET | `/api/v1/contracts` | List contracts (paginated, filterable, sortable; **no devices included**) |
| POST | `/api/v1/contracts` | Create a contract |
| GET | `/api/v1/contracts/{uuid}` | Show a contract **including its attached device units** |
| PATCH/PUT | `/api/v1/contracts/{uuid}` | Update a contract (partial updates supported) |
| DELETE | `/api/v1/contracts/{uuid}` | Delete a contract; its attached units are removed with it |
| POST | `/api/v1/contracts/{uuid}/devices` | Attach a device unit: `{"device": "<device uuid>", "serial_number": "SN-001"}` |
| DELETE | `/api/v1/contracts/{uuid}/devices/{serialNumber}` | Detach the unit with that serial number |

Notes:

- Resources are addressed by UUID; internal auto-increment IDs are never exposed.
- Response bodies use **camelCase** field names (`contractNumber`, `serialNumber`, `createdAt`, ...); request payloads and `filter`/`sort` query parameters stay snake_case.
- Serial numbers are limited to `A-Z a-z 0-9 . _ : -` (so every serial stays URL-addressable) and are stored uppercase; matching is case-insensitive.
- The same device type may be attached to one contract multiple times with different serials; a serial number is unique across the whole system.
- Attaching with an unknown device UUID is a validation error (`422`), not a `404`.

### Listing: filtering, sorting, pagination

List endpoints use [spatie/laravel-query-builder](https://spatie.be/docs/laravel-query-builder) conventions (non-whitelisted parameters are rejected with `400`):

```
GET /api/v1/devices?filter[name]=think&filter[category]=laptop&sort=-created_at&page=2
GET /api/v1/contracts?filter[partner_name]=acme&sort=contract_number
```

| Endpoint | Filters | Sorts |
|---|---|---|
| `/devices` | `name`, `manufacturer` (partial), `category` (exact) | `name`, `manufacturer`, `created_at` |
| `/contracts` | `contract_number`, `partner_name` (partial) | `contract_number`, `signed_at`, `created_at` |

Responses are paginated (25/page) with standard `links`/`meta` blocks.

### Error format

Every error uses the same envelope:

```json
{ "message": "Resource not found." }
```

Validation failures (`422`) additionally carry a field → messages map:

```json
{ "message": "The name field is required.", "errors": { "name": ["The name field is required."] } }
```

Covered statuses: `400` (bad filter/sort), `404` (unknown URL or resource), `405`, `409` (deleting an attached device), `422`, `500` (details hidden unless `APP_DEBUG=true`).

### Request logging

Every `/api/*` request is persisted to the `api_request_logs` table after the response is sent (terminable middleware — logging never delays a response): method, path, query, redacted headers, request/response bodies (truncated at 64 kB), status, duration and client IP. Even unmatched 404s and 405s are logged. Rows older than 30 days are removed by the scheduled `model:prune` run.

## Development

Local (non-Docker) development uses SQLite out of the box; tests always run on an in-memory SQLite database.

```bash
composer install
php artisan migrate --seed     # set DB_CONNECTION=sqlite in .env for local use
php artisan serve
```

Useful commands:

| Command | Purpose |
|---|---|
| `composer test` | Full QA gate: Pint (check) → PHPStan (larastan, level 7) → Pest test suite |
| `php artisan test --compact` | Run the test suite only |
| `vendor/bin/pint --dirty` | Fix code style on changed files |
| `vendor/bin/phpstan analyse` | Static analysis |
| `vendor/bin/rector` | Automated refactors |
| `php artisan migrate:fresh --seed` | Rebuild the database with demo data |
| `php artisan model:prune --model="App\Models\ApiRequestLog"` | Prune old API logs manually |

### Architecture

Requests flow through a fixed lifecycle: **Controller → FormRequest (validation) → Data object ([spatie/laravel-data](https://spatie.be/docs/laravel-data)) → Action (business logic) → API Resource (response shape)**.

```
app/
├── Actions/            # One class per business operation (CreateDevice, AttachDeviceToContract, ...)
├── Concerns/           # HasUuid — uuid route keys on all exposed models
├── Data/               # Typed input DTOs; arrays never cross layer boundaries
├── Enums/              # DeviceCategory
├── Http/
│   ├── Controllers/Api # Thin controllers: authorize, build DTO, call one Action, respond
│   ├── Middleware/     # LogApiRequest (DB logging), DocsBasicAuth (docs guard)
│   ├── Requests/       # FormRequests — the only place validation lives
│   └── Resources/      # Explicit field allowlists; expose uuid, never internal ids
└── Models/             # Contract, Device, ContractDevice (pivot = one physical unit), ApiRequestLog
```

### Scope notes

- The API itself is intentionally unauthenticated — the assignment only requires Basic Auth on the documentation. Consequently there are no authorization policies and no API-wide rate limiting.
- Tests (183 at the time of writing) cover every endpoint positively and negatively, every validation rule via datasets, the logging middleware, the docs guard, and the seeders.
