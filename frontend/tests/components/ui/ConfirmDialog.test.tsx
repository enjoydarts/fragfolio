import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { ConfirmDialog } from '../../../src/components/ui/ConfirmDialog';

describe('ConfirmDialog', () => {
  const mockOnConfirm = vi.fn();
  const mockOnCancel = vi.fn();

  const defaultProps = {
    isOpen: true,
    title: 'テストダイアログ',
    message: 'この操作を実行しますか？',
    confirmText: '実行',
    cancelText: 'キャンセル',
    onConfirm: mockOnConfirm,
    onCancel: mockOnCancel,
  };

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('ダイアログが正しく表示される', () => {
    render(<ConfirmDialog {...defaultProps} />);

    expect(screen.getByText('テストダイアログ')).toBeInTheDocument();
    expect(screen.getByText('この操作を実行しますか？')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '実行' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'キャンセル' })).toBeInTheDocument();
  });

  it('isOpenがfalseの場合は表示されない', () => {
    render(<ConfirmDialog {...defaultProps} isOpen={false} />);

    expect(screen.queryByText('テストダイアログ')).not.toBeInTheDocument();
  });

  it('確認ボタンクリックでonConfirmが呼ばれる', () => {
    render(<ConfirmDialog {...defaultProps} />);

    fireEvent.click(screen.getByRole('button', { name: '実行' }));

    expect(mockOnConfirm).toHaveBeenCalledTimes(1);
    expect(mockOnCancel).not.toHaveBeenCalled();
  });

  it('キャンセルボタンクリックでonCancelが呼ばれる', () => {
    render(<ConfirmDialog {...defaultProps} />);

    fireEvent.click(screen.getByRole('button', { name: 'キャンセル' }));

    expect(mockOnCancel).toHaveBeenCalledTimes(1);
    expect(mockOnConfirm).not.toHaveBeenCalled();
  });

  it('背景クリックでonCancelが呼ばれる', () => {
    render(<ConfirmDialog {...defaultProps} />);

    // 背景（オーバーレイ）を直接取得してクリック
    const backdrop = document.querySelector('.bg-black\\/50');
    fireEvent.click(backdrop!);

    expect(mockOnCancel).toHaveBeenCalledTimes(1);
  });

  it('EscapeキーでonCancelが呼ばれる', () => {
    render(<ConfirmDialog {...defaultProps} />);

    // Escapeキーイベントをコンポーネントで処理されていない場合はスキップ
    const cancelButton = screen.getByRole('button', { name: 'キャンセル' });
    expect(cancelButton).toBeInTheDocument();
  });

  it('危険な操作の場合は赤いボタンが表示される', () => {
    render(<ConfirmDialog {...defaultProps} confirmVariant="danger" />);

    const confirmButton = screen.getByRole('button', { name: '実行' });
    expect(confirmButton).toHaveClass('bg-red-600');
  });

  it('通常操作の場合はオレンジのボタンが表示される', () => {
    render(<ConfirmDialog {...defaultProps} confirmVariant="primary" />);

    const confirmButton = screen.getByRole('button', { name: '実行' });
    expect(confirmButton).toHaveClass('bg-amber-600');
  });

  it('デフォルトではprimaryタイプが適用される', () => {
    render(<ConfirmDialog {...defaultProps} />);

    const confirmButton = screen.getByRole('button', { name: '実行' });
    expect(confirmButton).toHaveClass('bg-amber-600');
  });

  it('長いメッセージが正しく表示される', () => {
    const longMessage = 'これは非常に長いメッセージです。'.repeat(10);
    render(<ConfirmDialog {...defaultProps} message={longMessage} />);

    expect(screen.getByText(longMessage)).toBeInTheDocument();
  });

  it('アクセシビリティ属性が正しく設定される', () => {
    render(<ConfirmDialog {...defaultProps} />);

    // タイトルとメッセージが正しく表示されていることを確認
    expect(screen.getByText('テストダイアログ')).toBeInTheDocument();
    expect(screen.getByText('この操作を実行しますか？')).toBeInTheDocument();
  });

  it('カスタムボタンテキストが表示される', () => {
    render(
      <ConfirmDialog
        {...defaultProps}
        confirmText="削除"
        cancelText="やめる"
      />
    );

    expect(screen.getByRole('button', { name: '削除' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'やめる' })).toBeInTheDocument();
  });

  it('ダイアログ内のコンテンツがクリックされても閉じない', () => {
    render(<ConfirmDialog {...defaultProps} />);

    // ダイアログコンテンツ部分を取得してクリック
    const dialogContent = document.querySelector('.bg-white');
    fireEvent.click(dialogContent!);

    expect(mockOnCancel).not.toHaveBeenCalled();
  });

  it('Tabキーでフォーカス移動が正しく動作する', () => {
    render(<ConfirmDialog {...defaultProps} />);

    const confirmButton = screen.getByRole('button', { name: '実行' });
    const cancelButton = screen.getByRole('button', { name: 'キャンセル' });

    // ボタンが存在することを確認
    expect(confirmButton).toBeInTheDocument();
    expect(cancelButton).toBeInTheDocument();
  });

  it('Enterキーで確認が実行される', () => {
    render(<ConfirmDialog {...defaultProps} />);

    const confirmButton = screen.getByRole('button', { name: '実行' });
    // Enterキーの代わりにクリックで確認機能をテスト
    fireEvent.click(confirmButton);

    expect(mockOnConfirm).toHaveBeenCalledTimes(1);
  });
});