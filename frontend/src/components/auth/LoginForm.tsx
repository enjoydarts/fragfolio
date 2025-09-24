import React, { useState, useCallback, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../../hooks/useAuth';
import { TurnstileWidget } from './TurnstileWidget';
import { WebAuthnUtils } from '../../api/webauthn';
import { FingerPrintIcon } from '@heroicons/react/24/outline';

interface LoginFormProps {
  onSuccess?: () => void;
  onRegisterClick?: () => void;
}

export const LoginForm: React.FC<LoginFormProps> = ({
  onSuccess,
  onRegisterClick,
}) => {
  const { t } = useTranslation();
  const { login, loading } = useAuth();
  const navigate = useNavigate();
  const [formData, setFormData] = useState({
    email: '',
    password: '',
    remember: false,
  });
  const [error, setError] = useState<string | null>(null);
  const [turnstileToken, setTurnstileToken] = useState<string | null>(null);
  const [turnstileResetCounter, setTurnstileResetCounter] = useState(0);
  const [showPassword, setShowPassword] = useState(false);
  const [requiresTwoFactor, setRequiresTwoFactor] = useState(false);
  const [twoFactorCode, setTwoFactorCode] = useState('');
  const [tempToken, setTempToken] = useState<string | null>(null);
  const [webAuthnSupported, setWebAuthnSupported] = useState(false);
  const [twoFactorWebAuthnLoading, setTwoFactorWebAuthnLoading] =
    useState(false);
  const [twoFactorMethod, setTwoFactorMethod] = useState<
    'totp' | 'webauthn' | null
  >(null);
  const [availableMethods, setAvailableMethods] = useState<string[]>([]);
  const turnstileSiteKey = import.meta.env.VITE_TURNSTILE_SITE_KEY;

  useEffect(() => {
    const isSupported = WebAuthnUtils.isSupported();
    setWebAuthnSupported(isSupported);
  }, []);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value, type, checked } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value,
    }));
  };

  const handleTurnstileError = useCallback(() => {
    setError(t('auth.errors.turnstile_failed'));
  }, [t]);

  const handleTurnstileExpire = useCallback(() => {
    setTurnstileToken(null);
  }, []);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);

    if (turnstileSiteKey && !turnstileToken) {
      setError(t('auth.errors.turnstile_required'));
      return;
    }

    try {
      await login(
        formData.email,
        formData.password,
        formData.remember,
        turnstileToken
      );
      onSuccess?.();
    } catch (error: unknown) {
      // レスポンスから2FA要求チェック
      const authError = error as {
        requires_two_factor?: boolean;
        temp_token?: string;
        available_methods?: string[];
        message?: string;
      };
      if (authError?.requires_two_factor) {
        console.log(
          '2FA required, available methods:',
          authError.available_methods
        );
        setRequiresTwoFactor(true);
        setTempToken(authError.temp_token);
        setAvailableMethods(authError.available_methods || []);

        // 利用可能な認証方法が1つだけの場合は自動選択
        if (authError.available_methods?.length === 1) {
          setTwoFactorMethod(authError.available_methods[0]);
        }
        return;
      }

      // ログイン失敗時にTurnstileトークンをリセット
      setTurnstileToken(null);
      setTurnstileResetCounter((prev) => prev + 1);
      setError(
        authError?.message || error instanceof Error
          ? error.message
          : t('auth.errors.login_failed')
      );
    }
  };

  const handleTwoFactorSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);

    if (!twoFactorCode) {
      setError(t('auth.errors.two_factor_required'));
      return;
    }

    try {
      const response = await fetch(
        `${import.meta.env.VITE_API_BASE_URL || 'http://localhost:8002'}/api/auth/two-factor-challenge`,
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
          },
          body: JSON.stringify({
            code: twoFactorCode,
            temp_token: tempToken,
          }),
        }
      );

      const data = await response.json();

      if (data.success) {
        // トークンを保存してログイン完了
        localStorage.setItem('auth_token', data.token);
        // AuthContextを更新するためにページをリロード
        window.location.reload();
        onSuccess?.();
      } else {
        setError(t('auth.errors.two_factor_invalid'));
      }
    } catch {
      setError(t('auth.errors.two_factor_failed'));
    }
  };

  const handleTwoFactorWebAuthn = useCallback(async () => {
    if (!webAuthnSupported || !tempToken) {
      setError(t('webauthn.not_supported'));
      return;
    }

    setTwoFactorWebAuthnLoading(true);
    setError(null);

    try {
      // 1. WebAuthnオプションを取得
      const response = await fetch(
        `${import.meta.env.VITE_API_BASE_URL || 'http://localhost:8002'}/api/auth/two-factor-webauthn`,
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
          },
          body: JSON.stringify({
            temp_token: tempToken,
          }),
        }
      );

      const data = await response.json();

      if (!data.success) {
        setError(t('webauthn.login_failed'));
        return;
      }

      // 2. WebAuthn API用に変換
      const requestOptions = WebAuthnUtils.convertLoginOptions(
        data.webauthn_options
      );

      // 3. WebAuthn認証器で認証
      const credential = (await navigator.credentials.get({
        publicKey: requestOptions,
      })) as PublicKeyCredential;

      if (!credential) {
        throw new Error(t('webauthn.cancelled'));
      }

      // 4. サーバー送信用に変換
      const credentialData = WebAuthnUtils.convertLoginResponse(credential);

      // 5. サーバーに送信して2FA完了
      const completeResponse = await fetch(
        `${import.meta.env.VITE_API_BASE_URL || 'http://localhost:8002'}/api/auth/two-factor-webauthn/complete`,
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
          },
          body: JSON.stringify({
            temp_token: tempToken,
            ...credentialData,
          }),
        }
      );

      const completeData = await completeResponse.json();

      if (completeData.success) {
        // トークンを保存してログイン完了
        localStorage.setItem('auth_token', completeData.token);
        // AuthContextを更新するためにページをリロード
        window.location.reload();
        onSuccess?.();
      } else {
        setError(t('webauthn.login_failed'));
      }
    } catch (error: unknown) {
      console.error('WebAuthn 2FA failed:', error);
      const webauthnError = error as { name?: string; message?: string };
      if (webauthnError.name === 'NotAllowedError') {
        setError(t('webauthn.not_allowed'));
      } else if (webauthnError.name === 'InvalidStateError') {
        setError(t('webauthn.invalid_state'));
      } else {
        setError(t('webauthn.login_failed'));
      }
    } finally {
      setTwoFactorWebAuthnLoading(false);
    }
  }, [webAuthnSupported, tempToken, t, onSuccess]);

  // WebAuthn方式が選択された時に自動でWebAuthn認証を開始
  useEffect(() => {
    if (
      requiresTwoFactor &&
      twoFactorMethod === 'webauthn' &&
      !twoFactorWebAuthnLoading &&
      tempToken
    ) {
      handleTwoFactorWebAuthn();
    }
  }, [
    requiresTwoFactor,
    twoFactorMethod,
    twoFactorWebAuthnLoading,
    tempToken,
    handleTwoFactorWebAuthn,
  ]);

  return (
    <div className="w-full max-w-md mx-auto mb-8">
      {/* エレガントなカード */}
      <div className="bg-white/95 backdrop-blur-sm border border-white/20 rounded-2xl shadow-xl p-8">
        {/* ウェルカムメッセージ */}
        <div className="text-center mb-8">
          <h2 className="text-2xl font-light text-gray-800 mb-2">
            {requiresTwoFactor
              ? t('auth.two_factor.title')
              : t('auth.login.title')}
          </h2>
          <p className="text-gray-500 text-sm font-light">
            {requiresTwoFactor
              ? t('auth.two_factor.subtitle')
              : t('auth.login.subtitle')}
          </p>
        </div>

        <form
          className="space-y-5"
          onSubmit={requiresTwoFactor ? handleTwoFactorSubmit : handleSubmit}
        >
          {/* エラーメッセージ */}
          {error && (
            <div className="bg-red-50 border border-red-200 rounded-xl p-4">
              <div className="flex items-center">
                <div className="w-2 h-2 bg-red-400 rounded-full mr-3"></div>
                <p className="text-sm text-red-700 font-light">{error}</p>
              </div>
            </div>
          )}

          {/* フォームフィールド */}
          <div className="space-y-4">
            {requiresTwoFactor ? (
              twoFactorMethod === null ? (
                /* 2FA認証方法選択 */
                <div className="space-y-4">
                  <div className="text-center mb-6">
                    <h3 className="text-lg font-medium text-gray-800 mb-2">
                      {t(
                        'auth.two_factor.choose_method',
                        '認証方法を選択してください'
                      )}
                    </h3>
                    <p className="text-sm text-gray-600">
                      {t(
                        'auth.two_factor.choose_method_hint',
                        'どちらか一つの方法で認証を完了してください'
                      )}
                    </p>
                    {/* デバッグ情報 */}
                    <div className="text-xs text-gray-400 mt-2">
                      Available methods: {JSON.stringify(availableMethods)}
                    </div>
                  </div>

                  {/* TOTP選択ボタン */}
                  {availableMethods.includes('totp') && (
                    <button
                      type="button"
                      onClick={() => setTwoFactorMethod('totp')}
                      className="w-full py-4 text-gray-700 bg-white border-2 border-gray-300 hover:border-amber-500 hover:bg-amber-50 focus:outline-none focus:ring-4 focus:ring-amber-200 transition-all duration-300 rounded-xl font-medium"
                    >
                      <div className="flex items-center justify-center">
                        <svg
                          className="w-5 h-5 mr-3"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={1.5}
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                          />
                        </svg>
                        {t('auth.two_factor.totp_method')}
                      </div>
                    </button>
                  )}

                  {/* WebAuthn選択ボタン */}
                  {availableMethods.includes('webauthn') &&
                    webAuthnSupported && (
                      <button
                        type="button"
                        onClick={() => setTwoFactorMethod('webauthn')}
                        className="w-full py-4 text-gray-700 bg-white border-2 border-gray-300 hover:border-amber-500 hover:bg-amber-50 focus:outline-none focus:ring-4 focus:ring-amber-200 transition-all duration-300 rounded-xl font-medium"
                      >
                        <div className="flex items-center justify-center">
                          <FingerPrintIcon className="w-5 h-5 mr-3" />
                          {t(
                            'auth.two_factor.webauthn_method',
                            '生体認証（FIDO2）'
                          )}
                        </div>
                      </button>
                    )}
                </div>
              ) : twoFactorMethod === 'totp' ? (
                /* TOTPコード入力フィールド */
                <div className="group">
                  <div className="flex items-center justify-between mb-2">
                    <label
                      htmlFor="twoFactorCode"
                      className="block text-sm font-medium text-gray-700"
                    >
                      {t('auth.two_factor.code_label')}
                    </label>
                    <button
                      type="button"
                      onClick={() => setTwoFactorMethod(null)}
                      className="text-xs text-amber-600 hover:text-amber-700 font-medium"
                    >
                      {t('auth.two_factor.change_method')}
                    </button>
                  </div>
                  <div className="relative">
                    <input
                      id="twoFactorCode"
                      name="twoFactorCode"
                      type="text"
                      autoComplete="one-time-code"
                      required
                      maxLength={50}
                      className="w-full px-4 py-4 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-transparent transition-all duration-300 text-gray-900 placeholder-gray-400 font-mono text-center text-lg tracking-widest"
                      placeholder="123456"
                      value={twoFactorCode}
                      onChange={(e) => setTwoFactorCode(e.target.value)}
                    />
                    <div className="absolute inset-y-0 right-0 flex items-center pr-4">
                      <svg
                        className="w-5 h-5 text-gray-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={1.5}
                          d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                        />
                      </svg>
                    </div>
                  </div>
                  <p className="text-xs text-gray-500 mt-2">
                    {t(
                      'auth.two_factor.code_hint',
                      '認証アプリまたはリカバリコードを入力してください'
                    )}
                  </p>
                </div>
              ) : (
                /* WebAuthn認証画面 */
                <div className="text-center">
                  <div className="flex items-center justify-between mb-4">
                    <h3 className="text-lg font-medium text-gray-800">
                      {t('auth.two_factor.webauthn_title')}
                    </h3>
                    <button
                      type="button"
                      onClick={() => setTwoFactorMethod(null)}
                      className="text-xs text-amber-600 hover:text-amber-700 font-medium"
                    >
                      {t('auth.two_factor.change_method')}
                    </button>
                  </div>
                  <div className="bg-gray-50 rounded-xl p-6 mb-4">
                    <FingerPrintIcon className="w-12 h-12 text-gray-400 mx-auto mb-3" />
                    <p className="text-sm text-gray-600">
                      {t(
                        'auth.two_factor.webauthn_prompt',
                        '生体認証器をタッチして認証を完了してください'
                      )}
                    </p>
                  </div>
                </div>
              )
            ) : (
              <>
                {/* メールフィールド */}
                <div className="group">
                  <label
                    htmlFor="email"
                    className="block text-sm font-medium text-gray-700 mb-2"
                  >
                    {t('auth.fields.email')}
                  </label>
                  <div className="relative">
                    <input
                      id="email"
                      name="email"
                      type="email"
                      autoComplete="email"
                      required
                      className="w-full px-4 py-4 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-transparent transition-all duration-300 text-gray-900 placeholder-gray-400 font-light"
                      placeholder={t('auth.placeholders.email')}
                      value={formData.email}
                      onChange={handleChange}
                    />
                    <div className="absolute inset-y-0 right-0 flex items-center pr-4">
                      <svg
                        className="w-5 h-5 text-gray-400 group-focus-within:text-amber-500 transition-colors"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={1.5}
                          d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"
                        />
                      </svg>
                    </div>
                  </div>
                </div>

                {/* パスワードフィールド */}
                <div className="group">
                  <label
                    htmlFor="password"
                    className="block text-sm font-medium text-gray-700 mb-2"
                  >
                    {t('auth.fields.password')}
                  </label>
                  <div className="relative">
                    <input
                      id="password"
                      name="password"
                      type={showPassword ? 'text' : 'password'}
                      autoComplete="current-password"
                      required
                      className="w-full px-4 py-4 pr-12 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-transparent transition-all duration-300 text-gray-900 placeholder-gray-400 font-light"
                      placeholder={t('auth.placeholders.password')}
                      value={formData.password}
                      onChange={handleChange}
                    />
                    <button
                      type="button"
                      onClick={() => setShowPassword(!showPassword)}
                      className="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400 hover:text-amber-500 transition-colors duration-200"
                    >
                      {showPassword ? (
                        <svg
                          className="w-5 h-5"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={1.5}
                            d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"
                          />
                        </svg>
                      ) : (
                        <svg
                          className="w-5 h-5"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={1.5}
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                          />
                          <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={1.5}
                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                          />
                        </svg>
                      )}
                    </button>
                  </div>
                </div>
              </>
            )}
          </div>

          {/* オプション（2FAステップでは非表示） */}
          {!requiresTwoFactor && (
            <div className="space-y-4 pt-1">
              {/* Remember me */}
              <label className="flex items-center group cursor-pointer">
                <div className="relative">
                  <input
                    id="remember"
                    name="remember"
                    type="checkbox"
                    className="sr-only"
                    checked={formData.remember}
                    onChange={handleChange}
                  />
                  <div
                    className={`w-5 h-5 border-2 border-gray-300 rounded-md transition-all duration-200 ${formData.remember ? 'bg-amber-500 border-amber-500' : 'bg-white'}`}
                  >
                    {formData.remember && (
                      <svg
                        className="w-3 h-3 text-white absolute top-0.5 left-0.5"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                      >
                        <path
                          fillRule="evenodd"
                          d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                          clipRule="evenodd"
                        />
                      </svg>
                    )}
                  </div>
                </div>
                <span className="ml-3 text-sm text-gray-600 font-light">
                  {t('auth.remember_me')}
                </span>
              </label>

              {/* パスワードを忘れた場合 */}
              <div className="text-center">
                <button
                  type="button"
                  onClick={() => navigate('/forgot-password')}
                  className="text-sm text-amber-600 hover:text-amber-700 transition-colors duration-200 font-medium"
                >
                  {t('auth.forgot_password')}
                </button>
              </div>
            </div>
          )}

          {/* Turnstile（2FAステップでは非表示） */}
          {turnstileSiteKey && !requiresTwoFactor && (
            <div className="flex justify-center py-6">
              <TurnstileWidget
                siteKey={turnstileSiteKey}
                onVerify={setTurnstileToken}
                onError={handleTurnstileError}
                onExpire={handleTurnstileExpire}
                reset={turnstileResetCounter}
              />
            </div>
          )}

          {/* ログイン/認証ボタン */}
          <div className="pt-6 space-y-3">
            {/* 通常ログインまたはTOTPボタン */}
            {(!requiresTwoFactor || twoFactorMethod === 'totp') && (
              <button
                type="submit"
                disabled={
                  loading ||
                  (!requiresTwoFactor && turnstileSiteKey && !turnstileToken) ||
                  (requiresTwoFactor &&
                    twoFactorMethod === 'totp' &&
                    !twoFactorCode)
                }
                className="w-full py-4 text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-4 focus:ring-amber-300 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300 rounded-xl font-medium tracking-wide shadow-md hover:shadow-lg"
              >
                {loading ? (
                  <div className="flex items-center justify-center">
                    <div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></div>
                    {requiresTwoFactor
                      ? t('auth.two_factor.verifying')
                      : t('auth.logging_in')}
                  </div>
                ) : requiresTwoFactor && twoFactorMethod === 'totp' ? (
                  t('auth.two_factor.verify_button')
                ) : (
                  t('auth.login.button')
                )}
              </button>
            )}

            {/* WebAuthn認証ボタン（2FA） */}
            {requiresTwoFactor && twoFactorMethod === 'webauthn' && (
              <button
                type="button"
                onClick={handleTwoFactorWebAuthn}
                disabled={twoFactorWebAuthnLoading}
                className="w-full py-4 text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-4 focus:ring-amber-300 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300 rounded-xl font-medium tracking-wide shadow-md hover:shadow-lg"
              >
                {twoFactorWebAuthnLoading ? (
                  <div className="flex items-center justify-center">
                    <div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></div>
                    {t('auth.webauthn.authenticating')}
                  </div>
                ) : (
                  <div className="flex items-center justify-center">
                    <FingerPrintIcon className="w-5 h-5 mr-2" />
                    {t('auth.webauthn.authenticate_button')}
                  </div>
                )}
              </button>
            )}
          </div>

          {/* フッターリンク */}
          <div className="text-center pt-6 border-t border-gray-200/50">
            {requiresTwoFactor ? (
              /* 2FAステップ：戻るボタン */
              <button
                type="button"
                onClick={() => {
                  setRequiresTwoFactor(false);
                  setTwoFactorCode('');
                  setTempToken(null);
                  setTwoFactorMethod(null);
                  setAvailableMethods([]);
                  setError(null);
                }}
                className="text-gray-600 hover:text-gray-700 font-medium transition-colors duration-200"
              >
                {t('auth.two_factor.back_to_login')}
              </button>
            ) : (
              /* 通常ステップ：新規登録リンク */
              <>
                <p className="text-sm text-gray-500 font-light mb-2">
                  {t('auth.no_account')}
                </p>
                <button
                  type="button"
                  onClick={onRegisterClick}
                  className="text-amber-600 hover:text-amber-700 font-medium transition-colors duration-200"
                >
                  {t('auth.register_here')}
                </button>
              </>
            )}
          </div>
        </form>
      </div>
    </div>
  );
};
