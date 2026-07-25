# Cutting engine — placement policy

This document describes exactly what the engine does, so it can be compared
line by line with the client's costing workbook. Everything here is
deterministic: no randomness, no time input, no dependence on the order rows
were typed in.

## Inputs

```php
$config = new CutConfig(
    kerfMm: 4.4,              // consumed by every cut
    trimMm: 10.0,             // per-edge; trimTopMm/RightMm/BottomMm/LeftMm override it
    rotationAllowed: false,   // material property (mirror/brushed stock is directional)
    includeTrimInCutLength: true,
    minOffcutMm: 1.0,         // smaller leftovers are dust, not offcuts
);

$result = (new Estimator($config))->estimate(
    new Sheet(2440, 1220),
    [new Piece(600, 400, 4, 'PANEL-A')],
    EstimatorMode::Optimized,   // or EstimatorMode::FixedOrientation
);
```

Coordinates are millimetres, origin top-left, y increasing downwards (SVG
convention), so the serialized layout renders without transformation.

## 1. Usable area

```
usable.x      = trimLeft
usable.y      = trimTop
usable.width  = sheetWidth  - trimLeft - trimRight
usable.height = sheetHeight - trimTop  - trimBottom
```

A piece that does not fit the usable area — in either orientation when
rotation is available — is a **validation error**
(`PieceExceedsSheetException`), never a silently dropped or shrunk row. The
exception names every offending piece and the usable area it overflows.

## 2. Ordering (total order, no ties)

Pieces are expanded by quantity into individual instances, then sorted:

1. area descending
2. longest side descending
3. width descending
4. height descending
5. label ascending
6. instance index ascending

Steps 5 and 6 exist so that two different input orderings of the same cut list
produce the same layout.

## 3. Placement — guillotine shelves, first-fit-decreasing

Pieces are packed into **shelves**: full-usable-width horizontal bands whose
height is the height of the first piece placed in them. Because a shelf spans
the full width of the remaining board and pieces inside a shelf span its full
height, every cut is edge-to-edge on the rectangle it divides — guillotine by
construction.

For each piece, in sorted order:

1. Walk the open sheets in order. On each sheet:
   - **Best fit into an open shelf**: among shelves tall enough for the piece
     and with enough trailing width, take the one that leaves the least
     trailing width. Ties break on the lowest shelf index, then on the
     unrotated candidate.
   - Otherwise **open a new shelf** below the last one, if the piece still fits
     the remaining height.
2. If no open sheet can host it, start a new sheet.

The piece is placed at `x = previous piece's right edge + kerf` (or
`usable.x` when it opens the shelf), `y = shelf top`. A new shelf starts at
`previous shelf bottom + kerf`.

## 4. Cut accounting

Every cut consumes one kerf. The material inside the kerf is gone: it is not an
offcut and it is not re-usable.

| Cut kind | When it is made | Length |
|---|---|---|
| `trim` | one per trimmed edge (4 by default) | the full sheet edge |
| `shelf` | releasing a shelf from the board below it — skipped when the shelf ends flush with the usable bottom | usable width |
| `rip` | separating a piece column inside a shelf — skipped when the piece ends flush with the usable right edge | shelf height |
| `size` | bringing a piece down to height inside its column — only when the piece is shorter than its shelf | piece width |

`totalCutLengthMm = pieceCutLength + (includeTrimInCutLength ? trimCutLength : 0)`

**Open question for the client:** whether the four edge-trim cuts are billable.
The engine reports `trim_cut_length_mm` and `piece_cut_length_mm` separately
and the toggle lives in `cut_parameters`, so either answer is one setting away.

## 5. Modes

- **FixedOrientation** — pieces exactly as entered. This is the mode that has
  to reproduce the client's workbook.
- **Optimized** — runs a fixed list of deterministic candidate strategies and
  keeps the best result:

  | rank | strategy | what it does |
  |---|---|---|
  | 0 | `as_given` | identical to FixedOrientation |
  | 1 | `prefer_landscape` | every piece normalised to width ≥ height |
  | 2 | `prefer_portrait` | every piece normalised to height ≥ width |
  | 3 | `best_fit_per_piece` | tries both orientations at each placement, keeps the tighter fit |

  Candidates are ranked by `(unplaceable count, sheets, total cut length, strategy rank)`.
  Since `as_given` is always a candidate, **Optimized can never be worse than
  FixedOrientation** — that is a structural guarantee, not a heuristic, and
  `OptimizedNeverWorseTest` asserts it across every fixture cut list.

  When the material has `rotation_allowed = false`, only `as_given` runs, so
  Optimized output is byte-identical to FixedOrientation.

## 6. Output

`EstimateResult::toArray()` is the serialization the UI renders and the quote
snapshot freezes:

- `sheets_consumed`, `pieces_placed`, `per_sheet_pieces`
- `trim_cut_length_mm`, `piece_cut_length_mm`, `total_cut_length_mm`, `total_cut_metres`
- `offcuts` (sheet index + rect), `total_offcut_area_mm2`
- `unplaceable_pieces`
- `layouts[]` — per sheet: usable rect, `placements` (x, y, w, h, label,
  `rotated`), `offcuts`, `cuts` (coordinates + kind + length), `yield_pct`, and
  the full `tree`: the guillotine cut tree, each node a rect plus the single
  cut that split it.

Floats are rounded to 0.01 mm on serialization, so a snapshot diff always means
a real behaviour change rather than float noise.

## 7. Tests

`tests/Unit/Cutting/` — kerf and trim accounting, rotation on/off, multi-sheet
overflow, degenerate inputs, structured validation errors, guillotine
invariants (no overlaps, cuts edge-to-edge, tree agrees with placements, exactly
one kerf between siblings), determinism (three runs, reordered input, hand
expanded quantities) and the Optimized-never-worse property.

The golden snapshot `tests/Fixtures/engine-snapshot-ps1.json` fails the build on
any output drift. Regenerate deliberately with:

```
docker compose exec app php artisan cutting:snapshot
```
