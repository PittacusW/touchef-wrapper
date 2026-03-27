---
name: touchef
description: >
  Reference for the pittacusw/touchef PHP SDK — Chilean electronic invoicing (SII DTE system).
  Use this skill whenever working on code that issues or queries Chilean tax documents (facturas,
  boletas, notas de crédito/débito, guías de despacho), manages expenses (compras), handles
  digital certificates or CAF folios, or integrates with the Touchef/Frozenbox API. Trigger on
  any mention of DTE, SII, factura, boleta, nota de crédito, RUT-based API calls, TOUCHEF_TOKEN,
  or the Frozenbox API. Even if the user doesn't say "Touchef", use this skill if the task
  clearly involves Chilean e-invoicing logic in this codebase.
---

## Overview

`pittacusw/touchef` (`Pittacusw\Touchef\Touchef`) is a PHP SDK for the Touchef electronic
invoicing API. **Base URL:** `https://api.frozenbox.cl/v1/`

Every request sends two headers:
- `business`: the tenant RUT (e.g. `12345678-9`)
- `Authorization`: `Bearer {token}` (Laravel Sanctum)

Config lives in `config/touchef.php`, populated from `TOUCHEF_RUT` and `TOUCHEF_TOKEN` in `.env`.

---

## Instantiation

```php
use Pittacusw\Touchef\Touchef;

$touchef = new Touchef();           // uses TOUCHEF_RUT from config
$touchef = new Touchef('76123456-7'); // override RUT at runtime
```

---

## Method Index

Grouped by domain. See `references/api-reference.md` for full signatures, payloads, and examples.

### Auth
| Method | Description |
|--------|-------------|
| `login(string $password): void` | Authenticate; saves token to `.env` |
| `logout(): void` | Revoke current token |
| `refresh(): string` | Rotate token; returns new one |

### Sales (Ventas)
| Method | Description |
|--------|-------------|
| `sales(int $year, int $month)` | List sales for period (RCV) |
| `sales_summary(int $year, int $month)` | Summary for period |
| `sale(string $uuid)` | Single document by UUID |
| `show_by_number(int $type, int $number)` | Document by type + folio |
| `create_document(...)` | Issue a new DTE |
| `queue_documents(array $documents)` | Queue multiple DTEs for background issuance |
| `get_pdf(int $document_type, int $number)` | Base64-encoded PDF |
| `send_email(int $number, int $type)` | Send DTE email to receiver |
| `track_id(int $id)` | SII submission status by internal ID |
| `sii_status(int $id)` | Sync + get SII status |
| `provider_status(int $id)` | SOAP query against SII provider |

### Expenses (Compras)
| Method | Description |
|--------|-------------|
| `expenses(int $year, int $month)` | List purchases for period |
| `expenses_summary(int $year, int $month)` | Summary for period |
| `expense(string $uuid)` | Single expense by UUID |
| `pending()` | Current-month expenses with PENDIENTE status |
| `manage_expense(int $id, string $status)` | Accept (`ACD`) or reject (`RCD`/`RFP`/`RFT`) |

### Tenant / Business
| Method | Description |
|--------|-------------|
| `info()` | Get current tenant info |
| `get_data(string $rut)` | Look up business info from SII by RUT |
| `update_client(...)` | Update tenant profile |
| `create_client(...)` | Register a new tenant |

### Certificates & Folios
| Method | Description |
|--------|-------------|
| `certificate()` | Get active certificate info |
| `update_certificate(string $path, string $password)` | Upload .pfx certificate |
| `caf()` | Get available CAF folio ranges |

### Reference Data
| Method | Description |
|--------|-------------|
| `counties()` | Chilean counties list (`GET /comunas`) |
| `economic_activities()` | Economic activities list (`GET /actividades`) |

---

## Document Types

| Code | Name | Tax |
|------|------|-----|
| 33 | Factura Electrónica | Taxable |
| 34 | Factura No Afecta/Exenta | Exempt |
| 39 | Boleta Electrónica | Taxable |
| 41 | Boleta No Afecta/Exenta | Exempt |
| 52 | Guía de Despacho Electrónica | Taxable |
| 56 | Nota de Débito Electrónica | Taxable |
| 61 | Nota de Crédito Electrónica | Taxable |

**Key rules:**
- Types 56, 61: `references` array is required
- Types 39, 41 (boletas): receiver optional, max 1000 lines
- Types 33, 34, 52, 56, 61: receiver with RUT required, max 60 lines
- Types 34, 41: all lines must be exempt
- Types 33, 39, 52, 56, 61: at least one taxable line required

---

## Response Format

```json
{ "records": <data> }
```

The `get()` helper and `create_document()` automatically extract `records`; if missing, they
`abort()` with the HTTP status from the API response.

**Validation errors:**
```json
{ "data": null, "errors": { "field": ["message"] }, "msg": "", "total": 0 }
```

---

## Error Handling

```php
try {
    $result = $touchef->create_document(...);
} catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
    $statusCode = $e->getStatusCode(); // 401, 403, 404, 422, 429, 500
}
```

---

## Typical Flow

```php
$touchef = new Touchef('76123456-7');
$touchef->login('password');         // stores token in .env

$touchef = new Touchef();            // reinitialize with stored token

$invoice = $touchef->create_document(
    document_type: 33,
    issued_at: now()->format('Y-m-d'),
    receiver: ['rut' => '98765432-1', 'name' => 'Cliente SpA',
                'activity' => 'Retail', 'address' => 'Calle 123', 'county' => 'Santiago'],
    lines: [['name' => 'Widget', 'quantity' => 5, 'unit_price' => 10000]],
    mode: 'issue',
);

$status  = $touchef->sii_status($invoice->id);
$sales   = $touchef->sales(2025, 3);
$pending = $touchef->pending();
$touchef->manage_expense($pending[0]->id, 'ACD');
```

---

For full parameter signatures, optional fields, and per-endpoint payload details, read
`references/api-reference.md`.
