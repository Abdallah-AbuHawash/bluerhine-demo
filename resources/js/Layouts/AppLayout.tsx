import { Link, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

type PageProps = {
    auth: { user: { name: string; email: string } | null };
    flash: { success: string | null; error: string | null };
    demoOffline: boolean;
};

const NAV = [
    { href: '/estimates/new', label: 'New estimate' },
    { href: '/intake', label: 'AI intake' },
    { href: '/quotes', label: 'Quotes' },
    { href: '/admin', label: 'Admin' },
];

export default function AppLayout({ title, subtitle, children }: { title: string; subtitle?: string; children: ReactNode }) {
    const { props, url } = usePage<PageProps>();
    const { auth, flash, demoOffline } = props;

    return (
        <div className="min-h-full">
            <header className="border-b border-slate-200 bg-white">
                <div className="mx-auto flex max-w-7xl items-center gap-6 px-6 py-3">
                    <Link href="/" className="text-sm font-semibold tracking-tight text-teal-700">
                        CutToSize
                    </Link>
                    <nav className="flex gap-1">
                        {NAV.map((item) => {
                            const active = url.startsWith(item.href);
                            return (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    className={`rounded-md px-3 py-1.5 text-sm transition ${
                                        active ? 'bg-teal-50 font-medium text-teal-800' : 'text-slate-600 hover:bg-slate-100'
                                    }`}
                                >
                                    {item.label}
                                </Link>
                            );
                        })}
                    </nav>
                    <div className="ml-auto flex items-center gap-3 text-xs text-slate-500">
                        {demoOffline && (
                            <span className="rounded-full bg-amber-100 px-2.5 py-1 font-medium text-amber-800">
                                offline demo mode
                            </span>
                        )}
                        {auth.user && (
                            <>
                                <span>{auth.user.email}</span>
                                <button
                                    type="button"
                                    onClick={() => router.post('/logout')}
                                    className="rounded-md border border-slate-200 px-2.5 py-1 hover:bg-slate-50"
                                >
                                    Sign out
                                </button>
                            </>
                        )}
                    </div>
                </div>
            </header>

            <main className="mx-auto max-w-7xl px-6 py-8">
                <div className="mb-6">
                    <h1 className="text-xl font-semibold tracking-tight text-slate-900">{title}</h1>
                    {subtitle && <p className="mt-1 text-sm text-slate-500">{subtitle}</p>}
                </div>

                {flash.success && (
                    <div className="mb-6 rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900">
                        {flash.success}
                    </div>
                )}
                {flash.error && (
                    <div className="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                        {flash.error}
                    </div>
                )}

                {children}
            </main>
        </div>
    );
}
