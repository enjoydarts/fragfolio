<?php

namespace Tests;

use Illuminate\Support\Facades\DB;

trait SqldefTestCleanup
{
    protected static bool $schemaLoaded = false;

    /**
     * スキーマをロードして各テスト前にデータベースをクリーンアップ
     */
    protected function setUpSqldefCleanup(): void
    {
        // スキーマが未ロードの場合、sqldefでスキーマを適用
        if (! self::$schemaLoaded) {
            $this->loadSchemaWithSqldef();
            self::$schemaLoaded = true;
        }

        // 各テスト前にデータをクリーンアップ（テーブル構造は維持）
        $this->truncateAllTables();
    }

    /**
     * テスト後のクリーンアップ
     */
    protected function tearDownSqldefCleanup(): void
    {
        // 必要に応じて各テスト後の処理を追加
    }

    /**
     * sqldefを使用してスキーマをロード
     */
    protected function loadSchemaWithSqldef(): void
    {
        // 複数のパスを試す（Docker環境とCI環境で異なる）
        $possiblePaths = [
            base_path('sqldef/schema.sql'),           // Docker: /var/www/html/sqldef/schema.sql
            base_path('../sqldef/schema.sql'),        // CI: /home/runner/work/fragfolio/fragfolio/sqldef/schema.sql
            dirname(base_path()).'/sqldef/schema.sql', // 絶対パス
        ];

        $schemaPath = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $schemaPath = $path;
                break;
            }
        }

        if (! $schemaPath) {
            throw new \RuntimeException('Schema file not found. Tried: '.implode(', ', $possiblePaths));
        }

        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        $host = config('database.connections.mysql.host');

        $command = sprintf(
            'cat %s | mysqldef -u %s -p%s -h %s --ssl-mode=DISABLED %s',
            escapeshellarg($schemaPath),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($host),
            escapeshellarg($database)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException('Failed to load schema with sqldef: '.implode("\n", $output));
        }
    }

    /**
     * 全テーブルのデータを削除（テーブル構造は維持）
     */
    protected function truncateAllTables(): void
    {
        // 外部キー制約を一時的に無効化
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // migrations以外の全テーブルをTRUNCATE
        $tables = DB::select('SHOW TABLES');
        $databaseName = DB::getDatabaseName();
        $tableKey = "Tables_in_{$databaseName}";

        foreach ($tables as $table) {
            $tableName = $table->$tableKey;

            // migrationsテーブルはスキップ
            if ($tableName === 'migrations') {
                continue;
            }

            DB::statement("TRUNCATE TABLE `{$tableName}`");
        }

        // 外部キー制約を再度有効化
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
