import { describe, it, expect, vi, beforeEach } from 'vitest';
import { screen, fireEvent, waitFor } from '@testing-library/react';
import React from 'react';
import { useTranslation } from 'react-i18next';
import { AuthAPI } from '../../../src/api/auth';
import { render as customRender } from '../../utils';

// パスワード変更フォームコンポーネント（AccountSettingsから抽出して作成する必要がある）
// テスト用のシンプルなパスワード変更フォーム
const TestPasswordChangeForm = () => {
  const { t } = useTranslation();
  const [formData, setFormData] = React.useState({
    currentPassword: '',
    newPassword: '',
    confirmPassword: '',
  });
  const [showPassword, setShowPassword] = React.useState({
    current: false,
    new: false,
    confirm: false,
  });
  const [loading, setLoading] = React.useState(false);
  const [message, setMessage] = React.useState('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (formData.newPassword !== formData.confirmPassword) {
      setMessage(t('auth.errors.password_mismatch'));
      return;
    }

    try {
      setLoading(true);
      const response = await AuthAPI.changePassword('test-token', {
        current_password: formData.currentPassword,
        new_password: formData.newPassword,
        new_password_confirmation: formData.confirmPassword,
      });

      if (response.success) {
        setMessage(t('settings.security.password.success'));
      } else {
        // サーバーからのメッセージではなく、フロントエンドの翻訳キーを使用
        if (response.message && response.message.includes('incorrect')) {
          setMessage(t('settings.security.password.current_incorrect'));
        } else if (response.errors?.new_password) {
          setMessage(t('auth.errors.password_min'));
        } else {
          setMessage(t('settings.security.password.error'));
        }
      }
    } catch (error: unknown) {
      // エラーメッセージから適切な翻訳キーを選択
      if (error instanceof Error && error.message.includes('現在のパスワードが正しくありません')) {
        setMessage(t('settings.security.password.current_incorrect'));
      } else {
        setMessage(t('settings.security.password.error'));
      }
    } finally {
      setLoading(false);
    }
  };

  const togglePasswordVisibility = (field: 'current' | 'new' | 'confirm') => {
    setShowPassword(prev => ({ ...prev, [field]: !prev[field] }));
  };

  return (
    <form onSubmit={handleSubmit} data-testid="password-change-form">
      <div>
        <label htmlFor="current-password">{t('settings.security.password.current')}</label>
        <input
          id="current-password"
          type={showPassword.current ? 'text' : 'password'}
          value={formData.currentPassword}
          onChange={(e) => setFormData(prev => ({ ...prev, currentPassword: e.target.value }))}
          required
        />
        <button
          type="button"
          onClick={() => togglePasswordVisibility('current')}
          data-testid="toggle-current-password"
        >
          {showPassword.current ? t('common.hide') : t('common.show')}
        </button>
      </div>

      <div>
        <label htmlFor="new-password">{t('settings.security.password.new')}</label>
        <input
          id="new-password"
          type={showPassword.new ? 'text' : 'password'}
          value={formData.newPassword}
          onChange={(e) => setFormData(prev => ({ ...prev, newPassword: e.target.value }))}
          required
        />
        <button
          type="button"
          onClick={() => togglePasswordVisibility('new')}
          data-testid="toggle-new-password"
        >
          {showPassword.new ? t('common.hide') : t('common.show')}
        </button>
      </div>

      <div>
        <label htmlFor="confirm-password">{t('settings.security.password.confirm')}</label>
        <input
          id="confirm-password"
          type={showPassword.confirm ? 'text' : 'password'}
          value={formData.confirmPassword}
          onChange={(e) => setFormData(prev => ({ ...prev, confirmPassword: e.target.value }))}
          required
        />
        <button
          type="button"
          onClick={() => togglePasswordVisibility('confirm')}
          data-testid="toggle-confirm-password"
        >
          {showPassword.confirm ? t('common.hide') : t('common.show')}
        </button>
      </div>

      <button type="submit" disabled={loading} data-testid="submit-button">
        {loading ? t('settings.security.password.updating') : t('settings.security.password.update')}
      </button>

      {message && <div data-testid="message">{message}</div>}
    </form>
  );
};

// モックの設定
vi.mock('../../../src/api/auth', () => ({
  AuthAPI: {
    changePassword: vi.fn(),
  },
}));

describe('PasswordChangeForm', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('フォームが正しくレンダリングされる', () => {
    customRender(<TestPasswordChangeForm />);

    expect(screen.getByLabelText('現在のパスワード')).toBeInTheDocument();
    expect(screen.getByLabelText('新しいパスワード')).toBeInTheDocument();
    expect(screen.getByLabelText('パスワード確認')).toBeInTheDocument();
    expect(screen.getByTestId('submit-button')).toBeInTheDocument();
  });

  it('パスワード表示切り替えが動作する', async () => {
    customRender(<TestPasswordChangeForm />);

    const currentPasswordInput = screen.getByLabelText('現在のパスワード') as HTMLInputElement;
    const toggleCurrentButton = screen.getByTestId('toggle-current-password');

    // 初期状態ではパスワードタイプ
    expect(currentPasswordInput.type).toBe('password');

    // 表示ボタンをクリック
    fireEvent.click(toggleCurrentButton);
    expect(currentPasswordInput.type).toBe('text');

    // もう一度クリックして非表示に
    fireEvent.click(toggleCurrentButton);
    expect(currentPasswordInput.type).toBe('password');
  });

  it('正常なパスワード変更ができる', async () => {
    const mockChangePassword = vi.mocked(AuthAPI.changePassword);
    mockChangePassword.mockResolvedValue({
      success: true,
      message: 'パスワードが正常に変更されました',
    });

    customRender(<TestPasswordChangeForm />);

    // フォームに入力
    fireEvent.change(screen.getByLabelText('現在のパスワード'), {
      target: { value: 'Password123!' },
    });
    fireEvent.change(screen.getByLabelText('新しいパスワード'), {
      target: { value: 'NewPassword123!' },
    });
    fireEvent.change(screen.getByLabelText('パスワード確認'), {
      target: { value: 'NewPassword123!' },
    });

    // フォーム送信
    fireEvent.click(screen.getByTestId('submit-button'));

    await waitFor(() => {
      expect(mockChangePassword).toHaveBeenCalledWith('test-token', {
        current_password: 'Password123!',
        new_password: 'NewPassword123!',
        new_password_confirmation: 'NewPassword123!',
      });
    });

    await waitFor(() => {
      expect(screen.getByTestId('message')).toHaveTextContent('パスワードが正常に変更されました');
    });
  });

  it('パスワード確認が一致しない場合はエラーメッセージが表示される', async () => {
    customRender(<TestPasswordChangeForm />);

    // フォームに入力（確認パスワードが異なる）
    fireEvent.change(screen.getByLabelText('現在のパスワード'), {
      target: { value: 'Password123!' },
    });
    fireEvent.change(screen.getByLabelText('新しいパスワード'), {
      target: { value: 'NewPassword123!' },
    });
    fireEvent.change(screen.getByLabelText('パスワード確認'), {
      target: { value: 'DifferentPassword123!' },
    });

    // フォーム送信
    fireEvent.click(screen.getByTestId('submit-button'));

    await waitFor(() => {
      expect(screen.getByTestId('message')).toHaveTextContent('パスワードが一致しません');
    });

    // APIが呼び出されていないことを確認
    expect(AuthAPI.changePassword).not.toHaveBeenCalled();
  });

  it('API呼び出し失敗時にエラーメッセージが表示される', async () => {
    const mockChangePassword = vi.mocked(AuthAPI.changePassword);
    mockChangePassword.mockRejectedValue(new Error('現在のパスワードが正しくありません'));

    customRender(<TestPasswordChangeForm />);

    // フォームに入力
    fireEvent.change(screen.getByLabelText('現在のパスワード'), {
      target: { value: 'WrongPassword123!' },
    });
    fireEvent.change(screen.getByLabelText('新しいパスワード'), {
      target: { value: 'NewPassword123!' },
    });
    fireEvent.change(screen.getByLabelText('パスワード確認'), {
      target: { value: 'NewPassword123!' },
    });

    // フォーム送信
    fireEvent.click(screen.getByTestId('submit-button'));

    await waitFor(() => {
      expect(screen.getByTestId('message')).toHaveTextContent('現在のパスワードが正しくありません');
    });
  });

  it('送信中はボタンが無効になる', async () => {
    const mockChangePassword = vi.mocked(AuthAPI.changePassword);
    mockChangePassword.mockImplementation(() => new Promise(resolve => setTimeout(resolve, 100)));

    customRender(<TestPasswordChangeForm />);

    // フォームに入力
    fireEvent.change(screen.getByLabelText('現在のパスワード'), {
      target: { value: 'Password123!' },
    });
    fireEvent.change(screen.getByLabelText('新しいパスワード'), {
      target: { value: 'NewPassword123!' },
    });
    fireEvent.change(screen.getByLabelText('パスワード確認'), {
      target: { value: 'NewPassword123!' },
    });

    const submitButton = screen.getByTestId('submit-button');

    // フォーム送信
    fireEvent.click(submitButton);

    // ローディング中はボタンが無効
    expect(submitButton).toBeDisabled();
    expect(submitButton).toHaveTextContent('変更中...');
  });
});