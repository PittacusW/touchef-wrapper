# Touchef API Full Reference

## Table of Contents
1. [Authentication](#authentication)
2. [Sales Documents](#sales-documents)
3. [create_document — Full Signature](#create_document)
4. [Expenses](#expenses)
5. [Tenant / Business](#tenant--business)
6. [Certificates](#certificates)
7. [CAF Folios](#caf-folios)

---

## Authentication

### `login(string $password): void`
`POST /login` — Authenticates and saves the Sanctum token to `.env` as `TOUCHEF_TOKEN`.
```php
$touchef = new Touchef('12345678-9');
$touchef->login('your-password');
```

### `logout(): void`
`POST /logout` — Revokes the current bearer token.

### `refresh(): string`
`POST /refresh-token` — Revokes current token, issues a new one, returns it.

---

## Sales Documents

### `sales(int $year, int $month): mixed`
`GET /ventas/{year}/{month}` — List sales from SII RCV. Supports `?paginate=true` (20/page).

### `sales_summary(int $year, int $month): mixed`
`GET /ventas/resumen/{year}/{month}`

### `sale(string $uuid): mixed`
`GET /ventas/{uuid}`

### `show_by_number(int $type, int $number): mixed`
`GET /ventas/show/{type}/{number}`

### `queue_documents(array $documents): mixed`
`POST /ventas/cola` — Queue multiple documents for background issuance.
```php
$result = $touchef->queue_documents([
    ['document_type' => 33, 'issued_at' => '2025-03-15', 'receiver' => [...], 'lines' => [...]],
    ['document_type' => 39, 'issued_at' => '2025-03-15', 'lines' => [...]],
]);
// Returns: { "queued": 2 }
```

### `get_pdf(int $document_type, int $number): mixed`
`POST /ventas/pdf` — Returns `{ "document_type": 33, "number": 1234, "pdf": "base64..." }`.

### `send_email(int $number, int $type): void`
`POST /enviar-dte/{number}/{type}`

### `track_id(int $id): mixed`
`GET /ventas/track-id/{id}`

### `sii_status(int $id): mixed`
`GET /ventas/sii-status/{id}` — Syncs and returns SII status.

### `provider_status(int $id): mixed`
`GET /ventas/provider-status/{id}` — SOAP query to SII provider.

---

## create_document

`POST /ventas` — Issues a new electronic document (DTE).

```php
$document = $touchef->create_document(
    document_type: 33,          // required — see document type table in SKILL.md
    issued_at: '2025-03-15',   // required — Y-m-d
    receiver: [                 // required for types 33, 34, 52, 56, 61
        'rut'      => '98765432-1',
        'name'     => 'Empresa Cliente SpA',
        'activity' => 'Comercio al por menor',
        'address'  => 'Av. Providencia 1234',
        'county'   => 'Providencia',
        'contact'  => 'contacto@empresa.cl', // optional
    ],
    lines: [                    // required
        [
            'name'                => 'Servicio de consultoría', // required
            'quantity'            => 1,                          // required
            'unit_price'          => 100000,                     // required
            // optional:
            'description'         => 'Detalle largo del servicio',
            'code'                => 'SRV-001',
            'unit'                => 'UN',
            'discount_amount'     => 5000,
            'discount_percentage' => null,
            'surcharge_amount'    => null,
            'tax_category'        => 'taxable', // 'taxable' | 'exempt' | 'non_billable'
            'additional_tax_code' => null,
        ],
    ],
    mode: 'issue',              // 'draft' | 'issue'
    // --- all below are optional ---
    global_adjustments: [
        [
            'mode'         => 'discount',   // 'discount' | 'charge'
            'reason'       => 'Descuento por volumen',
            'value_type'   => 'percentage', // 'amount' | 'percentage'
            'value'        => 10,
            'tax_category' => 'taxable',    // 'taxable' | 'exempt'
        ],
    ],
    references: [               // required for types 56, 61
        [
            'document_type' => '33',
            'number'        => '1200',
            'date'          => '2025-03-01',
            'code'          => '1',   // '1'=Anula, '2'=Corrige texto, '3'=Corrige monto
            'reason'        => 'Anula factura anterior',
        ],
    ],
    transport: [                // type 52 (Guía de Despacho) only
        'dispatch_address' => 'Bodega Central, Ruta 5 KM 20',
        'dispatch_county'  => 'Maipú',
    ],
    payment: [
        'due_date'         => '2025-04-15',
        'period_from'      => '2025-03-01',
        'period_to'        => '2025-03-31',
        'previous_balance' => 50000,
    ],
    notifications: ['cliente@empresa.cl'],
);
```

---

## Expenses

### `expenses(int $year, int $month): mixed`
`GET /compras/{year}/{month}` — Supports `?paginate=true`.

### `expenses_summary(int $year, int $month): mixed`
`GET /compras/resumen/{year}/{month}`

### `expense(string $uuid): mixed`
`GET /compras/{uuid}`

### `pending(): mixed`
`GET /pendientes` — Current-month expenses with PENDIENTE status. Supports `?paginate=true`.

### `manage_expense(int $id, string $status): mixed`
`PUT /compras/{id}` — Accept or reject a received expense document.

| Code | Meaning |
|------|---------|
| `ACD` | Acuse de Recibo — accept; also sends ERM to SII |
| `RCD` | Reclamo por contenido del documento |
| `RFP` | Reclamo por falta parcial de mercaderías |
| `RFT` | Reclamo por falta total de mercaderías |

```php
$touchef->manage_expense(42, 'ACD');
$touchef->manage_expense(42, 'RCD');
```

---

## Tenant / Business

### `info(): mixed`
`GET /cliente` — Get current tenant's business profile.

### `get_data(string $rut): mixed`
`GET /consulta-datos/{rut}` — SII lookup by RUT.

### `update_client(...): mixed`
`PUT /cliente`
```php
$touchef->update_client(
    county: 1,
    economic_activity: 5,
    name: 'Mi Empresa SpA',
    rut: '12345678-9',
    activity: 'Servicios de software',
    address: 'Av. Providencia 1234',
    resolution_date: '2020-01-15',
    resolution_number: 80,
    email: 'admin@miempresa.cl'
);
```

### `create_client(...): mixed`
`POST /cliente`
```php
$touchef->create_client(
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
    website: 'https://nuevaempresa.cl' // optional
);
```

---

## Certificates

### `certificate(): mixed`
`GET /certificado` — Get active digital certificate info.

### `update_certificate(string $path, string $password): mixed`
`POST /certificado` — Reads the `.pfx` file at `$path`, base64-encodes it, and uploads it.
```php
$touchef->update_certificate('/path/to/cert.pfx', 'cert-password');
```

---

## CAF Folios

### `caf(): mixed`
`GET /folios` — Returns remaining folio ranges per document type.
```json
[{ "document_id": 33, "document": "Factura Electrónica", "start": 1001, "end": 2000 }]
```
