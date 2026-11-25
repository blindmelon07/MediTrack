import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';

/**
 * The hospital's square mark. It sits on a white disc so the navy lines stay
 * visible on dark and navy backgrounds.
 */
export default function AppLogoIcon({ className }: { className?: string }) {
    const { branding, name } = usePage().props;

    return (
        <img
            src={branding.mark}
            alt={name}
            className={cn(
                'aspect-square rounded-full bg-white object-contain p-0.5',
                className,
            )}
        />
    );
}
