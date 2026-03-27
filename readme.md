# pittacusw/touchef

Small Laravel wrapper around the Touchef API for Chilean electronic invoicing workflows.

It provides:

- A `Touchef` service class for the Touchef HTTP API
- Laravel service provider + facade auto-discovery
- Config driven credentials via `TOUCHEF_RUT` and `TOUCHEF_TOKEN`
- Publishing for package config and the bundled Codex agent skill

## Requirements

- PHP 8.1+
- Laravel 9.23, 12.x, or 13.x

## Installation

```bash
composer require pittacusw/touchef
```

Publish the package config if you want a local config file:

```bash
php artisan vendor:publish --tag=touchef-config
```

Add your credentials to `.env`:

```dotenv
TOUCHEF_RUT=76111111-1
TOUCHEF_TOKEN=
```

## Usage

### Resolve from the container

```php
use Pittacusw\Touchef\Touchef;

$touchef = app(Touchef::class);
```

### Use the facade

```php
use Touchef;

$business = Touchef::info();
```

### Instantiate directly

```php
use Pittacusw\Touchef\Touchef;

$touchef = new Touchef();

// Override the configured RUT for this runtime instance.
$touchef = new Touchef('76123456-7');
```

## Authentication

```php
$touchef->login('your-password');

$token = $touchef->refresh();

$touchef->logout();
```

`login()` and `refresh()` update the in-memory config token and also persist `TOUCHEF_TOKEN` to `.env` when that file exists. `login()` also persists the active `TOUCHEF_RUT`.

## Common operations

### Tenant and reference data

```php
$info = $touchef->info();
$counties = $touchef->counties();
$activities = $touchef->economic_activities();
$lookup = $touchef->get_data('12345678-9');
```

### Sales

```php
$sales = $touchef->sales(2025, 3);
$summary = $touchef->sales_summary(2025, 3);
$sale = $touchef->sale('uuid-value');
$byNumber = $touchef->show_by_number(33, 1200);
$track = $touchef->track_id(77);
$siiStatus = $touchef->sii_status(77);
$providerStatus = $touchef->provider_status(77);
$pdf = $touchef->get_pdf(33, 1200);

$touchef->send_email(1200, 33);
```

### Create a document

```php
$document = $touchef->create_document(
    document_type: 33,
    issued_at: '2025-03-15',
    lines: [[
        'name' => 'Servicio',
        'quantity' => 1,
        'unit_price' => 10000,
    ]],
    mode: 'issue',
    receiver: [
        'rut' => '98765432-1',
        'name' => 'Cliente SpA',
        'activity' => 'Servicios',
        'address' => 'Av. Siempre Viva 123',
        'county' => 'Santiago',
    ],
    payment: [
        'due_date' => '2025-04-15',
    ],
    notifications: ['facturacion@cliente.cl'],
);
```

Optional payload sections supported by `create_document()`:

- `receiver`
- `global_adjustments`
- `references`
- `transport`
- `payment`
- `notifications`

### Queue documents

```php
$queued = $touchef->queue_documents([
    [
        'document_type' => 33,
        'issued_at' => '2025-03-15',
        'receiver' => [
            'rut' => '98765432-1',
            'name' => 'Cliente SpA',
            'activity' => 'Servicios',
            'address' => 'Av. Siempre Viva 123',
            'county' => 'Santiago',
        ],
        'lines' => [[
            'name' => 'Servicio',
            'quantity' => 1,
            'unit_price' => 10000,
        ]],
    ],
]);
```

### Expenses

```php
$expenses = $touchef->expenses(2025, 3);
$summary = $touchef->expenses_summary(2025, 3);
$expense = $touchef->expense('uuid-value');
$pending = $touchef->pending();

$accepted = $touchef->manage_expense(42, 'ACD');
```

Supported expense management codes:

- `ACD`
- `RCD`
- `RFP`
- `RFT`

### Business management

```php
$updated = $touchef->update_client(
    county: 1,
    economic_activity: 5,
    name: 'Mi Empresa SpA',
    rut: '12345678-9',
    activity: 'Servicios de software',
    address: 'Av. Providencia 1234',
    resolution_date: '2020-01-15',
    resolution_number: 80,
    email: 'admin@miempresa.cl',
);

$created = $touchef->create_client(
    county_id: 1,
    economic_activity_id: 5,
    name: 'Nueva Empresa SpA',
    rut: '12345678-9',
    activity: 'Servicios de software',
    address: 'Av. Providencia 1234',
    resolution_date: '2020-01-15',
    resolution_number: 80,
    email: 'admin@nuevaempresa.cl',
    password: 'securepassword123',
    website: 'https://nuevaempresa.cl',
);
```

### Certificates and folios

```php
$certificate = $touchef->certificate();
$updated = $touchef->update_certificate(storage_path('certs/company.pfx'), 'pfx-password');
$caf = $touchef->caf();
```

## Response behavior

Most wrapper methods expect the API to return a JSON object containing `records` and will return that value directly.

The exception is `queue_documents()`, which returns the full decoded object and expects a `queued` property.

Failed HTTP responses abort with the response status code.

## Agent skill publishing

This package also ships with a Codex skill for Touchef-specific workflows. You can publish it with:

```bash
php artisan vendor:publish --tag=touchef-agent-skill
```

Or via the dedicated command:

```bash
php artisan touchef:install-agent-skill
```

That publishes the skill into:

```text
.agents/skills/touchef
```

## Testing

```bash
composer test
```

## Notes

- The wrapper sends the `business` header using the configured RUT formatted by `freshwork/chilean-bundle`.
- The bearer token is only attached when `TOUCHEF_TOKEN` is present.
- `update_certificate()` now throws a `RuntimeException` if the provided certificate file cannot be read.
