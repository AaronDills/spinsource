<x-app-layout>
<div class="container mx-auto p-4 max-w-7xl">
    <!-- Tab Navigation -->
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Admin Console</h1>
            <nav class="flex gap-2">
                <a href="{{ route('admin.monitoring') }}"
                   class="px-4 py-2 text-sm font-medium rounded-lg transition-colors text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700">
                    Monitoring
                </a>
                <a href="{{ route('admin.logs') }}"
                   class="px-4 py-2 text-sm font-medium rounded-lg transition-colors bg-blue-600 text-white">
                    Logs
                </a>
            </nav>
        </div>
        <div class="flex items-center gap-4">
            <span id="last-updated" class="text-sm text-gray-500 dark:text-gray-400"></span>
            <button onclick="fetchLogs()" class="px-3 py-1 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                Refresh
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <!-- File Selector -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Log File</label>
                <select id="file-select" onchange="fetchLogs()"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm">
                    <option value="">Loading...</option>
                </select>
            </div>

            <!-- Log Level -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Level</label>
                <select id="level-select" onchange="fetchLogs()"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm">
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
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Time Window</label>
                <select id="time-select" onchange="fetchLogs()"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm">
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
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                <div class="relative">
                    <input type="text" id="search-input" placeholder="Search logs..."
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm pl-10"
                           onkeyup="debounceSearch()">
                    <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Bar -->
    <div id="stats-bar" class="flex items-center gap-4 mb-4 text-sm text-gray-600 dark:text-gray-400">
        <span id="total-count"></span>
        <span id="file-info"></span>
    </div>

    <!-- Log Entries -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
        <div id="log-entries" class="divide-y divide-gray-100 dark:divide-gray-700">
            <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                <div class="animate-pulse">Loading logs...</div>
            </div>
        </div>
    </div>

    <!-- Load More -->
    <div id="load-more-container" class="mt-4 text-center hidden">
        <button onclick="loadMore()" id="load-more-btn"
                class="px-4 py-2 text-sm bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition-colors">
            Load More
        </button>
    </div>
</div>

<!-- Log Detail Modal -->
<div id="log-modal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4" onclick="closeModal(event)">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full max-h-[80vh] overflow-hidden" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="font-semibold text-gray-900 dark:text-gray-100">Log Entry Details</h3>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div id="modal-content" class="p-4 overflow-y-auto max-h-[calc(80vh-60px)]">
        </div>
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

async function fetchLogs(append = false) {
    if (!append) {
        currentOffset = 0;
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
        container.innerHTML = '<div class="p-8 text-center text-gray-500 dark:text-gray-400"><div class="animate-pulse">Loading logs...</div></div>';
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
                <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                    <svg class="w-12 h-12 mx-auto mb-4 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p>No log entries found</p>
                    <p class="text-sm mt-1">Try adjusting your filters</p>
                </div>`;
            document.getElementById('load-more-container').classList.add('hidden');
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

    } catch (err) {
        container.innerHTML = `
            <div class="p-8 text-center text-red-500 dark:text-red-400">
                <svg class="w-12 h-12 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p>Error loading logs: ${err.message}</p>
            </div>`;
    }
}

function renderLogEntry(entry) {
    const levelColors = {
        error: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        warning: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        info: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        debug: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
        default: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
    };

    const levelClass = levelColors[entry.level_class] || levelColors.default;
    const hasStack = entry.has_stack_trace ? 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50' : '';

    const escapedMessage = entry.full_message.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    const escapedStack = entry.stack_trace ? entry.stack_trace.replace(/"/g, '&quot;').replace(/'/g, '&#39;') : '';

    return `
        <div class="px-4 py-3 ${hasStack}" ${entry.has_stack_trace ? `onclick="showDetail('${escapedMessage}', '${escapedStack}', '${entry.level}', '${entry.timestamp_human}')"` : ''}>
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 mt-0.5">
                    <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded ${levelClass}">
                        ${entry.level.toUpperCase()}
                    </span>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-xs text-gray-500 dark:text-gray-400">${entry.timestamp_human}</span>
                        <span class="text-xs text-gray-400 dark:text-gray-500">${entry.environment}</span>
                        ${entry.has_stack_trace ? '<span class="text-xs text-blue-500 dark:text-blue-400">+ stack trace</span>' : ''}
                    </div>
                    <p class="text-sm text-gray-900 dark:text-gray-100 break-words font-mono">${escapeHtml(entry.message)}</p>
                </div>
            </div>
        </div>
    `;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showDetail(message, stackTrace, level, timestamp) {
    const modal = document.getElementById('log-modal');
    const content = document.getElementById('modal-content');

    const levelColors = {
        error: 'text-red-600 dark:text-red-400',
        warning: 'text-yellow-600 dark:text-yellow-400',
        info: 'text-blue-600 dark:text-blue-400',
        debug: 'text-gray-600 dark:text-gray-400',
    };

    content.innerHTML = `
        <div class="space-y-4">
            <div class="flex items-center gap-3">
                <span class="font-semibold ${levelColors[level] || ''}">${level.toUpperCase()}</span>
                <span class="text-sm text-gray-500 dark:text-gray-400">${timestamp}</span>
            </div>
            <div>
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Message</h4>
                <pre class="text-sm bg-gray-50 dark:bg-gray-900 p-3 rounded-lg overflow-x-auto whitespace-pre-wrap font-mono text-gray-800 dark:text-gray-200">${escapeHtml(decodeEntities(message))}</pre>
            </div>
            ${stackTrace ? `
            <div>
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Stack Trace</h4>
                <pre class="text-xs bg-gray-900 text-green-400 p-3 rounded-lg overflow-x-auto whitespace-pre font-mono">${escapeHtml(decodeEntities(stackTrace))}</pre>
            </div>
            ` : ''}
        </div>
    `;

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function decodeEntities(text) {
    const textarea = document.createElement('textarea');
    textarea.innerHTML = text;
    return textarea.value;
}

function closeModal(event) {
    if (event && event.target !== document.getElementById('log-modal')) return;
    document.getElementById('log-modal').classList.add('hidden');
    document.body.style.overflow = '';
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
    if (e.key === 'Escape') closeModal();
    if (e.key === 'r' && (e.metaKey || e.ctrlKey)) {
        e.preventDefault();
        fetchLogs();
    }
});
</script>
</x-app-layout>
