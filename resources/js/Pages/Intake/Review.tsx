import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import type { MaterialOption } from '../../types';

type Row = {
    material_hint: string | null;
    thickness_mm: number | null;
    width_mm: number | null;
    height_mm: number | null;
    qty: number;
    notes: string | null;
    suggested_material_id: number | null;
    material_id: number | null;
};

type Submission = {
    id: number;
    raw_input: string;
    status: string;
    confidence: number | null;
    offline_fallback: boolean;
    source: string;
    warnings: string[];
};

export default function IntakeReview({
    submission,
    rows,
    materials,
}: {
    submission: Submission;
    rows: Row[];
    materials: MaterialOption[];
}) {
    const form = useForm({
        customer_name: '',
        customer_reference: '',
        mode: 'optimized' as 'fixed' | 'optimized',
        rows: rows.map((row, index) => ({
            ...row,
            label: row.notes?.slice(0, 60) || `Row ${index + 1}`,
        })),
    });

    const unresolved = form.data.rows.filter((row) => !row.material_id).length;

    function updateRow(index: number, patch: Partial<(typeof form.data.rows)[number]>) {
        form.setData(
            'rows',
            form.data.rows.map((row, i) => (i === index ? { ...row, ...patch } : row)),
        );
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(`/intake/${submission.id}/approve`);
    }

    return (
        <AppLayout
            title={`Review parsed cut list #${submission.id}`}
            subtitle="Map each row to a real SKU, fix anything the parser guessed, then carry it into an estimate."
        >
            <Head title="Review cut list" />

            <div className="mb-6 flex flex-wrap items-center gap-3 text-sm">
                <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">
                    {submission.source === 'api' ? 'parsed by Anthropic' : 'offline fixture parse'}
                </span>
                {submission.confidence !== null && (
                    <span className="text-slate-500">
                        confidence {(submission.confidence * 100).toFixed(0)}%
                    </span>
                )}
                {unresolved > 0 && (
                    <span className="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-800">
                        {unresolved} row{unresolved > 1 ? 's' : ''} unresolved
                    </span>
                )}
            </div>

            {submission.warnings.length > 0 && (
                <ul className="mb-6 space-y-1 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    {submission.warnings.map((warning, i) => (
                        <li key={i}>• {warning}</li>
                    ))}
                </ul>
            )}

            <form onSubmit={submit} className="space-y-6">
                <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th className="px-3 py-3 font-medium">Customer wording</th>
                                <th className="px-3 py-3 font-medium">Material</th>
                                <th className="px-3 py-3 font-medium">Label</th>
                                <th className="px-3 py-3 font-medium">Width</th>
                                <th className="px-3 py-3 font-medium">Height</th>
                                <th className="px-3 py-3 font-medium">Qty</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {form.data.rows.map((row, index) => (
                                <tr key={index} className={row.material_id ? '' : 'bg-amber-50/60'}>
                                    <td className="px-3 py-2 align-top">
                                        <div className="text-slate-700">{row.material_hint ?? '—'}</div>
                                        <div className="text-xs text-slate-400">
                                            {row.thickness_mm ? `${row.thickness_mm} mm · ` : ''}
                                            {row.notes}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2">
                                        <select
                                            value={row.material_id ?? ''}
                                            onChange={(e) =>
                                                updateRow(index, {
                                                    material_id: e.target.value ? Number(e.target.value) : null,
                                                })
                                            }
                                            className="w-56 rounded-md border border-slate-300 px-2 py-1.5"
                                        >
                                            <option value="">— pick a SKU —</option>
                                            {materials.map((material) => (
                                                <option key={material.id} value={material.id}>
                                                    {material.sku} — {material.name}
                                                </option>
                                            ))}
                                        </select>
                                        {row.suggested_material_id && (
                                            <div className="mt-1 text-xs text-slate-400">
                                                suggested:{' '}
                                                {materials.find((m) => m.id === row.suggested_material_id)?.sku}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-3 py-2">
                                        <input
                                            value={row.label}
                                            onChange={(e) => updateRow(index, { label: e.target.value })}
                                            className="w-44 rounded-md border border-slate-300 px-2 py-1.5"
                                        />
                                    </td>
                                    <td className="px-3 py-2">
                                        <input
                                            type="number"
                                            min={1}
                                            value={row.width_mm ?? ''}
                                            onChange={(e) => updateRow(index, { width_mm: Number(e.target.value) })}
                                            className="w-24 rounded-md border border-slate-300 px-2 py-1.5"
                                        />
                                    </td>
                                    <td className="px-3 py-2">
                                        <input
                                            type="number"
                                            min={1}
                                            value={row.height_mm ?? ''}
                                            onChange={(e) => updateRow(index, { height_mm: Number(e.target.value) })}
                                            className="w-24 rounded-md border border-slate-300 px-2 py-1.5"
                                        />
                                    </td>
                                    <td className="px-3 py-2">
                                        <input
                                            type="number"
                                            min={1}
                                            value={row.qty}
                                            onChange={(e) => updateRow(index, { qty: Number(e.target.value) })}
                                            className="w-20 rounded-md border border-slate-300 px-2 py-1.5"
                                        />
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                form.setData(
                                                    'rows',
                                                    form.data.rows.filter((_, i) => i !== index),
                                                )
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
                </div>

                {form.errors.rows && <p className="text-sm text-red-600">{form.errors.rows}</p>}

                <div className="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 sm:grid-cols-3">
                    <div>
                        <label className="block text-sm font-medium text-slate-700">Customer</label>
                        <input
                            value={form.data.customer_name}
                            onChange={(e) => form.setData('customer_name', e.target.value)}
                            className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                        {form.errors.customer_name && (
                            <p className="mt-1 text-xs text-red-600">{form.errors.customer_name}</p>
                        )}
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700">Customer reference</label>
                        <input
                            value={form.data.customer_reference}
                            onChange={(e) => form.setData('customer_reference', e.target.value)}
                            className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                    </div>
                    <div>
                        <span className="block text-sm font-medium text-slate-700">Nesting mode</span>
                        <div className="mt-1 inline-flex rounded-md border border-slate-300 p-0.5">
                            {(['fixed', 'optimized'] as const).map((mode) => (
                                <button
                                    key={mode}
                                    type="button"
                                    onClick={() => form.setData('mode', mode)}
                                    className={`rounded px-3 py-1.5 text-sm ${
                                        form.data.mode === mode
                                            ? 'bg-teal-700 text-white'
                                            : 'text-slate-600 hover:bg-slate-100'
                                    }`}
                                >
                                    {mode === 'fixed' ? 'Fixed' : 'Optimized'}
                                </button>
                            ))}
                        </div>
                    </div>
                </div>

                <button
                    type="submit"
                    disabled={form.processing || unresolved > 0}
                    className="rounded-md bg-teal-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-800 disabled:opacity-50"
                    title={unresolved > 0 ? 'Every row needs a SKU first' : undefined}
                >
                    Approve → create estimate
                </button>
            </form>

            <details className="mt-8 rounded-xl border border-slate-200 bg-white p-4">
                <summary className="cursor-pointer text-sm font-medium text-slate-700">
                    Original submission
                </summary>
                <pre className="mt-3 overflow-x-auto whitespace-pre-wrap font-mono text-xs text-slate-600">
                    {submission.raw_input}
                </pre>
            </details>
        </AppLayout>
    );
}
