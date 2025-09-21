import { describe, it, expect, beforeEach, vi } from 'vitest';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import React from 'react';
import { renderWithRouter } from '../../../src/test/utils';
import { LoginForm } from '../../../src/components/auth/LoginForm';
import { AuthContext } from '../../../src/contexts/context';
import type { User } from '../../../src/types';

// 環境変数をモック
vi.mock('vite', () => ({
  defineConfig: vi.fn(),
}));

Object.defineProperty(import.meta, 'env', {
  value: {
    VITE_TURNSTILE_SITE_KEY: 'test-site-key',
  },
  writable: true,
});

// TurnstileWidgetをモック
vi.mock('../../../src/components/auth/TurnstileWidget', () => ({
  TurnstileWidget: ({ onVerify }: { onVerify: (token: string) => void }) => {
    // テスト用にTurnstileトークンを自動生成
    React.useEffect(() => {
      // 非同期でトークンを設定してより確実にする
      setTimeout(() => {
        onVerify('test-turnstile-token');
      }, 0);
    }, [onVerify]);

    return <div data-testid="turnstile-widget">Mocked Turnstile</div>;
  },
}));

// AuthContextのモック
const mockAuthContext = {
  user: null as User | null,
  loading: false,
  token: null,
  logout: vi.fn(),
  login: vi.fn(),
  register: vi.fn(),
  updateProfile: vi.fn(),
  refreshToken: vi.fn(),
  refreshUser: vi.fn(),
};

const renderWithAuth = (authOverrides = {}, props = {}) => {
  const authValue = { ...mockAuthContext, ...authOverrides };
  return renderWithRouter(
    <AuthContext.Provider value={authValue}>
      <LoginForm {...props} />
    </AuthContext.Provider>
  );
};

describe('LoginForm', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
    // Vitest環境変数モック
    vi.stubEnv('VITE_TURNSTILE_SITE_KEY', 'test-site-key');
    // 環境変数を確実に設定（フォールバック）
    Object.defineProperty(import.meta, 'env', {
      value: {
        VITE_TURNSTILE_SITE_KEY: 'test-site-key',
      },
      writable: true,
      configurable: true,
    });
  });

  afterEach(() => {
    vi.unstubAllEnvs();
  });

  describe('基本表示', () => {
    it('必要なフォームフィールドが表示される', () => {
      renderWithAuth();

      expect(screen.getByLabelText('メールアドレス')).toBeInTheDocument();
      expect(screen.getByLabelText('パスワード')).toBeInTheDocument();
      expect(
        screen.getByRole('button', { name: 'ログイン' })
      ).toBeInTheDocument();
    });

    it('適切な入力タイプが設定されている', () => {
      renderWithAuth();

      expect(screen.getByLabelText('メールアドレス')).toHaveAttribute(
        'type',
        'email'
      );
      expect(screen.getByLabelText('パスワード')).toHaveAttribute(
        'type',
        'password'
      );
    });

    it('記憶するオプションのチェックボックスが表示される', () => {
      renderWithAuth();

      expect(screen.getByLabelText('ログイン状態を保持')).toBeInTheDocument();
      expect(screen.getByLabelText('ログイン状態を保持')).toHaveAttribute(
        'type',
        'checkbox'
      );
    });

    it('パスワードを忘れた場合のリンクが表示される', () => {
      renderWithAuth();

      const forgotPasswordLink = screen.getByText('パスワードを忘れた場合');
      expect(forgotPasswordLink).toBeInTheDocument();
      // ボタンなのでhref属性は無い
    });
  });

  describe('フォーム送信', () => {
    it('有効な入力でフォーム送信される', async () => {
      const user = userEvent.setup();
      const mockLogin = vi.fn().mockResolvedValue({ success: true });
      renderWithAuth({ login: mockLogin });

      await user.type(
        screen.getByLabelText('メールアドレス'),
        'test@example.com'
      );
      await user.type(screen.getByLabelText('パスワード'), 'password123');
      await user.click(screen.getByRole('button', { name: 'ログイン' }));

      await waitFor(() => {
        expect(mockLogin).toHaveBeenCalledWith(
          'test@example.com',
          'password123',
          false,
          'test-turnstile-token'
        );
      });
    });

    it('記憶するオプションがチェックされた場合にrememberがtrueになる', async () => {
      const user = userEvent.setup();
      const mockLogin = vi.fn().mockResolvedValue({ success: true });
      renderWithAuth({ login: mockLogin });

      await user.type(
        screen.getByLabelText('メールアドレス'),
        'test@example.com'
      );
      await user.type(screen.getByLabelText('パスワード'), 'password123');
      await user.click(screen.getByLabelText('ログイン状態を保持'));
      await user.click(screen.getByRole('button', { name: 'ログイン' }));

      await waitFor(() => {
        expect(mockLogin).toHaveBeenCalledWith(
          'test@example.com',
          'password123',
          true,
          'test-turnstile-token'
        );
      });
    });

    it('空のフォームでは送信されない', async () => {
      const user = userEvent.setup();
      const mockLogin = vi.fn();
      renderWithAuth({ login: mockLogin });

      await user.click(screen.getByRole('button', { name: 'ログイン' }));

      // フォームバリデーションにより送信されない
      expect(mockLogin).not.toHaveBeenCalled();
    });
  });

  describe('ローディング状態', () => {
    it('ローディング中はボタンが無効になる', () => {
      renderWithAuth({ loading: true });

      const submitButton = screen.getByRole('button', {
        name: 'ログイン中...',
      });
      expect(submitButton).toBeDisabled();
    });

    it('ローディング中でも入力フィールドは有効', () => {
      renderWithAuth({ loading: true });

      expect(screen.getByLabelText('メールアドレス')).toBeEnabled();
      expect(screen.getByLabelText('パスワード')).toBeEnabled();
      expect(screen.getByLabelText('ログイン状態を保持')).toBeEnabled();
    });

    it('ローディング中はローディング表示される', () => {
      renderWithAuth({ loading: true });

      expect(screen.getByText('ログイン中...')).toBeInTheDocument();
    });
  });

  describe('エラー表示', () => {
    it('ログインエラー時にエラーメッセージが表示される', async () => {
      const user = userEvent.setup();
      const mockLogin = vi
        .fn()
        .mockRejectedValue(new Error('認証に失敗しました'));
      renderWithAuth({ login: mockLogin });

      await user.type(
        screen.getByLabelText('メールアドレス'),
        'test@example.com'
      );
      await user.type(screen.getByLabelText('パスワード'), 'wrongpassword');
      await user.click(screen.getByRole('button', { name: 'ログイン' }));

      await waitFor(() => {
        expect(screen.getByText('認証に失敗しました')).toBeInTheDocument();
      });
    });
  });

  describe('フォームバリデーション', () => {
    it('必須フィールドが適切に設定されている', () => {
      renderWithAuth();

      expect(screen.getByLabelText('メールアドレス')).toBeRequired();
      expect(screen.getByLabelText('パスワード')).toBeRequired();
    });
  });

  describe('アクセシビリティ', () => {
    it('適切なラベルが設定されている', () => {
      renderWithAuth();

      expect(screen.getByLabelText('メールアドレス')).toBeInTheDocument();
      expect(screen.getByLabelText('パスワード')).toBeInTheDocument();
      expect(screen.getByLabelText('ログイン状態を保持')).toBeInTheDocument();
    });

    it('フォームがキーボードで操作可能', async () => {
      const user = userEvent.setup();
      renderWithAuth();

      const emailInput = screen.getByLabelText('メールアドレス');
      const passwordInput = screen.getByLabelText('パスワード');
      const rememberCheckbox = screen.getByLabelText('ログイン状態を保持');
      const forgotPasswordButton = screen.getByText('パスワードを忘れた場合');

      // タブでフォーカス移動
      await user.tab();
      expect(emailInput).toHaveFocus();

      await user.tab();
      expect(passwordInput).toHaveFocus();

      await user.tab();
      expect(rememberCheckbox).toHaveFocus();

      await user.tab();
      expect(forgotPasswordButton).toHaveFocus();
    });
  });

  describe('Turnstile統合', () => {
    it('Turnstileウィジェットがモックで表示される', async () => {
      renderWithAuth();

      // 非同期でウィジェットが表示されるのを待つ
      const turnstileWidget = await screen.findByTestId('turnstile-widget');
      expect(turnstileWidget).toBeInTheDocument();
      expect(turnstileWidget).toHaveTextContent('Mocked Turnstile');
    });
  });
});
