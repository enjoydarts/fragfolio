<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // 管理者権限のGate定義
        Gate::define('admin', function (User $user) {
            return $user->role === 'admin';
        });

        // AI機能の管理者専用操作
        Gate::define('admin-ai-features', function (User $user) {
            return $user->role === 'admin';
        });

        // コスト統計の閲覧権限
        Gate::define('view-cost-stats', function (User $user) {
            return $user->role === 'admin';
        });

        // グローバル統計の閲覧権限
        Gate::define('view-global-stats', function (User $user) {
            return $user->role === 'admin';
        });

        // AI設定の変更権限
        Gate::define('modify-ai-settings', function (User $user) {
            return $user->role === 'admin';
        });

        // 一般ユーザーのAI機能利用権限
        Gate::define('use-ai-features', function (User $user) {
            return in_array($user->role, ['admin', 'user']);
        });

        // プロファイル更新権限（自分のプロファイルのみ）
        Gate::define('update-profile', function (User $user, $profileUserId = null) {
            if ($user->role === 'admin') {
                return true; // 管理者は全てのプロファイルを更新可能
            }

            return $profileUserId === null || $user->id === $profileUserId;
        });

        // コレクション管理権限（自分のコレクションのみ）
        Gate::define('manage-collection', function (User $user, $collectionUserId = null) {
            if ($user->role === 'admin') {
                return true; // 管理者は全てのコレクションを管理可能
            }

            return $collectionUserId === null || $user->id === $collectionUserId;
        });

        // WebAuthn認証の管理権限（自分の認証情報のみ）
        Gate::define('manage-webauthn', function (User $user, $webauthnUserId = null) {
            if ($user->role === 'admin') {
                return true; // 管理者は全てのWebAuthn情報を管理可能
            }

            return $webauthnUserId === null || $user->id === $webauthnUserId;
        });

        // AI使用履歴の閲覧権限
        Gate::define('view-ai-usage', function (User $user, $targetUserId = null) {
            if ($user->role === 'admin') {
                return true; // 管理者は全ユーザーの使用履歴を閲覧可能
            }

            return $targetUserId === null || $user->id === $targetUserId;
        });

        // レポート生成権限
        Gate::define('generate-reports', function (User $user, $targetUserId = null) {
            if ($user->role === 'admin') {
                return true; // 管理者は全ユーザーのレポートを生成可能
            }

            return $targetUserId === null || $user->id === $targetUserId;
        });
    }
}
