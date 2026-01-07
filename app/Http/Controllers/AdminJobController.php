<?php

namespace App\Http\Controllers;

use App\Support\AdminJobManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminJobController extends Controller
{
    public function __construct(protected AdminJobManager $jobs)
    {
    }

    public function index(): \Illuminate\Contracts\View\View
    {
        Gate::authorize('viewAdminDashboard');

        return view('admin.jobs');
    }

    public function data(): \Illuminate\Http\JsonResponse
    {
        Gate::authorize('viewAdminDashboard');

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'queue_connection' => $this->jobs->queueConnection(),
            'queue_driver' => $this->jobs->queueDriver(),
            'jobs' => $this->jobs->jobsWithStatus(),
            'failed_jobs' => $this->jobs->failedJobsSummary(),
        ]);
    }

    public function dispatch(Request $request): \Illuminate\Http\JsonResponse
    {
        Gate::authorize('viewAdminDashboard');

        $validated = $request->validate([
            'job_key' => 'required|string',
            'params' => 'nullable|array',
        ]);

        $result = $this->jobs->dispatchJob($validated['job_key'], $validated['params'] ?? []);

        if (! $result['dispatched']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Unable to dispatch job',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? 'Job dispatched',
            'job' => $result['job'] ?? null,
        ]);
    }

    public function cancel(Request $request): \Illuminate\Http\JsonResponse
    {
        Gate::authorize('viewAdminDashboard');

        $validated = $request->validate([
            'job_key' => 'required|string',
        ]);

        $result = $this->jobs->cancelJob($validated['job_key']);

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Unable to cancel jobs',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? 'Jobs cancelled',
            'removed' => $result['removed'] ?? [],
            'cancelled_runs' => $result['cancelled_runs'] ?? 0,
        ]);
    }

    public function clearFailed(Request $request): \Illuminate\Http\JsonResponse
    {
        Gate::authorize('viewAdminDashboard');

        $validated = $request->validate([
            'signature' => 'nullable|string',
        ]);

        $result = $this->jobs->clearFailedJobs($validated['signature'] ?? null);

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Unable to clear failed jobs',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? 'Failed jobs cleared',
            'cleared' => $result['cleared'] ?? 0,
        ]);
    }

    public function retryFailed(Request $request): \Illuminate\Http\JsonResponse
    {
        Gate::authorize('viewAdminDashboard');

        $validated = $request->validate([
            'signature' => 'nullable|string',
        ]);

        $result = $this->jobs->retryFailedJobs($validated['signature'] ?? null);

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Unable to retry failed jobs',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? 'Failed jobs retried',
            'retried' => $result['retried'] ?? 0,
        ]);
    }
}
