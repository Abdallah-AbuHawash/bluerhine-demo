import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

type Row = {
    id: number;
    reference: string;
    customer_name: string;
    status: string;
    total_aed: number;
    created_at: string;
};

export default function QuotesIndex({ quotes }: { quotes: Row[] }) {
    return (
        <AppLayout title="Quotes" subtitle="Drafts, issued quotes and orders.">
            <Head title="Quotes" />

            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <table className="w-full text-sm">
                    <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th className="px-4 py-3 font-medium">Reference</th>
                            <th className="px-4 py-3 font-medium">Customer</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3 font-medium">Created</th>
                            <th className="px-4 py-3 text-right font-medium">Total</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {quotes.map((quote) => (
                            <tr key={quote.id} className="hover:bg-slate-50">
                                <td className="px-4 py-3">
                                    <Link href={`/quotes/${quote.id}`} className="font-medium text-teal-700">
                                        {quote.reference}
                                    </Link>
                                </td>
                                <td className="px-4 py-3 text-slate-700">{quote.customer_name}</td>
                                <td className="px-4 py-3 text-slate-500">{quote.status}</td>
                                <td className="px-4 py-3 text-slate-500">{quote.created_at}</td>
                                <td className="px-4 py-3 text-right tabular-nums">
                                    AED {quote.total_aed.toFixed(2)}
                                </td>
                            </tr>
                        ))}
                        {quotes.length === 0 && (
                            <tr>
                                <td colSpan={5} className="px-4 py-10 text-center text-slate-400">
                                    No quotes yet — start from “New estimate” or the AI intake screen.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}
