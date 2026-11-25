<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\StoreHealthCheckAttachmentRequest;
use App\Models\AuditLog;
use App\Models\HealthCheck;
use App\Models\HealthCheckAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HealthCheckAttachmentController extends Controller
{
    /**
     * Upload files (such as lab results) to a health check.
     */
    public function store(StoreHealthCheckAttachmentRequest $request, HealthCheck $healthCheck): RedirectResponse
    {
        /** @var array<int, UploadedFile> $files */
        $files = $request->file('attachments', []);

        foreach ($files as $file) {
            $healthCheck->storeAttachment($file);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Files uploaded.')]);

        return to_route('health-checks.show', $healthCheck);
    }

    /**
     * Download an attachment from the private disk.
     */
    public function show(HealthCheckAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment->healthCheck);

        AuditLog::record(AuditAction::Downloaded, $attachment, 'Downloaded '.$attachment->auditLabel());

        return Storage::disk(HealthCheckAttachment::DISK)->download($attachment->path, $attachment->original_name);
    }

    /**
     * Delete an attachment and its stored file.
     */
    public function destroy(HealthCheckAttachment $attachment): RedirectResponse
    {
        Gate::authorize('update', $attachment->healthCheck);

        $healthCheckId = $attachment->health_check_id;

        $attachment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('File deleted.')]);

        return to_route('health-checks.show', $healthCheckId);
    }
}
