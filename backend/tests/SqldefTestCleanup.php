<?php

namespace Tests;

use Illuminate\Support\Facades\DB;

trait SqldefTestCleanup
{
    /**
     * Clean up test database by truncating tables
     * This works with sqldef since it doesn't require migrations
     */
    protected function cleanupTestDatabase(): void
    {
        // Ensure we're using the test database
        if (app()->environment() !== 'testing') {
            throw new \RuntimeException('This method should only be called in testing environment');
        }

        $currentDatabase = DB::connection()->getDatabaseName();
        if ($currentDatabase !== 'fragfolio_test') {
            throw new \RuntimeException('Not connected to test database: ' . $currentDatabase);
        }

        // Get all table names
        $tables = DB::select('SHOW TABLES');
        $databaseName = 'fragfolio_test';
        $tableColumn = "Tables_in_{$databaseName}";

        // Disable foreign key checks to avoid constraint errors during truncation
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($tables as $table) {
                $tableName = $table->$tableColumn;
                // Skip system tables and migration tables (if any exist)
                if (!in_array($tableName, ['migrations', 'failed_jobs'])) {
                    DB::table($tableName)->truncate();
                }
            }
        } finally {
            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * Setup method to be called before each test
     */
    protected function setUpSqldefCleanup(): void
    {
        $this->cleanupTestDatabase();
    }

    /**
     * Teardown method to be called after each test
     */
    protected function tearDownSqldefCleanup(): void
    {
        $this->cleanupTestDatabase();
    }
}