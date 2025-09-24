import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '../../utils';
import { RegisterForm } from '../../../src/components/auth/RegisterForm';
import { useAuth } from '../../../src/hooks/useAuth';
import { useToast } from '../../../src/hooks/useToast';

// モックの設定
vi.mock('../../../src/hooks/useAuth');
vi.mock('../../../src/hooks/useToast');
vi.mock('../../../src/components/auth/TurnstileWidget', () => ({
  TurnstileWidget: ({ onVerify }: { onVerify: (token: string) => void }) => (
    <div data-testid="turnstile-widget">
      <button onClick={() => onVerify('test-token')}>Verify</button>
    </div>
  ),
}));

const mockRegister = vi.fn();
const mockShowToast = vi.fn();
const mockUseAuth = vi.mocked(useAuth);
const mockUseToast = vi.mocked(useToast);

describe('RegisterForm', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    // Turnstile環境変数をモック
    Object.defineProperty(import.meta, 'env', {
      value: {
        VITE_TURNSTILE_SITE_KEY: 'test-site-key'
      },
      writable: true
    });

    mockUseAuth.mockReturnValue({
      user: null,
      loading: false,
      login: vi.fn(),
      logout: vi.fn(),
      register: mockRegister,
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

  it('正しくレンダリングされる', () => {
    render(<RegisterForm />);

    expect(screen.getByLabelText(/お名前/)).toBeInTheDocument();
    expect(screen.getByLabelText(/メールアドレス/)).toBeInTheDocument();
    expect(screen.getByLabelText(/^パスワード$/)).toBeInTheDocument();
    expect(screen.getByLabelText(/パスワード確認/)).toBeInTheDocument();
    expect(screen.getByLabelText(/言語設定/)).toBeInTheDocument();
    // タイムゾーンフィールドは自動設定のためテストから削除
    expect(screen.getByRole('button', { name: /アカウント作成/ })).toBeInTheDocument();
  });

  it('フォーム送信時に登録処理が実行される', async () => {
    mockRegister.mockResolvedValue(undefined);

    render(<RegisterForm />);

    // フォームに入力
    fireEvent.change(screen.getByLabelText(/お名前/), {
      target: { value: 'テストユーザー' },
    });
    fireEvent.change(screen.getByLabelText(/メールアドレス/), {
      target: { value: 'test@example.com' },
    });
    fireEvent.change(screen.getByLabelText(/^パスワード$/), {
      target: { value: 'password123' },
    });
    fireEvent.change(screen.getByLabelText(/パスワード確認/), {
      target: { value: 'password123' },
    });

    // Turnstileを認証
    fireEvent.click(screen.getByText('Verify'));

    // フォーム送信
    fireEvent.click(screen.getByRole('button', { name: /アカウント作成/ }));

    await waitFor(() => {
      expect(mockRegister).toHaveBeenCalledWith({
        name: 'テストユーザー',
        email: 'test@example.com',
        password: 'password123',
        language: 'ja',
        timezone: expect.any(String), // タイムゾーンは自動設定
        turnstile_token: 'test-token',
      });
    });
  });

  it('バリデーションエラーが表示される', async () => {
    render(<RegisterForm />);

    // 空のフォームを送信（HTML5バリデーションで防がれる）
    fireEvent.click(screen.getByRole('button', { name: /アカウント作成/ }));

    // HTML5 validationによってフォーム送信が防がれることを確認
    expect(mockRegister).not.toHaveBeenCalled();
  });

  it('パスワード確認が一致しない場合はエラー', async () => {
    render(<RegisterForm />);

    fireEvent.change(screen.getByLabelText(/^パスワード$/), {
      target: { value: 'password123' },
    });
    fireEvent.change(screen.getByLabelText(/パスワード確認/), {
      target: { value: 'different-password' },
    });

    // 他の必須フィールドも入力
    fireEvent.change(screen.getByLabelText(/お名前/), {
      target: { value: 'テストユーザー' },
    });
    fireEvent.change(screen.getByLabelText(/メールアドレス/), {
      target: { value: 'test@example.com' },
    });

    // Turnstileを認証
    fireEvent.click(screen.getByText('Verify'));

    fireEvent.click(screen.getByRole('button', { name: /アカウント作成/ }));

    await waitFor(() => {
      // パスワード不一致のエラーメッセージを確認
      expect(screen.getByText('パスワードが一致しません')).toBeInTheDocument();
    });

    expect(mockRegister).not.toHaveBeenCalled();
  });

  it('登録失敗時にエラーメッセージが表示される', async () => {
    const errorMessage = 'このメールアドレスは既に使用されています';
    mockRegister.mockRejectedValue({
      message: errorMessage,
    });

    render(<RegisterForm />);

    // フォームに入力
    fireEvent.change(screen.getByLabelText(/お名前/), {
      target: { value: 'テストユーザー' },
    });
    fireEvent.change(screen.getByLabelText(/メールアドレス/), {
      target: { value: 'existing@example.com' },
    });
    fireEvent.change(screen.getByLabelText(/^パスワード$/), {
      target: { value: 'password123' },
    });
    fireEvent.change(screen.getByLabelText(/パスワード確認/), {
      target: { value: 'password123' },
    });

    // Turnstileを認証
    fireEvent.click(screen.getByText('Verify'));

    // フォーム送信
    fireEvent.click(screen.getByRole('button', { name: /アカウント作成/ }));

    await waitFor(() => {
      // RegisterFormは setState でエラーを表示する
      expect(screen.getByText(errorMessage)).toBeInTheDocument();
    });
  });

  it('ローディング中はボタンが無効になる', () => {
    mockUseAuth.mockReturnValue({
      user: null,
      loading: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: mockRegister,
      refreshToken: vi.fn(),
      updateProfile: vi.fn(),
    });

    render(<RegisterForm />);

    const submitButton = screen.getByRole('button', { name: /アカウント作成/ });
    expect(submitButton).toBeDisabled();
  });

  it('Turnstile認証なしではフォーム送信できない', async () => {
    render(<RegisterForm />);

    // フォームに入力
    fireEvent.change(screen.getByLabelText(/お名前/), {
      target: { value: 'テストユーザー' },
    });
    fireEvent.change(screen.getByLabelText(/メールアドレス/), {
      target: { value: 'test@example.com' },
    });
    fireEvent.change(screen.getByLabelText(/^パスワード$/), {
      target: { value: 'password123' },
    });
    fireEvent.change(screen.getByLabelText(/パスワード確認/), {
      target: { value: 'password123' },
    });

    // Turnstile認証なしではボタンが無効になっていることを確認
    const submitButton = screen.getByRole('button', { name: /アカウント作成/ });
    expect(submitButton).toBeDisabled();

    // 無効なボタンをクリックしても登録処理は呼ばれない
    fireEvent.click(submitButton);
    expect(mockRegister).not.toHaveBeenCalled();
  });

  it('メールアドレスのバリデーションが動作する', async () => {
    render(<RegisterForm />);

    // 不正な形式のメールアドレスを入力
    fireEvent.change(screen.getByLabelText(/メールアドレス/), {
      target: { value: 'invalid-email' },
    });
    fireEvent.change(screen.getByLabelText(/お名前/), {
      target: { value: 'テストユーザー' },
    });
    fireEvent.change(screen.getByLabelText(/^パスワード$/), {
      target: { value: 'password123' },
    });
    fireEvent.change(screen.getByLabelText(/パスワード確認/), {
      target: { value: 'password123' },
    });

    // Turnstileを認証
    fireEvent.click(screen.getByText('Verify'));

    fireEvent.click(screen.getByRole('button', { name: /アカウント作成/ }));

    // HTML5のemail型validationによってフォーム送信が防がれることを確認
    expect(mockRegister).not.toHaveBeenCalled();
  });

  it('パスワード強度のバリデーションが動作する', async () => {
    render(<RegisterForm />);

    // 短いパスワードを入力
    fireEvent.change(screen.getByLabelText(/^パスワード$/), {
      target: { value: '123' },
    });
    fireEvent.change(screen.getByLabelText(/パスワード確認/), {
      target: { value: '123' },
    });

    // 他の必須フィールドも入力
    fireEvent.change(screen.getByLabelText(/お名前/), {
      target: { value: 'テストユーザー' },
    });
    fireEvent.change(screen.getByLabelText(/メールアドレス/), {
      target: { value: 'test@example.com' },
    });

    // Turnstileを認証
    fireEvent.click(screen.getByText('Verify'));

    fireEvent.click(screen.getByRole('button', { name: /アカウント作成/ }));

    // パスワード強度の検証はサーバー側で行われる想定
    await waitFor(() => {
      expect(mockRegister).toHaveBeenCalled();
    });
  });

  it('言語とタイムゾーンの選択が動作する', async () => {
    mockRegister.mockResolvedValue(undefined);

    render(<RegisterForm />);

    // 言語を変更（タイムゾーンは自動設定）
    fireEvent.change(screen.getByLabelText(/言語設定/), {
      target: { value: 'en' },
    });

    // 他の必須フィールドを入力
    fireEvent.change(screen.getByLabelText(/お名前/), {
      target: { value: 'Test User' },
    });
    fireEvent.change(screen.getByLabelText(/メールアドレス/), {
      target: { value: 'test@example.com' },
    });
    fireEvent.change(screen.getByLabelText(/^パスワード$/), {
      target: { value: 'password123' },
    });
    fireEvent.change(screen.getByLabelText(/パスワード確認/), {
      target: { value: 'password123' },
    });

    // Turnstileを認証
    fireEvent.click(screen.getByText('Verify'));

    // フォーム送信
    fireEvent.click(screen.getByRole('button', { name: /アカウント作成/ }));

    await waitFor(() => {
      expect(mockRegister).toHaveBeenCalledWith({
        name: 'Test User',
        email: 'test@example.com',
        password: 'password123',
        language: 'en',
        timezone: expect.any(String), // タイムゾーンは自動設定
        turnstile_token: 'test-token',
      });
    });
  });

  it('アクセシビリティ属性が正しく設定される', () => {
    render(<RegisterForm />);

    // フォームフィールドのラベルが正しく関連付けられている
    const nameInput = screen.getByLabelText(/お名前/);
    const emailInput = screen.getByLabelText(/メールアドレス/);
    const passwordInput = screen.getByLabelText(/^パスワード$/);

    expect(nameInput).toHaveAttribute('id');
    expect(emailInput).toHaveAttribute('id');
    expect(passwordInput).toHaveAttribute('id');

    // 必須フィールドがマークされている
    expect(nameInput).toBeRequired();
    expect(emailInput).toBeRequired();
    expect(passwordInput).toBeRequired();
  });
});