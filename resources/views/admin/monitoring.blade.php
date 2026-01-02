@extends('layouts.app')

@section('content')
<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4">Admin Monitoring</h1>

    <div id="warnings" class="mb-4"></div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="card p-4" id="card-queues">
            <h2 class="font-semibold">Queue Metrics <small id="queues-updated" class="text-sm text-gray-500"></small></h2>
            <div id="queues-content" class="mt-2"></div>
        </div>

        <div class="card p-4" id="card-tables">
            <h2 class="font-semibold">Database Table Counts <small id="tables-updated" class="text-sm text-gray-500"></small></h2>
            <div id="tables-content" class="mt-2"></div>
        </div>

        <div class="card p-4" id="card-failures">
            <h2 class="font-semibold">Failed Jobs <small id="failures-updated" class="text-sm text-gray-500"></small></h2>
            <div id="failures-content" class="mt-2"></div>
        </div>

        <div class="card p-4" id="card-ingest">
            <h2 class="font-semibold">Ingestion Activity <small id="ingest-updated" class="text-sm text-gray-500"></small></h2>
            <div id="ingest-content" class="mt-2"></div>
        </div>

        <div class="card p-4 col-span-1 md:col-span-2" id="card-env">
            <h2 class="font-semibold">Environment / Health <small id="env-updated" class="text-sm text-gray-500"></small></h2>
            <div id="env-content" class="mt-2"></div>
        </div>
    </div>
</div>

<script>
const DATA_URL = '{{ route('admin.monitoring.data') }}';

async function fetchData() {
    try {
        const res = await fetch(DATA_URL, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('Network response not ok');
        const data = await res.json();
        renderData(data);
    } catch (err) {
        document.getElementById('warnings').innerHTML = `<div class="p-2 bg-red-100 text-red-800">Error fetching monitoring data: ${err.message}</div>`;
    }
}

function renderData(d) {
    document.getElementById('queues-updated').textContent = `Updated ${d.generated_at}`;
    const qCont = document.getElementById('queues-content');
    qCont.innerHTML = '';
    if (!d.queues.redis_available) {
        qCont.innerHTML = '<div class="text-yellow-700">Redis not available</div>';
    } else {
        const rows = Object.entries(d.queues.queues).map(([k,v]) => `<div><strong>${k}</strong>: ${v.depth ?? 'n/a'}</div>`).join('');
        qCont.innerHTML = `<div>Connection: ${d.queues.connection} (driver: ${d.queues.driver})</div>${rows}`;
    }

    document.getElementById('tables-updated').textContent = `Updated ${d.generated_at}`;
    const tCont = document.getElementById('tables-content');
    tCont.innerHTML = Object.entries(d.tables).map(([k,v]) => `<div><strong>${k}</strong>: ${v.exists ? v.count : 'missing'}</div>`).join('');

    document.getElementById('failures-updated').textContent = `Updated ${d.generated_at}`;
    const fCont = document.getElementById('failures-content');
    fCont.innerHTML = `<div>Failed Jobs: ${d.failed_jobs.count}</div>` + (d.failed_jobs.recent.length ? '<ul>' + d.failed_jobs.recent.map(r => `<li>#${r.id} [${r.queue}] ${r.failed_at} - ${r.exception}</li>`).join('') + '</ul>' : '');

    document.getElementById('ingest-updated').textContent = `Updated ${d.generated_at}`;
    const iCont = document.getElementById('ingest-content');
    iCont.innerHTML = ['wikidata','musicbrainz'].map(src => `<div><strong>${src}</strong><div>${(d.ingestion_activity[src]||[]).map(i=>'<div>'+ (i.query||i.name) +' @ '+ i.at +'</div>').join('')}</div></div>`).join('');

    document.getElementById('env-updated').textContent = `Updated ${d.generated_at}`;
    const env = d.env;
    document.getElementById('env-content').innerHTML = `<div>ENV: ${env.app_env} — PHP ${env.php_version} — Queue: ${env.queue_connection} — Cache: ${env.cache_driver} — Commit: ${env.git_commit||'n/a'}</div>`;
}

fetchData();
setInterval(fetchData, 7000);
</script>
@endsection
