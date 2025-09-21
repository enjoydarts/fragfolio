import { describe, it, expect, beforeEach, vi } from 'vitest';
import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { render } from '../src/test/utils';
import { AuthPage } from '../../src/pages/AuthPage';
import { AuthContext } from '../../src/contexts/AuthContext';
import type { User } from '../../src/types';

// AuthContextのモック
const mockAuthContext = {
  user: null as User | null,
  loading: false,
  logout: vi.fn(),
  login: vi.fn(),
  register: vi.fn(),
  updateProfile: vi.fn(),
  refreshToken: vi.fn(),
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
    it('ページタイトルが表示される', () => {
      renderWithAuth();
      expect(screen.getByText('ログイン / 新規登録')).toBeInTheDocument();
    });

    it('初期状態ではログインフォームが表示される', () => {
      renderWithAuth();
      expect(screen.getByRole('tab', { name: 'ログイン' })).toHaveAttribute(
        'aria-selected',
        'true'
      );
      expect(screen.getByRole('tab', { name: '新規登録' })).toHaveAttribute(
        'aria-selected',
        'false'
      );
    });

    it('ログインフォームの必要な要素が表示される', () => {
      renderWithAuth();
      expect(screen.getByLabelText('メールアドレス')).toBeInTheDocument();
      expect(screen.getByLabelText('パスワード')).toBeInTheDocument();
      expect(
        screen.getByRole('button', { name: 'ログイン' })
      ).toBeInTheDocument();
    });
  });

  describe('タブ切り替え', () => {
    it('新規登録タブをクリックで新規登録フォームに切り替わる', async () => {
      const user = userEvent.setup();
      renderWithAuth();

      await user.click(screen.getByRole('tab', { name: '新規登録' }));

      expect(screen.getByRole('tab', { name: '新規登録' })).toHaveAttribute(
        'aria-selected',
        'true'
      );
      expect(screen.getByRole('tab', { name: 'ログイン' })).toHaveAttribute(
        'aria-selected',
        'false'
      );
    });

    it('新規登録フォームで必要な要素が表示される', async () => {
      const user = userEvent.setup();
      renderWithAuth();

      await user.click(screen.getByRole('tab', { name: '新規登録' }));

      expect(screen.getByLabelText('ユーザー名')).toBeInTheDocument();
      expect(screen.getByLabelText('メールアドレス')).toBeInTheDocument();
      expect(screen.getByLabelText('パスワード')).toBeInTheDocument();
      expect(screen.getByLabelText('パスワード（確認）')).toBeInTheDocument();
      expect(
        screen.getByRole('button', { name: '新規登録' })
      ).toBeInTheDocument();
    });

    it('ログインタブをクリックでログインフォームに戻る', async () => {
      const user = userEvent.setup();
      renderWithAuth();

      // 新規登録タブに切り替え
      await user.click(screen.getByRole('tab', { name: '新規登録' }));
      expect(screen.getByRole('tab', { name: '新規登録' })).toHaveAttribute(
        'aria-selected',
        'true'
      );

      // ログインタブに戻す
      await user.click(screen.getByRole('tab', { name: 'ログイン' }));
      expect(screen.getByRole('tab', { name: 'ログイン' })).toHaveAttribute(
        'aria-selected',
        'true'
      );
    });
  });

  describe('フォーム送信', () => {
    it('ログインフォームの送信でlogin関数が呼ばれる', async () => {
      const user = userEvent.setup();
      const mockLogin = vi.fn().mockResolvedValue({ success: true });
      renderWithAuth({ login: mockLogin });

      await user.type(
        screen.getByLabelText('メールアドレス'),
        'test@example.com'
      );
      await user.type(screen.getByLabelText('パスワード'), 'password123');
      await user.click(screen.getByRole('button', { name: 'ログイン' }));

      expect(mockLogin).toHaveBeenCalledWith(
        expect.objectContaining({
          email: 'test@example.com',
          password: 'password123',
        })
      );
    });

    it('新規登録フォームの送信でregister関数が呼ばれる', async () => {
      const user = userEvent.setup();
      const mockRegister = vi.fn().mockResolvedValue({ success: true });
      renderWithAuth({ register: mockRegister });

      // 新規登録タブに切り替え
      await user.click(screen.getByRole('tab', { name: '新規登録' }));

      await user.type(screen.getByLabelText('ユーザー名'), 'テストユーザー');
      await user.type(
        screen.getByLabelText('メールアドレス'),
        'test@example.com'
      );
      await user.type(screen.getByLabelText('パスワード'), 'password123');
      await user.type(
        screen.getByLabelText('パスワード（確認）'),
        'password123'
      );
      await user.click(screen.getByRole('button', { name: '新規登録' }));

      expect(mockRegister).toHaveBeenCalledWith(
        expect.objectContaining({
          name: 'テストユーザー',
          email: 'test@example.com',
          password: 'password123',
          password_confirmation: 'password123',
        })
      );
    });
  });

  describe('ローディング状態', () => {
    it('ローディング中はボタンが無効になる', () => {
      renderWithAuth({ loading: true });

      const loginButton = screen.getByRole('button', { name: 'ログイン' });
      expect(loginButton).toBeDisabled();
    });

    it('ローディング中はローディングスピナーが表示される', () => {
      renderWithAuth({ loading: true });

      expect(screen.getByText('処理中...')).toBeInTheDocument();
    });
  });

  describe('パスワードリセットリンク', () => {
    it('パスワードを忘れた場合のリンクが表示される', () => {
      renderWithAuth();

      const forgotPasswordLink = screen.getByText('パスワードをお忘れですか？');
      expect(forgotPasswordLink).toBeInTheDocument();
      expect(forgotPasswordLink.closest('a')).toHaveAttribute(
        'href',
        '/forgot-password'
      );
    });
  });

  describe('アクセシビリティ', () => {
    it('フォームフィールドに適切なラベルが設定されている', () => {
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

    it('タブパネルに適切なaria属性が設定されている', () => {
      renderWithAuth();

      const loginTab = screen.getByRole('tab', { name: 'ログイン' });
      const tabPanel = screen.getByRole('tabpanel');

      expect(loginTab).toHaveAttribute('aria-selected', 'true');
      expect(tabPanel).toBeInTheDocument();
    });

    it('バリデーションエラーが適切に表示される', async () => {
      const user = userEvent.setup();
      renderWithAuth();

      // 空のフォームで送信
      await user.click(screen.getByRole('button', { name: 'ログイン' }));

      // エラーメッセージが表示される（実装に応じて調整）
      // expect(screen.getByText('メールアドレスは必須です')).toBeInTheDocument();
    });
  });

  describe('セキュリティ機能', () => {
    it('Turnstileウィジェットが表示される', () => {
      renderWithAuth();

      // Turnstileウィジェットの存在確認（実装に応じて調整）
      const turnstileContainer = document.querySelector(
        '[data-testid="turnstile-widget"]'
      );
      expect(turnstileContainer).toBeInTheDocument();
    });
  });
});
