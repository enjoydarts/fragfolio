import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '../../utils';
import { LoginForm } from '../../../src/components/auth/LoginForm';
import { useAuth } from '../../../src/hooks/useAuth';
import { useToast } from '../../../src/hooks/useToast';

// モックの設定
vi.mock('../../../src/hooks/useAuth');
vi.mock('../../../src/hooks/useToast');
// グローバル変数でTurnstileの動作を制御
let skipAutoVerify = false;

vi.mock('../../../src/components/auth/TurnstileWidget', () => ({
  TurnstileWidget: ({ onVerify }: { onVerify: (token: string) => void }) => {
    React.useEffect(() => {
      // skipAutoVerifyがfalseの場合のみ自動的にトークンを検証
      if (!skipAutoVerify) {
        onVerify('test-token');
      }
    }, [onVerify]);

    return (
      <div data-testid="turnstile-widget">
        <button onClick={() => onVerify('test-token')}>Verify</button>
      </div>
    );
  },
}));

const mockLogin = vi.fn();
const mockShowToast = vi.fn();
const mockUseAuth = vi.mocked(useAuth);
const mockUseToast = vi.mocked(useToast);

describe('LoginForm', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    // グローバル変数をリセット
    skipAutoVerify = false;

    // Turnstile環境変数をVitest stubでモック（CI環境対応）
    vi.unstubAllEnvs();
    vi.stubEnv('VITE_TURNSTILE_SITE_KEY', 'test-site-key');

    mockUseAuth.mockReturnValue({
      user: null,
      loading: false,
      login: mockLogin,
      logout: vi.fn(),
      register: vi.fn(),
      refreshToken: vi.fn(),
      updateProfile: vi.fn(),
    });

    mockUseToast.mockReturnValue({
      toasts: [],
      toast: {
        success: mockShowToast,
        error: mockShowToast,
        info: mockShowToast,
      },
      removeToast: vi.fn(),
    });
  });

  afterAll(() => {
    vi.unstubAllEnvs();
  });

  it('正しくレンダリングされる', () => {
    render(<LoginForm />);

    expect(screen.getByLabelText(/メールアドレス/)).toBeInTheDocument();
    expect(screen.getByLabelText(/パスワード/)).toBeInTheDocument();
    expect(screen.getByLabelText(/ログイン状態を保持/)).toBeInTheDocument();
    expect(
      screen.getByRole('button', { name: /ログイン/ })
    ).toBeInTheDocument();
  });

  it('フォーム送信時にログイン処理が実行される', async () => {
    mockLogin.mockResolvedValue({ success: true });

    render(<LoginForm />);

    // フォームに入力
    fireEvent.change(screen.getByLabelText(/メールアドレス/), {
      target: { value: 'test@example.com' },
    });
    fireEvent.change(screen.getByLabelText(/パスワード/), {
      target: { value: 'password123' },
    });

    // Turnstileが表示されているか確認してから認証
    expect(screen.getByTestId('turnstile-widget')).toBeInTheDocument();
    fireEvent.click(screen.getByText('Verify'));

    // フォーム送信
    fireEvent.click(screen.getByRole('button', { name: /ログイン/ }));

    await waitFor(() => {
      expect(mockLogin).toHaveBeenCalledWith(
        'test@example.com',
        'password123',
        false,
        'test-token'
      );
    });
  });

  it('記憶する機能が正しく動作する', async () => {
    mockLogin.mockResolvedValue({ success: true });

    render(<LoginForm />);

    // フォームに入力
    fireEvent.change(screen.getByLabelText(/メールアドレス/), {
      target: { value: 'test@example.com' },
    });
    fireEvent.change(screen.getByLabelText(/パスワード/), {
      target: { value: 'password123' },
    });

    // 記憶するをチェック
    fireEvent.click(screen.getByLabelText(/ログイン状態を保持/));

    // Turnstileが表示されているか確認してから認証
    expect(screen.getByTestId('turnstile-widget')).toBeInTheDocument();
    fireEvent.click(screen.getByText('Verify'));

    // フォーム送信
    fireEvent.click(screen.getByRole('button', { name: /ログイン/ }));

    await waitFor(() => {
      expect(mockLogin).toHaveBeenCalledWith(
        'test@example.com',
        'password123',
        true,
        'test-token'
      );
    });
  });

  it('バリデーションエラーが表示される', async () => {
    render(<LoginForm />);

    // 空のフォームを送信（HTML5バリデーションで防がれる）
    fireEvent.click(screen.getByRole('button', { name: /ログイン/ }));

    // HTML5 validationによってフォーム送信が防がれることを確認
    expect(mockLogin).not.toHaveBeenCalled();
  });

  it('ログイン失敗時にエラーメッセージが表示される', async () => {
    const errorMessage = 'メールアドレスまたはパスワードが正しくありません';
    mockLogin.mockRejectedValue({
      message: errorMessage,
    });

    render(<LoginForm />);

    // フォームに入力
    fireEvent.change(screen.getByLabelText(/メールアドレス/), {
      target: { value: 'test@example.com' },
    });
    fireEvent.change(screen.getByLabelText(/パスワード/), {
      target: { value: 'wrong-password' },
    });

    // Turnstileが表示されているか確認してから認証
    expect(screen.getByTestId('turnstile-widget')).toBeInTheDocument();
    fireEvent.click(screen.getByText('Verify'));

    // フォーム送信
    fireEvent.click(screen.getByRole('button', { name: /ログイン/ }));

    await waitFor(() => {
      // LoginForm handles errors through setState, not toast
      expect(screen.getByText(errorMessage)).toBeInTheDocument();
    });
  });

  it('2段階認証が必要な場合の処理', async () => {
    // mockLoginが2FA必要レスポンスを返すようにするのではなく、
    // login関数が例外を投げて、その例外にrequires_two_factorフラグが含まれるようにする
    mockLogin.mockRejectedValue({
      requires_two_factor: true,
      temp_token: 'temp-token-123',
      available_methods: ['totp', 'webauthn'],
    });

    render(<LoginForm />);

    // フォームに入力
    fireEvent.change(screen.getByLabelText(/メールアドレス/), {
      target: { value: 'test@example.com' },
    });
    fireEvent.change(screen.getByLabelText(/パスワード/), {
      target: { value: 'password123' },
    });

    // Turnstileが表示されているか確認してから認証
    expect(screen.getByTestId('turnstile-widget')).toBeInTheDocument();
    fireEvent.click(screen.getByText('Verify'));

    // フォーム送信
    fireEvent.click(screen.getByRole('button', { name: /ログイン/ }));

    await waitFor(() => {
      // 2FA選択画面の表示を確認
      expect(screen.getByText(/認証方法を選択/)).toBeInTheDocument();
    });
  });

  it('ローディング中はボタンが無効になる', () => {
    mockUseAuth.mockReturnValue({
      user: null,
      loading: true,
      login: mockLogin,
      logout: vi.fn(),
      register: vi.fn(),
      refreshToken: vi.fn(),
      updateProfile: vi.fn(),
    });

    render(<LoginForm />);

    const submitButton = screen.getByRole('button', { name: /ログイン/ });
    expect(submitButton).toBeDisabled();
  });

  it('Turnstile認証なしではフォーム送信できない', async () => {
    // この特定のテストでは自動検証をスキップ
    skipAutoVerify = true;

    render(<LoginForm />);

    // フォームに入力
    fireEvent.change(screen.getByLabelText(/メールアドレス/), {
      target: { value: 'test@example.com' },
    });
    fireEvent.change(screen.getByLabelText(/パスワード/), {
      target: { value: 'password123' },
    });

    // TurnstileWidgetが表示されていることを確認
    const turnstileWidget = screen.getByTestId('turnstile-widget');
    expect(turnstileWidget).toBeInTheDocument();

    // Verifyボタンが表示されていることを確認
    const verifyButton = screen.getByText('Verify');
    expect(verifyButton).toBeInTheDocument();

    // ボタンが無効化されていることを確認（Turnstileトークンなしのため）
    const submitButton = screen.getByRole('button', { name: /ログイン/ });
    expect(submitButton).toBeDisabled();

    // フォームの直接送信をトリガー（ボタンではなくフォーム要素で）
    const form = submitButton.closest('form');
    expect(form).toBeInTheDocument();

    // フォームのsubmitイベントを直接発火
    fireEvent.submit(form!);

    await waitFor(() => {
      // Turnstile必須エラーメッセージを確認
      expect(screen.getByText(/Turnstile認証が必要です/)).toBeInTheDocument();
    });

    // ログイン処理は呼ばれない
    expect(mockLogin).not.toHaveBeenCalled();

    // テスト後にリセット
    skipAutoVerify = false;
  });

  it('メールアドレスのバリデーションが動作する', async () => {
    render(<LoginForm />);

    // 不正な形式のメールアドレスを入力
    fireEvent.change(screen.getByLabelText(/メールアドレス/), {
      target: { value: 'invalid-email' },
    });
    fireEvent.change(screen.getByLabelText(/パスワード/), {
      target: { value: 'password123' },
    });

    // Turnstileが表示されているか確認してから認証
    expect(screen.getByTestId('turnstile-widget')).toBeInTheDocument();
    fireEvent.click(screen.getByText('Verify'));

    fireEvent.click(screen.getByRole('button', { name: /ログイン/ }));

    // HTML5のemail型validationによってフォーム送信が防がれることを確認
    expect(mockLogin).not.toHaveBeenCalled();
  });
});
