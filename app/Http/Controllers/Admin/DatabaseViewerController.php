<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseViewerController extends Controller
{
    public function index()
    {
        $tables = $this->getTables();

        return view('admin.database.index', compact('tables'));
    }

    public function show(Request $request, string $table)
    {
        $tables = $this->getTables();

        if (! in_array($table, $tables)) {
            abort(404, 'Table not found');
        }

        $columns = Schema::getColumnListing($table);
        $perPage = $request->input('per_page', 25);
        $records = DB::table($table)->paginate($perPage)->withQueryString();

        return view('admin.database.show', compact('tables', 'table', 'columns', 'records'));
    }

    public function query(Request $request)
    {
        $tables = $this->getTables();
        $sql = $request->input('sql', '');
        $results = null;
        $error = null;
        $columns = [];

        if ($sql && $request->isMethod('post')) {
            $sql = trim($sql);

            // Only allow SELECT queries for safety
            if (! preg_match('/^\s*SELECT\s/i', $sql)) {
                $error = 'Only SELECT queries are allowed.';
            } else {
                try {
                    $results = DB::select($sql);
                    if (count($results) > 0) {
                        $columns = array_keys((array) $results[0]);
                    }
                } catch (\Exception $e) {
                    $error = $e->getMessage();
                }
            }
        }

        return view('admin.database.query', compact('tables', 'sql', 'results', 'columns', 'error'));
    }

    private function getTables(): array
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            $tables = DB::select('SHOW TABLES');
            $key = 'Tables_in_' . DB::connection()->getDatabaseName();
            return array_map(fn ($t) => $t->$key, $tables);
        }

        if ($driver === 'sqlite') {
            $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
            return array_map(fn ($t) => $t->name, $tables);
        }

        if ($driver === 'pgsql') {
            $tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
            return array_map(fn ($t) => $t->tablename, $tables);
        }

        return [];
    }
}
