import type { SheetLayout } from '../types';

const COLORS = {
    sheet: '#ffffff',
    sheetEdge: '#0f172a',
    trim: '#fde68a',
    piece: '#ccfbf1',
    pieceEdge: '#0f766e',
    kerf: '#dc2626',
    trimCut: '#b45309',
    text: '#0f172a',
};

/**
 * The quote centrepiece: one physical sheet drawn to scale straight from the
 * engine's layout tree output. Nothing here recomputes geometry — every rect
 * and every cut line comes from the estimator.
 */
export default function SheetSvg({
    layout,
    kerfMm,
    showCuts = true,
}: {
    layout: SheetLayout;
    kerfMm: number;
    showCuts?: boolean;
}) {
    const { width_mm: W, height_mm: H } = layout.sheet;
    const pad = Math.max(W, H) * 0.055;
    const font = Math.max(W, H) * 0.013;
    const hatchId = `hatch-${layout.index}`;

    return (
        <svg
            viewBox={`${-pad} ${-pad} ${W + pad * 2} ${H + pad * 2}`}
            className="w-full rounded-lg border border-slate-200 bg-white"
            role="img"
            aria-label={`Sheet ${layout.index + 1} cutting layout`}
        >
            <defs>
                <pattern id={hatchId} width="26" height="26" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
                    <rect width="26" height="26" fill="#f1f5f9" />
                    <line x1="0" y1="0" x2="0" y2="26" stroke="#94a3b8" strokeWidth="6" />
                </pattern>
            </defs>

            {/* Sheet, with the trim band showing through around the usable area. */}
            <rect x={0} y={0} width={W} height={H} fill={COLORS.trim} fillOpacity={0.55} />
            <rect
                x={layout.usable.x}
                y={layout.usable.y}
                width={layout.usable.w}
                height={layout.usable.h}
                fill={COLORS.sheet}
            />
            <rect x={0} y={0} width={W} height={H} fill="none" stroke={COLORS.sheetEdge} strokeWidth={W * 0.0012} />

            {/* Offcuts: usable leftovers big enough to keep. */}
            {layout.offcuts.map((offcut, i) => (
                <rect
                    key={`offcut-${i}`}
                    x={offcut.x}
                    y={offcut.y}
                    width={offcut.w}
                    height={offcut.h}
                    fill={`url(#${hatchId})`}
                    stroke="#cbd5e1"
                    strokeWidth={W * 0.0006}
                />
            ))}

            {/* Kerf: cut lines drawn at true kerf width, so the material the
                blade eats is visible rather than implied. */}
            {showCuts &&
                layout.cuts.map((cut, i) => (
                    <line
                        key={`cut-${i}`}
                        x1={cut.x1}
                        y1={cut.y1}
                        x2={cut.x2}
                        y2={cut.y2}
                        stroke={cut.kind === 'trim' ? COLORS.trimCut : COLORS.kerf}
                        strokeWidth={kerfMm}
                        strokeOpacity={cut.kind === 'trim' ? 0.5 : 0.75}
                        strokeDasharray={cut.kind === 'trim' ? `${kerfMm * 5} ${kerfMm * 3}` : undefined}
                    />
                ))}

            {layout.placements.map((piece, i) => {
                const cx = piece.x + piece.w / 2;
                const cy = piece.y + piece.h / 2;
                const fits = piece.w > font * 8 && piece.h > font * 3;

                return (
                    <g key={`piece-${i}`}>
                        <rect
                            x={piece.x}
                            y={piece.y}
                            width={piece.w}
                            height={piece.h}
                            fill={COLORS.piece}
                            stroke={COLORS.pieceEdge}
                            strokeWidth={W * 0.0009}
                        />
                        {fits && (
                            <>
                                <text
                                    x={cx}
                                    y={cy - font * 0.35}
                                    textAnchor="middle"
                                    fontSize={font}
                                    fontWeight={600}
                                    fill={COLORS.text}
                                >
                                    {piece.label}
                                    {piece.rotated ? ' ⟳' : ''}
                                </text>
                                <text x={cx} y={cy + font} textAnchor="middle" fontSize={font * 0.85} fill="#475569">
                                    {piece.w} × {piece.h} mm
                                </text>
                            </>
                        )}
                        {!fits && (
                            <title>
                                {piece.label} {piece.w} × {piece.h} mm{piece.rotated ? ' (rotated)' : ''}
                            </title>
                        )}
                    </g>
                );
            })}

            {/* Sheet dimensions. */}
            <text x={W / 2} y={-pad * 0.3} textAnchor="middle" fontSize={font} fill="#64748b">
                {W} mm
            </text>
            <text
                x={-pad * 0.3}
                y={H / 2}
                textAnchor="middle"
                fontSize={font}
                fill="#64748b"
                transform={`rotate(-90 ${-pad * 0.3} ${H / 2})`}
            >
                {H} mm
            </text>
            <text x={0} y={H + pad * 0.7} fontSize={font} fill="#64748b">
                Sheet {layout.index + 1} · {layout.placements.length} pieces · {layout.yield_pct}% yield
            </text>
        </svg>
    );
}

export function SvgLegend({ kerfMm, trimMm }: { kerfMm: number; trimMm: number }) {
    const items = [
        { color: COLORS.piece, border: COLORS.pieceEdge, label: 'Piece' },
        { color: '#e2e8f0', border: '#94a3b8', label: 'Offcut (hatched)' },
        { color: COLORS.trim, border: '#d97706', label: `Trim zone ${trimMm} mm` },
        { color: COLORS.kerf, border: COLORS.kerf, label: `Kerf ${kerfMm} mm` },
    ];

    return (
        <div className="flex flex-wrap items-center gap-4 text-xs text-slate-600">
            {items.map((item) => (
                <span key={item.label} className="flex items-center gap-1.5">
                    <span
                        className="inline-block h-3 w-3 rounded-sm"
                        style={{ backgroundColor: item.color, border: `1px solid ${item.border}` }}
                    />
                    {item.label}
                </span>
            ))}
            <span className="flex items-center gap-1.5">⟳ rotated piece</span>
        </div>
    );
}
