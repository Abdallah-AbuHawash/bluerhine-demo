import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import SheetSvg, { SvgLegend } from '../../Components/SheetSvg';
import type { Quote, QuoteLine } from '../../types';

type Availability = Record<number, { sku: string; stock_qty: number; available_sheets: number }>;

const aed = (value: number) => `AED ${value.toLocaleString('en-AE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const STATUS_STYLES: Record<string, string> = {
    draft: 'bg-slate-100 text-slate-700',
    issued: 'bg-teal-100 text-teal-800',
    ordered: 'bg-indigo-100 text-indigo-800',
};

export default function QuoteShow({
    quote,
    lines,
    availability,
}: {
    quote: Quote;
    lines: QuoteLine[];
    availability: Availability;
}) {
    const issued = quote.status !== 'draft';

    return (
        <AppLayout
            title={`${quote.reference} — ${quote.customer_name}`}
            subtitle={
                issued
                    ? 'Issued quote. Everything below is read from the frozen snapshot, not from live rates.'
                    : 'Draft. Issuing freezes these numbers and soft-allocates the stock for 48 hours.'
            }
        >
            <Head title={quote.reference} />

            <div className="mb-6 flex flex-wrap items-center gap-3">
                <span className={`rounded-full px-3 py-1 text-xs font-medium ${STATUS_STYLES[quote.status]}`}>
                    {quote.status}
                </span>
                {quote.customer_reference && (
                    <span className="text-sm text-slate-500">Customer ref {quote.customer_reference}</span>
                )}
                <div className="ml-auto flex gap-3">
                    {!issued && (
                        <button
                            type="button"
                            onClick={() => router.post(`/quotes/${quote.id}/issue`)}
                            className="rounded-md bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800"
                        >
                            Issue quote
                        </button>
                    )}
                    {issued && (
                        <Link
                            href={`/quotes/${quote.id}/order`}
                            className="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Order preview
                        </Link>
                    )}
                </div>
            </div>

            {lines.map((line) => {
                const snap = line.snapshot;
                const engine = snap.engine;
                const stock = availability[line.material_id];

                return (
                    <section key={line.id} className="mb-10 grid gap-6 lg:grid-cols-[1fr_22rem]">
                        <div className="space-y-4">
                            <div className="rounded-xl border border-slate-200 bg-white p-4">
                                <h2 className="text-sm font-semibold text-slate-900">
                                    {snap.material.sku} — {snap.material.name}
                                </h2>
                                <p className="mt-1 text-xs text-slate-500">
                                    Sheet {snap.material.sheet_w_mm}×{snap.material.sheet_h_mm} mm ·{' '}
                                    {snap.mode === 'fixed' ? 'Fixed orientation' : `Optimized (${engine.strategy})`} ·
                                    kerf {snap.parameters.kerf_mm} mm · trim {snap.parameters.trim_mm} mm
                                </p>
                                <div className="mt-3">
                                    <SvgLegend kerfMm={snap.parameters.kerf_mm} trimMm={snap.parameters.trim_mm} />
                                </div>
                            </div>

                            {engine.layouts.map((layout) => (
                                <SheetSvg key={layout.index} layout={layout} kerfMm={snap.parameters.kerf_mm} />
                            ))}

                            {engine.unplaceable_pieces.length > 0 && (
                                <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                                    Unplaceable:{' '}
                                    {engine.unplaceable_pieces
                                        .map((p) => `${p.label} (${p.width_mm}×${p.height_mm})`)
                                        .join(', ')}
                                </div>
                            )}
                        </div>

                        <aside className="space-y-4">
                            <div className="rounded-xl border border-slate-200 bg-white p-4">
                                <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-500">Nesting</h3>
                                <dl className="mt-3 space-y-2 text-sm">
                                    <Stat label="Sheets consumed" value={String(engine.sheets_consumed)} />
                                    <Stat label="Pieces placed" value={String(engine.pieces_placed)} />
                                    <Stat label="Total cut length" value={`${engine.total_cut_metres} m`} />
                                    <Stat
                                        label="of which trim"
                                        value={`${(engine.trim_cut_length_mm / 1000).toFixed(3)} m`}
                                    />
                                    <Stat
                                        label="Offcut area"
                                        value={`${(engine.total_offcut_area_mm2 / 1_000_000).toFixed(2)} m²`}
                                    />
                                    {stock && (
                                        <Stat
                                            label="Sheets available"
                                            value={`${stock.available_sheets} of ${stock.stock_qty}`}
                                        />
                                    )}
                                </dl>
                            </div>

                            <div className="rounded-xl border border-slate-200 bg-white p-4">
                                <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-500">Price</h3>
                                <table className="mt-3 w-full text-sm">
                                    <tbody className="divide-y divide-slate-100">
                                        <tr>
                                            <td className="py-2 text-slate-600">
                                                Material
                                                <span className="block text-xs text-slate-400">
                                                    {snap.pricing.sheets} sheets × {aed(snap.pricing.sheet_price_aed)}
                                                </span>
                                            </td>
                                            <td className="py-2 text-right tabular-nums">
                                                {aed(snap.pricing.material_total_aed)}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td className="py-2 text-slate-600">
                                                Cutting
                                                <span className="block text-xs text-slate-400">
                                                    {snap.pricing.cutting_basis}
                                                </span>
                                            </td>
                                            <td className="py-2 text-right tabular-nums">
                                                {aed(snap.pricing.cutting_total_aed)}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td className="py-2 text-slate-600">Subtotal</td>
                                            <td className="py-2 text-right tabular-nums">
                                                {aed(snap.pricing.subtotal_aed)}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td className="py-2 text-slate-600">VAT {snap.pricing.vat_pct}%</td>
                                            <td className="py-2 text-right tabular-nums">{aed(snap.pricing.vat_aed)}</td>
                                        </tr>
                                        <tr className="font-semibold">
                                            <td className="py-2">Line total</td>
                                            <td className="py-2 text-right tabular-nums">
                                                {aed(snap.pricing.total_aed)}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div className="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                                <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Delivery
                                </h3>
                                <dl className="mt-3 space-y-2">
                                    <Stat label="Promised date" value={quote.promised_date ?? '—'} />
                                    <Stat label="Quote valid until" value={quote.valid_until ?? '—'} />
                                    <Stat label="Cutting rate" value={snap.cutting_rate.label} />
                                    {line.cut_jobs.length > 0 && (
                                        <Stat
                                            label="Cut job scheduled"
                                            value={line.cut_jobs.map((job) => job.scheduled_date).join(', ')}
                                        />
                                    )}
                                </dl>
                            </div>

                            <div className="rounded-xl border border-slate-200 bg-white p-4">
                                <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Cut list
                                </h3>
                                <ul className="mt-3 space-y-1 text-sm text-slate-600">
                                    {snap.rows.map((row, i) => (
                                        <li key={i} className="flex justify-between gap-3">
                                            <span className="truncate">{row.label}</span>
                                            <span className="tabular-nums text-slate-500">
                                                {row.width_mm}×{row.height_mm} × {row.qty}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </aside>
                    </section>
                );
            })}

            <div className="rounded-xl border border-slate-200 bg-white p-5">
                <table className="w-full text-sm">
                    <tbody className="divide-y divide-slate-100">
                        <tr>
                            <td className="py-2 text-slate-600">Material total</td>
                            <td className="py-2 text-right tabular-nums">{aed(quote.material_total_aed)}</td>
                        </tr>
                        <tr>
                            <td className="py-2 text-slate-600">Cutting total</td>
                            <td className="py-2 text-right tabular-nums">{aed(quote.cutting_total_aed)}</td>
                        </tr>
                        <tr>
                            <td className="py-2 text-slate-600">VAT {quote.vat_pct}%</td>
                            <td className="py-2 text-right tabular-nums">{aed(quote.vat_aed)}</td>
                        </tr>
                        <tr className="text-base font-semibold">
                            <td className="py-2">Quote total</td>
                            <td className="py-2 text-right tabular-nums">{aed(quote.total_aed)}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}

function Stat({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between gap-3">
            <dt className="text-slate-500">{label}</dt>
            <dd className="tabular-nums text-slate-900">{value}</dd>
        </div>
    );
}
