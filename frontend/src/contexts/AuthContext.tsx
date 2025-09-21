import React, { useEffect, useState, useCallback } from 'react';
import { AuthAPI } from '../api/auth';
import type { User, AuthContextType, RegisterData } from '../types/auth';
import { AuthContext } from './context';

interface AuthProviderProps {
  children: React.ReactNode;
}

export const AuthProvider: React.FC<AuthProviderProps> = ({ children }) => {
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
      } else {
        throw new Error(response.message || 'ログインに失敗しました');
      }
    } catch (error) {
      console.error('Login failed:', error);
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
          response.message || 'ユーザー登録に失敗しました'
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
      throw new Error('認証トークンがありません');
    }

    try {
      const response = await AuthAPI.refreshToken(token);
      if (response.success && response.user && response.token) {
        setToken(response.token);
        setUser(response.user);
      } else {
        throw new Error('トークンリフレッシュに失敗しました');
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
      throw new Error('認証トークンがありません');
    }

    try {
      const response = await AuthAPI.updateProfile(token, data);
      if (response.success && response.user) {
        setUser(response.user);
      } else {
        throw new Error(response.message || 'プロフィール更新に失敗しました');
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
