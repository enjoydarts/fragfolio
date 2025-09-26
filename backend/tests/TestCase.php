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

        // 接続が正しくテスト用データベースになっているか確認
        if (\DB::connection()->getDatabaseName() !== 'fragfolio_test') {
            throw new \RuntimeException('テスト実行時にテスト用データベースに接続されていません: '.\DB::connection()->getDatabaseName());
        }

        // テスト開始前にデータをクリーンアップ
        $this->cleanupDatabase();
    }

    protected function tearDown(): void
    {
        // 接続が正しくテスト用データベースになっているか確認
        $currentDatabase = \DB::connection()->getDatabaseName();
        if ($currentDatabase !== 'fragfolio_test') {
            // データベース接続が変更されている場合は復旧を試行
            Config::set('database.connections.mysql.database', 'fragfolio_test');
            \DB::purge('mysql');
            \DB::reconnect('mysql');

            // 復旧後も接続できない場合はエラー
            if (\DB::connection()->getDatabaseName() !== 'fragfolio_test') {
                throw new \RuntimeException('テスト終了時にテスト用データベースに接続されていません: 現在='.$currentDatabase.', 復旧後='.\DB::connection()->getDatabaseName());
            }
        }

        // テスト終了後にデータをクリーンアップ
        $this->cleanupDatabase();

        parent::tearDown();
    }

    private function cleanupDatabase(): void
    {
        try {
            // テスト用データベースの主要テーブルをトランケート
            \DB::statement('SET FOREIGN_KEY_CHECKS=0');

            $tables = [
                'ai_cost_tracking',
                'user_profiles',
                'webauthn_credentials',
                'users',
            ];

            foreach ($tables as $table) {
                // テーブルが存在する場合のみトランケート
                if (\Schema::hasTable($table)) {
                    \DB::table($table)->truncate();
                }
            }

            \DB::statement('SET FOREIGN_KEY_CHECKS=1');
        } catch (\Exception $e) {
            // データベースエラーは無視（テーブルが存在しない場合など）
        }
    }
}
