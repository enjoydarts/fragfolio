import React from 'react';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../../hooks/useAuth';

export const Header: React.FC = () => {
  const { t, i18n } = useTranslation();
  const { user, logout, loading } = useAuth();

  const toggleLanguage = () => {
    i18n.changeLanguage(i18n.language === 'ja' ? 'en' : 'ja');
  };

  const handleLogout = async () => {
    try {
      await logout();
    } catch (error) {
      console.error('Logout failed:', error);
    }
  };

  return (
    <header className="header-nav sticky top-0 z-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between h-16">
          <div className="flex items-center">
            <div className="flex items-center space-x-3">
              <div className="w-8 h-8 bg-orange-600 rounded-lg flex items-center justify-center">
                <span className="text-white font-bold text-sm">F</span>
              </div>
              <h1 className="text-2xl font-bold text-gray-900 tracking-tight">
                {t('app.title')}
              </h1>
            </div>
          </div>
          <div className="flex items-center space-x-4">
            <button
              onClick={toggleLanguage}
              className="text-gray-600 hover:text-orange-600 text-sm font-medium transition-colors duration-200 px-3 py-2 rounded-lg hover:bg-orange-50"
            >
              {i18n.language === 'ja' ? 'English' : '日本語'}
            </button>

            {user ? (
              <div className="flex items-center space-x-4">
                <div className="flex items-center space-x-3">
                  <div className="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                    <span className="text-gray-600 font-medium text-sm">
                      {user.name.charAt(0).toUpperCase()}
                    </span>
                  </div>
                  <span className="text-sm font-medium text-gray-700">
                    {user.name}
                  </span>
                </div>
                <button
                  onClick={handleLogout}
                  disabled={loading}
                  className="btn-secondary disabled:opacity-50"
                >
                  {t('nav.logout')}
                </button>
              </div>
            ) : (
              <a href="/auth" className="btn-primary">
                {t('nav.login')}
              </a>
            )}
          </div>
        </div>
      </div>
    </header>
  );
};
