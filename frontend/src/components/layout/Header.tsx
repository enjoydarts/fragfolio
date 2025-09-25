import React, { useState, useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../../hooks/useAuth';
import logoSvg from '../../assets/logo.svg';
import {
  ChevronDownIcon,
  Bars3Icon,
  XMarkIcon,
  LanguageIcon,
  Cog6ToothIcon,
  ArrowRightOnRectangleIcon,
} from '@heroicons/react/24/outline';

export const Header: React.FC = () => {
  const { t, i18n } = useTranslation();
  const { user, logout, loading } = useAuth();
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const [isUserMenuOpen, setIsUserMenuOpen] = useState(false);
  const userMenuRef = useRef<HTMLDivElement>(null);

  // Close dropdowns when clicking outside
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (
        userMenuRef.current &&
        !userMenuRef.current.contains(event.target as Node)
      ) {
        setIsUserMenuOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, []);

  const toggleLanguage = () => {
    i18n.changeLanguage(i18n.language === 'ja' ? 'en' : 'ja');
    setIsMenuOpen(false);
  };

  const handleLogout = async () => {
    try {
      await logout();
      setIsUserMenuOpen(false);
    } catch (error) {
      console.error('Logout failed:', error);
    }
  };

  return (
    <header className="header-nav sticky top-0 z-50 bg-white border-b border-gray-200 shadow-sm">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between items-center h-16">
          {/* Logo */}
          <div className="flex items-center">
            <img src={logoSvg} alt="fragfolio" className="h-10" />
          </div>

          {/* Desktop Navigation */}
          <div className="hidden md:flex items-center space-x-4">
            <button
              onClick={toggleLanguage}
              className="text-gray-600 hover:text-orange-600 text-sm font-medium transition-colors duration-200 px-3 py-2 rounded-lg hover:bg-orange-50"
            >
              {i18n.language === 'ja' ? '🇺🇸 English' : '🇯🇵 日本語'}
            </button>

            {user ? (
              <div className="relative" ref={userMenuRef}>
                <button
                  onClick={() => setIsUserMenuOpen(!isUserMenuOpen)}
                  className="flex items-center space-x-3 px-3 py-2 rounded-lg text-gray-700 hover:bg-orange-50 transition-colors duration-200"
                >
                  <div className="w-8 h-8 bg-gradient-to-br from-orange-400 to-amber-500 rounded-full flex items-center justify-center">
                    <span className="text-white font-medium text-sm">
                      {user.name.charAt(0).toUpperCase()}
                    </span>
                  </div>
                  <span className="text-sm font-medium hidden lg:block">
                    {user.name}
                  </span>
                  <ChevronDownIcon className="w-4 h-4" />
                </button>

                {/* User Dropdown Menu */}
                {isUserMenuOpen && (
                  <div className="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50">
                    <div className="px-4 py-2 border-b border-gray-100">
                      <p className="text-sm font-medium text-gray-900">
                        {user.name}
                      </p>
                      <p className="text-xs text-gray-500">{user.email}</p>
                    </div>
                    <a
                      href="/settings"
                      className="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-orange-50 transition-colors duration-200"
                      onClick={() => setIsUserMenuOpen(false)}
                    >
                      <Cog6ToothIcon className="w-4 h-4 mr-3" />
                      {t('nav.settings')}
                    </a>
                    <button
                      onClick={handleLogout}
                      disabled={loading}
                      className="flex items-center w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-orange-50 transition-colors duration-200 disabled:opacity-50"
                    >
                      <ArrowRightOnRectangleIcon className="w-4 h-4 mr-3" />
                      {t('nav.logout')}
                    </button>
                  </div>
                )}
              </div>
            ) : (
              <a href="/auth" className="btn-primary">
                {t('nav.login')}
              </a>
            )}
          </div>

          {/* Mobile Menu Button */}
          <div className="md:hidden">
            <button
              onClick={() => setIsMenuOpen(!isMenuOpen)}
              className="p-2 rounded-lg text-gray-600 hover:text-orange-600 hover:bg-orange-50 transition-colors duration-200"
            >
              {isMenuOpen ? (
                <XMarkIcon className="w-6 h-6" />
              ) : (
                <Bars3Icon className="w-6 h-6" />
              )}
            </button>
          </div>
        </div>

        {/* Mobile Navigation Menu */}
        {isMenuOpen && (
          <div className="md:hidden border-t border-gray-200 py-4">
            <div className="space-y-2">
              <button
                onClick={toggleLanguage}
                className="flex items-center w-full px-3 py-2 text-left text-gray-600 hover:text-orange-600 hover:bg-orange-50 rounded-lg transition-colors duration-200"
              >
                <LanguageIcon className="w-5 h-5 mr-3" />
                {i18n.language === 'ja' ? 'English' : '日本語'}
              </button>

              {user ? (
                <>
                  <div className="flex items-center px-3 py-2 border-b border-gray-200">
                    <div className="w-10 h-10 bg-gradient-to-br from-orange-400 to-amber-500 rounded-full flex items-center justify-center mr-3">
                      <span className="text-white font-medium">
                        {user.name.charAt(0).toUpperCase()}
                      </span>
                    </div>
                    <div>
                      <p className="text-sm font-medium text-gray-900">
                        {user.name}
                      </p>
                      <p className="text-xs text-gray-500">{user.email}</p>
                    </div>
                  </div>
                  <a
                    href="/settings"
                    className="flex items-center w-full px-3 py-2 text-left text-gray-600 hover:text-orange-600 hover:bg-orange-50 rounded-lg transition-colors duration-200"
                    onClick={() => setIsMenuOpen(false)}
                  >
                    <Cog6ToothIcon className="w-5 h-5 mr-3" />
                    {t('nav.settings')}
                  </a>
                  <button
                    onClick={handleLogout}
                    disabled={loading}
                    className="flex items-center w-full px-3 py-2 text-left text-gray-600 hover:text-orange-600 hover:bg-orange-50 rounded-lg transition-colors duration-200 disabled:opacity-50"
                  >
                    <ArrowRightOnRectangleIcon className="w-5 h-5 mr-3" />
                    {t('nav.logout')}
                  </button>
                </>
              ) : (
                <a
                  href="/auth"
                  className="block w-full px-3 py-2 text-center bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors duration-200"
                  onClick={() => setIsMenuOpen(false)}
                >
                  {t('nav.login')}
                </a>
              )}
            </div>
          </div>
        )}
      </div>
    </header>
  );
};
