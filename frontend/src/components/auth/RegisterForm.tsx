import React, { useState, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../../hooks/useAuth';
import { TurnstileWidget } from './TurnstileWidget';

interface RegisterFormProps {
  onSuccess?: () => void;
  onLoginClick?: () => void;
}

export const RegisterForm: React.FC<RegisterFormProps> = ({
  onSuccess,
  onLoginClick,
}) => {
  const { t, i18n } = useTranslation();
  const { register, loading } = useAuth();
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    password: '',
    confirmPassword: '',
    language: i18n.language || 'ja',
    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
  });
  const [error, setError] = useState<string | null>(null);
  const [turnstileToken, setTurnstileToken] = useState<string | null>(null);
  const [turnstileResetCounter, setTurnstileResetCounter] = useState(0);
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);

  const turnstileSiteKey = import.meta.env.VITE_TURNSTILE_SITE_KEY;

  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>
  ) => {
    const { name, value } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]: value,
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

    if (formData.password !== formData.confirmPassword) {
      setError(t('auth.errors.password_mismatch'));
      return;
    }

    if (turnstileSiteKey && !turnstileToken) {
      setError(t('auth.errors.turnstile_required'));
      return;
    }

    try {
      await register({
        name: formData.name,
        email: formData.email,
        password: formData.password,
        language: formData.language,
        timezone: formData.timezone,
        turnstile_token: turnstileToken,
      });
      onSuccess?.();
    } catch (error: unknown) {
      console.error('Registration error:', error);

      // エラー時にTurnstileトークンをリセット
      setTurnstileToken(null);
      setTurnstileResetCounter((prev) => prev + 1);

      // 型ガードでエラーオブジェクトの構造を確認
      if (
        error &&
        typeof error === 'object' &&
        'response' in error &&
        error.response &&
        typeof error.response === 'object' &&
        'data' in error.response &&
        error.response.data &&
        typeof error.response.data === 'object' &&
        'errors' in error.response.data
      ) {
        // バリデーションエラーの詳細を表示
        const errorMessages = Object.values(
          error.response.data.errors as Record<string, string[]>
        ).flat();
        setError(errorMessages.join(', '));
      } else if (
        error &&
        typeof error === 'object' &&
        'response' in error &&
        error.response &&
        typeof error.response === 'object' &&
        'data' in error.response &&
        error.response.data &&
        typeof error.response.data === 'object' &&
        'message' in error.response.data &&
        typeof error.response.data.message === 'string'
      ) {
        setError(error.response.data.message);
      } else if (
        error &&
        typeof error === 'object' &&
        'message' in error &&
        typeof error.message === 'string'
      ) {
        setError(error.message);
      } else {
        setError(t('auth.errors.registration_failed'));
      }
    }
  };

  return (
    <div className="w-full max-w-md mx-auto mb-8">
      {/* エレガントなカード */}
      <div className="bg-white/95 backdrop-blur-sm border border-white/20 rounded-2xl shadow-xl p-8">
        {/* ウェルカムメッセージ */}
        <div className="text-center mb-8">
          <h2 className="text-2xl font-light text-gray-800 mb-2">
            {t('auth.register.title')}
          </h2>
          <p className="text-gray-500 text-sm font-light">
            {t('auth.register.subtitle')}
          </p>
        </div>

        <form className="space-y-5" onSubmit={handleSubmit}>
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
            {/* 名前フィールド */}
            <div className="group">
              <label
                htmlFor="name"
                className="block text-sm font-medium text-gray-700 mb-2"
              >
                {t('auth.fields.name')}
              </label>
              <div className="relative">
                <input
                  id="name"
                  name="name"
                  type="text"
                  autoComplete="name"
                  required
                  className="w-full px-4 py-4 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-transparent transition-all duration-300 text-gray-900 placeholder-gray-400 font-light"
                  placeholder={t('auth.placeholders.name')}
                  value={formData.name}
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
                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                    />
                  </svg>
                </div>
              </div>
            </div>

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
                  autoComplete="new-password"
                  required
                  className="w-full px-4 py-4 pr-12 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-transparent transition-all duration-300 text-gray-900 placeholder-gray-400 font-light"
                  placeholder={t('auth.placeholders.password_new')}
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

            {/* パスワード確認フィールド */}
            <div className="group">
              <label
                htmlFor="confirmPassword"
                className="block text-sm font-medium text-gray-700 mb-2"
              >
                {t('auth.fields.confirm_password')}
              </label>
              <div className="relative">
                <input
                  id="confirmPassword"
                  name="confirmPassword"
                  type={showConfirmPassword ? 'text' : 'password'}
                  autoComplete="new-password"
                  required
                  className="w-full px-4 py-4 pr-12 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-transparent transition-all duration-300 text-gray-900 placeholder-gray-400 font-light"
                  placeholder={t('auth.placeholders.confirm_password')}
                  value={formData.confirmPassword}
                  onChange={handleChange}
                />
                <button
                  type="button"
                  onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                  className="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400 hover:text-amber-500 transition-colors duration-200"
                >
                  {showConfirmPassword ? (
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

            {/* 言語選択 */}
            <div className="group">
              <label
                htmlFor="language"
                className="block text-sm font-medium text-gray-700 mb-2"
              >
                {t('auth.fields.language')}
              </label>
              <div className="relative">
                <select
                  id="language"
                  name="language"
                  className="w-full px-4 py-4 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-transparent transition-all duration-300 text-gray-900 font-light appearance-none cursor-pointer"
                  value={formData.language}
                  onChange={handleChange}
                >
                  <option value="ja">{t('auth.languages.ja')}</option>
                  <option value="en">{t('auth.languages.en')}</option>
                </select>
                <div className="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none">
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
                      d="M19 9l-7 7-7-7"
                    />
                  </svg>
                </div>
              </div>
            </div>
          </div>

          {/* Turnstile */}
          {turnstileSiteKey && (
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

          {/* 登録ボタン */}
          <div className="pt-6">
            <button
              type="submit"
              disabled={loading || (turnstileSiteKey && !turnstileToken)}
              className="w-full py-4 text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-4 focus:ring-amber-300 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300 rounded-xl font-medium tracking-wide shadow-md hover:shadow-lg"
            >
              {loading ? (
                <div className="flex items-center justify-center">
                  <div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></div>
                  {t('auth.creating_account')}
                </div>
              ) : (
                t('auth.register.button')
              )}
            </button>
          </div>

          {/* ログインリンク */}
          <div className="text-center pt-6 border-t border-gray-200/50">
            <p className="text-sm text-gray-500 font-light mb-2">
              {t('auth.have_account')}
            </p>
            <button
              type="button"
              onClick={onLoginClick}
              className="text-amber-600 hover:text-amber-700 font-medium transition-colors duration-200"
            >
              {t('auth.login_here')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
