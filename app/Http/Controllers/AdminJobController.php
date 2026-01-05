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

        $jobs = [
            'data' => [],
            'error' => null,
        ];

        $failedJobs = [
            'exists' => false,
            'count' => 0,
            'groups' => [],
            'error' => null,
        ];

        try {
            $jobs['data'] = $this->jobs->jobsWithStatus();
        } catch (\Throwable $e) {
            report($e);
            $jobs['error'] = 'Failed to load jobs';
        }

        try {
            $summary = $this->jobs->failedJobsSummary();
            $failedJobs = array_merge($summary, ['error' => null]);
        } catch (\Throwable $e) {
            report($e);
            $failedJobs['error'] = 'Failed to load failed jobs';
        }

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'queue_connection' => $this->jobs->queueConnection(),
            'queue_driver' => $this->jobs->queueDriver(),
            'jobs' => $jobs['data'],
            'jobs_error' => $jobs['error'],
            'failed_jobs' => $failedJobs,
        ]);
    }

    public function dispatch(Request $request): \Illuminate\Http\JsonResponse
    {
        Gate::authorize('viewAdminDashboard');

        $validated = $request->validate([
            'job_key' => 'required|string',
        ]);

        $result = $this->jobs->dispatchJob($validated['job_key']);

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
