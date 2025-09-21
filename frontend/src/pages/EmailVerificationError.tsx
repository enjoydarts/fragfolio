import React from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';

export const EmailVerificationError: React.FC = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const message =
    searchParams.get('message') || t('verification.error.message_default');

  return (
    <div className="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
      <div className="sm:mx-auto sm:w-full sm:max-w-md">
        <div className="mx-auto w-16 h-16 flex items-center justify-center rounded-full bg-red-100">
          <svg
            className="w-8 h-8 text-red-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M6 18L18 6M6 6l12 12"
            />
          </svg>
        </div>
        <h2 className="mt-6 text-center text-3xl font-bold text-gray-900">
          {t('verification.error.title')}
        </h2>
        <p className="mt-2 text-center text-sm text-gray-600">{message}</p>
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div className="card p-8">
          <div className="space-y-4">
            <button
              onClick={() => navigate('/email-verification')}
              className="btn-primary w-full"
            >
              {t('verification.error.resend_email')}
            </button>
            <button
              onClick={() => navigate('/auth')}
              className="btn-secondary w-full"
            >
              {t('verification.error.back_to_login')}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};
