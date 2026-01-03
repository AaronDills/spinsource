<x-main-layout title="Admin Logs - {{ config('app.name', 'Spin Source') }}" :showRecentReviews="false">
    <div class="container mx-auto p-4 max-w-7xl">
        <!-- Admin Sub-header -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <h1 class="text-2xl font-bold text-gray-100">Admin Console</h1>
                <nav class="flex gap-1 ml-4">
                    <a href="{{ route('admin.monitoring') }}"
                       class="px-4 py-2 text-sm font-medium rounded-lg text-gray-400 hover:bg-gray-800 hover:text-gray-200 transition-colors">
                        Monitoring
                    </a>
                    <a href="{{ route('admin.logs') }}"
                       class="px-4 py-2 text-sm font-medium rounded-lg bg-gray-700 text-white">
                        Logs
                    </a>
                </nav>
            </div>
            <div class="flex items-center gap-4">
                <span id="last-updated" class="text-sm text-gray-500"></span>
                <button id="refresh-btn" onclick="fetchLogs(false, true)" class="px-3 py-1.5 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors flex items-center gap-2">
                    <svg id="refresh-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <span id="refresh-text">Refresh</span>
                </button>
            </div>
        </div>

            <!-- Filters -->
            <div class="bg-gray-800 rounded-lg shadow-lg p-4 mb-6 border border-gray-700">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <!-- File Selector -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Log File</label>
                        <select id="file-select" onchange="fetchLogs()"
                                class="w-full rounded-lg border-gray-600 bg-gray-700 text-gray-100 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Loading...</option>
                        </select>
                    </div>

                    <!-- Log Level -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Level</label>
                        <select id="level-select" onchange="fetchLogs()"
                                class="w-full rounded-lg border-gray-600 bg-gray-700 text-gray-100 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All Levels</option>
                            <option value="emergency">Emergency</option>
                            <option value="alert">Alert</option>
                            <option value="critical">Critical</option>
                            <option value="error">Error</option>
                            <option value="warning">Warning</option>
                            <option value="notice">Notice</option>
                            <option value="info">Info</option>
                            <option value="debug">Debug</option>
                        </select>
                    </div>

                    <!-- Time Window -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Time Window</label>
                        <select id="time-select" onchange="fetchLogs()"
                                class="w-full rounded-lg border-gray-600 bg-gray-700 text-gray-100 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All Time</option>
                            <option value="15m">Last 15 minutes</option>
                            <option value="1h">Last hour</option>
                            <option value="6h">Last 6 hours</option>
                            <option value="24h" selected>Last 24 hours</option>
                            <option value="7d">Last 7 days</option>
                            <option value="30d">Last 30 days</option>
                        </select>
                    </div>

                    <!-- Search -->
                    <div class="lg:col-span-2">
                        <label class="block text-sm font-medium text-gray-300 mb-1">Search</label>
                        <div class="relative">
                            <input type="text" id="search-input" placeholder="Search logs..."
                                   class="w-full rounded-lg border-gray-600 bg-gray-700 text-gray-100 text-sm pl-10 focus:border-blue-500 focus:ring-blue-500 placeholder-gray-500"
                                   onkeyup="debounceSearch()">
                            <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Bar -->
            <div id="stats-bar" class="flex items-center gap-4 mb-4 text-sm text-gray-400">
                <span id="total-count"></span>
                <span id="file-info"></span>
            </div>

            <!-- Log Entries -->
            <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden border border-gray-700">
                <div id="log-entries" class="divide-y divide-gray-700">
                    <div class="p-8 text-center text-gray-500">
                        <div class="animate-pulse">Loading logs...</div>
                    </div>
                </div>
            </div>

            <!-- Load More -->
            <div id="load-more-container" class="mt-4 text-center hidden">
                <button onclick="loadMore()" id="load-more-btn"
                        class="px-4 py-2 text-sm bg-gray-700 hover:bg-gray-600 text-gray-300 rounded-lg transition-colors">
                    Load More
                </button>
            </div>
        </div>

<script>
const FILES_URL = '{{ route('admin.logs.files') }}';
const DATA_URL = '{{ route('admin.logs.data') }}';

let currentOffset = 0;
let currentTotal = 0;
let searchTimeout = null;

// Initialize
document.addEventListener('DOMContentLoaded', async () => {
    await fetchFiles();
    fetchLogs();
});

async function fetchFiles() {
    try {
        const res = await fetch(FILES_URL, { credentials: 'same-origin' });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();

        const select = document.getElementById('file-select');
        select.innerHTML = data.files.map(f =>
            `<option value="${f.path}" data-size="${f.size_human}" data-modified="${f.modified_human}">${f.name} (${f.size_human})</option>`
        ).join('');

    } catch (err) {
        console.error('Failed to fetch files:', err);
    }
}

async function fetchLogs(append = false, manual = false) {
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

    if (!append) {
        currentOffset = 0;
        entryCounter = 0;
    }

    const file = document.getElementById('file-select').value;
    const level = document.getElementById('level-select').value;
    const timeWindow = document.getElementById('time-select').value;
    const search = document.getElementById('search-input').value;

    const params = new URLSearchParams({
        file,
        level,
        time_window: timeWindow,
        search,
        limit: 100,
        offset: currentOffset,
    });

    const container = document.getElementById('log-entries');

    if (!append) {
        container.innerHTML = '<div class="p-8 text-center text-gray-500"><div class="animate-pulse">Loading logs...</div></div>';
    }

    try {
        const res = await fetch(`${DATA_URL}?${params}`, { credentials: 'same-origin' });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();

        currentTotal = data.total || 0;

        // Update stats
        document.getElementById('last-updated').textContent = `Updated: ${new Date().toLocaleTimeString()}`;
        document.getElementById('total-count').textContent = `${currentTotal} entries`;

        const selectedOption = document.getElementById('file-select').selectedOptions[0];
        if (selectedOption) {
            document.getElementById('file-info').textContent =
                `${selectedOption.dataset.size} • Modified ${selectedOption.dataset.modified}`;
        }

        // Render entries
        if (data.entries.length === 0 && !append) {
            container.innerHTML = `
                <div class="p-8 text-center text-gray-500">
                    <svg class="w-12 h-12 mx-auto mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p>No log entries found</p>
                    <p class="text-sm mt-1">Try adjusting your filters</p>
                </div>`;
            document.getElementById('load-more-container').classList.add('hidden');

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
            return;
        }

        const html = data.entries.map(renderLogEntry).join('');

        if (append) {
            container.insertAdjacentHTML('beforeend', html);
        } else {
            container.innerHTML = html;
        }

        currentOffset += data.entries.length;

        // Show/hide load more button
        const loadMoreContainer = document.getElementById('load-more-container');
        if (currentOffset < currentTotal) {
            loadMoreContainer.classList.remove('hidden');
        } else {
            loadMoreContainer.classList.add('hidden');
        }

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
        container.innerHTML = `
            <div class="p-8 text-center text-red-400">
                <svg class="w-12 h-12 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p>Error loading logs: ${err.message}</p>
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

let entryCounter = 0;

function renderLogEntry(entry) {
    const entryId = `log-entry-${entryCounter++}`;

    const levelColors = {
        error: 'bg-red-900/50 text-red-300 border-red-800',
        warning: 'bg-yellow-900/50 text-yellow-300 border-yellow-800',
        info: 'bg-blue-900/50 text-blue-300 border-blue-800',
        debug: 'bg-gray-700/50 text-gray-300 border-gray-600',
        default: 'bg-gray-700/50 text-gray-300 border-gray-600',
    };

    const levelClass = levelColors[entry.level_class] || levelColors.default;

    return `
        <div class="log-entry">
            <div class="px-4 py-3 cursor-pointer hover:bg-gray-700/50 transition-colors" onclick="toggleEntry('${entryId}')">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 mt-0.5">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded border ${levelClass}">
                            ${entry.level.toUpperCase()}
                        </span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-xs text-gray-500">${entry.timestamp_human}</span>
                            <span class="text-xs text-gray-600">${entry.environment}</span>
                            ${entry.has_stack_trace ? '<span class="text-xs text-blue-400">+ stack trace</span>' : ''}
                            <svg id="${entryId}-chevron" class="w-4 h-4 text-gray-500 transform transition-transform ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                        <p class="text-sm text-gray-200 break-words font-mono">${escapeHtml(entry.message)}</p>
                    </div>
                </div>
            </div>
            <div id="${entryId}" class="hidden px-4 pb-4 pt-0">
                <div class="ml-12 space-y-3">
                    <div>
                        <h4 class="text-xs font-medium text-gray-400 mb-1">Full Message</h4>
                        <pre class="text-sm bg-gray-900 p-3 rounded-lg overflow-x-auto whitespace-pre-wrap font-mono text-gray-200 border border-gray-700">${escapeHtml(entry.full_message)}</pre>
                    </div>
                    ${entry.stack_trace ? `
                    <div>
                        <h4 class="text-xs font-medium text-gray-400 mb-1">Stack Trace</h4>
                        <pre class="text-xs bg-gray-950 text-green-400 p-3 rounded-lg overflow-x-auto whitespace-pre font-mono border border-gray-700 max-h-96 overflow-y-auto">${escapeHtml(entry.stack_trace)}</pre>
                    </div>
                    ` : ''}
                </div>
            </div>
        </div>
    `;
}

function toggleEntry(entryId) {
    const details = document.getElementById(entryId);
    const chevron = document.getElementById(entryId + '-chevron');

    if (details.classList.contains('hidden')) {
        details.classList.remove('hidden');
        chevron.classList.add('rotate-180');
    } else {
        details.classList.add('hidden');
        chevron.classList.remove('rotate-180');
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function loadMore() {
    fetchLogs(true);
}

function debounceSearch() {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => fetchLogs(), 300);
}

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
    if (e.key === 'r' && (e.metaKey || e.ctrlKey)) {
        e.preventDefault();
        fetchLogs(false, true);
    }
});
</script>
    </div>
</x-main-layout>
