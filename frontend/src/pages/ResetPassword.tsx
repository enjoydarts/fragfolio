import React, { useState, useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AuthAPI } from '../api/auth';

export const ResetPassword: React.FC = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const [formData, setFormData] = useState({
    token: '',
    email: '',
    password: '',
    password_confirmation: '',
  });
  const [isLoading, setIsLoading] = useState(false);
  const [message, setMessage] = useState<string>('');
  const [error, setError] = useState<string>('');

  useEffect(() => {
    const token = searchParams.get('token') || '';
    const email = searchParams.get('email') || '';

    setFormData((prev) => ({
      ...prev,
      token,
      email,
    }));

    if (!token || !email) {
      setError('無効なリセットリンクです');
    }
  }, [searchParams]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]: value,
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);
    setError('');
    setMessage('');

    if (formData.password !== formData.password_confirmation) {
      setError(t('auth.errors.password_mismatch'));
      setIsLoading(false);
      return;
    }

    try {
      const response = await AuthAPI.resetPassword(formData);

      if (response.success) {
        setMessage(
          response.message || t('password_reset.password_reset_success')
        );
        setTimeout(() => {
          navigate('/auth');
        }, 2000);
      } else {
        setError(response.message || t('auth.errors.registration_failed'));
      }
    } catch {
      setError(t('auth.errors.registration_failed'));
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
      <div className="sm:mx-auto sm:w-full sm:max-w-md">
        <div className="mx-auto w-12 h-12 flex items-center justify-center rounded-full bg-green-100">
          <svg
            className="w-6 h-6 text-green-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"
            />
          </svg>
        </div>
        <h2 className="mt-6 text-center text-3xl font-bold text-gray-900">
          {t('password_reset.reset_title')}
        </h2>
        <p className="mt-2 text-center text-sm text-gray-600">
          {t('password_reset.reset_subtitle')}
        </p>
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div className="card p-8">
          <form onSubmit={handleSubmit} className="space-y-6">
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

            <div>
              <label
                htmlFor="email"
                className="block text-sm font-medium text-gray-700"
              >
                {t('password_reset.email_label')}
              </label>
              <input
                id="email"
                name="email"
                type="email"
                autoComplete="email"
                required
                readOnly
                className="input-field mt-1 w-full bg-gray-50"
                value={formData.email}
              />
            </div>

            <div>
              <label
                htmlFor="password"
                className="block text-sm font-medium text-gray-700"
              >
                {t('password_reset.new_password')}
              </label>
              <input
                id="password"
                name="password"
                type="password"
                autoComplete="new-password"
                required
                className="input-field mt-1 w-full"
                placeholder={t('password_reset.new_password')}
                value={formData.password}
                onChange={handleChange}
              />
            </div>

            <div>
              <label
                htmlFor="password_confirmation"
                className="block text-sm font-medium text-gray-700"
              >
                {t('password_reset.confirm_new_password')}
              </label>
              <input
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                autoComplete="new-password"
                required
                className="input-field mt-1 w-full"
                placeholder={t('password_reset.confirm_new_password')}
                value={formData.password_confirmation}
                onChange={handleChange}
              />
            </div>

            <div>
              <button
                type="submit"
                disabled={isLoading || !formData.token || !formData.email}
                className="btn-primary w-full"
              >
                {isLoading
                  ? t('password_reset.resetting')
                  : t('password_reset.reset_password')}
              </button>
            </div>

            <div className="text-center">
              <button
                type="button"
                onClick={() => navigate('/auth')}
                className="text-sm text-blue-600 hover:text-blue-500"
              >
                {t('password_reset.back_to_login')}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
};
