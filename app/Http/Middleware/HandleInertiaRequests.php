<?php

namespace App\Http\Middleware;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'branding' => [
                'tagline' => config('meditrack.branding.tagline'),
                'office' => config('meditrack.branding.office'),
                'officeAddress' => config('meditrack.branding.office_address'),
                'logo' => asset(config('meditrack.branding.logo')),
                'mark' => asset(config('meditrack.branding.mark')),
            ],
            'auth' => [
                'user' => $request->user(),
                'roleLabel' => $request->user()?->role->label(),
                'can' => fn (): array => $this->permissions($request->user()),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Get what the signed-in user may open, so the interface only shows reachable pages.
     *
     * @return array{viewEmployees: bool, viewAppointments: bool, manageHealthRecords: bool, manageUsers: bool, viewAuditLog: bool, ownEmployeeId: int|null}
     */
    private function permissions(?User $user): array
    {
        if ($user === null) {
            return ['viewEmployees' => false, 'manageHealthRecords' => false, 'manageUsers' => false, 'viewAuditLog' => false, 'viewAppointments' => false, 'ownEmployeeId' => null];
        }

        return [
            'viewEmployees' => $user->can('viewAny', Employee::class),
            'viewAppointments' => $user->can('viewAny', Appointment::class),
            'manageHealthRecords' => $user->managesHealthRecords(),
            'manageUsers' => $user->can('viewAny', User::class),
            'viewAuditLog' => $user->can('viewAny', AuditLog::class),
            'ownEmployeeId' => $user->employee()->value('id'),
        ];
    }
}
