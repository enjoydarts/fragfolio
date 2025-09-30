<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, SqldefTestCleanup;

    protected function setUp(): void
    {
        parent::setUp();

        // テスト環境であることを確認
        if (app()->environment() !== 'testing') {
            throw new \RuntimeException('テストはtesting環境でのみ実行してください。現在の環境: ' . app()->environment());
        }

        // テスト用データベースに接続されていることを確認
        $currentDatabase = \DB::connection()->getDatabaseName();
        if ($currentDatabase !== 'fragfolio_test') {
            throw new \RuntimeException('テスト実行時にテスト用データベースに接続されていません: ' . $currentDatabase);
        }

        // Clean up test database before each test
        $this->setUpSqldefCleanup();
    }

    protected function tearDown(): void
    {
        // テスト後もテスト用データベースに接続されていることを確認
        $currentDatabase = \DB::connection()->getDatabaseName();
        if ($currentDatabase !== 'fragfolio_test') {
            throw new \RuntimeException('テスト終了時にテスト用データベースに接続されていません: ' . $currentDatabase);
        }

        // Clean up test database after each test
        $this->tearDownSqldefCleanup();

        parent::tearDown();
    }
}
