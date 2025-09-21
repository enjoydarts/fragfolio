import { describe, it, expect, beforeEach, vi } from 'vitest';
import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import React from 'react';
import { renderWithRouter as render } from '../../src/test/utils';
import { AuthPage } from '../../src/pages/AuthPage';
import { AuthContext } from '../../src/contexts/context';
import type { User } from '../../src/types';

// TurnstileWidgetをモック
vi.mock('../../src/components/auth/TurnstileWidget', () => ({
  TurnstileWidget: ({ onVerify }: { onVerify: (token: string) => void }) => {
    React.useEffect(() => {
      onVerify('test-turnstile-token');
    }, [onVerify]);

    return <div data-testid="turnstile-widget">Mocked Turnstile</div>;
  }
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

const renderWithAuth = (authOverrides = {}) => {
  const authValue = { ...mockAuthContext, ...authOverrides };
  return render(
    <AuthContext.Provider value={authValue}>
      <AuthPage />
    </AuthContext.Provider>
  );
};

describe('AuthPage', () => {
  beforeEach(() => {
    localStorage.clear();
    vi.clearAllMocks();
  });

  describe('基本表示', () => {
    it('初期状態ではログインフォームが表示される', () => {
      renderWithAuth();

      expect(screen.getByLabelText('メールアドレス')).toBeInTheDocument();
      expect(screen.getByLabelText('パスワード')).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'ログイン' })).toBeInTheDocument();
    });

    it('アカウントをお持ちでない方はこちらモードに切り替えられる', async () => {
      const user = userEvent.setup();
      renderWithAuth();

      // アカウントをお持ちでない方はこちらリンクをクリック（実際のコンポーネントに応じて調整が必要）
      const registerLink = screen.getByText('アカウントをお持ちでない方はこちら');
      await user.click(registerLink);

      // アカウントをお持ちでない方はこちらフォームの要素が表示される
      expect(screen.getByLabelText('名前')).toBeInTheDocument();
      expect(screen.getByLabelText('パスワード確認')).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'アカウント作成' })).toBeInTheDocument();
    });

    it('ログインモードに戻ることができる', async () => {
      const user = userEvent.setup();
      renderWithAuth();

      // アカウントをお持ちでない方はこちらモードに切り替え
      const registerLink = screen.getByText('アカウントをお持ちでない方はこちら');
      await user.click(registerLink);

      // ログインリンクをクリック
      const loginLink = screen.getByText('既にアカウントをお持ちですか？ログインする');
      await user.click(loginLink);

      // ログインフォームに戻る
      expect(screen.getByRole('button', { name: 'ログイン' })).toBeInTheDocument();
    });
  });

  describe('フォーム送信', () => {
    it('ログイン成功時にログイン関数が呼ばれる', async () => {
      const user = userEvent.setup();
      const mockLogin = vi.fn().mockResolvedValue({ success: true });

      renderWithAuth({ login: mockLogin });

      await user.type(screen.getByLabelText('メールアドレス'), 'test@example.com');
      await user.type(screen.getByLabelText('パスワード'), 'password123');
      await user.click(screen.getByRole('button', { name: 'ログイン' }));

      expect(mockLogin).toHaveBeenCalledWith(
        'test@example.com',
        'password123',
        false,
        'test-turnstile-token'
      );
    });

    it('新規登録モードでは登録フォームが表示される', async () => {
      const user = userEvent.setup();
      renderWithAuth();

      // アカウントをお持ちでない方はこちらモードに切り替え
      const registerLink = screen.getByText('アカウントをお持ちでない方はこちら');
      await user.click(registerLink);

      // 新規登録フォームが表示される
      expect(screen.getByLabelText('名前')).toBeInTheDocument();
      expect(screen.getByLabelText('パスワード確認')).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'アカウント作成' })).toBeInTheDocument();
    });
  });

  describe('ローディング状態', () => {
    it('ローディング中はボタンが無効になる', () => {
      renderWithAuth({ loading: true });

      const loginButton = screen.getByRole('button', { name: 'ログイン中...' });
      expect(loginButton).toBeDisabled();
    });

    it('ローディング中はローディング表示される', () => {
      renderWithAuth({ loading: true });

      expect(screen.getByText('ログイン中...')).toBeInTheDocument();
    });
  });

  describe('セキュリティ機能', () => {
    it('Turnstileウィジェットがモックで表示される', () => {
      renderWithAuth();

      // モックされたTurnstileウィジェットが表示される
      const turnstileContainer = document.querySelector(
        '[data-testid="turnstile-widget"]'
      );
      expect(turnstileContainer).toBeInTheDocument();
      expect(turnstileContainer).toHaveTextContent('Mocked Turnstile');
    });
  });
});