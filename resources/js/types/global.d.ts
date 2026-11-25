import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            branding: {
                tagline: string;
                office: string;
                officeAddress: string | null;
                logo: string;
                mark: string;
            };
            auth: Auth;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
