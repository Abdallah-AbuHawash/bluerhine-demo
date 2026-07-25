# CutToSize — Phase 1 standalone demo

Cut-to-size quoting platform for a UAE polymer sheet trader: customers send a
cut list, the engine nests it into sheets with guillotine cuts, and the app
prices, schedules and freezes a quote.

Everything runs in Docker. Nothing is installed on the host except Docker
itself — no PHP, no Node, no Composer.

## Run it

```bash
docker compose up -d --build
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan test
docker compose exec app php artisan tinker
```

| Service | URL |
|---|---|
| App (nginx → PHP-FPM 8.5) | http://localhost:8080 |
| Vite dev server | http://localhost:5174 |
| MySQL 8.4 | localhost:33061 (user `cuttosize`, password `secret`) |

Containers: `app` (PHP-FPM 8.5), `nginx`, `mysql`, `node` (Vite). No queue or
scheduler container — nothing in this demo needs one.

`.env` is created from `.env.example` on first boot and an `APP_KEY` is
generated automatically.

Sign in with `demo@cuttosize.test` / `password` (pre-filled on the login form).

## Screens

| Screen | What it does |
|---|---|
| New estimate | Pick a material, enter the cut list, choose fixed or optimized nesting. |
| Quote | Per-sheet SVG drawn to scale from the layout tree, price breakdown, promised date, **Issue quote** (freezes the snapshot, soft-allocates stock for 48 h). |
| AI intake | Paste or upload a cut list; one Anthropic call with a strict JSON schema, canned fixtures offline. |
| Review | Editable parsed rows, SKU suggested per row, unresolved rows blocked, approve into an estimate. |
| Admin | Cutting rates (including the `rate_unit` switch), cut parameters, weekday capacity — new quotes only. |
| Order preview | The NetSuite-style payload, rendered next to the client's sample. Never sent. |

## What is here

| Path | What it is |
|---|---|
| `app/Services/Cutting/` | The estimation engine. Placement policy documented in [its README](app/Services/Cutting/README.md) — that is the document to compare with the client's costing workbook. |
| `app/Services/Quoting/` | Pricing, snapshot freezing, cut-queue scheduling. |
| `app/Services/Intake/` | Cut-list parsing: Anthropic client, offline fixtures, SKU matcher. |
| `app/Services/Ordering/` | The NetSuite payload builder. |
| `tests/Unit/Cutting/` | Full unit coverage of the engine: kerf, trim, rotation, overflow, validation, guillotine invariants, determinism, Optimized-never-worse. |
| `tests/Fixtures/` | Shared cut lists (including the PS-1 drawing pack BOM) and the golden output snapshot. |
| `DEMO-SCRIPT.md` | The 10-minute click-path for the demo. |

## Demo mode and the one external call

The only outbound call in the whole demo is cut-list parsing on the AI intake
screen. With `DEMO_OFFLINE=true` (the default) or an empty `ANTHROPIC_API_KEY`,
canned fixture parses are used and the demo needs no network at all.

## Client-facing assumptions

1. **The four edge-trim cuts count as billable cut length** by default. The
   engine reports trim and piece cut length separately and the toggle lives in
   `cut_parameters`.
2. **The cutting rate unit is unconfirmed**, so `cutting_rates.rate_unit` is an
   enum (`per_cut_metre` | `per_piece` | `per_sheet`) switchable in admin.
3. **Kerf is consumed between neighbouring pieces and between shelves**, not
   against the trimmed sheet edge — the trim cut already removed that material.
4. **The client's NetSuite sample payload is a placeholder.** The Phase 1 PDF is
   encrypted and could not be read, so
   `resources/fixtures/netsuite-sample-payload.json` was reconstructed from the
   brief's description. Drop the real sample into that file and the
   order-preview comparison updates itself.
