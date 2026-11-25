import { Link, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    CalendarDays,
    HeartPulse,
    LayoutGrid,
    ScrollText,
    Stethoscope,
    UserCog,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
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
import { index as appointmentsIndex } from '@/routes/appointments';
import { index as auditLogIndex } from '@/routes/audit-logs';
import {
    index as employeesIndex,
    show as showEmployee,
} from '@/routes/employees';
import { index as followUpsIndex } from '@/routes/follow-ups';
import { index as healthChecksIndex } from '@/routes/health-checks';
import { index as usersIndex } from '@/routes/users';
import type { NavItem, Permissions } from '@/types';

type NavGroup = { label: string; items: NavItem[] };

/**
 * Build the sidebar sections from what the signed-in user may open; the server enforces the same rules.
 */
function navGroupsFor(can: Permissions): NavGroup[] {
    const clinic: NavItem[] = [];
    const records: NavItem[] = [];
    const administration: NavItem[] = [];

    if (can.viewAppointments) {
        clinic.push({
            title: can.viewEmployees ? 'Clinic schedule' : 'My appointments',
            href: appointmentsIndex(),
            icon: CalendarDays,
        });
    }

    if (can.manageHealthRecords) {
        clinic.push(
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

    if (can.viewEmployees) {
        records.push({
            title: 'Employees',
            href: employeesIndex(),
            icon: Users,
        });
    }

    if (can.ownEmployeeId !== null) {
        records.push({
            title: 'My health record',
            href: showEmployee(can.ownEmployeeId),
            icon: HeartPulse,
        });
    }

    if (can.manageUsers) {
        administration.push({
            title: 'User accounts',
            href: usersIndex(),
            icon: UserCog,
        });
    }

    if (can.viewAuditLog) {
        administration.push({
            title: 'Audit log',
            href: auditLogIndex(),
            icon: ScrollText,
        });
    }

    return [
        {
            label: 'Overview',
            items: [
                { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
            ],
        },
        { label: 'Clinic', items: clinic },
        { label: 'Records', items: records },
        { label: 'Administration', items: administration },
    ].filter((group) => group.items.length > 0);
}

export function AppSidebar() {
    const { auth, branding } = usePage().props;

    return (
        <Sidebar collapsible="icon" variant="sidebar">
            <SidebarHeader className="border-b border-sidebar-border py-4">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="hover:bg-sidebar-accent hover:text-sidebar-foreground"
                        >
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-1 py-2">
                {navGroupsFor(auth.can).map((group) => (
                    <NavMain
                        key={group.label}
                        label={group.label}
                        items={group.items}
                    />
                ))}
            </SidebarContent>

            <SidebarFooter className="border-t border-sidebar-border px-4 py-3 group-data-[collapsible=icon]:hidden">
                <p className="text-xs leading-snug font-semibold text-sidebar-foreground">
                    {branding.office}
                </p>
                {branding.officeAddress && (
                    <p className="text-xs leading-snug text-sidebar-muted">
                        {branding.officeAddress}
                    </p>
                )}
            </SidebarFooter>
        </Sidebar>
    );
}
