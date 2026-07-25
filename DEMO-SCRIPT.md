# CutToSize — 10 minute demo script

All six milestones are in. Everything below runs offline: `DEMO_OFFLINE=true`
means the AI intake screen uses canned fixture parses, so no network is needed
at any point.

## 0. Before the call — 1 min

```bash
docker compose up -d --build
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan test
```

Open http://localhost:8080 and sign in — the credentials are pre-filled
(`demo@cuttosize.test` / `password`).

## 1. The engine is real, and it is tested — 2 min

`docker compose exec app php artisan test` — point at three things:

- **determinism** — same input, byte-identical serialized output over three
  runs, plus a golden snapshot that fails the build on any drift;
- **Optimized never worse** — asserted across every fixture cut list, because
  the fixed-orientation packing is always one of the ranked candidates;
- **validation, not silent fixes** — an oversized piece produces a structured
  error naming the piece.

Optional, in tinker:

```bash
docker compose exec app php artisan tinker
```

```php
use App\Services\Cutting\{Estimator, CutConfig, Sheet, Piece, EstimatorMode};

$config = new CutConfig(kerfMm: 4.4, trimMm: 10.0, rotationAllowed: true);
$sheet  = new Sheet(2440, 1220);
$pieces = [new Piece(1150, 700, 3, 'PANEL')];

(new Estimator($config))->estimate($sheet, $pieces, EstimatorMode::FixedOrientation)->sheetsConsumed; // 2
(new Estimator($config))->estimate($sheet, $pieces, EstimatorMode::Optimized)->sheetsConsumed;        // 1
```

## 2. Fixture A — the clean BOM — 2 min

**AI intake → "Fixture A — PS-1 drawing pack BOM" → Parse cut list.**

The review screen shows four rows with the customer's own wording, a
pre-selected SKU per row (`clear PC` → `PC6.0-000-84-XX`, `HDPE cable-duct
sheet` → the 5 mm HDPE), confidence, and the parser's warnings. Anything it
could not resolve is highlighted and blocks approval.

Fill in the customer, keep **Optimized**, then **Approve → create estimate**.
The rows land on the estimate screen grouped into one quote line per SKU.

## 3. Fixture B — the messy WhatsApp paste — 1 min

Back on **AI intake**, pick fixture B. Worth calling out on the review screen:

- `60x40cm` came back as 600 × 400 mm — the units were converted;
- `same material` on line 2 was resolved to the 3 mm opal white acrylic, and
  said so in a warning;
- the mirror row mapped to `AM3.0-SLV-84-MR`, which is **rotation locked** —
  the engine will not rotate those pieces even in Optimized mode.

## 4. The quote — the centrepiece — 3 min

Calculate the estimate. Per consumed sheet you get an SVG drawn to scale from
the engine's layout tree: shaded trim zone, labelled pieces with dimensions,
⟳ on rotated pieces, kerf drawn at its true 4.4 mm width, hatched offcuts.

Beside it: sheets consumed, total cut length (with the trim share broken out),
the price breakdown (material / cutting / VAT 5% / total AED), the promised
date from the cut queue and the validity date.

**Issue quote** freezes the snapshot, soft-allocates the stock for 48 hours and
drops the visible available-sheets counter.

## 5. Admin, and why the snapshot matters — 1 min

**Admin** → change the acrylic cutting rate to something absurd, and flip its
unit from *per cut metre* to *per sheet*. Then reopen the issued quote from the
"Snapshot check" list: **identical figures**, because an issued quote renders
only from its frozen snapshot. Start a new estimate and the new rate applies.

Same screen also has the kerf/trim/VAT/validity parameters and the weekday
cutting capacity that drives promised dates.

## 6. The order payload — 1 min

On the issued quote → **Order preview**. The NetSuite-style payload is rendered
side by side with the client's sample: `param[0].entity` with orderId,
customer, order date, `AED`, the sheet SKU lines, one `CUT-CHARGE` service
line, and the quote reference in `remarks`. Copy button included.

**Convert to order** books a cut job per line against weekday capacity and
marks the quote ordered. Nothing is ever sent anywhere.

## Questions this demo is designed to raise

1. Do the four edge-trim cuts count as billable cut length? (Toggle in admin —
   the engine reports trim and piece cut length separately either way.)
2. Is the cutting rate per cut metre, per piece, or per sheet? (Switchable per
   rate row.)
3. Does the placement policy in `app/Services/Cutting/README.md` match the
   costing workbook line for line?
