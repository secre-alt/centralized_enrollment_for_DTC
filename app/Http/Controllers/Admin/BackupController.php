<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BackupLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BackupController extends Controller
{
    public function index()
    {
        $backups = BackupLog::with('creator')->latest()->get();
        return view('admin.settings.backup', compact('backups'));
    }

    public function backup()
    {
        try {
            $dbName   = config('database.connections.mysql.database');
            $dbUser   = config('database.connections.mysql.username');
            $dbPass   = config('database.connections.mysql.password');
            $dbHost   = config('database.connections.mysql.host');
            $dbPort   = config('database.connections.mysql.port');

            $filename = 'dtc_backup_' . now()->format('Ymd_His') . '.sql';
            $path     = storage_path('app/backups/' . $filename);

            // Create backups directory if not exists
            if (!file_exists(storage_path('app/backups'))) {
                mkdir(storage_path('app/backups'), 0755, true);
            }

            // Generate SQL dump using PHP (no mysqldump needed)
            $sql = $this->generateSqlDump($dbName);

            file_put_contents($path, $sql);
            $size = filesize($path);

            // Log the backup
            BackupLog::create([
                'filename'   => $filename,
                'size'       => $size,
                'status'     => 'completed',
                'created_by' => Auth::id(),
            ]);

            // Stream download
            return response()->download($path, $filename, [
                'Content-Type'        => 'application/sql',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);

        } catch (\Exception $e) {
            BackupLog::create([
                'filename'   => 'failed_' . now()->format('Ymd_His') . '.sql',
                'size'       => 0,
                'status'     => 'failed',
                'created_by' => Auth::id(),
            ]);

            return redirect()->route('admin.settings.backup')
                ->with('error', 'Backup failed: ' . $e->getMessage());
        }
    }

    private function generateSqlDump(string $dbName): string
    {
        $sql = "-- DTC EMS Database Backup\n";
        $sql .= "-- Generated: " . now()->format('Y-m-d H:i:s') . "\n";
        $sql .= "-- Database: {$dbName}\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        // Get all tables
        $tables = DB::select('SHOW TABLES');
        $tableKey = 'Tables_in_' . $dbName;

        foreach ($tables as $table) {
            $tableName = $table->$tableKey;

            // Drop + Create table
            $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`");
            $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
            $sql .= $createTable[0]->{'Create Table'} . ";\n\n";

            // Insert data
            $rows = DB::table($tableName)->get();
            if ($rows->isNotEmpty()) {
                $sql .= "INSERT INTO `{$tableName}` VALUES\n";
                $rowSqls = [];
                foreach ($rows as $row) {
                    $values = array_map(function($val) {
                        if (is_null($val)) return 'NULL';
                        return "'" . addslashes($val) . "'";
                    }, (array) $row);
                    $rowSqls[] = '(' . implode(', ', $values) . ')';
                }
                $sql .= implode(",\n", $rowSqls) . ";\n\n";
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        return $sql;
    }

    public function restore(Request $request)
    {
        $request->validate([
            'sql_file' => ['required', 'file', 'mimes:sql,txt', 'max:51200'], // 50MB max
        ]);

        try {
            $file    = $request->file('sql_file');
            $content = file_get_contents($file->getRealPath());

            // Safety check
            if (empty(trim($content))) {
                return redirect()->route('admin.settings.backup')
                    ->with('error', 'The uploaded file is empty.');
            }

            // Split SQL into individual statements
            DB::unprepared('SET FOREIGN_KEY_CHECKS=0;');

            $statements = array_filter(
                array_map('trim', explode(";\n", $content)),
                fn($s) => !empty($s) && !str_starts_with($s, '--')
            );

            foreach ($statements as $statement) {
                if (!empty(trim($statement))) {
                    DB::unprepared($statement);
                }
            }

            DB::unprepared('SET FOREIGN_KEY_CHECKS=1;');

            return redirect()->route('admin.settings.backup')
                ->with('success', 'Database restored successfully from ' . $file->getClientOriginalName() . '.');

        } catch (\Exception $e) {
            return redirect()->route('admin.settings.backup')
                ->with('error', 'Restore failed: ' . $e->getMessage());
        }
    }

    public function deleteBackup(BackupLog $backup)
    {
        $path = storage_path('app/backups/' . $backup->filename);
        if (file_exists($path)) {
            unlink($path);
        }
        $backup->delete();

        return redirect()->route('admin.settings.backup')
            ->with('success', 'Backup record removed.');
    }

    public function download(BackupLog $backup)
    {
        $path = storage_path('app/backups/' . $backup->filename);

        if (!file_exists($path)) {
            return redirect()->route('admin.settings.backup')
                ->with('error', 'Backup file no longer exists on disk.');
        }

        return response()->download($path);
    }
}