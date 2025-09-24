import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils';
import { ProtectedRoute } from '../../../src/components/auth/ProtectedRoute';
import { useAuth } from '../../../src/contexts/AuthContext';

// モックの設定
vi.mock('../../../src/contexts/AuthContext');

const mockUseAuth = vi.mocked(useAuth);

// テスト用コンポーネント
const TestComponent = () => <div>Protected Content</div>;

describe.skip('ProtectedRoute', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('認証済みユーザーは保護されたコンテンツにアクセスできる', () => {
    mockUseAuth.mockReturnValue({
      user: {
        id: 1,
        name: 'Test User',
        email: 'test@example.com',
        email_verified_at: '2024-01-01T00:00:00Z',
        profile: { language: 'ja', timezone: 'Asia/Tokyo' },
        roles: ['user'],
      },
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      refreshToken: vi.fn(),
      updateProfile: vi.fn(),
    });

    render(
      <ProtectedRoute>
        <TestComponent />
      </ProtectedRoute>
    );

    expect(screen.getByText('Protected Content')).toBeInTheDocument();
  });

  it('未認証ユーザーはログインページにリダイレクトされる', () => {
    mockUseAuth.mockReturnValue({
      user: null,
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      refreshToken: vi.fn(),
      updateProfile: vi.fn(),
    });

    render(
      <ProtectedRoute>
        <TestComponent />
      </ProtectedRoute>
    );

    expect(screen.queryByText('Protected Content')).not.toBeInTheDocument();
    // Navigate to loginが呼ばれることを確認（実装に依存）
  });

  it('ローディング中はローディング表示される', () => {
    mockUseAuth.mockReturnValue({
      user: null,
      isLoading: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      refreshToken: vi.fn(),
      updateProfile: vi.fn(),
    });

    render(
      <ProtectedRoute>
        <TestComponent />
      </ProtectedRoute>
    );

    expect(screen.getByText('読み込み中...')).toBeInTheDocument();
    expect(screen.queryByText('Protected Content')).not.toBeInTheDocument();
  });

  it('メール未確認ユーザーで確認が必要な場合はアクセス拒否', () => {
    mockUseAuth.mockReturnValue({
      user: {
        id: 1,
        name: 'Test User',
        email: 'test@example.com',
        email_verified_at: null, // メール未確認
        profile: { language: 'ja', timezone: 'Asia/Tokyo' },
        roles: ['user'],
      },
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      refreshToken: vi.fn(),
      updateProfile: vi.fn(),
    });

    render(
      <ProtectedRoute requiresEmailVerification>
        <TestComponent />
      </ProtectedRoute>
    );

    expect(screen.getByText('メールアドレスの確認が必要です')).toBeInTheDocument();
    expect(screen.queryByText('Protected Content')).not.toBeInTheDocument();
  });

  it('メール確認済みユーザーは確認が必要なルートにアクセスできる', () => {
    mockUseAuth.mockReturnValue({
      user: {
        id: 1,
        name: 'Test User',
        email: 'test@example.com',
        email_verified_at: '2024-01-01T00:00:00Z', // メール確認済み
        profile: { language: 'ja', timezone: 'Asia/Tokyo' },
        roles: ['user'],
      },
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      refreshToken: vi.fn(),
      updateProfile: vi.fn(),
    });

    render(
      <ProtectedRoute requiresEmailVerification>
        <TestComponent />
      </ProtectedRoute>
    );

    expect(screen.getByText('Protected Content')).toBeInTheDocument();
  });

  it('管理者権限が必要なルートで権限チェックが動作する', () => {
    mockUseAuth.mockReturnValue({
      user: {
        id: 1,
        name: 'Test User',
        email: 'test@example.com',
        email_verified_at: '2024-01-01T00:00:00Z',
        profile: { language: 'ja', timezone: 'Asia/Tokyo' },
        roles: ['user'], // 管理者権限なし
      },
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      refreshToken: vi.fn(),
      updateProfile: vi.fn(),
    });

    render(
      <ProtectedRoute requiredRoles={['admin']}>
        <TestComponent />
      </ProtectedRoute>
    );

    expect(screen.getByText('アクセス権限がありません')).toBeInTheDocument();
    expect(screen.queryByText('Protected Content')).not.toBeInTheDocument();
  });

  it('管理者ユーザーは管理者権限が必要なルートにアクセスできる', () => {
    mockUseAuth.mockReturnValue({
      user: {
        id: 1,
        name: 'Admin User',
        email: 'admin@example.com',
        email_verified_at: '2024-01-01T00:00:00Z',
        profile: { language: 'ja', timezone: 'Asia/Tokyo' },
        roles: ['user', 'admin'], // 管理者権限あり
      },
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      refreshToken: vi.fn(),
      updateProfile: vi.fn(),
    });

    render(
      <ProtectedRoute requiredRoles={['admin']}>
        <TestComponent />
      </ProtectedRoute>
    );

    expect(screen.getByText('Protected Content')).toBeInTheDocument();
  });
});