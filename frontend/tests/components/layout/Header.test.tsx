import { describe, it, expect, beforeEach, vi } from 'vitest';
import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { render } from '../../utils';
import { Header } from '../../../src/components/layout/Header';
import { AuthContext } from '../../../src/contexts/context';
import type { User } from '../../../src/types';

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
      <Header />
    </AuthContext.Provider>
  );
};

describe('Header', () => {
  beforeEach(() => {
    localStorage.clear();
    vi.clearAllMocks();
  });

  describe('未認証状態', () => {
    it('アプリのロゴが表示される', () => {
      renderWithAuth();
      expect(screen.getByAltText('fragfolio')).toBeInTheDocument();
    });

    it('ログインリンクが表示される', () => {
      renderWithAuth();
      expect(screen.getByRole('link', { name: 'ログイン' })).toBeInTheDocument();
      expect(screen.getByRole('link', { name: 'ログイン' })).toHaveAttribute(
        'href',
        '/auth'
      );
    });

    it('言語切り替えボタンが表示される', () => {
      renderWithAuth();
      expect(screen.getByText('🇺🇸 English')).toBeInTheDocument();
    });

    it('アプリロゴが表示される', () => {
      renderWithAuth();
      const logo = screen.getByAltText('fragfolio');
      expect(logo).toBeInTheDocument();
      expect(logo).toHaveClass('h-10');
    });
  });

  describe('認証済み状態', () => {
    const mockUser: User = {
      id: 1,
      name: 'テストユーザー',
      email: 'test@example.com',
      email_verified_at: '2023-01-01T00:00:00.000Z',
      created_at: '2023-01-01T00:00:00.000Z',
      updated_at: '2023-01-01T00:00:00.000Z',
    };

    it('ユーザー名が表示される', () => {
      renderWithAuth({ user: mockUser });
      expect(screen.getByText('テストユーザー')).toBeInTheDocument();
    });

    it('ユーザーのイニシャルが表示される', () => {
      renderWithAuth({ user: mockUser });
      expect(screen.getByText('テ')).toBeInTheDocument();
    });

    it('ユーザーメニューボタンが表示される', async () => {
      const user = userEvent.setup();
      renderWithAuth({ user: mockUser });

      // ユーザーメニューボタン（ユーザー名を含むもの）をクリック
      const userButton = screen.getByText('テストユーザー').closest('button');
      expect(userButton).toBeInTheDocument();

      await user.click(userButton!);

      // ドロップダウンメニューが開かれることを確認
      expect(screen.getByText(mockUser.email)).toBeInTheDocument();
    });

    it('ログインリンクが表示されない', () => {
      renderWithAuth({ user: mockUser });
      expect(screen.queryByRole('link', { name: 'ログイン' })).not.toBeInTheDocument();
    });

    it('ユーザーメニューから設定にアクセスできる', async () => {
      const user = userEvent.setup();
      renderWithAuth({ user: mockUser });

      // ユーザーメニューボタン（ユーザー名を含むもの）をクリック
      const userButton = screen.getByText('テストユーザー').closest('button');
      await user.click(userButton!);

      // 設定リンクが表示される
      expect(screen.getByRole('link', { name: /設定/ })).toBeInTheDocument();
    });

    it('ローディング中の状態が正しく表示される', () => {
      renderWithAuth({ user: mockUser, loading: true });

      // ユーザー情報は表示される
      expect(screen.getByText('テストユーザー')).toBeInTheDocument();
    });
  });

  describe('言語切り替え', () => {
    it('英語切り替えボタンをクリックで英語に変更される', async () => {
      const user = userEvent.setup();
      renderWithAuth();

      // 初期状態は日本語
      expect(screen.getByText('🇺🇸 English')).toBeInTheDocument();
      expect(screen.getByRole('link', { name: 'ログイン' })).toBeInTheDocument();

      // 英語に切り替え
      await user.click(screen.getByText('🇺🇸 English'));

      // 英語表示に変更される
      expect(screen.getByText('🇯🇵 日本語')).toBeInTheDocument();
      expect(screen.getByRole('link', { name: 'Login' })).toBeInTheDocument();
    });

    it('日本語切り替えボタンをクリックで日本語に戻る', async () => {
      const user = userEvent.setup();
      renderWithAuth();

      // 英語に切り替え
      await user.click(screen.getByText('🇺🇸 English'));
      expect(screen.getByRole('link', { name: 'Login' })).toBeInTheDocument();

      // 日本語に戻す
      await user.click(screen.getByText('🇯🇵 日本語'));
      expect(screen.getByRole('link', { name: 'ログイン' })).toBeInTheDocument();
    });
  });

  describe('レスポンシブデザイン', () => {
    it('適切なCSSクラスが適用されている', () => {
      renderWithAuth();

      const header = screen.getByAltText('fragfolio').closest('header');
      expect(header).toHaveClass('header-nav', 'sticky', 'top-0', 'z-50');

      const container = header?.querySelector('.max-w-7xl');
      expect(container).toHaveClass('max-w-7xl', 'mx-auto', 'px-4');
    });

    it('フレックスレイアウトが適用されている', () => {
      renderWithAuth();

      const header = screen.getByAltText('fragfolio').closest('header');
      const flexContainer = header?.querySelector('.flex.justify-between');
      expect(flexContainer).toHaveClass('flex', 'justify-between', 'h-16');
    });
  });

  describe('アクセシビリティ', () => {
    it('ボタンがキーボードでアクセス可能', () => {
      renderWithAuth();

      const languageButton = screen.getByText('🇺🇸 English');
      expect(languageButton).toBeInTheDocument();
      expect(languageButton.tagName).toBe('BUTTON');
    });

    it('適切なセマンティクスが使用されている', () => {
      renderWithAuth();

      expect(screen.getByRole('banner')).toBeInTheDocument(); // header要素
      expect(screen.getByAltText('fragfolio')).toBeInTheDocument(); // logo要素
    });
  });
});
