import { Form, usePage } from '@inertiajs/react';
import { ChevronDown, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { index as employeesIndex } from '@/routes/employees';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

/**
 * Today's date and time, refreshed every half minute.
 */
function useClock(): string {
    const format = () =>
        new Date().toLocaleString(undefined, {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    const [now, setNow] = useState(format);

    useEffect(() => {
        const timer = window.setInterval(() => setNow(format()), 30_000);

        return () => window.clearInterval(timer);
    }, []);

    return now;
}

function EmployeeSearch() {
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        // Press "/" anywhere (outside a text field) to jump to search.
        function focusSearch(event: KeyboardEvent) {
            const target = event.target as HTMLElement;
            const isTyping =
                target.isContentEditable ||
                ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName);

            if (event.key === '/' && !isTyping) {
                event.preventDefault();
                inputRef.current?.focus();
            }
        }

        window.addEventListener('keydown', focusSearch);

        return () => window.removeEventListener('keydown', focusSearch);
    }, []);

    return (
        <Form
            {...employeesIndex.form()}
            className="relative hidden w-full max-w-sm md:block"
            role="search"
        >
            <Search
                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden
            />
            <input
                ref={inputRef}
                name="search"
                type="search"
                placeholder="Search name or employee no."
                aria-label="Search employees"
                className="h-9 w-full rounded-lg border border-input bg-muted/50 pr-10 pl-9 text-sm outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:bg-background focus-visible:ring-[3px] focus-visible:ring-ring/40"
            />
            <kbd className="pointer-events-none absolute top-1/2 right-2.5 -translate-y-1/2 rounded border bg-background px-1.5 text-[11px] text-muted-foreground">
                /
            </kbd>
        </Form>
    );
}

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { auth } = usePage().props;
    const getInitials = useInitials();
    const now = useClock();

    return (
        <header className="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-3 border-b bg-card px-4 md:px-6">
            <SidebarTrigger className="-ml-1" />
            <div className="min-w-0 flex-1">
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            {auth.can.viewEmployees && <EmployeeSearch />}

            <time className="hidden text-sm whitespace-nowrap text-muted-foreground lg:block">
                {now}
            </time>

            {auth.user && (
                <DropdownMenu>
                    <DropdownMenuTrigger
                        className="flex items-center gap-2 rounded-full border bg-card py-1 pr-2 pl-1 text-left outline-none hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring/40"
                        data-test="sidebar-menu-button"
                    >
                        <Avatar className="size-8">
                            <AvatarFallback className="bg-primary text-xs font-semibold text-primary-foreground">
                                {getInitials(auth.user.name)}
                            </AvatarFallback>
                        </Avatar>
                        <span className="hidden leading-tight sm:grid">
                            <span className="max-w-40 truncate text-sm font-semibold">
                                {auth.user.name}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {auth.roleLabel}
                            </span>
                        </span>
                        <ChevronDown
                            className="size-4 text-muted-foreground"
                            aria-hidden
                        />
                    </DropdownMenuTrigger>
                    <DropdownMenuContent className="min-w-56" align="end">
                        <UserMenuContent user={auth.user} />
                    </DropdownMenuContent>
                </DropdownMenu>
            )}
        </header>
    );
}
