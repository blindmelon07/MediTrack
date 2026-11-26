<?php

namespace App\Http\Controllers;

use App\Enums\FollowUpStatus;
use App\Models\FollowUp;
use App\Models\HealthCheck;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class HealthCheckFollowUpController extends Controller
{
    /**
     * Schedule several follow-ups for a health check at once, such as the ones the AI suggested.
     */
    public function store(Request $request, HealthCheck $healthCheck): RedirectResponse
    {
        Gate::authorize('update', $healthCheck);
        Gate::authorize('create', FollowUp::class);

        $validated = $request->validate([
            'follow_ups' => ['required', 'array', 'min:1', 'max:10'],
            'follow_ups.*.reason' => ['required', 'string', 'max:255'],
            'follow_ups.*.due_on' => ['required', 'date', 'after_or_equal:today'],
            'follow_ups.*.assigned_to' => ['nullable', 'integer', Rule::exists(User::class, 'id')],
        ]);

        DB::transaction(function () use ($healthCheck, $validated): void {
            foreach ($validated['follow_ups'] as $followUp) {
                $healthCheck->followUps()->create([
                    'employee_id' => $healthCheck->employee_id,
                    'reason' => $followUp['reason'],
                    'due_on' => $followUp['due_on'],
                    'assigned_to' => $followUp['assigned_to'] ?? null,
                    'status' => FollowUpStatus::Pending,
                ]);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice('{1} Follow-up scheduled.|[2,*] :count follow-ups scheduled.', count($validated['follow_ups'])),
        ]);

        return to_route('health-checks.show', $healthCheck);
    }
}
