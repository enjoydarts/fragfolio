import React, { useState, useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';

export const PasswordResetError: React.FC = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const [error, setError] = useState<string>('');

  useEffect(() => {
    const message = searchParams.get('message') || '';
    setError(message);

    setTimeout(() => {
      navigate('/auth');
    }, 5000);
  }, [searchParams, navigate]);

  return (
    <div className="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
      <div className="sm:mx-auto sm:w-full sm:max-w-md">
        <div className="mx-auto w-12 h-12 flex items-center justify-center rounded-full bg-red-100">
          <svg
            className="w-6 h-6 text-red-600"
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
          {t('password_reset.error_title')}
        </h2>
        <p className="mt-2 text-center text-sm text-gray-600">
          {t('password_reset.error_subtitle')}
        </p>
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div className="card p-8">
          <div className="p-4 bg-red-50 border border-red-200 rounded-md">
            <p className="text-sm text-red-600">
              {error || t('password_reset.invalid_link')}
            </p>
          </div>

          <div className="mt-6 text-center">
            <p className="text-sm text-gray-600 mb-4">
              {t('password_reset.redirecting_login')}
            </p>
            <button onClick={() => navigate('/auth')} className="btn-primary">
              {t('password_reset.back_to_login')}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};
