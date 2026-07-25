import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppLayout from '../../Layouts/AppLayout';

type Parameters = {
    kerf_mm: number;
    trim_mm: number;
    vat_pct: number;
    quote_validity_days: number;
    include_trim_in_cut_length: boolean;
};

type Rate = {
    id: number;
    material_group: string;
    thickness_mm: number;
    rate: number;
    rate_unit: 'per_cut_metre' | 'per_piece' | 'per_sheet';
};

type LeadTime = { id: number; weekday: number; weekday_name: string; capacity_cut_metres: number };
type Load = { date: string; capacity: number; booked: number; free: number };
type IssuedQuote = { id: number; reference: string; total_aed: number; issued_at: string | null };

const RATE_UNITS = [
    { value: 'per_cut_metre', label: 'per cut metre' },
    { value: 'per_piece', label: 'per piece' },
    { value: 'per_sheet', label: 'per sheet' },
] as const;

export default function AdminIndex({
    parameters,
    rates,
    leadTimes,
    materialGroups,
    load,
    issuedQuotes,
}: {
    parameters: Parameters;
    rates: Rate[];
    leadTimes: LeadTime[];
    materialGroups: string[];
    load: Load[];
    issuedQuotes: IssuedQuote[];
}) {
    const params = useForm({ ...parameters });
    const newRate = useForm({
        material_group: materialGroups[0] ?? '',
        thickness_mm: '',
        rate: '',
        rate_unit: 'per_cut_metre' as Rate['rate_unit'],
    });

    function saveParameters(event: FormEvent) {
        event.preventDefault();
        params.put('/admin/cut-parameters', { preserveScroll: true });
    }

    function saveRate(rate: Rate, patch: Partial<Rate>) {
        router.put(
            `/admin/cutting-rates/${rate.id}`,
            { rate: patch.rate ?? rate.rate, rate_unit: patch.rate_unit ?? rate.rate_unit },
            { preserveScroll: true },
        );
    }

    return (
        <AppLayout
            title="Admin"
            subtitle="Rates, cut parameters and shop-floor capacity. Changes apply to new quotes only — issued quotes keep their frozen snapshot."
        >
            <Head title="Admin" />

            <div className="grid gap-6 lg:grid-cols-2">
                <form onSubmit={saveParameters} className="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 className="text-sm font-semibold text-slate-900">Cut parameters</h2>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <Field label="Kerf (mm)">
                            <input
                                type="number"
                                step="0.1"
                                value={params.data.kerf_mm}
                                onChange={(e) => params.setData('kerf_mm', Number(e.target.value))}
                                className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                            />
                        </Field>
                        <Field label="Edge trim (mm)">
                            <input
                                type="number"
                                step="0.1"
                                value={params.data.trim_mm}
                                onChange={(e) => params.setData('trim_mm', Number(e.target.value))}
                                className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                            />
                        </Field>
                        <Field label="VAT %">
                            <input
                                type="number"
                                step="0.1"
                                value={params.data.vat_pct}
                                onChange={(e) => params.setData('vat_pct', Number(e.target.value))}
                                className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                            />
                        </Field>
                        <Field label="Quote validity (days)">
                            <input
                                type="number"
                                value={params.data.quote_validity_days}
                                onChange={(e) => params.setData('quote_validity_days', Number(e.target.value))}
                                className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                            />
                        </Field>
                    </div>

                    <label className="mt-4 flex items-start gap-2 text-sm text-slate-700">
                        <input
                            type="checkbox"
                            checked={params.data.include_trim_in_cut_length}
                            onChange={(e) => params.setData('include_trim_in_cut_length', e.target.checked)}
                            className="mt-0.5"
                        />
                        <span>
                            Charge the four edge-trim cuts as cut length
                            <span className="block text-xs text-slate-400">
                                Pending client confirmation — it moves the cutting charge.
                            </span>
                        </span>
                    </label>

                    <button
                        type="submit"
                        disabled={params.processing}
                        className="mt-5 rounded-md bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800 disabled:opacity-60"
                    >
                        Save parameters
                    </button>
                </form>

                <div className="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 className="text-sm font-semibold text-slate-900">Cutting capacity</h2>
                    <table className="mt-4 w-full text-sm">
                        <tbody className="divide-y divide-slate-100">
                            {leadTimes.map((rule) => (
                                <tr key={rule.id}>
                                    <td className="py-2 text-slate-600">{rule.weekday_name}</td>
                                    <td className="py-2 text-right">
                                        <input
                                            type="number"
                                            defaultValue={rule.capacity_cut_metres}
                                            onBlur={(e) =>
                                                router.put(
                                                    `/admin/lead-time-rules/${rule.id}`,
                                                    { capacity_cut_metres: Number(e.target.value) },
                                                    { preserveScroll: true },
                                                )
                                            }
                                            className="w-28 rounded-md border border-slate-300 px-2 py-1.5 text-right"
                                        />
                                        <span className="ml-2 text-xs text-slate-400">cut m</span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    <h3 className="mt-5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Next 10 days
                    </h3>
                    <ul className="mt-2 space-y-1 text-xs text-slate-600">
                        {load.map((day) => (
                            <li key={day.date} className="flex justify-between">
                                <span>{day.date}</span>
                                <span className="tabular-nums">
                                    {day.booked} / {day.capacity} booked
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>

            <div className="mt-6 rounded-xl border border-slate-200 bg-white p-5">
                <h2 className="text-sm font-semibold text-slate-900">Cutting rates</h2>
                <p className="mt-1 text-xs text-slate-500">
                    The client has not confirmed the unit, so every rate carries its own.
                </p>

                <table className="mt-4 w-full text-sm">
                    <thead className="text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th className="pb-2 font-medium">Group</th>
                            <th className="pb-2 font-medium">Thickness</th>
                            <th className="pb-2 font-medium">Rate (AED)</th>
                            <th className="pb-2 font-medium">Unit</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {rates.map((rate) => (
                            <tr key={rate.id}>
                                <td className="py-2 text-slate-700">{rate.material_group}</td>
                                <td className="py-2 text-slate-500">{rate.thickness_mm} mm</td>
                                <td className="py-2">
                                    <input
                                        type="number"
                                        step="0.5"
                                        defaultValue={rate.rate}
                                        onBlur={(e) => saveRate(rate, { rate: Number(e.target.value) })}
                                        className="w-28 rounded-md border border-slate-300 px-2 py-1.5"
                                    />
                                </td>
                                <td className="py-2">
                                    <select
                                        value={rate.rate_unit}
                                        onChange={(e) =>
                                            saveRate(rate, { rate_unit: e.target.value as Rate['rate_unit'] })
                                        }
                                        className="rounded-md border border-slate-300 px-2 py-1.5"
                                    >
                                        {RATE_UNITS.map((unit) => (
                                            <option key={unit.value} value={unit.value}>
                                                {unit.label}
                                            </option>
                                        ))}
                                    </select>
                                </td>
                                <td className="py-2 text-right">
                                    <button
                                        type="button"
                                        onClick={() =>
                                            router.delete(`/admin/cutting-rates/${rate.id}`, {
                                                preserveScroll: true,
                                            })
                                        }
                                        className="text-slate-400 hover:text-red-600"
                                    >
                                        ×
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        newRate.post('/admin/cutting-rates', {
                            preserveScroll: true,
                            onSuccess: () => newRate.reset('thickness_mm', 'rate'),
                        });
                    }}
                    className="mt-5 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4"
                >
                    <Field label="Group">
                        <select
                            value={newRate.data.material_group}
                            onChange={(e) => newRate.setData('material_group', e.target.value)}
                            className="rounded-md border border-slate-300 px-2 py-1.5 text-sm"
                        >
                            {materialGroups.map((group) => (
                                <option key={group} value={group}>
                                    {group}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Thickness (mm)">
                        <input
                            type="number"
                            step="0.1"
                            value={newRate.data.thickness_mm}
                            onChange={(e) => newRate.setData('thickness_mm', e.target.value)}
                            className="w-28 rounded-md border border-slate-300 px-2 py-1.5 text-sm"
                        />
                    </Field>
                    <Field label="Rate (AED)">
                        <input
                            type="number"
                            step="0.5"
                            value={newRate.data.rate}
                            onChange={(e) => newRate.setData('rate', e.target.value)}
                            className="w-28 rounded-md border border-slate-300 px-2 py-1.5 text-sm"
                        />
                    </Field>
                    <Field label="Unit">
                        <select
                            value={newRate.data.rate_unit}
                            onChange={(e) => newRate.setData('rate_unit', e.target.value as Rate['rate_unit'])}
                            className="rounded-md border border-slate-300 px-2 py-1.5 text-sm"
                        >
                            {RATE_UNITS.map((unit) => (
                                <option key={unit.value} value={unit.value}>
                                    {unit.label}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <button
                        type="submit"
                        className="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        Add rate
                    </button>
                </form>
            </div>

            {issuedQuotes.length > 0 && (
                <div className="mt-6 rounded-xl border border-slate-200 bg-white p-5">
                    <h2 className="text-sm font-semibold text-slate-900">Snapshot check</h2>
                    <p className="mt-1 text-xs text-slate-500">
                        Change a rate above, then reopen an issued quote — the figures are unchanged, because it
                        renders from its frozen snapshot.
                    </p>
                    <ul className="mt-3 space-y-1 text-sm">
                        {issuedQuotes.map((quote) => (
                            <li key={quote.id} className="flex justify-between gap-3">
                                <Link href={`/quotes/${quote.id}`} className="text-teal-700 hover:underline">
                                    {quote.reference}
                                </Link>
                                <span className="tabular-nums text-slate-500">
                                    AED {quote.total_aed.toFixed(2)} · issued {quote.issued_at}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </AppLayout>
    );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <label className="block">
            <span className="block text-xs font-medium uppercase tracking-wide text-slate-500">{label}</span>
            <span className="mt-1 block">{children}</span>
        </label>
    );
}
