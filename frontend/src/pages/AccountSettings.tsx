import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../hooks/useAuth';
import { TOTPSettings } from '../components/security/TOTPSettings';

interface AccountSettingsState {
  activeTab: 'profile' | 'security' | 'sessions';
}

export const AccountSettings: React.FC = () => {
  const { t } = useTranslation();
  const { user } = useAuth();
  const [state, setState] = useState<AccountSettingsState>({
    activeTab: 'profile'
  });

  const tabs = [
    { id: 'profile', label: t('settings.tabs.profile', 'プロフィール') },
    { id: 'security', label: t('settings.tabs.security', 'セキュリティ') },
    { id: 'sessions', label: t('settings.tabs.sessions', 'セッション') }
  ] as const;

  const setActiveTab = (tabId: AccountSettingsState['activeTab']) => {
    setState(prev => ({ ...prev, activeTab: tabId }));
  };

  if (!user) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="text-gray-500">{t('common.loading', '読み込み中...')}</div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        {/* ページヘッダー */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900">
            {t('settings.title', 'アカウント設定')}
          </h1>
          <p className="mt-2 text-gray-600">
            {t('settings.description', 'アカウント情報とセキュリティ設定を管理します')}
          </p>
        </div>

        <div className="bg-white shadow rounded-lg">
          {/* タブナビゲーション */}
          <div className="border-b border-gray-200">
            <nav className="flex space-x-8 px-6" aria-label="Tabs">
              {tabs.map((tab) => (
                <button
                  key={tab.id}
                  onClick={() => setActiveTab(tab.id)}
                  className={`py-4 px-1 border-b-2 font-medium text-sm ${
                    state.activeTab === tab.id
                      ? 'border-orange-500 text-orange-600'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                  }`}
                >
                  {tab.label}
                </button>
              ))}
            </nav>
          </div>

          {/* タブコンテンツ */}
          <div className="p-6">
            {state.activeTab === 'profile' && (
              <ProfileSettings />
            )}
            {state.activeTab === 'security' && (
              <SecuritySettings />
            )}
            {state.activeTab === 'sessions' && (
              <SessionsSettings />
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

// プロフィール設定コンポーネント
const ProfileSettings: React.FC = () => {
  const { t } = useTranslation();
  const { user } = useAuth();

  return (
    <div className="space-y-6">
      <div>
        <h3 className="text-lg font-medium text-gray-900">
          {t('settings.profile.title', 'プロフィール情報')}
        </h3>
        <p className="mt-1 text-sm text-gray-600">
          {t('settings.profile.description', '基本的なアカウント情報を設定します')}
        </p>
      </div>

      <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div>
          <label className="block text-sm font-medium text-gray-700">
            {t('auth.name', '名前')}
          </label>
          <input
            type="text"
            defaultValue={user?.name}
            className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700">
            {t('auth.email', 'メールアドレス')}
          </label>
          <input
            type="email"
            defaultValue={user?.email}
            className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700">
            {t('settings.profile.language', '言語')}
          </label>
          <select className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500">
            <option value="ja">日本語</option>
            <option value="en">English</option>
          </select>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700">
            {t('settings.profile.timezone', 'タイムゾーン')}
          </label>
          <select className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500">
            <option value="Asia/Tokyo">Asia/Tokyo</option>
            <option value="UTC">UTC</option>
          </select>
        </div>
      </div>

      <div className="flex justify-end">
        <button
          type="submit"
          className="btn-primary"
        >
          {t('common.save', '保存')}
        </button>
      </div>
    </div>
  );
};

// セキュリティ設定コンポーネント
const SecuritySettings: React.FC = () => {
  const { t } = useTranslation();

  return (
    <div className="space-y-8">
      <div>
        <h3 className="text-lg font-medium text-gray-900">
          {t('settings.security.title', 'セキュリティ設定')}
        </h3>
        <p className="mt-1 text-sm text-gray-600">
          {t('settings.security.description', 'アカウントのセキュリティを強化します')}
        </p>
      </div>

      {/* パスワード変更 */}
      <div className="border border-gray-200 rounded-lg p-6">
        <h4 className="text-base font-medium text-gray-900 mb-4">
          {t('settings.security.password.title', 'パスワード変更')}
        </h4>
        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700">
              {t('settings.security.password.current', '現在のパスワード')}
            </label>
            <input
              type="password"
              className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700">
              {t('settings.security.password.new', '新しいパスワード')}
            </label>
            <input
              type="password"
              className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700">
              {t('settings.security.password.confirm', 'パスワード確認')}
            </label>
            <input
              type="password"
              className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500"
            />
          </div>
          <button className="btn-primary">
            {t('settings.security.password.update', 'パスワードを更新')}
          </button>
        </div>
      </div>

      {/* 2FA設定 */}
      <TOTPSettings />

      {/* WebAuthn設定 */}
      <div className="border border-gray-200 rounded-lg p-6">
        <h4 className="text-base font-medium text-gray-900 mb-4">
          {t('settings.security.webauthn.title', 'WebAuthn / FIDO2')}
        </h4>
        <p className="text-sm text-gray-600 mb-4">
          {t('settings.security.webauthn.description', 'セキュリティキーや生体認証でパスワードレス認証')}
        </p>
        <div className="space-y-4">
          <div className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
            <div>
              <h5 className="font-medium text-gray-900">
                {t('settings.security.webauthn.register', '認証器の登録')}
              </h5>
              <p className="text-sm text-gray-600">
                {t('settings.security.webauthn.register_desc', 'セキュリティキーまたは生体認証を追加')}
              </p>
            </div>
            <button className="btn-secondary">
              {t('settings.security.webauthn.add', '追加')}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

// セッション管理コンポーネント
const SessionsSettings: React.FC = () => {
  const { t } = useTranslation();

  return (
    <div className="space-y-6">
      <div>
        <h3 className="text-lg font-medium text-gray-900">
          {t('settings.sessions.title', 'アクティブセッション')}
        </h3>
        <p className="mt-1 text-sm text-gray-600">
          {t('settings.sessions.description', '現在ログインしているデバイスを管理します')}
        </p>
      </div>

      <div className="space-y-4">
        <div className="border border-gray-200 rounded-lg p-4">
          <div className="flex items-center justify-between">
            <div>
              <h4 className="text-sm font-medium text-gray-900">
                {t('settings.sessions.current', '現在のセッション')}
              </h4>
              <p className="text-sm text-gray-600">
                Chrome - Tokyo, Japan - {new Date().toLocaleString()}
              </p>
            </div>
            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
              {t('settings.sessions.active', 'アクティブ')}
            </span>
          </div>
        </div>

        <div className="flex justify-end">
          <button className="btn-secondary text-red-600 border-red-300 hover:bg-red-50">
            {t('settings.sessions.logout_all', '他のセッションをすべてログアウト')}
          </button>
        </div>
      </div>
    </div>
  );
};