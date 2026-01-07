<x-main-layout title="Admin Jobs - {{ config('app.name', 'Spin Source') }}" :showRecentReviews="false">
    <div class="container mx-auto p-4 max-w-7xl">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <h1 class="text-2xl font-bold text-gray-100">Admin Console</h1>
                <nav class="flex gap-1 ml-4">
                    <a href="{{ route('admin.monitoring') }}"
                       class="px-4 py-2 text-sm font-medium rounded-lg text-gray-400 hover:bg-gray-800 hover:text-gray-200 transition-colors">
                        Monitoring
                    </a>
                    <a href="{{ route('admin.logs') }}"
                       class="px-4 py-2 text-sm font-medium rounded-lg text-gray-400 hover:bg-gray-800 hover:text-gray-200 transition-colors">
                        Logs
                    </a>
                    <a href="{{ route('admin.jobs') }}"
                       class="px-4 py-2 text-sm font-medium rounded-lg bg-gray-700 text-white">
                        Jobs
                    </a>
                </nav>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-sm text-gray-500" id="queue-meta"></div>
                <span id="last-updated" class="text-sm text-gray-500"></span>
                <button id="refresh-btn" onclick="fetchJobs(true)" class="px-3 py-1.5 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors flex items-center gap-2">
                    <svg id="refresh-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <span id="refresh-text">Refresh</span>
                </button>
            </div>
        </div>

        <div id="alert" class="hidden mb-4 p-3 rounded-lg border bg-gray-800/70"></div>

        <div class="mb-4 flex gap-2">
            <button onclick="switchTab('jobs')" id="tab-jobs" class="px-3 py-1.5 text-sm rounded-lg bg-blue-600 text-white border border-blue-700">
                Jobs
            </button>
            <button onclick="switchTab('failed')" id="tab-failed" class="px-3 py-1.5 text-sm rounded-lg bg-gray-800 text-gray-200 border border-gray-700 hover:bg-gray-700">
                Failed Jobs
            </button>
        </div>

        <div id="jobs-container" class="grid grid-cols-1 gap-4">
            <div class="p-6 rounded-lg border border-gray-700 bg-gray-800">
                <div class="animate-pulse space-y-2">
                    <div class="h-4 bg-gray-700 rounded w-3/4"></div>
                    <div class="h-4 bg-gray-700 rounded w-1/2"></div>
                    <div class="h-4 bg-gray-700 rounded w-2/3"></div>
                </div>
            </div>
        </div>

        <div id="failed-container" class="hidden grid grid-cols-1 gap-4">
            <div class="p-6 rounded-lg border border-gray-700 bg-gray-800">
                <div class="animate-pulse space-y-2">
                    <div class="h-4 bg-gray-700 rounded w-3/4"></div>
                    <div class="h-4 bg-gray-700 rounded w-1/2"></div>
                </div>
            </div>
        </div>
    </div>

<script>
const DATA_URL = '{{ route('api.admin.jobs.data') }}';
const DISPATCH_URL = '{{ route('api.admin.jobs.dispatch') }}';
const CANCEL_URL = '{{ route('api.admin.jobs.cancel') }}';
const CLEAR_FAILED_URL = '{{ route('api.admin.jobs.failed.clear') }}';
const RETRY_FAILED_URL = '{{ route('api.admin.jobs.failed.retry') }}';

let refreshInterval;
let currentTab = 'jobs';
let lastPayload = null;

async function fetchJobs(manual = false) {
    const btn = document.getElementById('refresh-btn');
    const icon = document.getElementById('refresh-icon');
    const text = document.getElementById('refresh-text');

    if (manual && btn) {
        btn.disabled = true;
        btn.classList.add('opacity-75');
        icon.classList.add('animate-spin');
        text.textContent = 'Loading...';
    }

    try {
        const res = await fetch(DATA_URL, { credentials: 'same-origin' });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        lastPayload = data;
        renderActiveView();

        if (manual && btn) {
            text.textContent = 'Updated';
            btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
            btn.classList.add('bg-green-600');
            setTimeout(() => {
                text.textContent = 'Refresh';
                btn.classList.remove('bg-green-600');
                btn.classList.add('bg-blue-600', 'hover:bg-blue-700');
            }, 800);
        }
    } catch (err) {
        showAlert(`Failed to load jobs: ${err.message}`, 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.classList.remove('opacity-75');
            icon.classList.remove('animate-spin');
        }
    }
}

function renderActiveView() {
    if (!lastPayload) return;

    if (currentTab === 'jobs') {
        document.getElementById('jobs-container').classList.remove('hidden');
        document.getElementById('failed-container').classList.add('hidden');
        document.getElementById('tab-jobs').classList.add('bg-blue-600', 'text-white', 'border-blue-700');
        document.getElementById('tab-jobs').classList.remove('bg-gray-800', 'text-gray-200', 'border-gray-700');
        document.getElementById('tab-failed').classList.add('bg-gray-800', 'text-gray-200', 'border-gray-700');
        document.getElementById('tab-failed').classList.remove('bg-blue-600', 'text-white', 'border-blue-700');
        renderJobs(lastPayload);
    } else {
        document.getElementById('failed-container').classList.remove('hidden');
        document.getElementById('jobs-container').classList.add('hidden');
        document.getElementById('tab-failed').classList.add('bg-blue-600', 'text-white', 'border-blue-700');
        document.getElementById('tab-failed').classList.remove('bg-gray-800', 'text-gray-200', 'border-gray-700');
        document.getElementById('tab-jobs').classList.add('bg-gray-800', 'text-gray-200', 'border-gray-700');
        document.getElementById('tab-jobs').classList.remove('bg-blue-600', 'text-white', 'border-blue-700');
        renderFailedJobs(lastPayload.failed_jobs);
    }
}

function switchTab(tab) {
    if (tab === currentTab) return;
    currentTab = tab;
    renderActiveView();
}

function renderJobs(payload) {
    const { jobs = [], generated_at, queue_connection, queue_driver } = payload;
    document.getElementById('last-updated').textContent = generated_at
        ? `Updated: ${new Date(generated_at).toLocaleTimeString()}`
        : '';
    document.getElementById('queue-meta').textContent = `Queue: ${queue_connection} (${queue_driver})`;

    if (!jobs.length) {
        document.getElementById('jobs-container').innerHTML = '<div class="p-6 rounded-lg border border-gray-700 bg-gray-800 text-gray-400">No jobs available.</div>';
        return;
    }

    const grouped = jobs.reduce((acc, job) => {
        const key = job.category || 'Jobs';
        if (!acc[key]) acc[key] = [];
        acc[key].push(job);
        return acc;
    }, {});

    const sections = Object.keys(grouped).sort().map(group => `
        <div>
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold text-gray-100">${group}</h2>
                <span class="text-xs text-gray-500">${grouped[group].length} job${grouped[group].length === 1 ? '' : 's'}</span>
            </div>
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                ${grouped[group].map(renderJobCard).join('')}
            </div>
        </div>
    `).join('');

    document.getElementById('jobs-container').innerHTML = sections;
}

function renderJobCard(job) {
    const counts = job.queue_counts || {};
    const running = job.running || [];
    const lastRun = job.last_run;
    const lastSuccess = job.last_success;

    const queueHtml = counts.supported
        ? `<div class="text-sm text-gray-300 flex flex-wrap gap-3">
                <span class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full ${counts.waiting > 0 ? 'bg-yellow-400' : 'bg-green-500'}"></span>
                    Waiting: <strong>${counts.waiting}</strong>
                </span>
                <span class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full ${counts.reserved > 0 ? 'bg-yellow-400' : 'bg-gray-500'}"></span>
                    Running/Reserved: <strong>${counts.reserved}</strong>
                </span>
                <span class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full ${counts.delayed > 0 ? 'bg-orange-400' : 'bg-gray-500'}"></span>
                    Delayed: <strong>${counts.delayed}</strong>
                </span>
            </div>`
        : `<div class="text-sm text-yellow-400">${counts.message || 'Queue counts unavailable'}</div>`;

    const runningHtml = running.length
        ? running.map(run => `
                <div class="px-2 py-1 rounded bg-blue-900/30 border border-blue-700 text-xs text-blue-100">
                    <div class="flex items-center justify-between">
                        <span>${run.started_at_human || 'Unknown start'}</span>
                        ${run.duration_human ? `<span class="text-gray-400 ml-2">${run.duration_human}</span>` : ''}
                    </div>
                </div>
            `).join('')
        : '<div class="text-sm text-gray-500">No running jobs</div>';

    const lastRunHtml = lastRun
        ? `<div class="flex items-center gap-2 text-sm text-gray-300">
                <span class="px-2 py-0.5 rounded text-xs ${statusBadgeClass(lastRun.status)}">${lastRun.status}</span>
                <span>${lastRun.finished_at_human || lastRun.started_at_human || 'In progress'}</span>
            </div>`
        : '<div class="text-sm text-gray-500">Never started</div>';

    const lastSuccessHtml = lastSuccess
        ? `<div class="text-sm text-gray-300">Last success: ${lastSuccess.finished_at_human || lastSuccess.started_at_human}</div>`
        : '<div class="text-sm text-gray-500">Last success: never</div>';

    const paramsHint = job.requires_params
        ? `<div class="text-xs text-amber-300 mt-2">${job.params_help || 'Requires parameters to run.'}</div>`
        : '';

    return `
        <div class="p-5 rounded-lg border border-gray-700 bg-gray-800 shadow">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-lg font-semibold text-gray-100">${job.label}</h3>
                        <span class="px-2 py-0.5 text-xs rounded bg-gray-700 text-gray-200">${job.queue}</span>
                    </div>
                    <p class="text-sm text-gray-400 mt-1">${job.description}</p>
                    ${paramsHint}
                </div>
                <div class="flex flex-col gap-2">
                    <button data-dispatch="${job.key}" onclick="dispatchJob('${job.key}')" class="px-3 py-1.5 text-sm bg-green-600 hover:bg-green-700 text-white rounded transition-colors">Start job</button>
                    <button data-cancel="${job.key}" onclick="cancelJob('${job.key}')" class="px-3 py-1.5 text-sm bg-red-600 hover:bg-red-700 text-white rounded transition-colors">Cancel queued/running</button>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-4">
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">Queue</div>
                    ${queueHtml}
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">Running</div>
                    <div class="space-y-1.5">${runningHtml}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">Recent Activity</div>
                    ${lastRunHtml}
                    ${lastSuccessHtml}
                </div>
            </div>
        </div>
    `;
}

function statusBadgeClass(status) {
    switch (status) {
        case 'success':
            return 'bg-green-900 text-green-200 border border-green-700';
        case 'failed':
            return 'bg-red-900 text-red-200 border border-red-700';
        default:
            return 'bg-blue-900 text-blue-200 border border-blue-700';
    }
}

async function dispatchJob(jobKey) {
    const btn = document.querySelector(`[data-dispatch="${jobKey}"]`);
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const job = lastPayload?.jobs?.find(item => item.key === jobKey);
    let params = null;

    if (btn) {
        btn.disabled = true;
        btn.classList.add('opacity-75');
        btn.textContent = 'Dispatching...';
    }

    try {
        if (job?.requires_params) {
            const promptText = job.params_help
                ? `Enter JSON parameters for ${job.label}.\n${job.params_help}`
                : `Enter JSON parameters for ${job.label}.`;
            const example = job.params_example || '{}';
            const input = prompt(promptText, example);
            if (input === null) {
                return;
            }

            try {
                params = JSON.parse(input);
            } catch (parseError) {
                throw new Error(`Invalid JSON: ${parseError.message}`);
            }

            if (params === null || Array.isArray(params) || typeof params !== 'object') {
                throw new Error('Parameters must be a JSON object.');
            }
        }

        const res = await fetch(DISPATCH_URL, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: JSON.stringify({ job_key: jobKey, params }),
        });

        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.message || `HTTP ${res.status}`);
        }

        showAlert(data.message || 'Job dispatched', 'success');
        fetchJobs();
    } catch (err) {
        showAlert(`Dispatch failed: ${err.message}`, 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.classList.remove('opacity-75');
            btn.textContent = 'Start job';
        }
    }
}

async function cancelJob(jobKey) {
    const btn = document.querySelector(`[data-cancel="${jobKey}"]`);
    const token = document.querySelector('meta[name="csrf-token"]').content;

    if (!confirm('Cancel queued and running jobs for this type?')) {
        return;
    }

    if (btn) {
        btn.disabled = true;
        btn.classList.add('opacity-75');
        btn.textContent = 'Cancelling...';
    }

    try {
        const res = await fetch(CANCEL_URL, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: JSON.stringify({ job_key: jobKey }),
        });

        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.message || `HTTP ${res.status}`);
        }

        const removed = data.removed || {};
        const removedSummary = [`${removed.waiting || 0} waiting`, `${removed.reserved || 0} reserved`, `${removed.delayed || 0} delayed`].join(', ');

        showAlert(`${data.message || 'Jobs cancelled'} (${removedSummary}; runs cancelled: ${data.cancelled_runs ?? 0})`, 'success');
        fetchJobs();
    } catch (err) {
        showAlert(`Cancel failed: ${err.message}`, 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.classList.remove('opacity-75');
            btn.textContent = 'Cancel queued/running';
        }
    }
}

function renderFailedJobs(failed) {
    const container = document.getElementById('failed-container');

    if (!failed || !failed.exists) {
        container.innerHTML = '<div class="p-6 rounded-lg border border-gray-700 bg-gray-800 text-yellow-300">Failed jobs table not available.</div>';
        return;
    }

    if (failed.count === 0 || !failed.groups || failed.groups.length === 0) {
        container.innerHTML = '<div class="p-6 rounded-lg border border-gray-700 bg-gray-800 text-green-400 flex items-center gap-2"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>No failed jobs 🎉</div>';
        return;
    }

    const groupsHtml = failed.groups.map(group => {
        const queueBadges = group.queues.map(q => `<span class="px-2 py-0.5 rounded text-xs bg-gray-700 text-gray-200">${q.queue}: ${q.count}</span>`).join(' ');
        return `<div class="p-5 rounded-lg border border-gray-700 bg-gray-800 shadow space-y-3">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-sm text-red-300">Latest: ${group.latest_failed_at_human}</div>
                    <div class="text-lg font-semibold text-gray-100 mt-1">${group.message}</div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-2 py-1 rounded text-xs bg-red-900 text-red-200 border border-red-700">${group.count} failure${group.count === 1 ? '' : 's'}</span>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">${queueBadges || '<span class="text-sm text-gray-500">Unknown queue</span>'}</div>
            <div class="flex flex-wrap gap-2">
                <button onclick="retryFailed('${group.signature}')" class="px-3 py-1.5 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded transition-colors">Retry group</button>
                <button onclick="clearFailed('${group.signature}')" class="px-3 py-1.5 text-sm bg-red-600 hover:bg-red-700 text-white rounded transition-colors">Clear group</button>
            </div>
        </div>`;
    }).join('');

    container.innerHTML = `
        <div class="flex items-center justify-between mb-2">
            <div class="text-sm text-gray-400">${failed.count} total failed jobs</div>
            <div class="flex gap-2">
                <button onclick="retryFailed()" class="px-3 py-1.5 text-sm bg-blue-700 hover:bg-blue-800 text-white rounded transition-colors">Retry all</button>
                <button onclick="clearFailed()" class="px-3 py-1.5 text-sm bg-red-700 hover:bg-red-800 text-white rounded transition-colors">Clear all</button>
            </div>
        </div>
        <div class="grid grid-cols-1 gap-4">${groupsHtml}</div>
    `;
}

async function clearFailed(signature = null) {
    if (!confirm(signature ? 'Clear failed jobs for this error?' : 'Clear ALL failed jobs? This cannot be undone.')) {
        return;
    }

    const token = document.querySelector('meta[name=\"csrf-token\"]').content;
    try {
        const res = await fetch(CLEAR_FAILED_URL, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: JSON.stringify({ signature }),
        });

        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.message || `HTTP ${res.status}`);
        }

        showAlert(data.message || 'Failed jobs cleared', 'success');
        fetchJobs();
    } catch (err) {
        showAlert(`Clear failed jobs failed: ${err.message}`, 'error');
    }
}

async function retryFailed(signature = null) {
    const token = document.querySelector('meta[name=\"csrf-token\"]').content;
    try {
        const res = await fetch(RETRY_FAILED_URL, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: JSON.stringify({ signature }),
        });

        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.message || `HTTP ${res.status}`);
        }

        showAlert(data.message || 'Failed jobs retried', 'success');
        fetchJobs();
    } catch (err) {
        showAlert(`Retry failed jobs failed: ${err.message}`, 'error');
    }
}

function showAlert(message, type = 'info') {
    const el = document.getElementById('alert');
    const color = type === 'success'
        ? 'border-green-700 bg-green-900/30 text-green-100'
        : type === 'error'
            ? 'border-red-700 bg-red-900/30 text-red-100'
            : 'border-gray-700 bg-gray-800/60 text-gray-200';

    el.className = `mb-4 p-3 rounded-lg border ${color}`;
    el.textContent = message;
    el.classList.remove('hidden');

    setTimeout(() => {
        el.classList.add('hidden');
    }, 4000);
}

// Initial load and polling
fetchJobs();
refreshInterval = setInterval(fetchJobs, 9000);

window.addEventListener('beforeunload', () => {
    if (refreshInterval) clearInterval(refreshInterval);
});
</script>
</x-main-layout>
