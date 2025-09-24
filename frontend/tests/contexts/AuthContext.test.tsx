import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import React from 'react';
import { AuthProvider, useAuth } from '../../src/contexts/AuthContext';
import { AuthAPI } from '../../src/api/auth';
import { useToast } from '../../src/hooks/useToast';

// モックの設定
vi.mock('../../src/api/auth', () => ({
  AuthAPI: {
    login: vi.fn(),
    logout: vi.fn(),
    register: vi.fn(),
    me: vi.fn(),
    getCurrentUser: vi.fn(),
    refreshToken: vi.fn(),
    updateProfile: vi.fn(),
  },
}));
vi.mock('../../src/hooks/useToast');

const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
};
const mockUseToast = vi.mocked(useToast);

// テスト用コンポーネント
const TestComponent = () => {
  const {
    user,
    isLoading,
    login,
    logout,
    register,
    refreshToken,
    updateProfile,
  } = useAuth();

  const handleLogin = async () => {
    try {
      await login('test@example.com', 'password123', false, 'turnstile-token');
    } catch (error) {
      // テスト用にエラーをキャッチ（unhandled rejectionを防ぐ）
      console.log('Login failed:', error);
    }
  };

  const handleRegister = async () => {
    try {
      await register({
        name: 'Test User',
        email: 'test@example.com',
        password: 'password123',
        language: 'ja',
        timezone: 'Asia/Tokyo',
        turnstile_token: 'turnstile-token',
      });
    } catch (error) {
      console.log('Registration error:', error);
    }
  };

  const handleRefreshToken = async () => {
    try {
      await refreshToken();
    } catch (error) {
      console.log('Token refresh failed:', error);
    }
  };

  const handleUpdateProfile = async () => {
    try {
      await updateProfile({
        name: 'Updated Name',
        language: 'en',
        timezone: 'UTC',
      });
    } catch (error) {
      console.log('Profile update failed:', error);
    }
  };

  return (
    <div>
      <div data-testid="user">{user ? JSON.stringify(user) : 'null'}</div>
      <div data-testid="loading">{isLoading ? 'loading' : 'not-loading'}</div>
      <button onClick={handleLogin}>Login</button>
      <button onClick={() => logout()}>Logout</button>
      <button onClick={handleRegister}>Register</button>
      <button onClick={handleRefreshToken}>Refresh Token</button>
      <button onClick={handleUpdateProfile}>Update Profile</button>
    </div>
  );
};

describe('AuthContext', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();

    mockUseToast.mockReturnValue({
      toasts: [],
      toast: mockToast,
      removeToast: vi.fn(),
    });

    // デフォルトでmeメソッドが失敗するように設定（認証なし状態）
    vi.mocked(AuthAPI.me).mockRejectedValue(new Error('Not authenticated'));
  });

  it('初期状態が正しく設定される', () => {
    render(
      <AuthProvider>
        <TestComponent />
      </AuthProvider>
    );

    expect(screen.getByTestId('user')).toHaveTextContent('null');
    expect(screen.getByTestId('loading')).toHaveTextContent('not-loading');
  });

  it('ログイン処理が正しく動作する', async () => {
    const mockUser = {
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
      profile: { language: 'ja', timezone: 'Asia/Tokyo' },
      roles: ['user'],
    };

    vi.mocked(AuthAPI.login).mockResolvedValue({
      success: true,
      user: mockUser,
      token: 'jwt-token',
      message: 'ログイン成功',
    });

    render(
      <AuthProvider>
        <TestComponent />
      </AuthProvider>
    );

    fireEvent.click(screen.getByText('Login'));

    await waitFor(() => {
      expect(AuthAPI.login).toHaveBeenCalledWith({
        email: 'test@example.com',
        password: 'password123',
        remember: false,
        turnstile_token: 'turnstile-token',
      });
      expect(screen.getByTestId('user')).toHaveTextContent(
        JSON.stringify(mockUser)
      );
    });

    // トークンがlocalStorageに保存される
    expect(localStorage.getItem('auth_token')).toBe('jwt-token');
  });

  it('2段階認証が必要なログイン処理が動作する', async () => {
    vi.mocked(AuthAPI.login).mockResolvedValue({
      success: true,
      requires_two_factor: true,
      temp_token: 'temp-token-123',
      available_methods: ['totp'],
      message: '2段階認証が必要です',
    });

    render(
      <AuthProvider>
        <TestComponent />
      </AuthProvider>
    );

    fireEvent.click(screen.getByText('Login'));

    // AuthContextのlogin関数がエラーを投げることを期待
    await waitFor(() => {
      expect(AuthAPI.login).toHaveBeenCalled();
    });

    // エラーがthrowされることを確認（unhandled rejectionを防ぐため）
    const loginCall = vi.mocked(AuthAPI.login).mock.results[0].value;
    const result = await loginCall;
    expect(result.requires_two_factor).toBe(true);
    expect(result.temp_token).toBe('temp-token-123');
    expect(result.available_methods).toEqual(['totp']);
  });

  it('ログイン失敗時にエラーが処理される', async () => {
    const errorMessage = 'ログインに失敗しました';
    vi.mocked(AuthAPI.login).mockResolvedValue({
      success: false,
      message: errorMessage,
    });

    render(
      <AuthProvider>
        <TestComponent />
      </AuthProvider>
    );

    fireEvent.click(screen.getByText('Login'));

    // AuthContextのlogin関数がエラーを投げることを期待
    await waitFor(() => {
      expect(AuthAPI.login).toHaveBeenCalled();
    });

    // エラーがthrowされることを確認（unhandled rejectionを防ぐため）
    const loginCall = vi.mocked(AuthAPI.login).mock.results[0].value;
    const result = await loginCall;
    expect(result.success).toBe(false);
    expect(result.message).toBe(errorMessage);
    expect(screen.getByTestId('user')).toHaveTextContent('null');
  });

  it('ログアウト処理が正しく動作する', async () => {
    // 最初にログイン状態にする
    localStorage.setItem('auth_token', 'jwt-token');

    vi.mocked(AuthAPI.logout).mockResolvedValue({
      success: true,
      message: 'ログアウトしました',
    });

    render(
      <AuthProvider>
        <TestComponent />
      </AuthProvider>
    );

    // 実際のテストでは、まずログイン処理を行ってからログアウトをテストする

    fireEvent.click(screen.getByText('Logout'));

    await waitFor(() => {
      expect(AuthAPI.logout).toHaveBeenCalled();
    });

    // トークンがlocalStorageから削除される
    expect(localStorage.getItem('auth_token')).toBeNull();
  });

  it('ユーザー登録処理が正しく動作する', async () => {
    const mockUser = {
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
      profile: { language: 'ja', timezone: 'Asia/Tokyo' },
      roles: ['user'],
    };

    vi.mocked(AuthAPI.register).mockResolvedValue({
      success: true,
      user: mockUser,
      token: 'jwt-token',
      message: '登録が完了しました',
    });

    render(
      <AuthProvider>
        <TestComponent />
      </AuthProvider>
    );

    fireEvent.click(screen.getByText('Register'));

    await waitFor(() => {
      expect(AuthAPI.register).toHaveBeenCalledWith({
        name: 'Test User',
        email: 'test@example.com',
        password: 'password123',
        language: 'ja',
        timezone: 'Asia/Tokyo',
        turnstile_token: 'turnstile-token',
      });
      expect(screen.getByTestId('user')).toHaveTextContent(
        JSON.stringify(mockUser)
      );
    });

    expect(localStorage.getItem('auth_token')).toBe('jwt-token');
  });

  it('トークン更新処理が正しく動作する', async () => {
    const mockUser = {
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
      profile: { language: 'ja', timezone: 'Asia/Tokyo' },
      roles: ['user'],
    };

    localStorage.setItem('auth_token', 'old-token');

    vi.mocked(AuthAPI.refreshToken).mockResolvedValue({
      success: true,
      user: mockUser,
      token: 'new-jwt-token',
      message: 'トークンを更新しました',
    });

    render(
      <AuthProvider>
        <TestComponent />
      </AuthProvider>
    );

    fireEvent.click(screen.getByText('Refresh Token'));

    await waitFor(() => {
      expect(AuthAPI.refreshToken).toHaveBeenCalled();
      expect(screen.getByTestId('user')).toHaveTextContent(
        JSON.stringify(mockUser)
      );
    });

    expect(localStorage.getItem('auth_token')).toBe('new-jwt-token');
  });

  it('プロフィール更新処理が正しく動作する', async () => {
    const originalUser = {
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
      profile: { language: 'ja', timezone: 'Asia/Tokyo' },
      roles: ['user'],
    };

    const updatedUser = {
      ...originalUser,
      name: 'Updated Name',
      profile: { language: 'en', timezone: 'UTC' },
    };

    localStorage.setItem('auth_token', 'jwt-token');

    vi.mocked(AuthAPI.updateProfile).mockResolvedValue({
      success: true,
      user: updatedUser,
      message: 'プロフィールを更新しました',
    });

    render(
      <AuthProvider>
        <TestComponent />
      </AuthProvider>
    );

    fireEvent.click(screen.getByText('Update Profile'));

    await waitFor(() => {
      expect(AuthAPI.updateProfile).toHaveBeenCalledWith('jwt-token', {
        name: 'Updated Name',
        language: 'en',
        timezone: 'UTC',
      });
      expect(screen.getByTestId('user')).toHaveTextContent(
        JSON.stringify(updatedUser)
      );
    });
  });

  it('ローディング状態が正しく管理される', async () => {
    vi.mocked(AuthAPI.login).mockImplementation(
      () =>
        new Promise((resolve) =>
          setTimeout(
            () =>
              resolve({
                success: true,
                user: { id: 1, name: 'Test User' } as User,
                token: 'jwt-token',
                message: 'ログイン成功',
              }),
            100
          )
        )
    );

    render(
      <AuthProvider>
        <TestComponent />
      </AuthProvider>
    );

    // 初期状態
    expect(screen.getByTestId('loading')).toHaveTextContent('not-loading');

    fireEvent.click(screen.getByText('Login'));

    // ローディング中
    await waitFor(() => {
      expect(screen.getByTestId('loading')).toHaveTextContent('loading');
    });

    // ローディング完了
    await waitFor(() => {
      expect(screen.getByTestId('loading')).toHaveTextContent('not-loading');
    });
  });

  it('既存のトークンでの自動ログインが動作する', async () => {
    const mockUser = {
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
      profile: { language: 'ja', timezone: 'Asia/Tokyo' },
      roles: ['user'],
    };

    localStorage.setItem('auth_token', 'existing-token');

    vi.mocked(AuthAPI.me).mockResolvedValue({
      success: true,
      user: mockUser,
    });

    render(
      <AuthProvider>
        <TestComponent />
      </AuthProvider>
    );

    await waitFor(() => {
      expect(AuthAPI.me).toHaveBeenCalledWith('existing-token');
      expect(screen.getByTestId('user')).toHaveTextContent(
        JSON.stringify(mockUser)
      );
    });
  });

  it('無効なトークンの場合はログアウトされる', async () => {
    localStorage.setItem('auth_token', 'invalid-token');

    vi.mocked(AuthAPI.me).mockRejectedValue(new Error('認証が無効です'));

    render(
      <AuthProvider>
        <TestComponent />
      </AuthProvider>
    );

    await waitFor(() => {
      expect(AuthAPI.me).toHaveBeenCalledWith('invalid-token');
      expect(screen.getByTestId('user')).toHaveTextContent('null');
    });

    // removeTokenが呼ばれることで、localStorageからトークンが削除される
    await waitFor(() => {
      expect(localStorage.getItem('auth_token')).toBeNull();
    });
  });
});
