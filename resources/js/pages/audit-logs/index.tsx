import { Form, Head, Link } from "@inertiajs/react";
import { Filter, X } from "lucide-react";
import { PageHeader } from "@/components/form-field";
import Pagination from "@/components/pagination";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { NativeSelect } from "@/components/ui/native-select";
import { index } from "@/routes/audit-logs";
import { show as showEmployee } from "@/routes/employees";
import type { Option, Paginated, StaffMember } from "@/types";

type AuditLogRow = {
    id: number;
    action: string;
    description: string;
    changed_fields: string[] | null;
    ip_address: string | null;
    created_at: string;
    user: { id: number; name: string; role: string } | null;
    employee: {
        id: number;
        employee_number: string;
        full_name: string;
    } | null;
};

const actionTones: Record<string, string> = {
    viewed: "bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300",
    downloaded:
        "bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300",
    created:
        "bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300",
    updated:
        "bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300",
    deleted: "bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300",
    imported:
        "bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300",
    ai_drafted:
        "bg-fuchsia-100 text-fuchsia-800 dark:bg-fuchsia-900/40 dark:text-fuchsia-300",
};

function formatTimestamp(value: string): string {
    return new Date(value).toLocaleString(undefined, {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "numeric",
        minute: "2-digit",
    });
}

export default function AuditLogIndex({
    logs,
    filters,
    filteredEmployee,
    users,
    actions,
}: {
    logs: Paginated<AuditLogRow>;
    filters: {
        user_id: number | null;
        employee_id: number | null;
        action: string;
        from: string;
        to: string;
    };
    filteredEmployee: AuditLogRow["employee"];
    users: StaffMember[];
    actions: Option[];
}) {
    return (
        <>
            <Head title="Audit log" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Audit log"
                    description="Every view, download, and change of employee and medical records. Entries record which fields changed, never the medical values themselves, and cannot be edited or deleted."
                />

                {filteredEmployee && (
                    <div className="flex items-center gap-2 text-sm">
                        <span className="text-muted-foreground">
                            Access history for
                        </span>
                        <Link
                            href={showEmployee(filteredEmployee.id)}
                            className="font-medium hover:underline"
                        >
                            {filteredEmployee.full_name} (
                            {filteredEmployee.employee_number})
                        </Link>
                        <Button variant="ghost" size="sm" asChild>
                            <Link
                                href={index({
                                    query: {
                                        ...filters,
                                        employee_id: undefined,
                                    },
                                })}
                                aria-label="Show all employees"
                            >
                                <X />
                            </Link>
                        </Button>
                    </div>
                )}

                <Form
                    {...index.form()}
                    className="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_auto_auto_auto] lg:items-end"
                >
                    {filters.employee_id && (
                        <input
                            type="hidden"
                            name="employee_id"
                            value={filters.employee_id}
                        />
                    )}
                    <div className="grid gap-1">
                        <Label htmlFor="user_id">Person</Label>
                        <NativeSelect
                            id="user_id"
                            name="user_id"
                            defaultValue={filters.user_id ?? ""}
                        >
                            <option value="">Anyone</option>
                            {users.map((user) => (
                                <option key={user.id} value={user.id}>
                                    {user.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="action">Action</Label>
                        <NativeSelect
                            id="action"
                            name="action"
                            defaultValue={filters.action}
                        >
                            <option value="">Any action</option>
                            {actions.map((action) => (
                                <option key={action.value} value={action.value}>
                                    {action.label}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="from">From</Label>
                        <Input
                            id="from"
                            name="from"
                            type="date"
                            defaultValue={filters.from}
                        />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="to">To</Label>
                        <Input
                            id="to"
                            name="to"
                            type="date"
                            defaultValue={filters.to}
                        />
                    </div>
                    <Button type="submit" variant="secondary">
                        <Filter /> Filter
                    </Button>
                </Form>

                <div className="overflow-x-auto rounded-xl border border-border bg-card">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">When</th>
                                <th className="px-4 py-3 font-medium">Who</th>
                                <th className="px-4 py-3 font-medium">
                                    Action
                                </th>
                                <th className="px-4 py-3 font-medium">What</th>
                                <th className="px-4 py-3 font-medium">
                                    Employee record
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.data.map((log) => (
                                <tr
                                    key={log.id}
                                    className="border-b align-top last:border-0"
                                >
                                    <td className="px-4 py-3 whitespace-nowrap tabular-nums">
                                        {formatTimestamp(log.created_at)}
                                        {log.ip_address && (
                                            <div className="text-xs text-muted-foreground">
                                                {log.ip_address}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {log.user?.name ?? (
                                            <span className="text-muted-foreground">
                                                System
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            className={`border-transparent ${actionTones[log.action] ?? ""}`}
                                        >
                                            {actions.find(
                                                (action) =>
                                                    action.value === log.action,
                                            )?.label ?? log.action}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3">
                                        {log.description}
                                        {log.changed_fields && (
                                            <div className="text-xs text-muted-foreground">
                                                Fields:{" "}
                                                {log.changed_fields
                                                    .map((field) =>
                                                        field.replaceAll(
                                                            "_",
                                                            " ",
                                                        ),
                                                    )
                                                    .join(", ")}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {log.employee ? (
                                            <Link
                                                href={index({
                                                    query: {
                                                        employee_id:
                                                            log.employee.id,
                                                    },
                                                })}
                                                className="hover:underline"
                                            >
                                                {log.employee.full_name}
                                            </Link>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {logs.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No entries match these filters.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={logs} />
            </div>
        </>
    );
}

AuditLogIndex.layout = {
    breadcrumbs: [{ title: "Audit log", href: index() }],
};
