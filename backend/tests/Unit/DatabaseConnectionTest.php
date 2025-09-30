<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseConnectionTest extends TestCase
{

    public function test_database_connection_uses_test_database(): void
    {
        // テスト環境で使用されているデータベース名を確認
        $databaseName = DB::connection()->getDatabaseName();

        // テスト用データベースを使用していることを確認
        $this->assertEquals('fragfolio_test', $databaseName,
            'Test should use fragfolio_test database, but using: ' . $databaseName);
    }

    public function test_app_env_is_testing(): void
    {
        // APP_ENVがtestingであることを確認
        $this->assertEquals('testing', app()->environment());
    }
}