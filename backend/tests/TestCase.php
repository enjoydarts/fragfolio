<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // テスト時は強制的にテスト用データベースを使用
        Config::set('database.connections.mysql.database', 'fragfolio_test');

        // データベース接続をリセット
        \DB::purge('mysql');
        \DB::reconnect('mysql');

        // テスト開始前にデータをクリーンアップ
        $this->cleanupDatabase();
    }

    protected function tearDown(): void
    {
        // テスト終了後にデータをクリーンアップ
        $this->cleanupDatabase();

        parent::tearDown();
    }

    private function cleanupDatabase(): void
    {
        // テスト用データベースの主要テーブルをトランケート
        \DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $tables = [
            'personal_access_tokens',
            'user_profiles',
            'users',
            'roles',
            'permissions',
            'model_has_roles',
            'model_has_permissions',
            'role_has_permissions',
        ];

        foreach ($tables as $table) {
            \DB::table($table)->truncate();
        }

        \DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
