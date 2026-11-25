import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    CalendarClock,
    FolderGit2,
    HeartPulse,
    LayoutGrid,
    Stethoscope,
    UserCog,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import {
    index as employeesIndex,
    show as showEmployee,
} from '@/routes/employees';
import { index as followUpsIndex } from '@/routes/follow-ups';
import { index as healthChecksIndex } from '@/routes/health-checks';
import { index as usersIndex } from '@/routes/users';
import type { NavItem, Permissions } from '@/types';

/**
 * Build the sidebar from what the signed-in user may open; the server enforces the same rules.
 */
function navItemsFor(can: Permissions): NavItem[] {
    const items: NavItem[] = [
        { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    ];

    if (can.ownEmployeeId !== null) {
        items.push({
            title: 'My health record',
            href: showEmployee(can.ownEmployeeId),
            icon: HeartPulse,
        });
    }

    if (can.viewEmployees) {
        items.push({ title: 'Employees', href: employeesIndex(), icon: Users });
    }

    if (can.manageHealthRecords) {
        items.push(
            {
                title: 'Health checks',
                href: healthChecksIndex(),
                icon: Stethoscope,
            },
            {
                title: 'Follow-ups',
                href: followUpsIndex(),
                icon: CalendarClock,
            },
        );
    }

    if (can.manageUsers) {
        items.push({
            title: 'User accounts',
            href: usersIndex(),
            icon: UserCog,
        });
    }

    return items;
}

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth } = usePage().props;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navItemsFor(auth.can)} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
