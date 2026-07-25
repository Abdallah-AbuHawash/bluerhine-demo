import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppLayout from '../../Layouts/AppLayout';

type Example = { label: string; text: string };
type Recent = {
    id: number;
    status: string;
    confidence: number | null;
    offline_fallback: boolean;
    created_at: string;
};

export default function IntakeCreate({
    examples,
    apiAvailable,
    recent,
}: {
    examples: Example[];
    apiAvailable: boolean;
    recent: Recent[];
}) {
    const form = useForm<{ raw_input: string; file: File | null }>({ raw_input: '', file: null });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/intake', { forceFormData: true });
    }

    return (
        <AppLayout
            title="AI intake"
            subtitle="Paste a cut list in any format — or upload the file the customer sent."
        >
            <Head title="AI intake" />

            <div className="grid gap-6 lg:grid-cols-[1fr_20rem]">
                <form onSubmit={submit} className="space-y-4 rounded-xl border border-slate-200 bg-white p-5">
                    <div>
                        <label className="block text-sm font-medium text-slate-700" htmlFor="raw_input">
                            Paste your cut list — any format
                        </label>
                        <textarea
                            id="raw_input"
                            rows={12}
                            value={form.data.raw_input}
                            onChange={(e) => form.setData('raw_input', e.target.value)}
                            placeholder={'e.g. 6 pcs 60x40cm 3mm opal white acrylic\nor a pasted BOM table'}
                            className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 font-mono text-sm focus:border-teal-500 focus:outline-none"
                        />
                        {form.errors.raw_input && (
                            <p className="mt-1 text-xs text-red-600">{form.errors.raw_input}</p>
                        )}
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <input
                            type="file"
                            accept=".txt,.csv,.html,text/plain,text/csv,text/html"
                            onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)}
                            className="text-sm text-slate-600 file:mr-3 file:rounded-md file:border file:border-slate-300 file:bg-white file:px-3 file:py-1.5 file:text-sm"
                        />
                        {form.errors.file && <p className="text-xs text-red-600">{form.errors.file}</p>}
                    </div>

                    <button
                        type="submit"
                        disabled={form.processing}
                        className="rounded-md bg-teal-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-800 disabled:opacity-60"
                    >
                        {form.processing ? 'Parsing…' : 'Parse cut list'}
                    </button>
                </form>

                <aside className="space-y-4">
                    <div className="rounded-xl border border-slate-200 bg-white p-4">
                        <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            One-click examples
                        </h3>
                        <div className="mt-3 space-y-2">
                            {examples.map((example) => (
                                <button
                                    key={example.label}
                                    type="button"
                                    onClick={() => form.setData('raw_input', example.text)}
                                    className="w-full rounded-md border border-slate-200 px-3 py-2 text-left text-sm text-slate-700 hover:border-teal-400 hover:bg-teal-50"
                                >
                                    {example.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
                        <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-500">Parser</h3>
                        <p className="mt-2">
                            {apiAvailable
                                ? 'Live: one Anthropic call with a strict JSON schema. If it fails, the canned fixtures take over automatically.'
                                : 'Offline: canned fixture parses, no network needed. Set ANTHROPIC_API_KEY and DEMO_OFFLINE=false for the live call.'}
                        </p>
                    </div>

                    {recent.length > 0 && (
                        <div className="rounded-xl border border-slate-200 bg-white p-4">
                            <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Recent submissions
                            </h3>
                            <ul className="mt-3 space-y-2 text-sm">
                                {recent.map((item) => (
                                    <li key={item.id} className="flex items-center justify-between gap-2">
                                        <a href={`/intake/${item.id}/review`} className="text-teal-700 hover:underline">
                                            #{item.id} · {item.status}
                                        </a>
                                        <span className="text-xs text-slate-400">
                                            {item.offline_fallback ? 'offline' : 'api'} · {item.created_at}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </aside>
            </div>
        </AppLayout>
    );
}
