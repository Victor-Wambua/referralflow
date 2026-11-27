<?php

namespace App\Console\Commands;

use App\Directory\DirectoryImporter;
use Illuminate\Console\Command;

class ImportDirectory extends Command
{
    protected $signature = 'directory:import {file : KMHFL-style CSV (synthetic fixtures only)} {--dry-run}';
    protected $description = 'Import facilities from a KMHFL-style CSV (format authored in docs/INTEROPERABILITY.md)';

    public function handle(): int
    {
        $r = DirectoryImporter::import($this->argument('file'), (bool) $this->option('dry-run'));
        $this->line("created {$r['created']}, updated {$r['updated']}, errors ".count($r['errors']).($r['dry_run'] ? ' (dry run)' : ''));
        foreach ($r['errors'] as $e) {
            $this->warn("line {$e['line']}: {$e['message']}");
        }

        return $r['errors'] ? self::FAILURE : self::SUCCESS;
    }
}
