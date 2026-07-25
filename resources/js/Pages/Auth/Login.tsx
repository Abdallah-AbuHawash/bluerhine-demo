import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

export default function Login({
    demoEmail,
    demoPassword,
    showHint,
}: {
    demoEmail: string;
    demoPassword: string;
    showHint: boolean;
}) {
    const form = useForm({ email: demoEmail, password: demoPassword });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/login');
    }

    return (
        <div className="flex min-h-screen items-center justify-center px-6">
            <Head title="Sign in" />
            <form onSubmit={submit} className="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                <h1 className="text-lg font-semibold tracking-tight text-teal-700">CutToSize</h1>
                <p className="mt-1 mb-6 text-sm text-slate-500">Cut-to-size quoting demo</p>

                <label className="block text-sm font-medium text-slate-700" htmlFor="email">
                    Email
                </label>
                <input
                    id="email"
                    type="email"
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                    className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none"
                />
                {form.errors.email && <p className="mt-1 text-xs text-red-600">{form.errors.email}</p>}

                <label className="mt-4 block text-sm font-medium text-slate-700" htmlFor="password">
                    Password
                </label>
                <input
                    id="password"
                    type="password"
                    value={form.data.password}
                    onChange={(e) => form.setData('password', e.target.value)}
                    className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none"
                />

                <button
                    type="submit"
                    disabled={form.processing}
                    className="mt-6 w-full rounded-md bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800 disabled:opacity-60"
                >
                    Sign in
                </button>

                {showHint && (
                    <p className="mt-4 text-xs text-slate-400">
                        Demo credentials are pre-filled: {demoEmail} / {demoPassword}
                    </p>
                )}
            </form>
        </div>
    );
}
