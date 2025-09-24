import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '../../utils';
import { TOTPSettings } from '../../../src/components/security/TOTPSettings';
import * as twoFactorApi from '../../../src/api/twoFactor';
import { useToast } from '../../../src/hooks/useToast';
import { useConfirm } from '../../../src/hooks/useConfirm';

// モックの設定
vi.mock('../../../src/api/twoFactor');
vi.mock('../../../src/hooks/useToast');
vi.mock('../../../src/hooks/useConfirm');

const mockShowToast = vi.fn();
const mockConfirm = vi.fn();
const mockUseToast = vi.mocked(useToast);
const mockUseConfirm = vi.mocked(useConfirm);

describe.skip('TOTPSettings', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    mockUseToast.mockReturnValue({
      showToast: mockShowToast,
    });

    mockUseConfirm.mockReturnValue({
      confirm: mockConfirm,
    });
  });

  it('2段階認証が無効な状態で正しくレンダリングされる', () => {
    render(<TOTPSettings twoFactorEnabled={false} onStatusChange={vi.fn()} />);

    expect(screen.getByText('TOTP 2段階認証')).toBeInTheDocument();
    expect(screen.getByText('無効')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '有効化' })).toBeInTheDocument();
  });

  it('2段階認証が有効な状態で正しくレンダリングされる', () => {
    render(<TOTPSettings twoFactorEnabled={true} onStatusChange={vi.fn()} />);

    expect(screen.getByText('TOTP 2段階認証')).toBeInTheDocument();
    expect(screen.getByText('有効')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '無効化' })).toBeInTheDocument();
    expect(
      screen.getByRole('button', { name: 'リカバリーコード表示' })
    ).toBeInTheDocument();
  });

  it('2段階認証の有効化ができる', async () => {
    const mockOnStatusChange = vi.fn();
    const mockEnableResponse = {
      success: true,
      secret: 'ABCDEFGHIJKLMNOP',
      qr_code_url: 'otpauth://totp/...',
      message: '2段階認証を有効にしました',
    };

    vi.mocked(twoFactorApi.enableTwoFactor).mockResolvedValue(
      mockEnableResponse
    );
    vi.mocked(twoFactorApi.confirmTwoFactor).mockResolvedValue({
      success: true,
      recovery_codes: ['code1', 'code2', 'code3'],
      message: '2段階認証を確認しました',
    });

    render(
      <TOTPSettings
        twoFactorEnabled={false}
        onStatusChange={mockOnStatusChange}
      />
    );

    // 有効化ボタンをクリック
    fireEvent.click(screen.getByRole('button', { name: '有効化' }));

    await waitFor(() => {
      expect(
        screen.getByText('QRコードをスキャンしてください')
      ).toBeInTheDocument();
      expect(
        screen.getByText('シークレットキー: ABCDEFGHIJKLMNOP')
      ).toBeInTheDocument();
    });

    // 確認コードを入力
    const codeInput = screen.getByLabelText('認証コード');
    fireEvent.change(codeInput, { target: { value: '123456' } });

    // 確認ボタンをクリック
    fireEvent.click(screen.getByRole('button', { name: '確認' }));

    await waitFor(() => {
      expect(twoFactorApi.confirmTwoFactor).toHaveBeenCalledWith('123456');
      expect(mockShowToast).toHaveBeenCalledWith(
        '2段階認証を確認しました',
        'success'
      );
      expect(mockOnStatusChange).toHaveBeenCalledWith(true);
    });

    // リカバリーコードが表示される
    expect(screen.getByText('リカバリーコード')).toBeInTheDocument();
    expect(screen.getByText('code1')).toBeInTheDocument();
    expect(screen.getByText('code2')).toBeInTheDocument();
    expect(screen.getByText('code3')).toBeInTheDocument();
  });

  it('2段階認証の無効化ができる', async () => {
    const mockOnStatusChange = vi.fn();
    mockConfirm.mockResolvedValue(true);
    vi.mocked(twoFactorApi.disableTwoFactor).mockResolvedValue({
      success: true,
      message: '2段階認証を無効にしました',
    });

    render(
      <TOTPSettings
        twoFactorEnabled={true}
        onStatusChange={mockOnStatusChange}
      />
    );

    // 無効化ボタンをクリック
    fireEvent.click(screen.getByRole('button', { name: '無効化' }));

    await waitFor(() => {
      expect(mockConfirm).toHaveBeenCalledWith({
        title: '2段階認証を無効化',
        message: '2段階認証を無効化しますか？セキュリティが低下します。',
        confirmText: '無効化',
        cancelText: 'キャンセル',
      });
    });

    await waitFor(() => {
      expect(twoFactorApi.disableTwoFactor).toHaveBeenCalled();
      expect(mockShowToast).toHaveBeenCalledWith(
        '2段階認証を無効にしました',
        'success'
      );
      expect(mockOnStatusChange).toHaveBeenCalledWith(false);
    });
  });

  it('リカバリーコードの表示ができる', async () => {
    vi.mocked(twoFactorApi.getRecoveryCodes).mockResolvedValue({
      success: true,
      recovery_codes: ['recovery1', 'recovery2', 'recovery3'],
    });

    render(<TOTPSettings twoFactorEnabled={true} onStatusChange={vi.fn()} />);

    // リカバリーコード表示ボタンをクリック
    fireEvent.click(
      screen.getByRole('button', { name: 'リカバリーコード表示' })
    );

    await waitFor(() => {
      expect(screen.getByText('リカバリーコード')).toBeInTheDocument();
      expect(screen.getByText('recovery1')).toBeInTheDocument();
      expect(screen.getByText('recovery2')).toBeInTheDocument();
      expect(screen.getByText('recovery3')).toBeInTheDocument();
    });
  });

  it('リカバリーコードの再生成ができる', async () => {
    const mockConfirm = vi.fn().mockResolvedValue(true);
    mockUseConfirm.mockReturnValue({ confirm: mockConfirm });

    vi.mocked(twoFactorApi.getRecoveryCodes).mockResolvedValue({
      success: true,
      recovery_codes: ['old1', 'old2'],
    });

    vi.mocked(twoFactorApi.regenerateRecoveryCodes).mockResolvedValue({
      success: true,
      recovery_codes: ['new1', 'new2'],
      message: 'リカバリーコードを再生成しました',
    });

    render(<TOTPSettings twoFactorEnabled={true} onStatusChange={vi.fn()} />);

    // リカバリーコード表示
    fireEvent.click(
      screen.getByRole('button', { name: 'リカバリーコード表示' })
    );

    await waitFor(() => {
      expect(screen.getByText('old1')).toBeInTheDocument();
    });

    // 再生成ボタンをクリック
    fireEvent.click(
      screen.getByRole('button', { name: 'リカバリーコード再生成' })
    );

    await waitFor(() => {
      expect(mockConfirm).toHaveBeenCalled();
      expect(twoFactorApi.regenerateRecoveryCodes).toHaveBeenCalled();
      expect(mockShowToast).toHaveBeenCalledWith(
        'リカバリーコードを再生成しました',
        'success'
      );
    });

    await waitFor(() => {
      expect(screen.getByText('new1')).toBeInTheDocument();
      expect(screen.getByText('new2')).toBeInTheDocument();
    });
  });

  it('エラー時にエラーメッセージが表示される', async () => {
    const errorMessage = 'エラーが発生しました';
    vi.mocked(twoFactorApi.enableTwoFactor).mockRejectedValue(
      new Error(errorMessage)
    );

    render(<TOTPSettings twoFactorEnabled={false} onStatusChange={vi.fn()} />);

    // 有効化ボタンをクリック
    fireEvent.click(screen.getByRole('button', { name: '有効化' }));

    await waitFor(() => {
      expect(mockShowToast).toHaveBeenCalledWith(errorMessage, 'error');
    });
  });

  it('確認コードが無効な場合エラーが表示される', async () => {
    vi.mocked(twoFactorApi.enableTwoFactor).mockResolvedValue({
      success: true,
      secret: 'ABCDEFGHIJKLMNOP',
      qr_code_url: 'otpauth://totp/...',
      message: '2段階認証を有効にしました',
    });

    vi.mocked(twoFactorApi.confirmTwoFactor).mockResolvedValue({
      success: false,
      message: '認証コードが無効です',
    });

    render(<TOTPSettings twoFactorEnabled={false} onStatusChange={vi.fn()} />);

    fireEvent.click(screen.getByRole('button', { name: '有効化' }));

    await waitFor(() => {
      expect(screen.getByLabelText('認証コード')).toBeInTheDocument();
    });

    const codeInput = screen.getByLabelText('認証コード');
    fireEvent.change(codeInput, { target: { value: '000000' } });
    fireEvent.click(screen.getByRole('button', { name: '確認' }));

    await waitFor(() => {
      expect(mockShowToast).toHaveBeenCalledWith(
        '認証コードが無効です',
        'error'
      );
    });
  });

  it('キャンセル時に設定をリセットする', async () => {
    vi.mocked(twoFactorApi.enableTwoFactor).mockResolvedValue({
      success: true,
      secret: 'ABCDEFGHIJKLMNOP',
      qr_code_url: 'otpauth://totp/...',
      message: '2段階認証を有効にしました',
    });

    render(<TOTPSettings twoFactorEnabled={false} onStatusChange={vi.fn()} />);

    // 有効化を開始
    fireEvent.click(screen.getByRole('button', { name: '有効化' }));

    await waitFor(() => {
      expect(
        screen.getByText('QRコードをスキャンしてください')
      ).toBeInTheDocument();
    });

    // キャンセルボタンをクリック
    fireEvent.click(screen.getByRole('button', { name: 'キャンセル' }));

    // 初期状態に戻る
    expect(screen.getByRole('button', { name: '有効化' })).toBeInTheDocument();
    expect(
      screen.queryByText('QRコードをスキャンしてください')
    ).not.toBeInTheDocument();
  });
});
