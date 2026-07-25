import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import type { MaterialOption } from '../../types';

type Row = { width: string; height: string; qty: string; label: string };
type Line = { material_id: number; mode: 'fixed' | 'optimized'; rows: Row[] };

type Prefill = {
    customer_name?: string;
    customer_reference?: string | null;
    lines: { material_id: number; mode?: 'fixed' | 'optimized'; rows: Row[] }[];
};

const emptyRow = (index: number): Row => ({ width: '', height: '', qty: '1', label: `Row ${index + 1}` });

export default function NewEstimate({ materials, prefill }: { materials: MaterialOption[]; prefill: Prefill | null }) {
    const initialLines: Line[] = prefill?.lines?.length
        ? prefill.lines.map((line) => ({
              material_id: line.material_id,
              mode: line.mode ?? 'optimized',
              rows: line.rows.length ? line.rows : [emptyRow(0)],
          }))
        : [{ material_id: materials[0]?.id ?? 0, mode: 'optimized', rows: [emptyRow(0), emptyRow(1)] }];

    const form = useForm<{ customer_name: string; customer_reference: string; lines: Line[] }>({
        customer_name: prefill?.customer_name ?? '',
        customer_reference: prefill?.customer_reference ?? '',
        lines: initialLines,
    });

    const setLines = (lines: Line[]) => form.setData('lines', lines);

    function updateRow(lineIndex: number, rowIndex: number, patch: Partial<Row>) {
        const lines = form.data.lines.map((line, li) =>
            li === lineIndex
                ? { ...line, rows: line.rows.map((row, ri) => (ri === rowIndex ? { ...row, ...patch } : row)) }
                : line,
        );
        setLines(lines);
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            lines: data.lines.map((line) => ({
                ...line,
                rows: line.rows
                    .filter((row) => row.width && row.height && Number(row.qty) > 0)
                    .map((row, index) => ({
                        width: Number(row.width),
                        height: Number(row.height),
                        qty: Number(row.qty),
                        label: row.label || `Row ${index + 1}`,
                    })),
            })),
        }));
        form.post('/estimates');
    }

    return (
        <AppLayout title="New estimate" subtitle="Pick a material, enter the cut list, choose a nesting mode.">
            <Head title="New estimate" />

            <form onSubmit={submit} className="space-y-6">
                <div className="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 sm:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-slate-700">Customer</label>
                        <input
                            value={form.data.customer_name}
                            onChange={(e) => form.setData('customer_name', e.target.value)}
                            placeholder="Aisle One Contracting LLC"
                            className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none"
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
                            placeholder="PO-4471"
                            className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none"
                        />
                    </div>
                </div>

                {form.errors.lines && (
                    <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        {form.errors.lines}
                    </div>
                )}

                {form.data.lines.map((line, lineIndex) => {
                    const material = materials.find((m) => m.id === line.material_id);

                    return (
                        <div key={lineIndex} className="rounded-xl border border-slate-200 bg-white p-5">
                            <div className="flex flex-wrap items-end gap-4">
                                <div className="min-w-72 flex-1">
                                    <label className="block text-sm font-medium text-slate-700">Material</label>
                                    <select
                                        value={line.material_id}
                                        onChange={(e) =>
                                            setLines(
                                                form.data.lines.map((l, i) =>
                                                    i === lineIndex ? { ...l, material_id: Number(e.target.value) } : l,
                                                ),
                                            )
                                        }
                                        className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none"
                                    >
                                        {materials.map((m) => (
                                            <option key={m.id} value={m.id}>
                                                {m.sku} — {m.name} ({m.sheet_w_mm}×{m.sheet_h_mm})
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <span className="block text-sm font-medium text-slate-700">Nesting mode</span>
                                    <div className="mt-1 inline-flex rounded-md border border-slate-300 p-0.5">
                                        {(['fixed', 'optimized'] as const).map((mode) => (
                                            <button
                                                key={mode}
                                                type="button"
                                                onClick={() =>
                                                    setLines(
                                                        form.data.lines.map((l, i) =>
                                                            i === lineIndex ? { ...l, mode } : l,
                                                        ),
                                                    )
                                                }
                                                className={`rounded px-3 py-1.5 text-sm ${
                                                    line.mode === mode
                                                        ? 'bg-teal-700 text-white'
                                                        : 'text-slate-600 hover:bg-slate-100'
                                                }`}
                                            >
                                                {mode === 'fixed' ? 'Fixed orientation' : 'Optimized'}
                                            </button>
                                        ))}
                                    </div>
                                </div>

                                {form.data.lines.length > 1 && (
                                    <button
                                        type="button"
                                        onClick={() => setLines(form.data.lines.filter((_, i) => i !== lineIndex))}
                                        className="ml-auto text-sm text-slate-500 hover:text-red-600"
                                    >
                                        Remove material
                                    </button>
                                )}
                            </div>

                            {material && (
                                <p className="mt-2 text-xs text-slate-500">
                                    AED {material.selling_price_aed.toFixed(2)} per sheet ·{' '}
                                    {material.available_sheets} of {material.stock_qty} sheets available ·{' '}
                                    {material.rotation_allowed ? 'rotation allowed' : 'directional: rotation locked'}
                                </p>
                            )}

                            <table className="mt-4 w-full text-sm">
                                <thead>
                                    <tr className="text-left text-xs uppercase tracking-wide text-slate-500">
                                        <th className="pb-2 font-medium">Label</th>
                                        <th className="pb-2 font-medium">Width (mm)</th>
                                        <th className="pb-2 font-medium">Height (mm)</th>
                                        <th className="pb-2 font-medium">Qty</th>
                                        <th />
                                    </tr>
                                </thead>
                                <tbody>
                                    {line.rows.map((row, rowIndex) => (
                                        <tr key={rowIndex}>
                                            <td className="py-1 pr-2">
                                                <input
                                                    value={row.label}
                                                    onChange={(e) =>
                                                        updateRow(lineIndex, rowIndex, { label: e.target.value })
                                                    }
                                                    className="w-full rounded-md border border-slate-300 px-2 py-1.5"
                                                />
                                            </td>
                                            <td className="py-1 pr-2">
                                                <input
                                                    type="number"
                                                    min={1}
                                                    value={row.width}
                                                    onChange={(e) =>
                                                        updateRow(lineIndex, rowIndex, { width: e.target.value })
                                                    }
                                                    className="w-32 rounded-md border border-slate-300 px-2 py-1.5"
                                                />
                                            </td>
                                            <td className="py-1 pr-2">
                                                <input
                                                    type="number"
                                                    min={1}
                                                    value={row.height}
                                                    onChange={(e) =>
                                                        updateRow(lineIndex, rowIndex, { height: e.target.value })
                                                    }
                                                    className="w-32 rounded-md border border-slate-300 px-2 py-1.5"
                                                />
                                            </td>
                                            <td className="py-1 pr-2">
                                                <input
                                                    type="number"
                                                    min={1}
                                                    value={row.qty}
                                                    onChange={(e) =>
                                                        updateRow(lineIndex, rowIndex, { qty: e.target.value })
                                                    }
                                                    className="w-20 rounded-md border border-slate-300 px-2 py-1.5"
                                                />
                                            </td>
                                            <td className="py-1 text-right">
                                                {line.rows.length > 1 && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setLines(
                                                                form.data.lines.map((l, i) =>
                                                                    i === lineIndex
                                                                        ? {
                                                                              ...l,
                                                                              rows: l.rows.filter(
                                                                                  (_, ri) => ri !== rowIndex,
                                                                              ),
                                                                          }
                                                                        : l,
                                                                ),
                                                            )
                                                        }
                                                        className="text-slate-400 hover:text-red-600"
                                                    >
                                                        ×
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>

                            <button
                                type="button"
                                onClick={() =>
                                    setLines(
                                        form.data.lines.map((l, i) =>
                                            i === lineIndex ? { ...l, rows: [...l.rows, emptyRow(l.rows.length)] } : l,
                                        ),
                                    )
                                }
                                className="mt-3 text-sm text-teal-700 hover:text-teal-900"
                            >
                                + Add row
                            </button>
                        </div>
                    );
                })}

                <div className="flex items-center gap-4">
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="rounded-md bg-teal-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-800 disabled:opacity-60"
                    >
                        Calculate estimate
                    </button>
                    <button
                        type="button"
                        onClick={() =>
                            setLines([
                                ...form.data.lines,
                                { material_id: materials[0]?.id ?? 0, mode: 'optimized', rows: [emptyRow(0)] },
                            ])
                        }
                        className="text-sm text-slate-600 hover:text-slate-900"
                    >
                        + Add another material
                    </button>
                </div>
            </form>
        </AppLayout>
    );
}
