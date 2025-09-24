import React, { useEffect, useState, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { AuthAPI } from '../api/auth';
import type { User, AuthContextType, RegisterData } from '../types/auth';
import { AuthContext } from './context';

interface AuthProviderProps {
  children: React.ReactNode;
}

export const AuthProvider: React.FC<AuthProviderProps> = ({ children }) => {
  const { t } = useTranslation();
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  // トークンをlocalStorageから取得
  const getToken = (): string | null => {
    return localStorage.getItem('auth_token');
  };

  // トークンをlocalStorageに保存
  const setToken = (token: string): void => {
    localStorage.setItem('auth_token', token);
  };

  // トークンをlocalStorageから削除
  const removeToken = (): void => {
    localStorage.removeItem('auth_token');
  };

  // 認証状態を確認
  const checkAuth = useCallback(async (): Promise<void> => {
    const token = getToken();
    if (!token) {
      setLoading(false);
      return;
    }

    try {
      const response = await AuthAPI.me(token);
      if (response.success && response.user) {
        setUser(response.user);
      } else {
        removeToken();
      }
    } catch (error) {
      console.error('Auth check failed:', error);
      removeToken();
    } finally {
      setLoading(false);
    }
  }, []);

  // ログイン
  const login = async (
    email: string,
    password: string,
    remember = false,
    turnstileToken?: string | null
  ): Promise<void> => {
    try {
      const response = await AuthAPI.login({
        email,
        password,
        remember,
        turnstile_token: turnstileToken,
      });
      if (response.success && response.user && response.token) {
        setToken(response.token);
        setUser(response.user);
      } else if (response.requires_two_factor) {
        // 2FA要求エラーオブジェクトを作成
        const error = new Error(response.message || '2要素認証が必要です') as Error & { requires_two_factor?: boolean; temp_token?: string; available_methods?: string[] };
        error.requires_two_factor = true;
        error.temp_token = response.temp_token;
        error.available_methods = response.available_methods;
        throw error;
      } else {
        throw new Error(response.message || t('auth.errors.login_failed'));
      }
    } catch (error: unknown) {
      // 2FA要求の場合はエラーログを出さない
      const authError = error as { requires_two_factor?: boolean };
      if (!authError?.requires_two_factor) {
        console.error('Login failed:', error);
      }
      throw error;
    }
  };

  // ユーザー登録
  const register = async (data: RegisterData): Promise<void> => {
    try {
      const response = await AuthAPI.register(data);
      if (response.success && response.user && response.token) {
        setToken(response.token);
        setUser(response.user);
      } else {
        // レスポンスデータをそのまま含むエラーオブジェクトを作成
        const error = new Error(
          response.message || t('auth.errors.registration_failed')
        ) as Error & { response?: { data: unknown } };
        error.response = { data: response };
        throw error;
      }
    } catch (error) {
      console.error('Registration failed:', error);
      throw error;
    }
  };

  // ログアウト
  const logout = async (): Promise<void> => {
    const token = getToken();
    try {
      if (token) {
        await AuthAPI.logout(token);
      }
    } catch (error) {
      console.error('Logout API failed:', error);
    } finally {
      removeToken();
      setUser(null);
    }
  };

  // トークンリフレッシュ
  const refreshToken = async (): Promise<void> => {
    const token = getToken();
    if (!token) {
      throw new Error(t('auth.errors.auth_token_missing'));
    }

    try {
      const response = await AuthAPI.refreshToken(token);
      if (response.success && response.user && response.token) {
        setToken(response.token);
        setUser(response.user);
      } else {
        throw new Error(t('auth.errors.token_refresh_failed'));
      }
    } catch (error) {
      console.error('Token refresh failed:', error);
      removeToken();
      setUser(null);
      throw error;
    }
  };

  // プロフィール更新
  const updateProfile = async (data: Partial<User>): Promise<void> => {
    const token = getToken();
    if (!token) {
      throw new Error(t('auth.errors.auth_token_missing'));
    }

    try {
      const response = await AuthAPI.updateProfile(token, data);
      if (response.success && response.user) {
        setUser(response.user);
      } else {
        throw new Error(response.message || t('auth.errors.profile_update_failed'));
      }
    } catch (error) {
      console.error('Profile update failed:', error);
      throw error;
    }
  };

  // 初期化時に認証状態を確認
  useEffect(() => {
    checkAuth();
  }, [checkAuth]);

  const value: AuthContextType = {
    user,
    loading,
    token: getToken(),
    login,
    register,
    logout,
    refreshToken,
    refreshUser: checkAuth,
    updateProfile,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

export const useAuth = (): AuthContextType => {
  const context = React.useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};
