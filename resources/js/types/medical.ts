export type Option = {
    value: string;
    label: string;
};

export type ResultOption = Option & {
    needsFollowUp: boolean;
};

export type StaffMember = {
    id: number;
    name: string;
};

export type EmployeeOption = {
    id: number;
    employee_number: string;
    first_name: string;
    last_name: string;
    full_name: string;
};

export type Company = {
    id: number;
    name: string;
};

export type Clearance = {
    value:
        | 'cleared'
        | 'restricted'
        | 'not_cleared'
        | 'needs_follow_up'
        | 'pending';
    label: string;
};

/** What HR sees of a health check: when, what kind, and whether the employee is cleared. */
export type ClearanceSummary = {
    id: number;
    checked_on: string;
    type: string;
    clearance: Clearance;
};

/** What HR sees of a follow-up: when it is due and whether it happened, without the medical reason. */
export type FollowUpScheduleItem = {
    id: number;
    due_on: string;
    status: string;
    completed_on: string | null;
    is_overdue: boolean;
};

export type EmployeeListRow = {
    id: number;
    employee_number: string;
    full_name: string;
    department: string | null;
    position: string | null;
    is_active: boolean;
    company: string | null;
    pending_follow_ups_count: number;
    overdue_follow_ups_count: number;
    latest_check: {
        checked_on: string;
        type: string;
        clearance: Clearance;
        result: string | null;
    } | null;
};

export type Employee = EmployeeOption & {
    user_id: number | null;
    company_id: number | null;
    company?: Company | null;
    department: string | null;
    position: string | null;
    email: string | null;
    phone: string | null;
    birth_date: string | null;
    sex: 'male' | 'female' | null;
    hired_on: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
};

export type HealthCheckAttachment = {
    id: number;
    health_check_id: number;
    original_name: string;
    mime_type: string | null;
    size: number;
    created_at: string;
};

export type HealthCheck = {
    id: number;
    employee_id: number;
    recorded_by: number | null;
    type: string;
    checked_on: string;
    examined_by: string | null;
    blood_pressure: string | null;
    heart_rate: number | null;
    temperature: string | null;
    weight_kg: string | null;
    height_cm: string | null;
    findings: string | null;
    diagnosis: string | null;
    recommendations: string | null;
    result: string;
    created_at: string;
    updated_at: string;
    employee?: Employee;
    recorder?: StaffMember | null;
    follow_ups?: FollowUp[];
    attachments?: HealthCheckAttachment[];
    attachments_count?: number;
};

export type FollowUp = {
    id: number;
    employee_id: number;
    health_check_id: number | null;
    assigned_to: number | null;
    reason: string;
    due_on: string;
    status: string;
    completed_on: string | null;
    notes: string | null;
    is_overdue: boolean;
    created_at: string;
    updated_at: string;
    employee?: EmployeeOption & { department: string | null };
    health_check?: Pick<
        HealthCheck,
        'id' | 'employee_id' | 'type' | 'checked_on'
    > | null;
    assignee?: StaffMember | null;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};
