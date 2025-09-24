import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../hooks/useAuth';
import { AuthAPI } from '../api/auth';

export const EmailVerification: React.FC = () => {
  const { t } = useTranslation();
  const { user, refreshUser, token } = useAuth();
  const navigate = useNavigate();
  const [isResending, setIsResending] = useState(false);
  const [message, setMessage] = useState<string>('');
  const [error, setError] = useState<string>('');

  const handleResendEmail = async () => {
    if (!token) return;

    setIsResending(true);
    setError('');
    setMessage('');

    try {
      const response = await AuthAPI.resendVerificationEmail(token);
      if (response.success) {
        setMessage(t('email_verification.success'));
      } else {
        setError(t('auth.errors.registration_failed'));
      }
    } catch {
      setError(t('auth.errors.registration_failed'));
    } finally {
      setIsResending(false);
    }
  };

  const handleCheckVerification = async () => {
    try {
      await refreshUser();
      if (user?.email_verified_at) {
        navigate('/dashboard');
      }
    } catch (err) {
      console.error('ユーザー情報の更新に失敗しました:', err);
    }
  };

  // 既に認証済みの場合はダッシュボードへリダイレクト
  React.useEffect(() => {
    if (user?.email_verified_at) {
      navigate('/dashboard');
    }
  }, [user, navigate]);

  return (
    <div className="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
      <div className="sm:mx-auto sm:w-full sm:max-w-md">
        <div className="mx-auto w-12 h-12 flex items-center justify-center rounded-full bg-amber-100">
          <svg
            className="w-6 h-6 text-amber-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
            />
          </svg>
        </div>
        <h2 className="mt-6 text-center text-3xl font-bold text-gray-900">
          {t('email_verification.title')}
        </h2>
        <p className="mt-2 text-center text-sm text-gray-600">
          {t('email_verification.subtitle')}
        </p>
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div className="card p-8">
          <div className="space-y-6">
            <div className="text-center">
              <p className="text-sm text-gray-600 mb-4">
                {t('email_verification.sent_to', { email: user?.email })}
              </p>
              <p className="text-xs text-gray-500">
                {t('email_verification.check_spam')}
              </p>
            </div>

            {message && (
              <div className="p-4 bg-green-50 border border-green-200 rounded-md">
                <p className="text-sm text-green-600">{message}</p>
              </div>
            )}

            {error && (
              <div className="p-4 bg-red-50 border border-red-200 rounded-md">
                <p className="text-sm text-red-600">{error}</p>
              </div>
            )}

            <div className="space-y-4">
              <button
                onClick={handleCheckVerification}
                className="btn-primary w-full"
              >
                {t('email_verification.check_status')}
              </button>

              <button
                onClick={handleResendEmail}
                disabled={isResending}
                className="btn-secondary w-full"
              >
                {isResending
                  ? t('email_verification.resending')
                  : t('email_verification.resend')}
              </button>
            </div>

            <div className="text-center pt-4">
              <button
                onClick={() => navigate('/login')}
                className="text-sm text-blue-600 hover:text-blue-500"
              >
                {t('email_verification.back_to_login')}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};
