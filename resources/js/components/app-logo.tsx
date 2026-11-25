import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';

/**
 * The brand mark and name shown at the top of the sidebar.
 */
export default function AppLogo() {
    const { name, branding } = usePage().props;

    return (
        <>
            <AppLogoIcon className="size-10 shrink-0 p-1 shadow-sm" />
            <div className="ml-1 grid flex-1 text-left leading-tight">
                <span className="truncate text-sm font-semibold">{name}</span>
                <span className="truncate text-xs text-sidebar-muted">
                    {branding.tagline}
                </span>
            </div>
        </>
    );
}
