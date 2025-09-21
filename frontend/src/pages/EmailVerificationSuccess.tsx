import React, { useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';

export const EmailVerificationSuccess: React.FC = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const message =
    searchParams.get('message') || t('verification.success.message_default');

  useEffect(() => {
    // 3秒後にログイン画面にリダイレクト
    const timer = setTimeout(() => {
      navigate('/auth');
    }, 3000);

    return () => clearTimeout(timer);
  }, [navigate]);

  return (
    <div className="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
      <div className="sm:mx-auto sm:w-full sm:max-w-md">
        <div className="mx-auto w-16 h-16 flex items-center justify-center rounded-full bg-green-100">
          <svg
            className="w-8 h-8 text-green-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M5 13l4 4L19 7"
            />
          </svg>
        </div>
        <h2 className="mt-6 text-center text-3xl font-bold text-gray-900">
          {t('verification.success.title')}
        </h2>
        <p className="mt-2 text-center text-sm text-gray-600">
          {message || t('verification.success.message_default')}
        </p>
        <p className="mt-4 text-center text-xs text-gray-500">
          {t('verification.success.auto_redirect')}
        </p>
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div className="card p-8">
          <div className="text-center">
            <button onClick={() => navigate('/auth')} className="btn-primary">
              {t('verification.success.go_to_login')}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};
