import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '../../utils';
import { WebAuthnSettings } from '../../../src/components/security/WebAuthnSettings';
import * as webauthnApi from '../../../src/api/webauthn';
import { useToast } from '../../../src/hooks/useToast';
import { useConfirm } from '../../../src/hooks/useConfirm';

// モックの設定
vi.mock('../../../src/api/webauthn');
vi.mock('../../../src/hooks/useToast');
vi.mock('../../../src/hooks/useConfirm');

const mockShowToast = vi.fn();
const mockConfirm = vi.fn();
const mockUseToast = vi.mocked(useToast);
const mockUseConfirm = vi.mocked(useConfirm);

const mockCredentials = [
  {
    id: 'cred1',
    alias: 'iPhone Touch ID',
    created_at: '2024-01-01T00:00:00Z',
    disabled_at: null,
  },
  {
    id: 'cred2',
    alias: 'Security Key',
    created_at: '2024-01-02T00:00:00Z',
    disabled_at: null,
  },
];

describe.skip('WebAuthnSettings', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    mockUseToast.mockReturnValue({
      showToast: mockShowToast,
    });

    mockUseConfirm.mockReturnValue({
      confirm: mockConfirm,
    });

    // デフォルトでクレデンシャル一覧を返す
    vi.mocked(webauthnApi.getCredentials).mockResolvedValue({
      success: true,
      credentials: mockCredentials,
    });
  });

  it('WebAuthn設定が正しくレンダリングされる', async () => {
    render(<WebAuthnSettings />);

    expect(screen.getByText('WebAuthn / FIDO2認証')).toBeInTheDocument();

    await waitFor(() => {
      expect(screen.getByText('iPhone Touch ID')).toBeInTheDocument();
      expect(screen.getByText('Security Key')).toBeInTheDocument();
    });
  });

  it('新しいWebAuthnキーの登録ができる', async () => {
    // WebAuthn登録のモック
    const mockNavigatorCredentials = {
      create: vi.fn().mockResolvedValue({
        id: 'new-credential-id',
        rawId: new ArrayBuffer(32),
        type: 'public-key',
        response: {
          attestationObject: new ArrayBuffer(100),
          clientDataJSON: new ArrayBuffer(50),
        },
      }),
    };

    Object.defineProperty(global.navigator, 'credentials', {
      value: mockNavigatorCredentials,
      writable: true,
    });

    vi.mocked(webauthnApi.getRegistrationOptions).mockResolvedValue({
      success: true,
      challenge: 'test-challenge',
      user: { id: 'test-user-id', name: 'test@example.com', displayName: 'Test User' },
      pubKeyCredParams: [{ type: 'public-key', alg: -7 }],
    });

    vi.mocked(webauthnApi.registerCredential).mockResolvedValue({
      success: true,
      message: 'WebAuthnキーを登録しました',
    });

    render(<WebAuthnSettings />);

    const addButton = screen.getByRole('button', { name: /新しいキーを追加/ });
    fireEvent.click(addButton);

    await waitFor(() => {
      expect(webauthnApi.getRegistrationOptions).toHaveBeenCalled();
      expect(mockNavigatorCredentials.create).toHaveBeenCalled();
      expect(webauthnApi.registerCredential).toHaveBeenCalled();
      expect(mockShowToast).toHaveBeenCalledWith('WebAuthnキーを登録しました', 'success');
    });
  });

  it('WebAuthnキーの削除ができる', async () => {
    mockConfirm.mockResolvedValue(true);

    vi.mocked(webauthnApi.deleteCredential).mockResolvedValue({
      success: true,
      message: 'WebAuthnキーを削除しました',
    });

    render(<WebAuthnSettings />);

    await waitFor(() => {
      expect(screen.getByText('iPhone Touch ID')).toBeInTheDocument();
    });

    const deleteButtons = screen.getAllByRole('button', { name: /削除/ });
    fireEvent.click(deleteButtons[0]);

    await waitFor(() => {
      expect(mockConfirm).toHaveBeenCalledWith({
        title: 'WebAuthnキーを削除',
        message: 'このWebAuthnキー「iPhone Touch ID」を削除しますか？',
        confirmText: '削除',
        cancelText: 'キャンセル',
      });
      expect(webauthnApi.deleteCredential).toHaveBeenCalledWith('cred1');
      expect(mockShowToast).toHaveBeenCalledWith('WebAuthnキーを削除しました', 'success');
    });
  });

  it('WebAuthnキーのエイリアス編集ができる', async () => {
    vi.mocked(webauthnApi.updateCredentialAlias).mockResolvedValue({
      success: true,
      credential: {
        id: 'cred1',
        alias: 'Updated Name',
        created_at: '2024-01-01T00:00:00Z',
        disabled_at: null,
      },
      message: 'エイリアスを更新しました',
    });

    render(<WebAuthnSettings />);

    await waitFor(() => {
      expect(screen.getByText('iPhone Touch ID')).toBeInTheDocument();
    });

    // 編集ボタンをクリック
    const editButtons = screen.getAllByRole('button', { name: /編集/ });
    fireEvent.click(editButtons[0]);

    // 入力フィールドが表示される
    const input = screen.getByDisplayValue('iPhone Touch ID');
    fireEvent.change(input, { target: { value: 'Updated Name' } });

    // 保存ボタンをクリック
    const saveButton = screen.getByRole('button', { name: /保存/ });
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(webauthnApi.updateCredentialAlias).toHaveBeenCalledWith('cred1', 'Updated Name');
      expect(mockShowToast).toHaveBeenCalledWith('エイリアスを更新しました', 'success');
    });
  });

  it('WebAuthnキーの無効化ができる', async () => {
    vi.mocked(webauthnApi.disableCredential).mockResolvedValue({
      success: true,
      message: 'WebAuthnキーを無効化しました',
    });

    render(<WebAuthnSettings />);

    await waitFor(() => {
      expect(screen.getByText('iPhone Touch ID')).toBeInTheDocument();
    });

    const disableButtons = screen.getAllByRole('button', { name: /無効化/ });
    fireEvent.click(disableButtons[0]);

    await waitFor(() => {
      expect(webauthnApi.disableCredential).toHaveBeenCalledWith('cred1');
      expect(mockShowToast).toHaveBeenCalledWith('WebAuthnキーを無効化しました', 'success');
    });
  });

  it('WebAuthn非対応ブラウザでは登録ボタンが無効', () => {
    // WebAuthn非対応環境をシミュレート
    Object.defineProperty(global.navigator, 'credentials', {
      value: undefined,
      writable: true,
    });

    render(<WebAuthnSettings />);

    const addButton = screen.getByRole('button', { name: /新しいキーを追加/ });
    expect(addButton).toBeDisabled();
    expect(screen.getByText('このブラウザはWebAuthnに対応していません')).toBeInTheDocument();
  });

  it('WebAuthn登録時のエラーハンドリング', async () => {
    const mockNavigatorCredentials = {
      create: vi.fn().mockRejectedValue(new Error('User cancelled')),
    };

    Object.defineProperty(global.navigator, 'credentials', {
      value: mockNavigatorCredentials,
      writable: true,
    });

    vi.mocked(webauthnApi.getRegistrationOptions).mockResolvedValue({
      success: true,
      challenge: 'test-challenge',
      user: { id: 'test-user-id', name: 'test@example.com', displayName: 'Test User' },
      pubKeyCredParams: [{ type: 'public-key', alg: -7 }],
    });

    render(<WebAuthnSettings />);

    const addButton = screen.getByRole('button', { name: /新しいキーを追加/ });
    fireEvent.click(addButton);

    await waitFor(() => {
      expect(mockShowToast).toHaveBeenCalledWith('WebAuthn登録に失敗しました: User cancelled', 'error');
    });
  });

  it('クレデンシャルがない場合のメッセージ表示', async () => {
    vi.mocked(webauthnApi.getCredentials).mockResolvedValue({
      success: true,
      credentials: [],
    });

    render(<WebAuthnSettings />);

    await waitFor(() => {
      expect(screen.getByText('登録されたWebAuthnキーはありません')).toBeInTheDocument();
    });
  });

  it('削除確認をキャンセルした場合は削除されない', async () => {
    mockConfirm.mockResolvedValue(false);

    render(<WebAuthnSettings />);

    await waitFor(() => {
      expect(screen.getByText('iPhone Touch ID')).toBeInTheDocument();
    });

    const deleteButtons = screen.getAllByRole('button', { name: /削除/ });
    fireEvent.click(deleteButtons[0]);

    await waitFor(() => {
      expect(mockConfirm).toHaveBeenCalled();
    });

    expect(webauthnApi.deleteCredential).not.toHaveBeenCalled();
  });

  it('エイリアス編集をキャンセルした場合は元に戻る', async () => {
    render(<WebAuthnSettings />);

    await waitFor(() => {
      expect(screen.getByText('iPhone Touch ID')).toBeInTheDocument();
    });

    // 編集開始
    const editButtons = screen.getAllByRole('button', { name: /編集/ });
    fireEvent.click(editButtons[0]);

    const input = screen.getByDisplayValue('iPhone Touch ID');
    fireEvent.change(input, { target: { value: 'Changed Name' } });

    // キャンセルボタンをクリック
    const cancelButton = screen.getByRole('button', { name: /キャンセル/ });
    fireEvent.click(cancelButton);

    // 元のテキストが表示される
    expect(screen.getByText('iPhone Touch ID')).toBeInTheDocument();
    expect(webauthnApi.updateCredentialAlias).not.toHaveBeenCalled();
  });
});