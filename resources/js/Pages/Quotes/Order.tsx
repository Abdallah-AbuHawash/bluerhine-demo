import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import type { Quote, QuoteLine } from '../../types';

export default function OrderPreview({
    quote,
    lines,
    payload,
    samplePayload,
}: {
    quote: Quote;
    lines: QuoteLine[];
    payload: unknown;
    samplePayload: unknown;
}) {
    const [copied, setCopied] = useState(false);
    const pretty = JSON.stringify(payload, null, 2);

    async function copy() {
        await navigator.clipboard.writeText(pretty);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    }

    return (
        <AppLayout
            title={`Order payload — ${quote.reference}`}
            subtitle="Rendered, never sent. Built from the frozen quote snapshot, side by side with the client's sample for comparison."
        >
            <Head title={`Order payload ${quote.reference}`} />

            <div className="mb-6 flex flex-wrap items-center gap-3">
                <Link href={`/quotes/${quote.id}`} className="text-sm text-teal-700 hover:underline">
                    ← back to quote
                </Link>
                <span className="text-sm text-slate-500">status {quote.status}</span>
                <div className="ml-auto flex gap-3">
                    <button
                        type="button"
                        onClick={copy}
                        className="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        {copied ? 'Copied' : 'Copy payload'}
                    </button>
                    {quote.status !== 'ordered' && (
                        <button
                            type="button"
                            onClick={() => router.post(`/quotes/${quote.id}/convert`)}
                            className="rounded-md bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800"
                        >
                            Convert to order
                        </button>
                    )}
                </div>
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <section>
                    <h2 className="mb-2 text-sm font-semibold text-slate-900">
                        Generated from {quote.reference}
                    </h2>
                    <pre className="max-h-[36rem] overflow-auto rounded-xl border border-teal-200 bg-teal-50/40 p-4 font-mono text-xs text-slate-800">
                        {pretty}
                    </pre>
                </section>

                <section>
                    <h2 className="mb-2 text-sm font-semibold text-slate-900">Client sample (static)</h2>
                    <pre className="max-h-[36rem] overflow-auto rounded-xl border border-slate-200 bg-slate-50 p-4 font-mono text-xs text-slate-600">
                        {JSON.stringify(samplePayload, null, 2)}
                    </pre>
                </section>
            </div>

            <div className="mt-6 rounded-xl border border-slate-200 bg-white p-5">
                <h2 className="text-sm font-semibold text-slate-900">What the lines came from</h2>
                <table className="mt-3 w-full text-sm">
                    <thead className="text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th className="pb-2 font-medium">SKU</th>
                            <th className="pb-2 font-medium">Sheets</th>
                            <th className="pb-2 font-medium">Cut metres</th>
                            <th className="pb-2 font-medium">Cut job</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {lines.map((line) => (
                            <tr key={line.id}>
                                <td className="py-2 text-slate-700">{line.snapshot.material.sku}</td>
                                <td className="py-2 tabular-nums text-slate-600">{line.sheets_consumed}</td>
                                <td className="py-2 tabular-nums text-slate-600">{line.cut_metres}</td>
                                <td className="py-2 text-slate-500">
                                    {line.cut_jobs.map((job) => job.scheduled_date).join(', ') || '—'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}
