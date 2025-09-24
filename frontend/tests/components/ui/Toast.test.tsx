import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, act } from '@testing-library/react';
import { Toast } from '../../../src/components/ui/Toast';
import type { ToastType } from '../../../src/contexts/ToastContext';

describe.skip('Toast', () => {
  const mockOnClose = vi.fn();
  const defaultProps = {
    id: 'test-toast',
    message: 'テストメッセージ',
    type: 'info' as ToastType,
    onClose: mockOnClose,
  };

  beforeEach(() => {
    vi.clearAllMocks();
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('基本的なトーストが表示される', () => {
    render(<Toast {...defaultProps} />);

    expect(screen.getByText('テストメッセージ')).toBeInTheDocument();
    expect(screen.getByRole('alert')).toBeInTheDocument();
  });

  it('成功タイプのトーストが正しいスタイルで表示される', () => {
    render(<Toast {...defaultProps} type="success" />);

    const toast = screen.getByRole('alert');
    expect(toast).toHaveClass('bg-green-100', 'border-green-400', 'text-green-700');
  });

  it('エラータイプのトーストが正しいスタイルで表示される', () => {
    render(<Toast {...defaultProps} type="error" />);

    const toast = screen.getByRole('alert');
    expect(toast).toHaveClass('bg-red-100', 'border-red-400', 'text-red-700');
  });

  it('警告タイプのトーストが正しいスタイルで表示される', () => {
    render(<Toast {...defaultProps} type="warning" />);

    const toast = screen.getByRole('alert');
    expect(toast).toHaveClass('bg-yellow-100', 'border-yellow-400', 'text-yellow-700');
  });

  it('情報タイプのトーストが正しいスタイルで表示される', () => {
    render(<Toast {...defaultProps} type="info" />);

    const toast = screen.getByRole('alert');
    expect(toast).toHaveClass('bg-blue-100', 'border-blue-400', 'text-blue-700');
  });

  it('閉じるボタンをクリックするとonCloseが呼ばれる', () => {
    render(<Toast {...defaultProps} />);

    const closeButton = screen.getByRole('button', { name: /閉じる/ });
    fireEvent.click(closeButton);

    expect(mockOnClose).toHaveBeenCalledWith('test-toast');
  });

  it('自動的に閉じる（デフォルト5秒）', () => {
    render(<Toast {...defaultProps} />);

    expect(mockOnClose).not.toHaveBeenCalled();

    act(() => {
      vi.advanceTimersByTime(5000);
    });

    expect(mockOnClose).toHaveBeenCalledWith('test-toast');
  });

  it('カスタム自動閉じる時間が設定できる', () => {
    render(<Toast {...defaultProps} autoClose={3000} />);

    act(() => {
      vi.advanceTimersByTime(2999);
    });
    expect(mockOnClose).not.toHaveBeenCalled();

    act(() => {
      vi.advanceTimersByTime(1);
    });
    expect(mockOnClose).toHaveBeenCalledWith('test-toast');
  });

  it('autoCloseがfalseの場合は自動で閉じない', () => {
    render(<Toast {...defaultProps} autoClose={false} />);

    act(() => {
      vi.advanceTimersByTime(10000);
    });

    expect(mockOnClose).not.toHaveBeenCalled();
  });

  it('マウスホバーで自動閉じるを一時停止', () => {
    render(<Toast {...defaultProps} autoClose={2000} />);

    const toast = screen.getByRole('alert');

    // 1秒経過
    act(() => {
      vi.advanceTimersByTime(1000);
    });

    // ホバー
    fireEvent.mouseEnter(toast);

    // さらに2秒経過（本来なら閉じるはず）
    act(() => {
      vi.advanceTimersByTime(2000);
    });
    expect(mockOnClose).not.toHaveBeenCalled();

    // ホバー終了
    fireEvent.mouseLeave(toast);

    // 残り1秒経過
    act(() => {
      vi.advanceTimersByTime(1000);
    });
    expect(mockOnClose).toHaveBeenCalledWith('test-toast');
  });

  it('アクセシビリティ属性が正しく設定される', () => {
    render(<Toast {...defaultProps} />);

    const toast = screen.getByRole('alert');
    expect(toast).toHaveAttribute('aria-live', 'polite');

    const closeButton = screen.getByRole('button');
    expect(closeButton).toHaveAttribute('aria-label', '閉じる');
  });

  it('長いメッセージが正しく表示される', () => {
    const longMessage = 'これは非常に長いメッセージです。'.repeat(10);
    render(<Toast {...defaultProps} message={longMessage} />);

    expect(screen.getByText(longMessage)).toBeInTheDocument();
  });

  it('HTMLが含まれたメッセージもテキストとして安全に表示される', () => {
    const messageWithHTML = '<script>alert("xss")</script>安全なメッセージ';
    render(<Toast {...defaultProps} message={messageWithHTML} />);

    // HTMLタグはそのまま文字列として表示される
    expect(screen.getByText(messageWithHTML)).toBeInTheDocument();
    // スクリプトは実行されない
    expect(document.querySelector('script')).toBeNull();
  });

  it('アニメーション用のCSSクラスが設定される', () => {
    render(<Toast {...defaultProps} />);

    const toast = screen.getByRole('alert');
    expect(toast).toHaveClass('transform', 'transition-all', 'duration-300');
  });
});