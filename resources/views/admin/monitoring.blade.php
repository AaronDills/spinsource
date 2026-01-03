<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Admin Monitoring - {{ config('app.name', 'Laravel') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-gray-900 min-h-screen font-sans antialiased" style="background-color: #111827 !important;">
        <div class="container mx-auto p-4 max-w-7xl">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-4">
                    <a href="/" class="text-gray-400 hover:text-gray-200 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </a>
                    <h1 class="text-2xl font-bold text-gray-100">Admin Console</h1>
                    <nav class="flex gap-1 ml-4">
                        <a href="{{ route('admin.monitoring') }}"
                           class="px-4 py-2 text-sm font-medium rounded-lg bg-gray-700 text-white">
                            Monitoring
                        </a>
                        <a href="{{ route('admin.logs') }}"
                           class="px-4 py-2 text-sm font-medium rounded-lg text-gray-400 hover:bg-gray-800 hover:text-gray-200 transition-colors">
                            Logs
                        </a>
                    </nav>
                </div>
                <div class="flex items-center gap-4">
                    <span id="last-updated" class="text-sm text-gray-500"></span>
                    <button id="refresh-btn" onclick="fetchData(true)" class="px-3 py-1.5 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors flex items-center gap-2">
                        <svg id="refresh-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span id="refresh-text">Refresh</span>
                    </button>
                </div>
            </div>

            <!-- Warnings Banner -->
            <div id="warnings" class="mb-6 space-y-2"></div>

            <!-- Main Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- Queue Metrics -->
                <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden border border-gray-700">
                    <div class="px-4 py-3 bg-gray-800/50 border-b border-gray-700">
                        <h2 class="font-semibold text-gray-100 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                            Queue Metrics
                        </h2>
                    </div>
                    <div id="queues-content" class="p-4">
                        <div class="animate-pulse space-y-2">
                            <div class="h-4 bg-gray-700 rounded w-3/4"></div>
                            <div class="h-4 bg-gray-700 rounded w-1/2"></div>
                        </div>
                    </div>
                </div>

                <!-- Database Table Counts -->
                <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden border border-gray-700">
                    <div class="px-4 py-3 bg-gray-800/50 border-b border-gray-700">
                        <h2 class="font-semibold text-gray-100 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
                            </svg>
                            Database Tables
                        </h2>
                    </div>
                    <div id="tables-content" class="p-4">
                        <div class="animate-pulse space-y-2">
                            <div class="h-4 bg-gray-700 rounded w-full"></div>
                            <div class="h-4 bg-gray-700 rounded w-full"></div>
                        </div>
                    </div>
                </div>

                <!-- Failed Jobs -->
                <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden border border-gray-700">
                    <div class="px-4 py-3 bg-gray-800/50 border-b border-gray-700">
                        <h2 class="font-semibold text-gray-100 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            Failed Jobs
                            <span id="failed-count-badge" class="ml-2 hidden px-2 py-0.5 text-xs font-medium rounded-full bg-red-900 text-red-200"></span>
                        </h2>
                    </div>
                    <div id="failures-content" class="p-4">
                        <div class="animate-pulse space-y-2">
                            <div class="h-4 bg-gray-700 rounded w-3/4"></div>
                        </div>
                    </div>
                </div>

                <!-- Ingestion Activity -->
                <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden border border-gray-700">
                    <div class="px-4 py-3 bg-gray-800/50 border-b border-gray-700">
                        <h2 class="font-semibold text-gray-100 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                            </svg>
                            Ingestion Activity
                        </h2>
                    </div>
                    <div id="ingest-content" class="p-4">
                        <div class="animate-pulse space-y-2">
                            <div class="h-4 bg-gray-700 rounded w-3/4"></div>
                        </div>
                    </div>
                </div>

                <!-- Job Heartbeats -->
                <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden border border-gray-700">
                    <div class="px-4 py-3 bg-gray-800/50 border-b border-gray-700">
                        <h2 class="font-semibold text-gray-100 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                            Job Heartbeats
                        </h2>
                    </div>
                    <div id="heartbeats-content" class="p-4">
                        <div class="animate-pulse space-y-2">
                            <div class="h-4 bg-gray-700 rounded w-3/4"></div>
                        </div>
                    </div>
                </div>

                <!-- Environment / Health -->
                <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden border border-gray-700">
                    <div class="px-4 py-3 bg-gray-800/50 border-b border-gray-700">
                        <h2 class="font-semibold text-gray-100 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            Environment / Health
                        </h2>
                    </div>
                    <div id="env-content" class="p-4">
                        <div class="animate-pulse space-y-2">
                            <div class="h-4 bg-gray-700 rounded w-3/4"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<script>
const DATA_URL = '{{ route('admin.monitoring.data') }}';
let refreshInterval;

async function fetchData(manual = false) {
    const btn = document.getElementById('refresh-btn');
    const icon = document.getElementById('refresh-icon');
    const text = document.getElementById('refresh-text');

    // Show loading state if manual refresh
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
        renderData(data);

        // Flash success feedback if manual refresh
        if (manual && btn) {
            text.textContent = 'Updated!';
            btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
            btn.classList.add('bg-green-600');
            setTimeout(() => {
                text.textContent = 'Refresh';
                btn.classList.remove('bg-green-600');
                btn.classList.add('bg-blue-600', 'hover:bg-blue-700');
            }, 1000);
        }
    } catch (err) {
        document.getElementById('warnings').innerHTML = `
            <div class="p-4 rounded-lg bg-red-900/50 border border-red-700">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-red-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="text-red-200 font-medium">Error fetching monitoring data: ${err.message}</span>
                </div>
            </div>`;

        if (manual && btn) {
            text.textContent = 'Error';
            btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
            btn.classList.add('bg-red-600');
            setTimeout(() => {
                text.textContent = 'Refresh';
                btn.classList.remove('bg-red-600');
                btn.classList.add('bg-blue-600', 'hover:bg-blue-700');
            }, 2000);
        }
    } finally {
        // Reset button state
        if (btn) {
            btn.disabled = false;
            btn.classList.remove('opacity-75');
            icon.classList.remove('animate-spin');
        }
    }
}

function renderData(d) {
    document.getElementById('last-updated').textContent = `Last updated: ${d.generated_at_human}`;
    renderWarnings(d.warnings);
    renderQueues(d.queues);
    renderTables(d.tables);
    renderFailedJobs(d.failed_jobs);
    renderIngestion(d.ingestion_activity);
    renderHeartbeats(d.heartbeats);
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
            ? 'bg-red-900/50 border-red-700 text-red-200'
            : 'bg-yellow-900/50 border-yellow-700 text-yellow-200';
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
        container.innerHTML = `<div class="text-yellow-400 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            Redis not available
        </div>`;
        return;
    }

    const queueRows = Object.entries(queues.queues).map(([name, q]) => {
        const depthClass = q.warning
            ? 'text-red-400 font-bold'
            : q.depth > 0
                ? 'text-yellow-400'
                : 'text-green-400';
        return `<div class="flex justify-between items-center py-1.5">
            <span class="text-gray-300">${name}</span>
            <span class="${depthClass}">${q.depth} jobs</span>
        </div>`;
    }).join('');

    container.innerHTML = `
        <div class="text-sm text-gray-500 mb-3">
            Connection: <span class="font-medium text-gray-300">${queues.connection}</span>
            (driver: <span class="font-medium text-gray-300">${queues.driver}</span>)
        </div>
        <div class="space-y-0.5 divide-y divide-gray-700">${queueRows}</div>
    `;
}

function renderTables(tables) {
    const container = document.getElementById('tables-content');

    const rows = Object.entries(tables).map(([name, t]) => {
        if (!t.exists) {
            return `<div class="flex justify-between items-center py-1.5">
                <span class="text-gray-600">${name}</span>
                <span class="text-gray-600 text-sm">missing</span>
            </div>`;
        }

        let deltaHtml = '';
        if (t.delta !== null && t.delta !== 0) {
            const deltaClass = t.delta > 0 ? 'text-green-400' : 'text-red-400';
            const deltaSign = t.delta > 0 ? '+' : '';
            deltaHtml = `<span class="${deltaClass} text-xs ml-2">${deltaSign}${t.delta}</span>`;
        }

        return `<div class="flex justify-between items-center py-1.5">
            <span class="text-gray-300">${name}</span>
            <span class="font-mono text-gray-100">${t.formatted}${deltaHtml}</span>
        </div>`;
    }).join('');

    container.innerHTML = `<div class="space-y-0.5 divide-y divide-gray-700">${rows}</div>`;
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
        container.innerHTML = '<div class="text-gray-500">Table not available</div>';
        return;
    }

    if (failed.count === 0) {
        container.innerHTML = `<div class="text-green-400 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            No failed jobs
        </div>`;
        return;
    }

    const rows = failed.recent.map(r => `
        <div class="py-2 border-b border-gray-700 last:border-0">
            <div class="flex justify-between items-start">
                <span class="text-sm font-medium text-gray-100">#${r.id}</span>
                <span class="text-xs text-gray-500">${r.failed_at_human}</span>
            </div>
            <div class="text-xs text-gray-500 mt-1">Queue: ${r.queue}</div>
            <div class="text-xs text-red-400 mt-1 truncate" title="${r.exception || ''}">${r.exception || 'No exception info'}</div>
        </div>
    `).join('');

    container.innerHTML = `
        <div class="flex items-center justify-between mb-3">
            <div class="text-sm font-medium text-red-400">${failed.count} total failed jobs</div>
            <button onclick="clearFailedJobs()" id="clear-failed-btn" class="px-2 py-1 text-xs bg-red-600 hover:bg-red-700 text-white rounded transition-colors">
                Clear All
            </button>
        </div>
        <div class="max-h-64 overflow-y-auto">${rows}</div>
    `;
}

async function clearFailedJobs() {
    if (!confirm('Are you sure you want to clear all failed jobs? This cannot be undone.')) {
        return;
    }

    const btn = document.getElementById('clear-failed-btn');
    btn.disabled = true;
    btn.textContent = 'Clearing...';

    try {
        const res = await fetch('{{ route("admin.monitoring.clear-failed") }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const data = await res.json();

        btn.textContent = 'Cleared!';
        btn.classList.remove('bg-red-600', 'hover:bg-red-700');
        btn.classList.add('bg-green-600');

        // Refresh data after a moment
        setTimeout(() => {
            fetchData(true);
        }, 500);

    } catch (err) {
        alert('Failed to clear jobs: ' + err.message);
        btn.textContent = 'Clear All';
        btn.disabled = false;
    }
}

function formatDuration(minutes) {
    if (minutes < 60) {
        return `${minutes} minute${minutes !== 1 ? 's' : ''}`;
    }

    if (minutes < 1440) { // Less than 24 hours
        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        let result = `${hours} hour${hours !== 1 ? 's' : ''}`;
        if (mins > 0) {
            result += ` ${mins} min${mins !== 1 ? 's' : ''}`;
        }
        return result;
    }

    // Days and hours
    const days = Math.floor(minutes / 1440);
    const remainingMins = minutes % 1440;
    const hours = Math.floor(remainingMins / 60);

    let result = `${days} day${days !== 1 ? 's' : ''}`;
    if (hours > 0) {
        result += ` ${hours} hour${hours !== 1 ? 's' : ''}`;
    }
    return result;
}

function renderIngestion(activity) {
    const container = document.getElementById('ingest-content');

    let html = '';

    if (activity.last_activity) {
        const duration = formatDuration(activity.minutes_since_activity);
        html += `<div class="text-sm mb-4 text-gray-300">Last activity: <span class="text-gray-100 font-medium">${duration} ago</span></div>`;
    }

    ['wikidata', 'musicbrainz'].forEach(source => {
        const items = activity[source] || [];
        const sourceTitle = source.charAt(0).toUpperCase() + source.slice(1);

        html += `<div class="mb-4 last:mb-0">
            <h4 class="text-sm font-medium text-gray-300 mb-2">${sourceTitle}</h4>`;

        if (items.length === 0) {
            html += '<div class="text-sm text-gray-600">No recent activity</div>';
        } else {
            html += '<div class="space-y-1 max-h-32 overflow-y-auto">';
            items.forEach(item => {
                html += `<div class="text-xs flex justify-between">
                    <span class="text-gray-400 truncate mr-2">${item.name || item.query || 'Query'}</span>
                    <span class="text-gray-600 flex-shrink-0">${item.at_human}</span>
                </div>`;
            });
            html += '</div>';
        }

        html += '</div>';
    });

    container.innerHTML = html || '<div class="text-gray-500">No ingestion data available</div>';
}

function renderHeartbeats(heartbeats) {
    const container = document.getElementById('heartbeats-content');

    if (!heartbeats.summary.available) {
        container.innerHTML = '<div class="text-gray-500">Heartbeat tracking not available (run migrations)</div>';
        return;
    }

    let html = '';

    const summary = heartbeats.summary;
    if (summary.last_activity) {
        html += `<div class="text-sm mb-3 text-gray-300">Last job: <span class="font-medium text-gray-100">${summary.last_job}</span></div>`;
    }

    const runs = heartbeats.runs || [];
    if (runs.length === 0) {
        html += '<div class="text-gray-500">No recent job runs</div>';
    } else {
        html += '<div class="space-y-2 max-h-64 overflow-y-auto">';
        runs.forEach(run => {
            const statusColors = {
                completed: 'bg-green-900/50 text-green-300 border-green-700',
                failed: 'bg-red-900/50 text-red-300 border-red-700',
                running: 'bg-blue-900/50 text-blue-300 border-blue-700',
            };
            const statusClass = statusColors[run.status] || statusColors.running;

            let durationHtml = '';
            if (run.duration_human) {
                durationHtml = `<span class="text-gray-500 ml-2">(${run.duration_human})</span>`;
            }

            let errorHtml = '';
            if (run.error) {
                errorHtml = `<div class="text-xs text-red-400 mt-1 truncate" title="${run.error}">${run.error.substring(0, 80)}${run.error.length > 80 ? '...' : ''}</div>`;
            }

            html += `
                <div class="p-2 rounded border border-gray-700 bg-gray-800/50">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-100">${run.job}</span>
                        <span class="text-xs px-1.5 py-0.5 rounded border ${statusClass}">${run.status}</span>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                        ${run.started_at_human || 'Unknown'}${durationHtml}
                    </div>
                    ${errorHtml}
                </div>
            `;
        });
        html += '</div>';
    }

    container.innerHTML = html;
}

function renderEnvironment(env) {
    const container = document.getElementById('env-content');

    const envClass = env.app_env === 'production'
        ? 'bg-red-900 text-red-200'
        : env.app_env === 'staging'
            ? 'bg-yellow-900 text-yellow-200'
            : 'bg-green-900 text-green-200';

    container.innerHTML = `
        <div class="grid grid-cols-2 gap-3 text-sm">
            <div>
                <span class="text-gray-500">Environment</span>
                <div class="mt-1"><span class="px-2 py-0.5 rounded text-xs font-medium ${envClass}">${env.app_env}</span></div>
            </div>
            <div>
                <span class="text-gray-500">Debug Mode</span>
                <div class="mt-1 font-medium text-gray-100">${env.app_debug ? 'Enabled' : 'Disabled'}</div>
            </div>
            <div>
                <span class="text-gray-500">PHP Version</span>
                <div class="mt-1 font-medium text-gray-100">${env.php_version}</div>
            </div>
            <div>
                <span class="text-gray-500">Laravel Version</span>
                <div class="mt-1 font-medium text-gray-100">${env.laravel_version}</div>
            </div>
            <div>
                <span class="text-gray-500">Queue Driver</span>
                <div class="mt-1 font-medium text-gray-100">${env.queue_connection}</div>
            </div>
            <div>
                <span class="text-gray-500">Cache Driver</span>
                <div class="mt-1 font-medium text-gray-100">${env.cache_driver}</div>
            </div>
            <div>
                <span class="text-gray-500">Database</span>
                <div class="mt-1 font-medium text-gray-100">${env.db_connection}</div>
            </div>
            <div>
                <span class="text-gray-500">Git Commit</span>
                <div class="mt-1 font-mono text-xs text-gray-100">${env.git_commit || 'n/a'}</div>
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
    </body>
</html>
