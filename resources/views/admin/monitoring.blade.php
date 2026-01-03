<x-app-layout>
<div class="container mx-auto p-4 max-w-7xl">
    <!-- Tab Navigation -->
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Admin Console</h1>
            <nav class="flex gap-2">
                <a href="{{ route('admin.monitoring') }}"
                   class="px-4 py-2 text-sm font-medium rounded-lg transition-colors bg-blue-600 text-white">
                    Monitoring
                </a>
                <a href="{{ route('admin.logs') }}"
                   class="px-4 py-2 text-sm font-medium rounded-lg transition-colors text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700">
                    Logs
                </a>
            </nav>
        </div>
        <div class="flex items-center gap-4">
            <span id="last-updated" class="text-sm text-gray-500 dark:text-gray-400"></span>
            <button onclick="fetchData()" class="px-3 py-1 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                Refresh
            </button>
        </div>
    </div>

    <!-- Warnings Banner -->
    <div id="warnings" class="mb-6 space-y-2"></div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Queue Metrics -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                <h2 class="font-semibold text-gray-900 dark:text-gray-100 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                    Queue Metrics
                </h2>
            </div>
            <div id="queues-content" class="p-4">
                <div class="animate-pulse space-y-2">
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4"></div>
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/2"></div>
                </div>
            </div>
        </div>

        <!-- Database Table Counts -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                <h2 class="font-semibold text-gray-900 dark:text-gray-100 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
                    </svg>
                    Database Tables
                </h2>
            </div>
            <div id="tables-content" class="p-4">
                <div class="animate-pulse space-y-2">
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-full"></div>
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-full"></div>
                </div>
            </div>
        </div>

        <!-- Failed Jobs -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                <h2 class="font-semibold text-gray-900 dark:text-gray-100 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    Failed Jobs
                    <span id="failed-count-badge" class="ml-2 hidden px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200"></span>
                </h2>
            </div>
            <div id="failures-content" class="p-4">
                <div class="animate-pulse space-y-2">
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4"></div>
                </div>
            </div>
        </div>

        <!-- Ingestion Activity -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                <h2 class="font-semibold text-gray-900 dark:text-gray-100 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Ingestion Activity
                </h2>
            </div>
            <div id="ingest-content" class="p-4">
                <div class="animate-pulse space-y-2">
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4"></div>
                </div>
            </div>
        </div>

        <!-- Job Heartbeats -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                <h2 class="font-semibold text-gray-900 dark:text-gray-100 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                    Job Heartbeats
                </h2>
            </div>
            <div id="heartbeats-content" class="p-4">
                <div class="animate-pulse space-y-2">
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4"></div>
                </div>
            </div>
        </div>

        <!-- Environment / Health -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                <h2 class="font-semibold text-gray-900 dark:text-gray-100 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Environment / Health
                </h2>
            </div>
            <div id="env-content" class="p-4">
                <div class="animate-pulse space-y-2">
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const DATA_URL = '{{ route('admin.monitoring.data') }}';
let refreshInterval;

async function fetchData() {
    try {
        const res = await fetch(DATA_URL, { credentials: 'same-origin' });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        renderData(data);
    } catch (err) {
        document.getElementById('warnings').innerHTML = `
            <div class="p-4 rounded-lg bg-red-100 dark:bg-red-900/30 border border-red-200 dark:border-red-800">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-red-600 dark:text-red-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="text-red-800 dark:text-red-200 font-medium">Error fetching monitoring data: ${err.message}</span>
                </div>
            </div>`;
    }
}

function renderData(d) {
    // Update timestamp
    document.getElementById('last-updated').textContent = `Last updated: ${d.generated_at_human}`;

    // Render warnings
    renderWarnings(d.warnings);

    // Render queue metrics
    renderQueues(d.queues);

    // Render table counts
    renderTables(d.tables);

    // Render failed jobs
    renderFailedJobs(d.failed_jobs);

    // Render ingestion activity
    renderIngestion(d.ingestion_activity);

    // Render heartbeats
    renderHeartbeats(d.heartbeats);

    // Render environment
    renderEnvironment(d.env);
}

function renderWarnings(warnings) {
    const container = document.getElementById('warnings');
    if (!warnings || warnings.length === 0) {
        container.innerHTML = '';
        return;
    }

    container.innerHTML = warnings.map(w => {
        const colors = w.level === 'error'
            ? 'bg-red-100 dark:bg-red-900/30 border-red-200 dark:border-red-800 text-red-800 dark:text-red-200'
            : 'bg-yellow-100 dark:bg-yellow-900/30 border-yellow-200 dark:border-yellow-800 text-yellow-800 dark:text-yellow-200';
        const icon = w.level === 'error'
            ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'
            : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>';

        return `<div class="p-3 rounded-lg border ${colors} flex items-center">
            <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">${icon}</svg>
            <span class="font-medium">${w.message}</span>
        </div>`;
    }).join('');
}

function renderQueues(queues) {
    const container = document.getElementById('queues-content');

    if (!queues.redis_available) {
        container.innerHTML = `<div class="text-yellow-600 dark:text-yellow-400 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            Redis not available
        </div>`;
        return;
    }

    const queueRows = Object.entries(queues.queues).map(([name, q]) => {
        const depthClass = q.warning
            ? 'text-red-600 dark:text-red-400 font-bold'
            : q.depth > 0
                ? 'text-yellow-600 dark:text-yellow-400'
                : 'text-green-600 dark:text-green-400';
        return `<div class="flex justify-between items-center py-1">
            <span class="text-gray-700 dark:text-gray-300">${name}</span>
            <span class="${depthClass}">${q.depth} jobs</span>
        </div>`;
    }).join('');

    container.innerHTML = `
        <div class="text-sm text-gray-500 dark:text-gray-400 mb-3">
            Connection: <span class="font-medium text-gray-700 dark:text-gray-300">${queues.connection}</span>
            (driver: <span class="font-medium text-gray-700 dark:text-gray-300">${queues.driver}</span>)
        </div>
        <div class="space-y-1 divide-y divide-gray-100 dark:divide-gray-700">${queueRows}</div>
    `;
}

function renderTables(tables) {
    const container = document.getElementById('tables-content');

    const rows = Object.entries(tables).map(([name, t]) => {
        if (!t.exists) {
            return `<div class="flex justify-between items-center py-1">
                <span class="text-gray-400 dark:text-gray-500">${name}</span>
                <span class="text-gray-400 dark:text-gray-500 text-sm">missing</span>
            </div>`;
        }

        let deltaHtml = '';
        if (t.delta !== null && t.delta !== 0) {
            const deltaClass = t.delta > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400';
            const deltaSign = t.delta > 0 ? '+' : '';
            deltaHtml = `<span class="${deltaClass} text-xs ml-2">${deltaSign}${t.delta}</span>`;
        }

        return `<div class="flex justify-between items-center py-1">
            <span class="text-gray-700 dark:text-gray-300">${name}</span>
            <span class="font-mono text-gray-900 dark:text-gray-100">${t.formatted}${deltaHtml}</span>
        </div>`;
    }).join('');

    container.innerHTML = `<div class="space-y-1 divide-y divide-gray-100 dark:divide-gray-700">${rows}</div>`;
}

function renderFailedJobs(failed) {
    const container = document.getElementById('failures-content');
    const badge = document.getElementById('failed-count-badge');

    if (failed.count > 0) {
        badge.textContent = failed.count;
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }

    if (!failed.exists) {
        container.innerHTML = '<div class="text-gray-500 dark:text-gray-400">Table not available</div>';
        return;
    }

    if (failed.count === 0) {
        container.innerHTML = `<div class="text-green-600 dark:text-green-400 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            No failed jobs
        </div>`;
        return;
    }

    const rows = failed.recent.map(r => `
        <div class="py-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
            <div class="flex justify-between items-start">
                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">#${r.id}</span>
                <span class="text-xs text-gray-500 dark:text-gray-400">${r.failed_at_human}</span>
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Queue: ${r.queue}</div>
            <div class="text-xs text-red-600 dark:text-red-400 mt-1 truncate" title="${r.exception || ''}">${r.exception || 'No exception info'}</div>
        </div>
    `).join('');

    container.innerHTML = `
        <div class="text-sm font-medium text-red-600 dark:text-red-400 mb-3">${failed.count} total failed jobs</div>
        <div class="max-h-64 overflow-y-auto">${rows}</div>
    `;
}

function renderIngestion(activity) {
    const container = document.getElementById('ingest-content');

    let html = '';

    if (activity.last_activity) {
        const statusClass = activity.warning
            ? 'text-yellow-600 dark:text-yellow-400'
            : 'text-green-600 dark:text-green-400';
        html += `<div class="text-sm mb-4">Last activity: <span class="${statusClass} font-medium">${activity.minutes_since_activity} minutes ago</span></div>`;
    }

    ['wikidata', 'musicbrainz'].forEach(source => {
        const items = activity[source] || [];
        const sourceTitle = source.charAt(0).toUpperCase() + source.slice(1);

        html += `<div class="mb-4 last:mb-0">
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">${sourceTitle}</h4>`;

        if (items.length === 0) {
            html += '<div class="text-sm text-gray-400 dark:text-gray-500">No recent activity</div>';
        } else {
            html += '<div class="space-y-1 max-h-32 overflow-y-auto">';
            items.forEach(item => {
                html += `<div class="text-xs flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400 truncate mr-2">${item.name || item.query || 'Query'}</span>
                    <span class="text-gray-400 dark:text-gray-500 flex-shrink-0">${item.at_human}</span>
                </div>`;
            });
            html += '</div>';
        }

        html += '</div>';
    });

    container.innerHTML = html || '<div class="text-gray-500 dark:text-gray-400">No ingestion data available</div>';
}

function renderHeartbeats(heartbeats) {
    const container = document.getElementById('heartbeats-content');

    if (!heartbeats.summary.available) {
        container.innerHTML = '<div class="text-gray-500 dark:text-gray-400">Heartbeat tracking not available (run migrations)</div>';
        return;
    }

    let html = '';

    // Summary
    const summary = heartbeats.summary;
    if (summary.last_activity) {
        html += `<div class="text-sm mb-3">Last job: <span class="font-medium text-gray-900 dark:text-gray-100">${summary.last_job}</span></div>`;
    }

    // Recent heartbeats
    const recent = heartbeats.recent || [];
    if (recent.length === 0) {
        html += '<div class="text-gray-500 dark:text-gray-400">No recent heartbeats</div>';
    } else {
        html += '<div class="space-y-1 max-h-48 overflow-y-auto">';
        recent.forEach(h => {
            const metricClass = h.metric === 'completed' ? 'text-green-600 dark:text-green-400'
                : h.metric === 'failed' ? 'text-red-600 dark:text-red-400'
                : h.metric === 'started' ? 'text-blue-600 dark:text-blue-400'
                : 'text-gray-600 dark:text-gray-400';

            let contextInfo = '';
            if (h.context) {
                if (h.context.percent !== undefined) {
                    contextInfo = ` (${h.context.percent}%)`;
                } else if (h.context.error) {
                    contextInfo = ` - ${h.context.error.substring(0, 50)}...`;
                }
            }

            html += `<div class="text-xs flex justify-between items-center py-1">
                <span class="text-gray-700 dark:text-gray-300">${h.job}</span>
                <span class="${metricClass}">${h.metric}${contextInfo}</span>
            </div>`;
        });
        html += '</div>';
    }

    container.innerHTML = html;
}

function renderEnvironment(env) {
    const container = document.getElementById('env-content');

    const envClass = env.app_env === 'production'
        ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
        : env.app_env === 'staging'
            ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
            : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';

    container.innerHTML = `
        <div class="grid grid-cols-2 gap-3 text-sm">
            <div>
                <span class="text-gray-500 dark:text-gray-400">Environment</span>
                <div class="mt-1"><span class="px-2 py-0.5 rounded text-xs font-medium ${envClass}">${env.app_env}</span></div>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400">Debug Mode</span>
                <div class="mt-1 font-medium text-gray-900 dark:text-gray-100">${env.app_debug ? 'Enabled' : 'Disabled'}</div>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400">PHP Version</span>
                <div class="mt-1 font-medium text-gray-900 dark:text-gray-100">${env.php_version}</div>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400">Laravel Version</span>
                <div class="mt-1 font-medium text-gray-900 dark:text-gray-100">${env.laravel_version}</div>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400">Queue Driver</span>
                <div class="mt-1 font-medium text-gray-900 dark:text-gray-100">${env.queue_connection}</div>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400">Cache Driver</span>
                <div class="mt-1 font-medium text-gray-900 dark:text-gray-100">${env.cache_driver}</div>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400">Database</span>
                <div class="mt-1 font-medium text-gray-900 dark:text-gray-100">${env.db_connection}</div>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400">Git Commit</span>
                <div class="mt-1 font-mono text-xs text-gray-900 dark:text-gray-100">${env.git_commit || 'n/a'}</div>
            </div>
        </div>
    `;
}

// Initial fetch and start polling
fetchData();
refreshInterval = setInterval(fetchData, 7000);

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (refreshInterval) clearInterval(refreshInterval);
});
</script>
</x-app-layout>
