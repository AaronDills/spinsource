<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AdminLogController extends Controller
{
    /**
     * Maximum number of log entries to return per request
     */
    private const MAX_ENTRIES = 500;

    /**
     * Maximum file size to read (10MB)
     */
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

    public function index(Request $request)
    {
        Gate::authorize('viewAdminDashboard');

        return view('admin.logs');
    }

    /**
     * Get available log files
     */
    public function files(Request $request)
    {
        Gate::authorize('viewAdminDashboard');

        $logPath = storage_path('logs');
        $files = [];

        if (File::isDirectory($logPath)) {
            $logFiles = File::glob($logPath . '/*.log');

            foreach ($logFiles as $file) {
                $filename = basename($file);
                $files[] = [
                    'name' => $filename,
                    'path' => $filename,
                    'size' => File::size($file),
                    'size_human' => $this->humanFileSize(File::size($file)),
                    'modified' => Carbon::createFromTimestamp(File::lastModified($file))->toIso8601String(),
                    'modified_human' => Carbon::createFromTimestamp(File::lastModified($file))->diffForHumans(),
                    'type' => $this->detectLogType($filename),
                ];
            }

            // Sort by modified date, newest first
            usort($files, fn($a, $b) => $b['modified'] <=> $a['modified']);
        }

        return response()->json([
            'files' => $files,
            'log_path' => $logPath,
        ]);
    }

    /**
     * Get log entries with filtering
     */
    public function data(Request $request)
    {
        Gate::authorize('viewAdminDashboard');

        $file = $request->get('file') ?? 'laravel.log';
        $search = $request->get('search') ?? '';
        $level = $request->get('level') ?? '';
        $timeWindow = $request->get('time_window') ?? ''; // e.g., '1h', '24h', '7d'
        $limit = min((int) ($request->get('limit') ?? 100), self::MAX_ENTRIES);
        $offset = (int) ($request->get('offset') ?? 0);

        // Sanitize filename to prevent directory traversal
        $file = basename($file);
        $filePath = storage_path('logs/' . $file);

        if (!File::exists($filePath)) {
            return response()->json([
                'error' => 'Log file not found',
                'entries' => [],
                'total' => 0,
            ], 404);
        }

        // Check file size
        if (File::size($filePath) > self::MAX_FILE_SIZE) {
            return response()->json([
                'warning' => 'File too large, showing last portion only',
                'entries' => $this->parseLogFileTail($filePath, $search, $level, $timeWindow, $limit),
                'total' => null,
            ]);
        }

        $entries = $this->parseLogFile($filePath, $search, $level, $timeWindow);
        $total = count($entries);

        // Apply pagination
        $entries = array_slice($entries, $offset, $limit);

        return response()->json([
            'entries' => $entries,
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
            'file' => $file,
            'filters' => [
                'search' => $search,
                'level' => $level,
                'time_window' => $timeWindow,
            ],
        ]);
    }

    /**
     * Parse a Laravel log file
     */
    protected function parseLogFile(string $filePath, string $search = '', string $level = '', string $timeWindow = ''): array
    {
        $content = File::get($filePath);
        $entries = [];

        // Laravel log format: [YYYY-MM-DD HH:MM:SS] environment.LEVEL: message
        $pattern = '/\[(\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2})\]\s+(\w+)\.(\w+):\s+(.*?)(?=\[\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}\]|$)/s';

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        $cutoffTime = $this->getCutoffTime($timeWindow);

        foreach ($matches as $match) {
            $timestamp = $match[1];
            $environment = $match[2];
            $logLevel = strtolower($match[3]);
            $message = trim($match[4]);

            // Parse timestamp
            try {
                $entryTime = Carbon::parse($timestamp);
            } catch (\Exception $e) {
                continue;
            }

            // Apply time window filter
            if ($cutoffTime && $entryTime->lt($cutoffTime)) {
                continue;
            }

            // Apply level filter
            if ($level && strtolower($level) !== $logLevel) {
                continue;
            }

            // Apply search filter
            if ($search && !Str::contains(strtolower($message), strtolower($search))) {
                continue;
            }

            // Extract stack trace if present
            $stackTrace = null;
            if (preg_match('/^(.*?)(#\d+\s+.*)/s', $message, $parts)) {
                $message = trim($parts[1]);
                $stackTrace = trim($parts[2]);
            }

            $entries[] = [
                'timestamp' => $entryTime->toIso8601String(),
                'timestamp_human' => $entryTime->diffForHumans(),
                'environment' => $environment,
                'level' => $logLevel,
                'level_class' => $this->getLevelClass($logLevel),
                'message' => Str::limit($message, 500),
                'full_message' => $message,
                'has_stack_trace' => !empty($stackTrace),
                'stack_trace' => $stackTrace,
            ];
        }

        // Return in reverse order (newest first)
        return array_reverse($entries);
    }

    /**
     * Parse only the tail of a large log file
     */
    protected function parseLogFileTail(string $filePath, string $search, string $level, string $timeWindow, int $limit): array
    {
        // Read last 1MB of file
        $handle = fopen($filePath, 'r');
        $fileSize = filesize($filePath);
        $readSize = min($fileSize, 1024 * 1024);

        fseek($handle, -$readSize, SEEK_END);
        $content = fread($handle, $readSize);
        fclose($handle);

        // Find the first complete log entry
        $firstBracket = strpos($content, '[');
        if ($firstBracket !== false) {
            $content = substr($content, $firstBracket);
        }

        // Write to temp file and parse
        $tempFile = tempnam(sys_get_temp_dir(), 'log');
        file_put_contents($tempFile, $content);

        $entries = $this->parseLogFile($tempFile, $search, $level, $timeWindow);
        unlink($tempFile);

        return array_slice($entries, 0, $limit);
    }

    /**
     * Get cutoff time based on time window
     */
    protected function getCutoffTime(string $timeWindow): ?Carbon
    {
        if (empty($timeWindow)) {
            return null;
        }

        $now = Carbon::now();

        return match ($timeWindow) {
            '15m' => $now->subMinutes(15),
            '1h' => $now->subHour(),
            '6h' => $now->subHours(6),
            '24h' => $now->subDay(),
            '7d' => $now->subWeek(),
            '30d' => $now->subMonth(),
            default => null,
        };
    }

    /**
     * Get CSS class for log level
     */
    protected function getLevelClass(string $level): string
    {
        return match (strtolower($level)) {
            'emergency', 'alert', 'critical' => 'error',
            'error' => 'error',
            'warning' => 'warning',
            'notice', 'info' => 'info',
            'debug' => 'debug',
            default => 'default',
        };
    }

    /**
     * Detect log type from filename
     */
    protected function detectLogType(string $filename): string
    {
        if (Str::contains($filename, 'laravel')) {
            return 'application';
        }
        if (Str::contains($filename, ['access', 'nginx', 'apache'])) {
            return 'access';
        }
        if (Str::contains($filename, ['error', 'php'])) {
            return 'error';
        }
        return 'other';
    }

    /**
     * Human readable file size
     */
    protected function humanFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
