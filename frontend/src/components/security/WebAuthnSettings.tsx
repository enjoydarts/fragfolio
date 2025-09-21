import React, { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../../hooks/useAuth';
import { WebAuthnAPI, WebAuthnUtils, type WebAuthnCredential } from '../../api/webauthn';

interface WebAuthnSettingsState {
  credentials: WebAuthnCredential[];
  isSupported: boolean;
  isPlatformAvailable: boolean;
  isRegistering: boolean;
  loading: boolean;
  error: string | null;
  newCredentialAlias: string;
}

export const WebAuthnSettings: React.FC = () => {
  const { t } = useTranslation();
  const { token } = useAuth();
  const [state, setState] = useState<WebAuthnSettingsState>({
    credentials: [],
    isSupported: false,
    isPlatformAvailable: false,
    isRegistering: false,
    loading: false,
    error: null,
    newCredentialAlias: '',
  });

  useEffect(() => {
    const loadCredentialsIfNeeded = async () => {
      if (token) {
        setState(prev => ({ ...prev, loading: true, error: null }));

        try {
          const credentials = await WebAuthnAPI.getCredentials(token);
          setState(prev => ({
            ...prev,
            credentials,
            loading: false,
          }));
        } catch {
          setState(prev => ({
            ...prev,
            error: 'WebAuthn認証器の取得に失敗しました',
            loading: false,
          }));
        }
      }
    };

    checkWebAuthnSupport();
    loadCredentialsIfNeeded();
  }, [token]);

  const checkWebAuthnSupport = async () => {
    const isSupported = WebAuthnUtils.isSupported();
    const isPlatformAvailable = isSupported
      ? await WebAuthnUtils.isPlatformAuthenticatorAvailable()
      : false;

    setState(prev => ({
      ...prev,
      isSupported,
      isPlatformAvailable,
    }));
  };

  const loadCredentials = async () => {
    if (!token) return;

    setState(prev => ({ ...prev, loading: true, error: null }));

    try {
      const credentials = await WebAuthnAPI.getCredentials(token);
      setState(prev => ({
        ...prev,
        credentials,
        loading: false,
      }));
    } catch {
      setState(prev => ({
        ...prev,
        error: 'WebAuthn認証器の取得に失敗しました',
        loading: false,
      }));
    }
  };

  const handleRegister = async () => {
    if (!token) return;

    setState(prev => ({ ...prev, isRegistering: true, error: null }));

    try {
      // 1. 登録オプションを取得
      const options = await WebAuthnAPI.getRegistrationOptions(token);

      // 2. WebAuthn API用に変換
      const createOptions = WebAuthnUtils.convertRegistrationOptions(options);

      // 3. WebAuthn認証器で登録
      const credential = await navigator.credentials.create({
        publicKey: createOptions,
      }) as PublicKeyCredential;

      if (!credential) {
        throw new Error('認証器の登録がキャンセルされました');
      }

      // 4. サーバー送信用に変換
      const credentialData = WebAuthnUtils.convertRegistrationResponse(credential);

      // 5. サーバーに送信
      const response = await WebAuthnAPI.registerCredential(
        token,
        credentialData,
        state.newCredentialAlias || undefined
      );

      if (response.success) {
        setState(prev => ({
          ...prev,
          isRegistering: false,
          newCredentialAlias: '',
        }));
        await loadCredentials(); // 認証器一覧を再読み込み
      } else {
        setState(prev => ({
          ...prev,
          error: response.message || 'WebAuthn認証器の登録に失敗しました',
          isRegistering: false,
        }));
      }
    } catch (error: unknown) {
      setState(prev => ({
        ...prev,
        error: error instanceof Error ? error.message : 'WebAuthn認証器の登録に失敗しました',
        isRegistering: false,
      }));
    }
  };

  const handleDisable = async (credentialId: string) => {
    if (!token) return;

    const confirmed = window.confirm(
      t('settings.security.webauthn.disable_confirm', 'この認証器を無効化しますか？')
    );

    if (!confirmed) return;

    setState(prev => ({ ...prev, loading: true, error: null }));

    try {
      const response = await WebAuthnAPI.disableCredential(token, credentialId);
      if (response.success) {
        await loadCredentials();
      } else {
        setState(prev => ({
          ...prev,
          error: response.message || '認証器の無効化に失敗しました',
          loading: false,
        }));
      }
    } catch {
      setState(prev => ({
        ...prev,
        error: '認証器の無効化に失敗しました',
        loading: false,
      }));
    }
  };

  const handleUpdateAlias = async (credentialId: string, newAlias: string) => {
    if (!token) return;

    setState(prev => ({ ...prev, loading: true, error: null }));

    try {
      const response = await WebAuthnAPI.updateCredentialAlias(token, credentialId, newAlias);
      if (response.success) {
        await loadCredentials();
      } else {
        setState(prev => ({
          ...prev,
          error: response.message || 'エイリアスの更新に失敗しました',
          loading: false,
        }));
      }
    } catch {
      setState(prev => ({
        ...prev,
        error: 'エイリアスの更新に失敗しました',
        loading: false,
      }));
    }
  };

  if (!state.isSupported) {
    return (
      <div className="border border-gray-200 rounded-lg p-6">
        <h4 className="text-base font-medium text-gray-900 mb-4">
          {t('settings.security.webauthn.title', 'WebAuthn / FIDO2')}
        </h4>
        <div className="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded">
          {t('settings.security.webauthn.not_supported', 'お使いのブラウザはWebAuthnをサポートしていません')}
        </div>
      </div>
    );
  }

  return (
    <div className="border border-gray-200 rounded-lg p-6">
      <h4 className="text-base font-medium text-gray-900 mb-4">
        {t('settings.security.webauthn.title', 'WebAuthn / FIDO2')}
      </h4>

      {state.error && (
        <div className="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
          {state.error}
        </div>
      )}

      <p className="text-sm text-gray-600 mb-6">
        {t('settings.security.webauthn.description', 'セキュリティキーや生体認証でパスワードレス認証が利用できます')}
      </p>

      {/* 新しい認証器の登録 */}
      <div className="mb-6">
        <h5 className="font-medium text-gray-900 mb-3">
          {t('settings.security.webauthn.register_new', '新しい認証器を登録')}
        </h5>

        <div className="space-y-3">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              {t('settings.security.webauthn.alias', '認証器名 (任意)')}
            </label>
            <input
              type="text"
              value={state.newCredentialAlias}
              onChange={(e) => setState(prev => ({ ...prev, newCredentialAlias: e.target.value }))}
              placeholder={t('settings.security.webauthn.alias_placeholder', '例: iPhone Touch ID、YubiKey')}
              className="w-full p-2 border border-gray-300 rounded focus:ring-orange-500 focus:border-orange-500"
              disabled={state.isRegistering}
            />
          </div>

          <button
            onClick={handleRegister}
            disabled={state.isRegistering}
            className="btn-primary"
          >
            {state.isRegistering
              ? t('settings.security.webauthn.registering', '登録中...')
              : t('settings.security.webauthn.register', '認証器を登録')
            }
          </button>

          {state.isPlatformAvailable && (
            <p className="text-xs text-gray-500">
              {t('settings.security.webauthn.platform_available', 'このデバイスの生体認証が利用可能です')}
            </p>
          )}
        </div>
      </div>

      {/* 登録済み認証器一覧 */}
      <div>
        <h5 className="font-medium text-gray-900 mb-3">
          {t('settings.security.webauthn.registered_credentials', '登録済み認証器')}
        </h5>

        {state.loading ? (
          <div className="text-center py-4">
            <div className="text-gray-500">{t('common.loading', '読み込み中...')}</div>
          </div>
        ) : state.credentials.length === 0 ? (
          <div className="text-center py-4 text-gray-500">
            {t('settings.security.webauthn.no_credentials', '登録済みの認証器がありません')}
          </div>
        ) : (
          <div className="space-y-3">
            {state.credentials.map((credential) => (
              <CredentialItem
                key={credential.id}
                credential={credential}
                onDisable={handleDisable}
                onUpdateAlias={handleUpdateAlias}
                disabled={state.loading}
              />
            ))}
          </div>
        )}
      </div>
    </div>
  );
};

// 個別の認証器アイテムコンポーネント
interface CredentialItemProps {
  credential: WebAuthnCredential;
  onDisable: (credentialId: string) => void;
  onUpdateAlias: (credentialId: string, alias: string) => void;
  disabled: boolean;
}

const CredentialItem: React.FC<CredentialItemProps> = ({
  credential,
  onDisable,
  onUpdateAlias,
  disabled,
}) => {
  const { t } = useTranslation();
  const [isEditing, setIsEditing] = useState(false);
  const [editAlias, setEditAlias] = useState(credential.alias || '');

  const handleSaveAlias = () => {
    if (editAlias !== credential.alias) {
      onUpdateAlias(credential.id, editAlias);
    }
    setIsEditing(false);
  };

  const handleCancelEdit = () => {
    setEditAlias(credential.alias || '');
    setIsEditing(false);
  };

  return (
    <div className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
      <div className="flex-1">
        {isEditing ? (
          <div className="flex items-center space-x-2">
            <input
              type="text"
              value={editAlias}
              onChange={(e) => setEditAlias(e.target.value)}
              placeholder={t('settings.security.webauthn.alias_placeholder', '認証器名')}
              className="flex-1 p-1 border border-gray-300 rounded text-sm"
              autoFocus
            />
            <button
              onClick={handleSaveAlias}
              disabled={disabled}
              className="text-xs bg-orange-500 text-white px-2 py-1 rounded hover:bg-orange-600 disabled:opacity-50"
            >
              {t('common.save', '保存')}
            </button>
            <button
              onClick={handleCancelEdit}
              disabled={disabled}
              className="text-xs bg-gray-500 text-white px-2 py-1 rounded hover:bg-gray-600 disabled:opacity-50"
            >
              {t('common.cancel', 'キャンセル')}
            </button>
          </div>
        ) : (
          <div>
            <div className="flex items-center space-x-2">
              <h6 className="font-medium text-gray-900">
                {credential.alias || t('settings.security.webauthn.unnamed_credential', '名前なし認証器')}
              </h6>
              {!credential.disabled_at && (
                <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                  {t('settings.security.webauthn.active', 'アクティブ')}
                </span>
              )}
            </div>
            <p className="text-sm text-gray-600">
              {t('settings.security.webauthn.registered_at', '登録日')}: {new Date(credential.created_at).toLocaleDateString()}
            </p>
            <p className="text-xs text-gray-500 font-mono">
              ID: {credential.id.substring(0, 20)}...
            </p>
          </div>
        )}
      </div>

      {!isEditing && (
        <div className="flex items-center space-x-2">
          <button
            onClick={() => setIsEditing(true)}
            disabled={disabled}
            className="text-sm text-blue-600 hover:text-blue-800 disabled:opacity-50"
          >
            {t('common.edit', '編集')}
          </button>
          <button
            onClick={() => onDisable(credential.id)}
            disabled={disabled}
            className="text-sm text-red-600 hover:text-red-800 disabled:opacity-50"
          >
            {t('settings.security.webauthn.disable', '無効化')}
          </button>
        </div>
      )}
    </div>
  );
};