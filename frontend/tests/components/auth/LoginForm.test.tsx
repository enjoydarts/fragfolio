import { describe, it, expect, beforeEach, vi } from 'vitest';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { render } from '../../src/test/utils';
import { LoginForm } from '../../../src/components/auth/LoginForm';

// プロップスのモック
const mockProps = {
  onSubmit: vi.fn(),
  loading: false,
  error: null as string | null,
};

describe('LoginForm', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
  });

  describe('基本表示', () => {
    it('必要なフォームフィールドが表示される', () => {
      render(<LoginForm {...mockProps} />);

      expect(screen.getByLabelText('メールアドレス')).toBeInTheDocument();
      expect(screen.getByLabelText('パスワード')).toBeInTheDocument();
      expect(
        screen.getByRole('button', { name: 'ログイン' })
      ).toBeInTheDocument();
    });

    it('適切な入力タイプが設定されている', () => {
      render(<LoginForm {...mockProps} />);

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
      render(<LoginForm {...mockProps} />);

      expect(
        screen.getByLabelText('ログイン状態を記憶する')
      ).toBeInTheDocument();
      expect(screen.getByLabelText('ログイン状態を記憶する')).toHaveAttribute(
        'type',
        'checkbox'
      );
    });

    it('パスワードを忘れた場合のリンクが表示される', () => {
      render(<LoginForm {...mockProps} />);

      const forgotPasswordLink = screen.getByText('パスワードをお忘れですか？');
      expect(forgotPasswordLink).toBeInTheDocument();
      expect(forgotPasswordLink.closest('a')).toHaveAttribute(
        'href',
        '/forgot-password'
      );
    });
  });

  describe('フォーム送信', () => {
    it('有効な入力でフォーム送信される', async () => {
      const user = userEvent.setup();
      const mockOnSubmit = vi.fn();
      render(<LoginForm {...mockProps} onSubmit={mockOnSubmit} />);

      await user.type(
        screen.getByLabelText('メールアドレス'),
        'test@example.com'
      );
      await user.type(screen.getByLabelText('パスワード'), 'password123');
      await user.click(screen.getByRole('button', { name: 'ログイン' }));

      await waitFor(() => {
        expect(mockOnSubmit).toHaveBeenCalledWith({
          email: 'test@example.com',
          password: 'password123',
          remember: false,
        });
      });
    });

    it('記憶するオプションがチェックされた場合にrememberがtrueになる', async () => {
      const user = userEvent.setup();
      const mockOnSubmit = vi.fn();
      render(<LoginForm {...mockProps} onSubmit={mockOnSubmit} />);

      await user.type(
        screen.getByLabelText('メールアドレス'),
        'test@example.com'
      );
      await user.type(screen.getByLabelText('パスワード'), 'password123');
      await user.click(screen.getByLabelText('ログイン状態を記憶する'));
      await user.click(screen.getByRole('button', { name: 'ログイン' }));

      await waitFor(() => {
        expect(mockOnSubmit).toHaveBeenCalledWith({
          email: 'test@example.com',
          password: 'password123',
          remember: true,
        });
      });
    });

    it('空のフォームでは送信されない', async () => {
      const user = userEvent.setup();
      const mockOnSubmit = vi.fn();
      render(<LoginForm {...mockProps} onSubmit={mockOnSubmit} />);

      await user.click(screen.getByRole('button', { name: 'ログイン' }));

      // フォームバリデーションにより送信されない
      expect(mockOnSubmit).not.toHaveBeenCalled();
    });

    it('無効なメールアドレスでは送信されない', async () => {
      const user = userEvent.setup();
      const mockOnSubmit = vi.fn();
      render(<LoginForm {...mockProps} onSubmit={mockOnSubmit} />);

      await user.type(screen.getByLabelText('メールアドレス'), 'invalid-email');
      await user.type(screen.getByLabelText('パスワード'), 'password123');
      await user.click(screen.getByRole('button', { name: 'ログイン' }));

      // HTMLバリデーションにより送信されない
      expect(mockOnSubmit).not.toHaveBeenCalled();
    });
  });

  describe('ローディング状態', () => {
    it('ローディング中はボタンが無効になる', () => {
      render(<LoginForm {...mockProps} loading={true} />);

      const submitButton = screen.getByRole('button', { name: 'ログイン' });
      expect(submitButton).toBeDisabled();
    });

    it('ローディング中は入力フィールドが無効になる', () => {
      render(<LoginForm {...mockProps} loading={true} />);

      expect(screen.getByLabelText('メールアドレス')).toBeDisabled();
      expect(screen.getByLabelText('パスワード')).toBeDisabled();
      expect(screen.getByLabelText('ログイン状態を記憶する')).toBeDisabled();
    });

    it('ローディング中はローディング表示される', () => {
      render(<LoginForm {...mockProps} loading={true} />);

      expect(screen.getByText('ログイン中...')).toBeInTheDocument();
    });
  });

  describe('エラー表示', () => {
    it('エラーメッセージが表示される', () => {
      const errorMessage = 'メールアドレスまたはパスワードが正しくありません';
      render(<LoginForm {...mockProps} error={errorMessage} />);

      expect(screen.getByText(errorMessage)).toBeInTheDocument();
      expect(screen.getByText(errorMessage)).toHaveClass('text-red-600');
    });

    it('エラーがない場合はエラーメッセージが表示されない', () => {
      render(<LoginForm {...mockProps} error={null} />);

      expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    });
  });

  describe('フォームバリデーション', () => {
    it('必須フィールドが適切に設定されている', () => {
      render(<LoginForm {...mockProps} />);

      expect(screen.getByLabelText('メールアドレス')).toBeRequired();
      expect(screen.getByLabelText('パスワード')).toBeRequired();
    });

    it('メールアドレスフィールドでバリデーションエラーが表示される', async () => {
      const user = userEvent.setup();
      render(<LoginForm {...mockProps} />);

      const emailInput = screen.getByLabelText('メールアドレス');

      await user.type(emailInput, 'invalid-email');
      await user.tab(); // フォーカスを外す

      // ブラウザの標準バリデーションが動作する
      expect(emailInput).toBeInvalid();
    });
  });

  describe('アクセシビリティ', () => {
    it('適切なラベルが設定されている', () => {
      render(<LoginForm {...mockProps} />);

      expect(screen.getByLabelText('メールアドレス')).toBeInTheDocument();
      expect(screen.getByLabelText('パスワード')).toBeInTheDocument();
      expect(
        screen.getByLabelText('ログイン状態を記憶する')
      ).toBeInTheDocument();
    });

    it('フォームがキーボードで操作可能', async () => {
      const user = userEvent.setup();
      render(<LoginForm {...mockProps} />);

      const emailInput = screen.getByLabelText('メールアドレス');
      const passwordInput = screen.getByLabelText('パスワード');
      const rememberCheckbox = screen.getByLabelText('ログイン状態を記憶する');
      const submitButton = screen.getByRole('button', { name: 'ログイン' });

      // タブでフォーカス移動
      await user.tab();
      expect(emailInput).toHaveFocus();

      await user.tab();
      expect(passwordInput).toHaveFocus();

      await user.tab();
      expect(rememberCheckbox).toHaveFocus();

      await user.tab();
      expect(submitButton).toHaveFocus();
    });

    it('エラーメッセージがスクリーンリーダーで読み上げられる', () => {
      const errorMessage = 'ログインに失敗しました';
      render(<LoginForm {...mockProps} error={errorMessage} />);

      const errorElement = screen.getByText(errorMessage);
      expect(errorElement).toHaveAttribute('role', 'alert');
    });
  });

  describe('Turnstile統合', () => {
    it('Turnstileウィジェットが表示される', () => {
      render(<LoginForm {...mockProps} />);

      // Turnstileウィジェットの存在確認
      const turnstileContainer = document.querySelector(
        '[data-testid="turnstile-widget"]'
      );
      expect(turnstileContainer).toBeInTheDocument();
    });
  });
});
