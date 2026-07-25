# PROMPT FOR CLAUDE CODE — Cut-to-Size Platform, Standalone Demo

Copy everything below this line into Claude Code. Fill in the two placeholders marked `<<...>>` first.

---

You are building a **standalone demo** of a cut-to-size polymer quoting platform for a UAE building-materials trader. Customers buy acrylic/polycarbonate/HDPE sheets cut to their dimensions. The demo runs entirely locally with **no external integrations** — the only outbound call is one LLM API call for parsing cut lists, and it must have an offline fallback.

This demo is Phase 1 scaffolding of a real project, not a throwaway: the estimation engine you build here will later be tested against the client's golden costing workbook, so build it clean.

## Stack (do not substitute)

- Laravel 13, PHP 8.5, MySQL, Inertia.js + React (TypeScript ok), Vite, Tailwind.
- **Fully containerized**: everything runs via Docker Compose — `app` (PHP-FPM 8.5), `nginx`, `mysql`, `node` (Vite dev server), plus a `queue`/`scheduler` container only if actually needed (it shouldn't be for this demo). Provide a `Dockerfile` and `docker-compose.yml`. No Makefile, no wrapper scripts — everything is driven with plain `docker compose` commands, and the README documents them exactly: `docker compose up -d --build`, `docker compose exec app php artisan migrate:fresh --seed`, `docker compose exec app php artisan test`, `docker compose exec app php artisan tinker`. The whole demo must boot from a clean clone with those commands and nothing installed on the host except Docker. `.env.example` complete; no host PHP/Node assumed.
- The estimation engine is a normal Laravel service layer, `app/Services/Cutting/` — use Laravel freely (Eloquent models, collections, config, DI). No purity constraint. What is mandatory: **full unit test coverage of the engine** (PHPUnit or Pest inside the container), including the determinism and never-worse tests described in Part 1. Structure the engine so tests can drive it directly with in-memory inputs (piece lists, sheet dims, parameters) without booting the whole HTTP stack.
- LLM: Anthropic API, model `claude-sonnet-4-6`, key in `.env` as `ANTHROPIC_API_KEY`.

## Part 1 — The estimation engine (build this first, TDD)

Laravel service layer under `App\Services\Cutting`. Public API roughly:

```php
$result = (new Estimator($config))->estimate(new Sheet($w, $h), $pieces, EstimatorMode::Optimized);
// $config: kerf, trimTop/Right/Bottom/Left, rotationAllowed (per material)
// $pieces: list of {width, height, qty, label}
// $result: SheetLayout[] (a layout tree per physical sheet), sheetsConsumed,
//          totalCutLengthMm, perSheetPieces, offcuts, unplaceablePieces
```

Rules:
- **Guillotine nesting only**: every cut is edge-to-edge on the current sub-rectangle (build layouts as a cut tree: each node = one straight cut splitting a rectangle in two). No non-guillotine placements.
- **Kerf 4.4 mm** consumed by every cut. **Edge trim 10 mm** on all four sheet sides (both configurable via `$config` — these defaults may change).
- Two modes on the same engine, switched by a flag:
  - `FixedOrientation`: pieces placed as given, no rotation. Deterministic. (This mode will later have to reproduce the client's workbook exactly — determinism is non-negotiable: same input, same output, always. No randomness, no time-dependent behavior.)
  - `Optimized`: may rotate individual pieces 90° where `rotationAllowed` is true; must never use more sheets than FixedOrientation for the same input, and if sheets are equal, never more cut length.
- Algorithm suggestion: sort pieces by area desc, first-fit-decreasing into guillotine shelves/strips with best-fit sub-rectangle selection; keep it simple and correct over clever. Document the placement policy in `app/Services/Cutting/README.md` so it can be compared to the client's workbook later.
- Outputs must include a **serializable layout tree** (rects with x, y, w, h, piece label, rotated flag, plus offcut rects and cut segments with coordinates) — the frontend renders this as SVG.
- Validation errors, not silent fixes: piece larger than usable sheet area → structured error naming the piece.
- Tests: kerf accounting, trim accounting, rotation on/off, multi-sheet overflow, degenerate inputs (0 qty, piece == full usable sheet), determinism (same input twice → identical serialized output), Optimized-never-worse property across a set of fixture cut lists.

## Part 2 — App and data model

Migrations + models + seeders for (subset of the real schema, keep names):
- `materials` — sku, name, brand, thickness_mm, sheet_w_mm, sheet_h_mm, color_code, color_name, selling_price_aed, stock_qty, rotation_allowed, is_cut_eligible
- `cutting_rates` — material_group, thickness_mm, rate (leave the **unit** as an enum column `rate_unit` with values per_cut_metre|per_piece|per_sheet, default per_cut_metre — the client hasn't confirmed the unit yet, so make it switchable in admin)
- `cut_parameters` — singleton: kerf_mm (4.4), trim_mm (10), vat_pct (5), quote_validity_days (7)
- `lead_time_rules` — weekday, capacity_cut_metres (default 400 Mon–Fri, 0 Sat/Sun)
- `quotes`, `quote_lines` — quote_lines stores a **frozen JSON snapshot**: full engine result + all rates/prices/parameters used. Displaying an issued quote reads ONLY the snapshot, never live tables.
- `cut_jobs` — one per ordered quote line: cut_metres, scheduled_date (fills weekday capacity from lead_time_rules, first free day wins)
- `soft_allocations` — material_id, qty_sheets, quote_id, expires_at
- `intake_submissions` — raw_input (text), source_type, parse_result JSON, status (parsed|reviewed|quoted)

Seed data (invent plausibly, follow the client's SKU pattern `<material><thickness>-<color>-<size>-<supplier>`):
- ~12 materials: e.g. `AS3.0-430-84-XX` Acrylic Cast 3.0mm Opal White 2440×1220 @ ~180 AED, `AS5.0-430-84-XX`, `AS2.8-2016-84-YL` Dark Yellow (matches their sample payload), a 6.0mm clear polycarbonate (`PC6.0-000-84-XX`) sized 2440×1220 — needed for the demo drawing, an HDPE 5.0mm 2440×1220, and **one mirror acrylic with `rotation_allowed = false`** to demo directional material.
- Cutting rates per group/thickness (e.g. 8–18 AED per cut-metre, thicker = pricier).
- Availability = `stock_qty − active soft_allocations` via a `availableSheets()` accessor.

## Part 3 — Screens (Inertia + React)

Keep the UI clean and fast; Tailwind, no component-library bloat. Screens:

1. **New estimate** — pick material, enter cut list rows (w, h, qty) manually, mode toggle (fixed/optimized), submit → quote preview.
2. **Quote view (the centerpiece)** — for each consumed sheet render the layout tree as an **SVG drawn to scale**: sheet outline, trim zone shaded, pieces labeled with dims, rotated pieces marked ⟳, kerf lines visible, offcuts hatched. Beside it: sheets consumed, total cut length, price breakdown table (material = sheets × selling price; cutting = engine cut output × rate; VAT 5%; total AED), promised delivery date from cut queue capacity, validity date. Button: **Issue quote** → freezes snapshot, creates soft allocation (expires_at = now + 48h), decrements the visible available-sheets counter.
3. **AI intake** — a textarea ("paste your cut list — any format") + file upload (txt/csv/html). Calls the Anthropic API with a system prompt that extracts `{pieces: [{material_hint, thickness_mm, width_mm, height_mm, qty, notes}], confidence, warnings}` as **strict JSON** (use a tool/JSON schema, temperature 0). Store in intake_submissions. **Offline fallback**: if the API call fails OR a `DEMO_OFFLINE=true` env flag is set, return a canned parse result for the two rehearsal inputs (fixtures below).
4. **Review screen** — parsed rows in an editable table; each row gets a material dropdown (map hint → real SKU, pre-select best guess by thickness + name match); unresolved rows highlighted; "Approve → create estimate" carries the rows into screen 1's flow.
5. **Admin** — CRUD for cutting_rates (incl. rate_unit switch), cut_parameters, lead_time_rules. Changing anything affects only NEW quotes — demo this by re-opening an issued quote (snapshot unchanged).
6. **Order preview** — on an issued quote, "Convert to order" renders (does NOT send) the NetSuite-style JSON payload: same shape as the client's sample — `param[0].entity` with orderId, customer, order_date, currency "AED", items array of sheet SKU lines (item_code, description, qty, rate, tax_rate) **plus one service line** `{"item_code": "CUT-CHARGE", "description": "Cut-to-size service", "qty": 1, "rate": <cutting charge>}`, and the quote reference in `remarks`. Show it pretty-printed with a copy button, side by side with a static copy of their sample payload for comparison.

## Part 4 — Demo fixtures (seed these as one-click examples on the intake screen)

Fixture A — clean BOM (from the client's PS-1 drawing pack), paste text:
```
PS1-PNL-CT-1212  Ceiling tile 1200x1200x6 mm clear PC   qty 4
PS1-PNL-IF-1220  Infill panel 1200x200x6 mm clear PC    qty 4
PS1-PNL-EC-200   End-of-row infill 1200x200x6 clear PC  qty 2
HDPE cable-duct sheet 1000x600x5 mm                      qty 6
```
Expected mapping: PC pieces → the 6.0mm polycarbonate SKU, HDPE → the 5mm HDPE SKU.

Fixture B — messy WhatsApp-style paste:
```
hi need cutting: 6 pcs 60x40cm 3mm opal white acrylic, also 2 pcs 120 x 80 same material,
and if u have mirror silver 3mm need 4 pieces 50by50 thanks
```
Expected: cm→mm conversion, 3mm opal → AS3.0-430-84-XX, mirror → the mirror SKU (rotation locked).

Cache the correct parse JSON for both fixtures as the offline fallback.

## Working method

- Work milestone by milestone in this order; after each, run the tests inside the container (`docker compose exec app php artisan test`) and stop to show me: **(1)** Docker environment boots clean + engine service green tests → **(2)** migrations/seeders + a tinker-verifiable quote calculation → **(3)** estimate + quote screens with SVG → **(4)** intake + review → **(5)** admin + snapshot-freeze demo → **(6)** order payload preview.
- Engine work is TDD: write the failing test first.
- Deterministic engine output is a hard requirement — add a test that snapshots serialized output for a fixture and fails on any diff.
- No auth needed beyond a trivial single-user login. No queues, no schedulers, no retry logic, no real HTTP to any storefront/ERP. Do not build a catalog sync.
- Keep a `DEMO-SCRIPT.md` at repo root: the 10-minute click-path through fixtures A and B, updated as screens land.

Start with milestone 1: the Docker Compose environment (verify `docker compose up -d --build` followed by `docker compose exec app php artisan migrate:fresh --seed` boots to the Laravel welcome page), then the `App\Services\Cutting` engine with its first failing test (single piece on a single sheet, fixed orientation, kerf + trim accounting).
