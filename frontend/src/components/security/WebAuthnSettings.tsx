import React, { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../../hooks/useAuth';
import {
  WebAuthnAPI,
  WebAuthnUtils,
  type WebAuthnCredential,
} from '../../api/webauthn';
import { ConfirmDialog } from '../ui/ConfirmDialog';

interface WebAuthnSettingsState {
  credentials: WebAuthnCredential[];
  isSupported: boolean;
  isPlatformAvailable: boolean;
  isRegistering: boolean;
  loading: boolean;
  error: string | null;
  newCredentialAlias: string;
  confirmDialog: {
    isOpen: boolean;
    credentialId: string;
    credentialName: string;
    action: 'disable' | 'delete';
  };
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
    confirmDialog: {
      isOpen: false,
      credentialId: '',
      credentialName: '',
      action: 'disable',
    },
  });

  useEffect(() => {
    const loadCredentialsIfNeeded = async () => {
      if (token) {
        setState((prev) => ({ ...prev, loading: true, error: null }));

        try {
          const credentials = await WebAuthnAPI.getCredentials(token);
          setState((prev) => ({
            ...prev,
            credentials,
            loading: false,
          }));
        } catch {
          setState((prev) => ({
            ...prev,
            error: t('settings.security.webauthn.fetch_failed'),
            loading: false,
          }));
        }
      }
    };

    checkWebAuthnSupport();
    loadCredentialsIfNeeded();
  }, [token, t]);

  const checkWebAuthnSupport = async () => {
    const isSupported = WebAuthnUtils.isSupported();
    const isPlatformAvailable = isSupported
      ? await WebAuthnUtils.isPlatformAuthenticatorAvailable()
      : false;

    setState((prev) => ({
      ...prev,
      isSupported,
      isPlatformAvailable,
    }));
  };

  const loadCredentials = async () => {
    if (!token) return;

    setState((prev) => ({ ...prev, loading: true, error: null }));

    try {
      const credentials = await WebAuthnAPI.getCredentials(token);
      setState((prev) => ({
        ...prev,
        credentials,
        loading: false,
      }));
    } catch {
      setState((prev) => ({
        ...prev,
        error: t('settings.security.webauthn.fetch_failed'),
        loading: false,
      }));
    }
  };

  const handleRegister = async () => {
    if (!token) return;

    setState((prev) => ({ ...prev, isRegistering: true, error: null }));

    try {
      // 1. 登録オプションを取得
      const options = await WebAuthnAPI.getRegistrationOptions(token);

      // 2. WebAuthn API用に変換
      const createOptions = WebAuthnUtils.convertRegistrationOptions(options);

      // 3. WebAuthn認証器で登録
      const credential = (await navigator.credentials.create({
        publicKey: createOptions,
      })) as PublicKeyCredential;

      if (!credential) {
        throw new Error(t('settings.security.webauthn.registration_cancelled'));
      }

      // 4. サーバー送信用に変換
      const credentialData =
        WebAuthnUtils.convertRegistrationResponse(credential);

      // 5. サーバーに送信
      const response = await WebAuthnAPI.registerCredential(
        token,
        credentialData,
        state.newCredentialAlias || undefined
      );

      if (response.success) {
        setState((prev) => ({
          ...prev,
          isRegistering: false,
          newCredentialAlias: '',
        }));
        await loadCredentials(); // 認証器一覧を再読み込み
      } else {
        setState((prev) => ({
          ...prev,
          error: t('settings.security.webauthn.registration_failed'),
          isRegistering: false,
        }));
      }
    } catch (error: unknown) {
      let errorMessage = t('settings.security.webauthn.registration_failed');

      if (error instanceof Error) {
        // WebAuthnの特定エラーをハンドリング
        if (error.name === 'NotAllowedError') {
          // ユーザーがキャンセルした場合
          errorMessage = t(
            'settings.security.webauthn.registration_cancelled',
            '登録がキャンセルされました'
          );
        } else if (error.name === 'TimeoutError') {
          // タイムアウトの場合
          errorMessage = t(
            'settings.security.webauthn.registration_timeout',
            '登録がタイムアウトしました'
          );
        } else if (error.name === 'InvalidStateError') {
          // 既に登録済みの認証器の場合
          errorMessage = t(
            'settings.security.webauthn.registration_invalid_state',
            '認証器の状態が無効です。別の認証器をお試しください'
          );
        } else if (error.name === 'NotSupportedError') {
          // サポートされていない場合
          errorMessage = t(
            'settings.security.webauthn.registration_not_supported',
            'この認証器はサポートされていません'
          );
        } else if (error.message.includes('Invalid base64url string')) {
          // base64url変換エラーの場合
          errorMessage = t(
            'settings.security.webauthn.invalid_response',
            'サーバーからの応答が無効です'
          );
        } else {
          errorMessage = error.message;
        }
      }

      setState((prev) => ({
        ...prev,
        error: errorMessage,
        isRegistering: false,
      }));
    }
  };

  const handleDisable = (credentialId: string) => {
    const credential = state.credentials.find((c) => c.id === credentialId);
    const credentialName =
      credential?.alias ||
      t('settings.security.webauthn.unnamed_credential');

    setState((prev) => ({
      ...prev,
      confirmDialog: {
        isOpen: true,
        credentialId,
        credentialName,
        action: 'disable',
      },
    }));
  };

  const handleDelete = (credentialId: string) => {
    const credential = state.credentials.find((c) => c.id === credentialId);
    const credentialName =
      credential?.alias ||
      t('settings.security.webauthn.unnamed_credential');

    setState((prev) => ({
      ...prev,
      confirmDialog: {
        isOpen: true,
        credentialId,
        credentialName,
        action: 'delete',
      },
    }));
  };

  const handleEnable = async (credentialId: string) => {
    if (!token) return;

    setState((prev) => ({ ...prev, loading: true, error: null }));

    try {
      const response = await WebAuthnAPI.enableCredential(token, credentialId);
      if (response.success) {
        await loadCredentials();
      } else {
        setState((prev) => ({
          ...prev,
          error: t('settings.security.webauthn.enable_failed'),
          loading: false,
        }));
      }
    } catch {
      setState((prev) => ({
        ...prev,
        error: t(
          'settings.security.webauthn.enable_failed',
          'WebAuthn認証器の有効化に失敗しました'
        ),
        loading: false,
      }));
    }
  };

  const handleConfirmAction = async () => {
    if (!token || !state.confirmDialog.credentialId) return;

    const { credentialId, action } = state.confirmDialog;

    setState((prev) => ({
      ...prev,
      loading: true,
      error: null,
      confirmDialog: {
        isOpen: false,
        credentialId: '',
        credentialName: '',
        action: 'disable',
      },
    }));

    try {
      let response;
      if (action === 'delete') {
        response = await WebAuthnAPI.deleteCredential(token, credentialId);
      } else {
        response = await WebAuthnAPI.disableCredential(token, credentialId);
      }

      if (response.success) {
        await loadCredentials();
      } else {
        const errorKey =
          action === 'delete'
            ? 'settings.security.webauthn.delete_failed'
            : 'settings.security.webauthn.disable_failed';
        setState((prev) => ({
          ...prev,
          error: t(errorKey),
          loading: false,
        }));
      }
    } catch {
      const errorKey =
        action === 'delete'
          ? 'settings.security.webauthn.delete_failed'
          : 'settings.security.webauthn.disable_failed';
      setState((prev) => ({
        ...prev,
        error: t(errorKey),
        loading: false,
      }));
    }
  };

  const handleCancelAction = () => {
    setState((prev) => ({
      ...prev,
      confirmDialog: {
        isOpen: false,
        credentialId: '',
        credentialName: '',
        action: 'disable',
      },
    }));
  };

  const handleUpdateAlias = async (credentialId: string, newAlias: string) => {
    if (!token) return;

    setState((prev) => ({ ...prev, loading: true, error: null }));

    try {
      const response = await WebAuthnAPI.updateCredentialAlias(
        token,
        credentialId,
        newAlias
      );
      if (response.success) {
        await loadCredentials();
      } else {
        setState((prev) => ({
          ...prev,
          error: t('settings.security.webauthn.alias_update_failed'),
          loading: false,
        }));
      }
    } catch {
      setState((prev) => ({
        ...prev,
        error: t('settings.security.webauthn.alias_update_failed'),
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
          {t(
            'settings.security.webauthn.not_supported',
            'お使いのブラウザはWebAuthnをサポートしていません'
          )}
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
        {t(
          'settings.security.webauthn.description',
          'セキュリティキーや生体認証でパスワードレス認証が利用できます'
        )}
      </p>

      {/* 新しい認証器の登録 */}
      <div className="mb-6">
        <h5 className="font-medium text-gray-900 mb-3">
          {t('settings.security.webauthn.register_new')}
        </h5>

        <div className="space-y-3">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              {t('settings.security.webauthn.alias')}
            </label>
            <input
              type="text"
              value={state.newCredentialAlias}
              onChange={(e) =>
                setState((prev) => ({
                  ...prev,
                  newCredentialAlias: e.target.value,
                }))
              }
              placeholder={t(
                'settings.security.webauthn.alias_placeholder',
                '例: iPhone Touch ID、YubiKey'
              )}
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
              ? t('settings.security.webauthn.registering')
              : t('settings.security.webauthn.register')}
          </button>

          {state.isPlatformAvailable && (
            <p className="text-xs text-gray-500">
              {t(
                'settings.security.webauthn.platform_available',
                'このデバイスの生体認証が利用可能です'
              )}
            </p>
          )}
        </div>
      </div>

      {/* 登録済み認証器一覧 */}
      <div>
        <h5 className="font-medium text-gray-900 mb-3">
          {t(
            'settings.security.webauthn.registered_credentials',
            '登録済み認証器'
          )}
        </h5>

        {state.loading ? (
          <div className="text-center py-4">
            <div className="text-gray-500">
              {t('common.loading')}
            </div>
          </div>
        ) : state.credentials.length === 0 ? (
          <div className="text-center py-4 text-gray-500">
            {t(
              'settings.security.webauthn.no_credentials',
              '登録済みの認証器がありません'
            )}
          </div>
        ) : (
          <div className="space-y-3">
            {state.credentials.map((credential) => (
              <CredentialItem
                key={credential.id}
                credential={credential}
                onDisable={handleDisable}
                onEnable={handleEnable}
                onDelete={handleDelete}
                onUpdateAlias={handleUpdateAlias}
                disabled={state.loading}
              />
            ))}
          </div>
        )}
      </div>

      {state.confirmDialog.isOpen &&
        createPortal(
          <ConfirmDialog
            isOpen={state.confirmDialog.isOpen}
            title={
              state.confirmDialog.action === 'delete'
                ? t(
                    'settings.security.webauthn.delete_confirm_title',
                    '認証器の削除'
                  )
                : t(
                    'settings.security.webauthn.disable_confirm_title',
                    '認証器の無効化'
                  )
            }
            message={
              state.confirmDialog.action === 'delete'
                ? t(
                    'settings.security.webauthn.delete_confirm_message',
                    '「{{credentialName}}」を完全に削除しますか？この操作は取り消せません。',
                    {
                      credentialName: state.confirmDialog.credentialName,
                    }
                  )
                : t(
                    'settings.security.webauthn.disable_confirm_message',
                    '「{{credentialName}}」を無効化しますか？この操作は元に戻せません。',
                    {
                      credentialName: state.confirmDialog.credentialName,
                    }
                  )
            }
            confirmText={
              state.loading
                ? state.confirmDialog.action === 'delete'
                  ? t('settings.security.webauthn.deleting')
                  : t('settings.security.webauthn.disabling')
                : state.confirmDialog.action === 'delete'
                  ? t('settings.security.webauthn.delete')
                  : t('settings.security.webauthn.disable')
            }
            cancelText={t('common.cancel')}
            confirmVariant="danger"
            onConfirm={handleConfirmAction}
            onCancel={handleCancelAction}
          />,
          document.body
        )}
    </div>
  );
};

// 認証器アイテムコンポーネント
interface CredentialItemProps {
  credential: WebAuthnCredential;
  onDisable: (credentialId: string) => void;
  onEnable: (credentialId: string) => void;
  onDelete: (credentialId: string) => void;
  onUpdateAlias: (credentialId: string, alias: string) => void;
  disabled: boolean;
}

const CredentialItem: React.FC<CredentialItemProps> = ({
  credential,
  onDisable,
  onEnable,
  onDelete,
  onUpdateAlias,
  disabled,
}) => {
  const { t } = useTranslation();
  const [isEditing, setIsEditing] = useState(false);
  const [editAlias, setEditAlias] = useState(credential.alias || '');

  const handleSaveAlias = async () => {
    try {
      await onUpdateAlias(credential.id, editAlias);
      setIsEditing(false);
    } catch (error) {
      console.error('Failed to update alias:', error);
    }
  };

  const handleCancelEdit = () => {
    setEditAlias(credential.alias || '');
    setIsEditing(false);
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('ja-JP');
  };

  const isDisabled = !!credential.disabled_at;

  return (
    <div
      className={`flex items-center justify-between p-4 border rounded-lg ${
        isDisabled ? 'border-red-200 bg-red-50' : 'border-gray-200'
      }`}
    >
      <div className="flex-1">
        <div className="flex items-center gap-2 mb-1">
          <div
            className={`w-3 h-3 rounded-full ${
              isDisabled ? 'bg-red-400' : 'bg-green-400'
            }`}
          ></div>
          {isEditing ? (
            <input
              type="text"
              value={editAlias}
              onChange={(e) => setEditAlias(e.target.value)}
              placeholder={t(
                'settings.security.webauthn.alias_placeholder',
                '例: iPhone Touch ID、YubiKey'
              )}
              className="flex-1 text-sm font-medium text-gray-900 border border-gray-300 rounded px-2 py-1 focus:ring-blue-500 focus:border-blue-500"
              disabled={disabled}
            />
          ) : (
            <h6 className="text-sm font-medium text-gray-900">
              {credential.alias ||
                t(
                  'settings.security.webauthn.unnamed_credential',
                  '名前なし認証器'
                )}
            </h6>
          )}
        </div>
        <div className="text-xs text-gray-500">
          {t('settings.security.webauthn.registered_on')}:{' '}
          {formatDate(credential.created_at)}
        </div>
        {isDisabled && (
          <div className="text-xs text-red-500">
            {t('settings.security.webauthn.disabled_on')}:{' '}
            {formatDate(credential.disabled_at)}
          </div>
        )}
        <div className="text-xs text-gray-500">
          ID: {credential.id.substring(0, 16)}...
        </div>
        {isDisabled && (
          <div className="text-xs text-red-600 font-medium">
            {t('settings.security.webauthn.disabled_status')}
          </div>
        )}
      </div>

      <div className="flex items-center gap-2 ml-4">
        {isEditing ? (
          <>
            <button
              onClick={handleSaveAlias}
              disabled={disabled}
              className="text-xs text-blue-600 hover:text-blue-700 disabled:opacity-50"
            >
              {t('common.save')}
            </button>
            <button
              onClick={handleCancelEdit}
              disabled={disabled}
              className="text-xs text-gray-600 hover:text-gray-700 disabled:opacity-50"
            >
              {t('common.cancel')}
            </button>
          </>
        ) : (
          <>
            {!isDisabled && (
              <button
                onClick={() => setIsEditing(true)}
                disabled={disabled}
                className="text-xs text-blue-600 hover:text-blue-700 disabled:opacity-50"
              >
                {t('common.edit')}
              </button>
            )}
            {isDisabled && (
              <button
                onClick={() => onEnable(credential.id)}
                disabled={disabled}
                className="text-xs text-green-600 hover:text-green-700 disabled:opacity-50"
              >
                {t('settings.security.webauthn.enable')}
              </button>
            )}
            <button
              onClick={() => onDisable(credential.id)}
              disabled={disabled}
              className="text-xs text-red-600 hover:text-red-700 disabled:opacity-50"
            >
              {t('settings.security.webauthn.disable')}
            </button>
            <button
              onClick={() => onDelete(credential.id)}
              disabled={disabled}
              className="text-xs text-red-600 hover:text-red-700 disabled:opacity-50"
            >
              {t('settings.security.webauthn.delete')}
            </button>
          </>
        )}
      </div>
    </div>
  );
};
