import React, { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../../hooks/useAuth';
import { TwoFactorAPI } from '../../api/twoFactor';
import { useToastContext } from '../../contexts/ToastContext';
import { ConfirmDialog } from '../ui/ConfirmDialog';
import { ExclamationTriangleIcon } from '@heroicons/react/24/outline';

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

interface ConfirmDialogState {
  isOpen: boolean;
  type: 'disable' | 'regenerate' | null;
}

export const TOTPSettings: React.FC = () => {
  const { t } = useTranslation();
  const { user, token } = useAuth();
  const { toast } = useToastContext();
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

  const [confirmDialog, setConfirmDialog] = useState<ConfirmDialogState>({
    isOpen: false,
    type: null
  });

  useEffect(() => {
    // ユーザーの2FA状態を確認
    if (user?.two_factor_confirmed_at) {
      setState(prev => ({ ...prev, enabled: true }));
    } else {
      // 2FA状態をAPIで確認
      checkTwoFactorStatus();
    }
  }, [user]);

  const copyToClipboard = async (text: string) => {
    try {
      await navigator.clipboard.writeText(text);
      toast.success(t('settings.security.twofa.code_copied', 'リカバリコードをコピーしました'));
    } catch (error) {
      console.error('Failed to copy:', error);
      toast.error(t('settings.security.twofa.copy_failed', 'コピーに失敗しました'));
    }
  };

  const copyAllRecoveryCodes = async () => {
    try {
      const allCodes = state.recoveryCodes.join('\n');
      await navigator.clipboard.writeText(allCodes);
      toast.success(t('settings.security.twofa.all_codes_copied', 'すべてのリカバリコードをコピーしました'));
    } catch (error) {
      console.error('Failed to copy all codes:', error);
      toast.error(t('settings.security.twofa.copy_failed', 'コピーに失敗しました'));
    }
  };

  const checkTwoFactorStatus = async () => {
    if (!token) return;

    try {
      const status = await TwoFactorAPI.getStatus(token);

      if (status.confirmed) {
        // 2FA確認済み = 有効
        setState(prev => ({ ...prev, enabled: true, step: 'initial' }));
      } else if (status.enabled) {
        // 2FA有効化済みだが未確認 = 認証待ち
        setState(prev => ({ ...prev, enabled: false, step: 'verify' }));

        // QRコードと秘密キーを取得
        try {
          const [qrCode, secretData] = await Promise.all([
            TwoFactorAPI.getQRCode(token),
            TwoFactorAPI.getSecretKey(token)
          ]);

          setState(prev => ({
            ...prev,
            qrCode,
            secretKey: secretData.secret_key,
          }));
        } catch (error) {
          console.error('Failed to load 2FA setup data:', error);
        }
      } else {
        // 2FA未有効化
        setState(prev => ({ ...prev, enabled: false, step: 'initial' }));
      }
    } catch (error) {
      console.error('2FA status check failed:', error);
      setState(prev => ({ ...prev, step: 'initial' }));
    }
  };

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
          error: response.message || t('settings.security.twofa.enable_failed'),
          loading: false
        }));
      }
    } catch (error: unknown) {
      console.error('2FA enable error:', error);
      const errorMessage = error instanceof Error ? error.message : t('settings.security.twofa.enable_failed');
      setState(prev => ({
        ...prev,
        error: errorMessage,
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
          recoveryCodes: recoveryData.recovery_codes || [],
          step: 'complete',
          loading: false
        }));
      } else {
        setState(prev => ({
          ...prev,
          error: response.message || t('settings.security.twofa.invalid_code'),
          loading: false
        }));
      }
    } catch (error) {
      console.error('2FA verification error:', error);
      setState(prev => ({
        ...prev,
        error: error instanceof Error ? error.message : t('settings.security.twofa.verification_failed'),
        loading: false
      }));
    }
  };

  const handleDisable = () => {
    setConfirmDialog({ isOpen: true, type: 'disable' });
  };

  const handleConfirmAction = async () => {
    if (!token || !confirmDialog.type) return;

    const actionType = confirmDialog.type;

    setConfirmDialog({ isOpen: false, type: null });
    setState(prev => ({
      ...prev,
      loading: true,
      error: null
    }));

    if (actionType === 'disable') {

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
            error: response.message || t('settings.security.twofa.disable_failed'),
            loading: false
          }));
        }
      } catch {
        setState(prev => ({
          ...prev,
          error: t('settings.security.twofa.disable_failed'),
          loading: false
        }));
      }
    } else if (actionType === 'regenerate') {
      try {
        const recoveryData = await TwoFactorAPI.regenerateRecoveryCodes(token);
        setState(prev => ({
          ...prev,
          recoveryCodes: recoveryData.recovery_codes || [],
          showRecoveryCodes: true,
          loading: false
        }));
      } catch (error) {
        console.error('Recovery codes regeneration error:', error);
        setState(prev => ({
          ...prev,
          error: t('settings.security.twofa.recovery_regenerate_failed'),
          loading: false
        }));
      }
    }
  };

  const handleShowRecoveryCodes = async () => {
    if (!token) return;

    setState(prev => ({ ...prev, loading: true, error: null }));

    try {
      const recoveryData = await TwoFactorAPI.getRecoveryCodes(token);
      setState(prev => ({
        ...prev,
        recoveryCodes: recoveryData.recovery_codes || [],
        showRecoveryCodes: true,
        loading: false
      }));
    } catch {
      setState(prev => ({
        ...prev,
        error: t('settings.security.twofa.recovery_fetch_failed'),
        loading: false
      }));
    }
  };

  const handleRegenerateRecoveryCodes = () => {
    setConfirmDialog({ isOpen: true, type: 'regenerate' });
  };

  const handleCancelAction = () => {
    setConfirmDialog({ isOpen: false, type: null });
  };

  if (state.enabled && state.step === 'initial') {
    return (
      <div className="border border-gray-200 rounded-lg p-6">

        <div className="flex items-center justify-between mb-4">
          <div>
            <h4 className="text-base font-medium text-gray-900">
              {t('settings.security.twofa.title', '2要素認証 (TOTP)')}
            </h4>
            <p className="text-sm text-gray-600">
              {t('settings.security.twofa.enabled_desc', '2要素認証が有効になっています')}
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
            {state.loading ? t('common.loading', '読み込み中...') : t('settings.security.twofa.disable', '2要素認証を無効化')}
          </button>
        </div>

        {state.showRecoveryCodes && state.recoveryCodes && state.recoveryCodes.length > 0 && (
          <div className="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
            <h5 className="font-medium text-yellow-800 mb-2">
              {t('settings.security.twofa.recovery_codes', 'リカバリコード')}
            </h5>
            <div className="text-sm text-yellow-700 mb-3 space-y-2">
              <p>
                {t('settings.security.twofa.recovery_desc', 'これらのコードを安全な場所に保存してください。認証アプリにアクセスできない場合に使用できます。')}
              </p>
              <div className="flex items-start gap-2 font-medium">
                <ExclamationTriangleIcon className="w-4 h-4 text-yellow-600 mt-0.5 flex-shrink-0" />
                <span>
                  {t('settings.security.twofa.recovery_usage_warning', '重要: リカバリーコードを使用すると、セキュリティのため自動的に新しいコードが生成され、古いコードは無効になります。')}
                </span>
              </div>
            </div>
            <div className="grid grid-cols-2 gap-2 mb-3">
              {state.recoveryCodes.map((code, index) => (
                <div
                  key={index}
                  className="bg-white p-2 rounded border font-mono text-sm cursor-pointer hover:bg-gray-50 group relative"
                  onClick={() => copyToClipboard(code)}
                  title={t('settings.security.twofa.click_to_copy', 'クリックしてコピー')}
                >
                  {code}
                  <div className="absolute inset-0 bg-blue-50 opacity-0 group-hover:opacity-30 rounded transition-opacity"></div>
                </div>
              ))}
            </div>
            <div className="flex gap-2">
              <button
                onClick={copyAllRecoveryCodes}
                className="px-3 py-1 text-sm bg-yellow-600 text-white rounded hover:bg-yellow-700 transition-colors"
              >
                {t('settings.security.twofa.copy_all', 'すべてコピー')}
              </button>
              <button
                onClick={() => setState(prev => ({ ...prev, showRecoveryCodes: false }))}
                className="text-sm text-yellow-700 hover:text-yellow-800"
              >
                {t('common.close', '閉じる')}
              </button>
            </div>
          </div>
        )}

        {/* Confirmation Dialog for this return branch */}
        {confirmDialog.isOpen && createPortal(
          <ConfirmDialog
            isOpen={confirmDialog.isOpen}
            title={
              confirmDialog.type === 'disable'
                ? t('settings.security.twofa.disable_confirm_title', '2要素認証の無効化')
                : t('settings.security.twofa.regenerate_confirm_title', 'リカバリコードの再生成')
            }
            message={
              confirmDialog.type === 'disable'
                ? t('settings.security.twofa.disable_confirm', '2要素認証を無効化しますか？セキュリティが低下する可能性があります。')
                : t('settings.security.twofa.regenerate_confirm', '新しいリカバリコードを生成しますか？古いコードは使用できなくなります。')
            }
            confirmText={
              confirmDialog.type === 'disable'
                ? t('settings.security.twofa.disable', '無効化')
                : t('settings.security.twofa.regenerate', '再生成')
            }
            cancelText={t('common.cancel', 'キャンセル')}
            confirmVariant={confirmDialog.type === 'disable' ? 'danger' : 'primary'}
            onConfirm={handleConfirmAction}
            onCancel={handleCancelAction}
          />,
          document.body
        )}
      </div>
    );
  }

  return (
    <div className="border border-gray-200 rounded-lg p-6">
      <h4 className="text-base font-medium text-gray-900 mb-4">
        {t('settings.security.twofa.title', '2要素認証 (TOTP)')}
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
            {state.loading ? t('common.loading', '読み込み中...') : t('settings.security.twofa.enable', '2要素認証を有効化')}
          </button>
        </div>
      )}

      {(state.step === 'setup' || state.step === 'verify') && (
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

          {/* 2FA設定中でも無効化オプションを提供 */}
          <div className="pt-4 mt-4 border-t border-gray-200">
            <button
              onClick={handleDisable}
              disabled={state.loading}
              className="btn-secondary w-full text-red-600 border-red-300 hover:bg-red-50"
            >
              {state.loading ? t('common.loading', '読み込み中...') : t('settings.security.twofa.disable', '2要素認証を無効化')}
            </button>
          </div>
        </div>
      )}

      {state.step === 'complete' && (
        <div className="space-y-4">
          <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
            {t('settings.security.twofa.enabled_success', '2要素認証が正常に有効化されました！')}
          </div>

          <div className="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
            <h5 className="font-medium text-yellow-800 mb-2">
              {t('settings.security.twofa.recovery_codes', 'リカバリコード')}
            </h5>
            <div className="text-sm text-yellow-700 mb-3 space-y-2">
              <p>
                {t('settings.security.twofa.recovery_desc', 'これらのコードを安全な場所に保存してください。認証アプリにアクセスできない場合に使用できます。')}
              </p>
              <div className="flex items-start gap-2 font-medium">
                <ExclamationTriangleIcon className="w-4 h-4 text-yellow-600 mt-0.5 flex-shrink-0" />
                <span>
                  {t('settings.security.twofa.recovery_usage_warning', '重要: リカバリーコードを使用すると、セキュリティのため自動的に新しいコードが生成され、古いコードは無効になります。')}
                </span>
              </div>
            </div>
            <div className="grid grid-cols-2 gap-2 mb-3">
              {state.recoveryCodes.map((code, index) => (
                <div
                  key={index}
                  className="bg-white p-2 rounded border font-mono text-sm cursor-pointer hover:bg-gray-50 group relative"
                  onClick={() => copyToClipboard(code)}
                  title={t('settings.security.twofa.click_to_copy', 'クリックしてコピー')}
                >
                  {code}
                  <div className="absolute inset-0 bg-blue-50 opacity-0 group-hover:opacity-30 rounded transition-opacity"></div>
                </div>
              ))}
            </div>
            <div className="flex gap-2 mb-3">
              <button
                onClick={copyAllRecoveryCodes}
                className="px-3 py-1 text-sm bg-yellow-600 text-white rounded hover:bg-yellow-700 transition-colors"
              >
                {t('settings.security.twofa.copy_all', 'すべてコピー')}
              </button>
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