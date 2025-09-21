import { describe, it, expect, beforeEach, vi } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useAuth } from '../../src/hooks/useAuth';
import { AuthContext } from '../../src/contexts/context';
import type { User } from '../../src/types';
import React from 'react';

// AuthContextのモック値
const mockAuthValue = {
  user: null as User | null,
  loading: false,
  login: vi.fn(),
  register: vi.fn(),
  logout: vi.fn(),
  updateProfile: vi.fn(),
  refreshToken: vi.fn(),
};

// テスト用のWrapper
const createWrapper = (authValue = mockAuthValue) => {
  return ({ children }: { children: React.ReactNode }) =>
    React.createElement(AuthContext.Provider, { value: authValue }, children);
};

describe('useAuth', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('基本機能', () => {
    it('AuthContextの値を正しく返す', () => {
      const mockUser: User = {
        id: 1,
        name: 'テストユーザー',
        email: 'test@example.com',
        email_verified_at: '2023-01-01T00:00:00.000Z',
        created_at: '2023-01-01T00:00:00.000Z',
        updated_at: '2023-01-01T00:00:00.000Z',
      };

      const authValue = {
        ...mockAuthValue,
        user: mockUser,
        loading: true,
      };

      const { result } = renderHook(() => useAuth(), {
        wrapper: createWrapper(authValue),
      });

      expect(result.current.user).toEqual(mockUser);
      expect(result.current.loading).toBe(true);
      expect(result.current.login).toBeDefined();
      expect(result.current.register).toBeDefined();
      expect(result.current.logout).toBeDefined();
      expect(result.current.updateProfile).toBeDefined();
      expect(result.current.refreshToken).toBeDefined();
    });

    it('ユーザーが未認証の場合はnullを返す', () => {
      const { result } = renderHook(() => useAuth(), {
        wrapper: createWrapper(),
      });

      expect(result.current.user).toBeNull();
      expect(result.current.loading).toBe(false);
    });
  });

  describe('認証状態の判定', () => {
    it('ユーザーがログイン済みかどうかを判定できる', () => {
      const mockUser: User = {
        id: 1,
        name: 'テストユーザー',
        email: 'test@example.com',
        email_verified_at: '2023-01-01T00:00:00.000Z',
        created_at: '2023-01-01T00:00:00.000Z',
        updated_at: '2023-01-01T00:00:00.000Z',
      };

      // ログイン済みユーザー
      const { result: loggedInResult } = renderHook(() => useAuth(), {
        wrapper: createWrapper({ ...mockAuthValue, user: mockUser }),
      });

      expect(loggedInResult.current.user).not.toBeNull();

      // 未ログインユーザー
      const { result: loggedOutResult } = renderHook(() => useAuth(), {
        wrapper: createWrapper({ ...mockAuthValue, user: null }),
      });

      expect(loggedOutResult.current.user).toBeNull();
    });

    it('メール認証状態を確認できる', () => {
      const verifiedUser: User = {
        id: 1,
        name: 'テストユーザー',
        email: 'test@example.com',
        email_verified_at: '2023-01-01T00:00:00.000Z',
        created_at: '2023-01-01T00:00:00.000Z',
        updated_at: '2023-01-01T00:00:00.000Z',
      };

      const unverifiedUser: User = {
        ...verifiedUser,
        email_verified_at: null,
      };

      // 認証済みユーザー
      const { result: verifiedResult } = renderHook(() => useAuth(), {
        wrapper: createWrapper({ ...mockAuthValue, user: verifiedUser }),
      });

      expect(verifiedResult.current.user?.email_verified_at).not.toBeNull();

      // 未認証ユーザー
      const { result: unverifiedResult } = renderHook(() => useAuth(), {
        wrapper: createWrapper({ ...mockAuthValue, user: unverifiedUser }),
      });

      expect(unverifiedResult.current.user?.email_verified_at).toBeNull();
    });
  });

  describe('認証関数の呼び出し', () => {
    it('login関数を呼び出せる', async () => {
      const mockLogin = vi.fn().mockResolvedValue({ success: true });
      const authValue = { ...mockAuthValue, login: mockLogin };

      const { result } = renderHook(() => useAuth(), {
        wrapper: createWrapper(authValue),
      });

      const loginData = {
        email: 'test@example.com',
        password: 'password123',
      };

      await act(async () => {
        await result.current.login(loginData);
      });

      expect(mockLogin).toHaveBeenCalledWith(loginData);
    });

    it('register関数を呼び出せる', async () => {
      const mockRegister = vi.fn().mockResolvedValue({ success: true });
      const authValue = { ...mockAuthValue, register: mockRegister };

      const { result } = renderHook(() => useAuth(), {
        wrapper: createWrapper(authValue),
      });

      const registerData = {
        name: 'テストユーザー',
        email: 'test@example.com',
        password: 'password123',
        password_confirmation: 'password123',
      };

      await act(async () => {
        await result.current.register(registerData);
      });

      expect(mockRegister).toHaveBeenCalledWith(registerData);
    });

    it('logout関数を呼び出せる', async () => {
      const mockLogout = vi.fn().mockResolvedValue(undefined);
      const authValue = { ...mockAuthValue, logout: mockLogout };

      const { result } = renderHook(() => useAuth(), {
        wrapper: createWrapper(authValue),
      });

      await act(async () => {
        await result.current.logout();
      });

      expect(mockLogout).toHaveBeenCalled();
    });

    it('updateProfile関数を呼び出せる', async () => {
      const mockUpdateProfile = vi.fn().mockResolvedValue({ success: true });
      const authValue = { ...mockAuthValue, updateProfile: mockUpdateProfile };

      const { result } = renderHook(() => useAuth(), {
        wrapper: createWrapper(authValue),
      });

      const profileData = {
        name: '更新されたユーザー名',
        bio: '自己紹介文',
      };

      await act(async () => {
        await result.current.updateProfile(profileData);
      });

      expect(mockUpdateProfile).toHaveBeenCalledWith(profileData);
    });

    it('refreshToken関数を呼び出せる', async () => {
      const mockRefreshToken = vi.fn().mockResolvedValue({ success: true });
      const authValue = { ...mockAuthValue, refreshToken: mockRefreshToken };

      const { result } = renderHook(() => useAuth(), {
        wrapper: createWrapper(authValue),
      });

      await act(async () => {
        await result.current.refreshToken();
      });

      expect(mockRefreshToken).toHaveBeenCalled();
    });
  });

  describe('エラーハンドリング', () => {
    it('AuthContextが提供されていない場合はエラーをスローする', () => {
      // コンソールエラーを一時的に無効化
      const consoleSpy = vi
        .spyOn(console, 'error')
        .mockImplementation(() => {});

      expect(() => {
        renderHook(() => useAuth());
      }).toThrow('useAuth must be used within an AuthProvider');

      consoleSpy.mockRestore();
    });
  });

  describe('型安全性', () => {
    it('適切な型を返す', () => {
      const { result } = renderHook(() => useAuth(), {
        wrapper: createWrapper(),
      });

      // TypeScriptの型チェックにより、コンパイル時に型安全性が保証される
      expect(typeof result.current.login).toBe('function');
      expect(typeof result.current.register).toBe('function');
      expect(typeof result.current.logout).toBe('function');
      expect(typeof result.current.updateProfile).toBe('function');
      expect(typeof result.current.refreshToken).toBe('function');
      expect(typeof result.current.loading).toBe('boolean');
    });
  });
});
