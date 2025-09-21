import React, { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../../hooks/useAuth';
import { TwoFactorAPI } from '../../api/twoFactor';

interface TOTPSettingsState {
  enabled: boolean;
  isEnabling: boolean;
  qrCode: string | null;
  secretKey: string | null;
  verificationCode: string;
  recoveryCodes: string[];
  showRecoveryCodes: boolean;
  loading: boolean;
  error: string | null;
  step: 'initial' | 'setup' | 'verify' | 'complete';
}

export const TOTPSettings: React.FC = () => {
  const { t } = useTranslation();
  const { user, token } = useAuth();
  const [state, setState] = useState<TOTPSettingsState>({
    enabled: false,
    isEnabling: false,
    qrCode: null,
    secretKey: null,
    verificationCode: '',
    recoveryCodes: [],
    showRecoveryCodes: false,
    loading: false,
    error: null,
    step: 'initial'
  });

  useEffect(() => {
    // ユーザーの2FA状態を確認
    if (user?.two_factor_confirmed_at) {
      setState(prev => ({ ...prev, enabled: true }));
    }
  }, [user]);

  const handleEnable = async () => {
    if (!token) return;

    setState(prev => ({ ...prev, loading: true, error: null }));

    try {
      const response = await TwoFactorAPI.enable(token);
      if (response.success) {
        const qrCode = await TwoFactorAPI.getQRCode(token);
        const secretData = await TwoFactorAPI.getSecretKey(token);

        setState(prev => ({
          ...prev,
          qrCode,
          secretKey: secretData.secret_key,
          step: 'setup',
          loading: false
        }));
      } else {
        setState(prev => ({
          ...prev,
          error: response.message || '2FAの有効化に失敗しました',
          loading: false
        }));
      }
    } catch {
      setState(prev => ({
        ...prev,
        error: '2FAの有効化に失敗しました',
        loading: false
      }));
    }
  };

  const handleVerify = async () => {
    if (!token || !state.verificationCode) return;

    setState(prev => ({ ...prev, loading: true, error: null }));

    try {
      const response = await TwoFactorAPI.confirm(token, state.verificationCode);
      if (response.success) {
        const recoveryData = await TwoFactorAPI.getRecoveryCodes(token);
        setState(prev => ({
          ...prev,
          enabled: true,
          recoveryCodes: recoveryData.recovery_codes,
          step: 'complete',
          loading: false
        }));
      } else {
        setState(prev => ({
          ...prev,
          error: response.message || '認証コードが正しくありません',
          loading: false
        }));
      }
    } catch {
      setState(prev => ({
        ...prev,
        error: '認証に失敗しました',
        loading: false
      }));
    }
  };

  const handleDisable = async () => {
    if (!token) return;

    const confirmed = window.confirm(
      t('settings.security.twofa.disable_confirm', '2FAを無効化しますか？セキュリティが低下する可能性があります。')
    );

    if (!confirmed) return;

    setState(prev => ({ ...prev, loading: true, error: null }));

    try {
      const response = await TwoFactorAPI.disable(token);
      if (response.success) {
        setState(prev => ({
          ...prev,
          enabled: false,
          step: 'initial',
          qrCode: null,
          secretKey: null,
          verificationCode: '',
          recoveryCodes: [],
          loading: false
        }));
      } else {
        setState(prev => ({
          ...prev,
          error: response.message || '2FAの無効化に失敗しました',
          loading: false
        }));
      }
    } catch {
      setState(prev => ({
        ...prev,
        error: '2FAの無効化に失敗しました',
        loading: false
      }));
    }
  };

  const handleShowRecoveryCodes = async () => {
    if (!token) return;

    setState(prev => ({ ...prev, loading: true, error: null }));

    try {
      const recoveryData = await TwoFactorAPI.getRecoveryCodes(token);
      setState(prev => ({
        ...prev,
        recoveryCodes: recoveryData.recovery_codes,
        showRecoveryCodes: true,
        loading: false
      }));
    } catch {
      setState(prev => ({
        ...prev,
        error: 'リカバリコードの取得に失敗しました',
        loading: false
      }));
    }
  };

  const handleRegenerateRecoveryCodes = async () => {
    if (!token) return;

    const confirmed = window.confirm(
      t('settings.security.twofa.regenerate_confirm', '新しいリカバリコードを生成しますか？古いコードは使用できなくなります。')
    );

    if (!confirmed) return;

    setState(prev => ({ ...prev, loading: true, error: null }));

    try {
      const recoveryData = await TwoFactorAPI.regenerateRecoveryCodes(token);
      setState(prev => ({
        ...prev,
        recoveryCodes: recoveryData.recovery_codes,
        showRecoveryCodes: true,
        loading: false
      }));
    } catch {
      setState(prev => ({
        ...prev,
        error: 'リカバリコードの再生成に失敗しました',
        loading: false
      }));
    }
  };

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    // トースト通知を追加したい場合はここで実装
  };

  if (state.enabled && state.step === 'initial') {
    return (
      <div className="border border-gray-200 rounded-lg p-6">
        <div className="flex items-center justify-between mb-4">
          <div>
            <h4 className="text-base font-medium text-gray-900">
              {t('settings.security.twofa.title', '二要素認証 (TOTP)')}
            </h4>
            <p className="text-sm text-gray-600">
              {t('settings.security.twofa.enabled_desc', '2FAが有効になっています')}
            </p>
          </div>
          <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
            {t('settings.security.twofa.enabled', '有効')}
          </span>
        </div>

        {state.error && (
          <div className="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
            {state.error}
          </div>
        )}

        <div className="space-y-3">
          <button
            onClick={handleShowRecoveryCodes}
            disabled={state.loading}
            className="btn-secondary w-full"
          >
            {state.loading ? t('common.loading', '読み込み中...') : t('settings.security.twofa.show_recovery', 'リカバリコードを表示')}
          </button>

          <button
            onClick={handleRegenerateRecoveryCodes}
            disabled={state.loading}
            className="btn-secondary w-full"
          >
            {t('settings.security.twofa.regenerate_recovery', 'リカバリコードを再生成')}
          </button>

          <button
            onClick={handleDisable}
            disabled={state.loading}
            className="btn-secondary w-full text-red-600 border-red-300 hover:bg-red-50"
          >
            {state.loading ? t('common.loading', '読み込み中...') : t('settings.security.twofa.disable', '2FAを無効化')}
          </button>
        </div>

        {state.showRecoveryCodes && state.recoveryCodes.length > 0 && (
          <div className="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
            <h5 className="font-medium text-yellow-800 mb-2">
              {t('settings.security.twofa.recovery_codes', 'リカバリコード')}
            </h5>
            <p className="text-sm text-yellow-700 mb-3">
              {t('settings.security.twofa.recovery_desc', 'これらのコードを安全な場所に保存してください。認証アプリにアクセスできない場合に使用できます。')}
            </p>
            <div className="grid grid-cols-2 gap-2 mb-3">
              {state.recoveryCodes.map((code, index) => (
                <div
                  key={index}
                  className="bg-white p-2 rounded border font-mono text-sm cursor-pointer hover:bg-gray-50"
                  onClick={() => copyToClipboard(code)}
                >
                  {code}
                </div>
              ))}
            </div>
            <button
              onClick={() => setState(prev => ({ ...prev, showRecoveryCodes: false }))}
              className="text-sm text-yellow-700 hover:text-yellow-800"
            >
              {t('common.close', '閉じる')}
            </button>
          </div>
        )}
      </div>
    );
  }

  return (
    <div className="border border-gray-200 rounded-lg p-6">
      <h4 className="text-base font-medium text-gray-900 mb-4">
        {t('settings.security.twofa.title', '二要素認証 (TOTP)')}
      </h4>

      {state.error && (
        <div className="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
          {state.error}
        </div>
      )}

      {state.step === 'initial' && (
        <div>
          <p className="text-sm text-gray-600 mb-4">
            {t('settings.security.twofa.description', 'TOTPアプリを使用してアカウントのセキュリティを強化します')}
          </p>
          <button
            onClick={handleEnable}
            disabled={state.loading}
            className="btn-primary"
          >
            {state.loading ? t('common.loading', '読み込み中...') : t('settings.security.twofa.enable', '2FAを有効化')}
          </button>
        </div>
      )}

      {state.step === 'setup' && (
        <div className="space-y-4">
          <div>
            <h5 className="font-medium text-gray-900 mb-2">
              {t('settings.security.twofa.setup_step1', 'ステップ1: 認証アプリでQRコードをスキャン')}
            </h5>
            <p className="text-sm text-gray-600 mb-3">
              {t('settings.security.twofa.setup_apps', 'Google Authenticator、Authy、1Passwordなどの認証アプリを使用してください')}
            </p>
            {state.qrCode && (
              <div className="flex justify-center p-4 bg-white border rounded-lg">
                <div dangerouslySetInnerHTML={{ __html: state.qrCode }} />
              </div>
            )}
          </div>

          {state.secretKey && (
            <div>
              <h5 className="font-medium text-gray-900 mb-2">
                {t('settings.security.twofa.manual_entry', '手動入力用キー')}
              </h5>
              <div className="flex items-center space-x-2">
                <input
                  type="text"
                  value={state.secretKey}
                  readOnly
                  className="flex-1 p-2 border border-gray-300 rounded bg-gray-50 font-mono text-sm"
                />
                <button
                  onClick={() => copyToClipboard(state.secretKey!)}
                  className="btn-secondary"
                >
                  {t('common.copy', 'コピー')}
                </button>
              </div>
            </div>
          )}

          <div>
            <h5 className="font-medium text-gray-900 mb-2">
              {t('settings.security.twofa.setup_step2', 'ステップ2: 認証コードを入力')}
            </h5>
            <div className="flex space-x-2">
              <input
                type="text"
                placeholder="123456"
                value={state.verificationCode}
                onChange={(e) => setState(prev => ({ ...prev, verificationCode: e.target.value }))}
                className="flex-1 p-2 border border-gray-300 rounded focus:ring-orange-500 focus:border-orange-500"
                maxLength={6}
              />
              <button
                onClick={handleVerify}
                disabled={state.loading || !state.verificationCode}
                className="btn-primary"
              >
                {state.loading ? t('common.loading', '読み込み中...') : t('settings.security.twofa.verify', '確認')}
              </button>
            </div>
          </div>
        </div>
      )}

      {state.step === 'complete' && (
        <div className="space-y-4">
          <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
            {t('settings.security.twofa.enabled_success', '2FAが正常に有効化されました！')}
          </div>

          <div className="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
            <h5 className="font-medium text-yellow-800 mb-2">
              {t('settings.security.twofa.recovery_codes', 'リカバリコード')}
            </h5>
            <p className="text-sm text-yellow-700 mb-3">
              {t('settings.security.twofa.recovery_desc', 'これらのコードを安全な場所に保存してください。認証アプリにアクセスできない場合に使用できます。')}
            </p>
            <div className="grid grid-cols-2 gap-2 mb-3">
              {state.recoveryCodes.map((code, index) => (
                <div
                  key={index}
                  className="bg-white p-2 rounded border font-mono text-sm cursor-pointer hover:bg-gray-50"
                  onClick={() => copyToClipboard(code)}
                >
                  {code}
                </div>
              ))}
            </div>
          </div>

          <button
            onClick={() => setState(prev => ({ ...prev, step: 'initial' }))}
            className="btn-primary w-full"
          >
            {t('common.complete', '完了')}
          </button>
        </div>
      )}
    </div>
  );
};