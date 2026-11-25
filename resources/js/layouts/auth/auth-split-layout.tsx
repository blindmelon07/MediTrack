import { usePage } from '@inertiajs/react';
import {
    BellRing,
    CalendarDays,
    LayoutDashboard,
    Printer,
    ShieldCheck,
    Stethoscope,
} from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import type { AuthLayoutProps } from '@/types';

const features = [
    { icon: Stethoscope, label: 'Health checks & follow-ups' },
    { icon: Printer, label: 'Printable exam records & certificates' },
    { icon: CalendarDays, label: 'Clinic appointments' },
    { icon: BellRing, label: 'SMS & email reminders' },
    { icon: ShieldCheck, label: 'Role-based access & audit trail' },
    { icon: LayoutDashboard, label: 'Dashboard & compliance' },
];

/**
 * Splits "GGHI-EmployeeTrack" into a plain lead and a gold accent, so the
 * product word stands out the way the civil-registry style does.
 */
function splitName(name: string): [string, string] {
    const dash = name.indexOf('-');

    if (dash > 0) {
        return [name.slice(0, dash + 1), name.slice(dash + 1)];
    }

    const lastSpace = name.lastIndexOf(' ');

    return lastSpace > 0
        ? [`${name.slice(0, lastSpace)} `, name.slice(lastSpace + 1)]
        : ['', name];
}

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name, branding } = usePage().props;
    const [lead, accent] = splitName(name);

    return (
        <div className="grid min-h-dvh bg-background lg:grid-cols-[1.15fr_1fr]">
            <aside className="relative hidden overflow-hidden bg-[#0b1f4d] text-white lg:flex lg:flex-col lg:justify-between lg:p-12">
                {/* Soft glow and rings, echoing the reference design. */}
                <div
                    aria-hidden
                    className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_85%_15%,rgba(250,204,21,0.18),transparent_45%),radial-gradient(ellipse_at_10%_100%,rgba(236,72,153,0.16),transparent_50%),linear-gradient(160deg,#0b1f4d_0%,#102a72_55%,#1534a0_100%)]"
                />
                <div
                    aria-hidden
                    className="pointer-events-none absolute -top-40 right-[-8rem] size-[30rem] rounded-full border border-white/10"
                />
                <div
                    aria-hidden
                    className="pointer-events-none absolute -top-20 right-0 size-[18rem] rounded-full border border-amber-200/20"
                />

                <div className="relative z-10 space-y-8">
                    <AppLogoIcon className="size-28 p-2 shadow-lg ring-4 ring-white/15" />

                    <div className="space-y-4">
                        <h1 className="text-4xl leading-tight font-extrabold tracking-tight xl:text-5xl">
                            {lead}
                            <span className="text-amber-300">{accent}</span>
                        </h1>
                        <p className="text-xl font-semibold text-white/90">
                            {branding.tagline}
                        </p>
                        <p className="max-w-lg text-[15px] leading-relaxed text-blue-100/80">
                            {branding.office} — health checks, follow-ups,
                            clinic appointments, and medical certificates in one
                            place.
                        </p>
                    </div>

                    <ul className="grid max-w-lg grid-cols-2 gap-x-6 gap-y-3 text-sm text-blue-50">
                        {features.map(({ icon: Icon, label }) => (
                            <li
                                key={label}
                                className="flex items-center gap-2.5"
                            >
                                <Icon
                                    className="size-4 shrink-0 text-amber-300"
                                    aria-hidden
                                />
                                {label}
                            </li>
                        ))}
                    </ul>
                </div>

                <p className="relative z-10 text-xs text-blue-100/60">
                    {branding.officeAddress
                        ? `${branding.officeAddress} · `
                        : ''}
                    Confidential medical records · Data Privacy Act of 2012
                </p>
            </aside>

            <main className="flex items-center justify-center bg-card px-6 py-12 sm:px-12">
                <div className="w-full max-w-sm space-y-8">
                    <div className="flex items-center gap-3">
                        <AppLogoIcon className="size-12 p-1 shadow-sm ring-1 ring-border" />
                        <div className="leading-tight">
                            <p className="font-bold">{name}</p>
                            <p className="text-sm text-muted-foreground">
                                {branding.office}
                            </p>
                        </div>
                    </div>

                    {(title || description) && (
                        <div className="space-y-1.5">
                            {title && (
                                <h2 className="text-2xl font-bold tracking-tight">
                                    {title}
                                </h2>
                            )}
                            {description && (
                                <p className="text-sm text-muted-foreground">
                                    {description}
                                </p>
                            )}
                        </div>
                    )}

                    {children}
                </div>
            </main>
        </div>
    );
}
