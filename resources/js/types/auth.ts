export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    role: Role;
    company_id: number | null;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Role = 'admin' | 'medical_staff' | 'hr' | 'employee';

export type Permissions = {
    viewEmployees: boolean;
    viewAppointments: boolean;
    manageHealthRecords: boolean;
    manageUsers: boolean;
    viewAuditLog: boolean;
    useAi: boolean;
    ownEmployeeId: number | null;
};

export type Auth = {
    user: User;
    roleLabel: string | null;
    can: Permissions;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
