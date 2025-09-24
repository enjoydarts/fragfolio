import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, act } from '../../utils';
import { ToastContainer } from '../../../src/components/ui/ToastContainer';
import { ToastProvider, useToast } from '../../../src/contexts/ToastContext';

// テスト用コンポーネント
const TestComponent = () => {
  const { showToast } = useToast();

  return (
    <div>
      <button onClick={() => showToast('成功メッセージ', 'success')}>
        Show Success
      </button>
      <button onClick={() => showToast('エラーメッセージ', 'error')}>
        Show Error
      </button>
      <button onClick={() => showToast('情報メッセージ', 'info')}>
        Show Info
      </button>
      <button onClick={() => showToast('警告メッセージ', 'warning')}>
        Show Warning
      </button>
    </div>
  );
};

const TestWrapper = ({ children }: { children: React.ReactNode }) => (
  <ToastProvider>
    {children}
    <ToastContainer />
  </ToastProvider>
);

describe.skip('ToastContainer', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('トーストコンテナが正しくレンダリングされる', () => {
    render(<ToastContainer />, { wrapper: ToastProvider });

    // 初期状態ではトーストは表示されない
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('成功トーストが表示される', () => {
    render(
      <TestWrapper>
        <TestComponent />
      </TestWrapper>
    );

    act(() => {
      screen.getByText('Show Success').click();
    });

    expect(screen.getByText('成功メッセージ')).toBeInTheDocument();
    expect(screen.getByRole('alert')).toHaveClass('bg-green-100', 'border-green-400', 'text-green-700');
  });

  it('エラートーストが表示される', () => {
    render(
      <TestWrapper>
        <TestComponent />
      </TestWrapper>
    );

    act(() => {
      screen.getByText('Show Error').click();
    });

    expect(screen.getByText('エラーメッセージ')).toBeInTheDocument();
    expect(screen.getByRole('alert')).toHaveClass('bg-red-100', 'border-red-400', 'text-red-700');
  });

  it('情報トーストが表示される', () => {
    render(
      <TestWrapper>
        <TestComponent />
      </TestWrapper>
    );

    act(() => {
      screen.getByText('Show Info').click();
    });

    expect(screen.getByText('情報メッセージ')).toBeInTheDocument();
    expect(screen.getByRole('alert')).toHaveClass('bg-blue-100', 'border-blue-400', 'text-blue-700');
  });

  it('警告トーストが表示される', () => {
    render(
      <TestWrapper>
        <TestComponent />
      </TestWrapper>
    );

    act(() => {
      screen.getByText('Show Warning').click();
    });

    expect(screen.getByText('警告メッセージ')).toBeInTheDocument();
    expect(screen.getByRole('alert')).toHaveClass('bg-yellow-100', 'border-yellow-400', 'text-yellow-700');
  });

  it('複数のトーストが同時に表示される', () => {
    render(
      <TestWrapper>
        <TestComponent />
      </TestWrapper>
    );

    act(() => {
      screen.getByText('Show Success').click();
      screen.getByText('Show Error').click();
      screen.getByText('Show Info').click();
    });

    expect(screen.getByText('成功メッセージ')).toBeInTheDocument();
    expect(screen.getByText('エラーメッセージ')).toBeInTheDocument();
    expect(screen.getByText('情報メッセージ')).toBeInTheDocument();
    expect(screen.getAllByRole('alert')).toHaveLength(3);
  });

  it('トーストが自動的に閉じる', () => {
    render(
      <TestWrapper>
        <TestComponent />
      </TestWrapper>
    );

    act(() => {
      screen.getByText('Show Success').click();
    });

    expect(screen.getByText('成功メッセージ')).toBeInTheDocument();

    // 5秒経過
    act(() => {
      vi.advanceTimersByTime(5000);
    });

    expect(screen.queryByText('成功メッセージ')).not.toBeInTheDocument();
  });

  it('閉じるボタンでトーストを手動で閉じることができる', () => {
    render(
      <TestWrapper>
        <TestComponent />
      </TestWrapper>
    );

    act(() => {
      screen.getByText('Show Success').click();
    });

    expect(screen.getByText('成功メッセージ')).toBeInTheDocument();

    const closeButton = screen.getByRole('button', { name: /閉じる/ });
    act(() => {
      closeButton.click();
    });

    expect(screen.queryByText('成功メッセージ')).not.toBeInTheDocument();
  });

  it('最大表示数を超えたトーストは古いものから削除される', () => {
    const MAX_TOASTS = 5; // ToastContextの設定に依存

    render(
      <TestWrapper>
        <TestComponent />
      </TestWrapper>
    );

    // 最大数を超えてトーストを表示
    act(() => {
      for (let i = 1; i <= MAX_TOASTS + 2; i++) {
        screen.getByText('Show Success').click();
      }
    });

    // 最大表示数に制限される
    const toasts = screen.getAllByRole('alert');
    expect(toasts.length).toBeLessThanOrEqual(MAX_TOASTS);
  });

  it('トーストコンテナの位置クラスが正しく設定される', () => {
    render(<ToastContainer position="top-right" />, { wrapper: ToastProvider });

    const container = screen.getByTestId('toast-container');
    expect(container).toHaveClass('top-4', 'right-4');
  });

  it('異なる位置設定が適用される', () => {
    render(<ToastContainer position="bottom-left" />, { wrapper: ToastProvider });

    const container = screen.getByTestId('toast-container');
    expect(container).toHaveClass('bottom-4', 'left-4');
  });

  it('トーストにアニメーションクラスが適用される', () => {
    render(
      <TestWrapper>
        <TestComponent />
      </TestWrapper>
    );

    act(() => {
      screen.getByText('Show Success').click();
    });

    const toast = screen.getByRole('alert');
    expect(toast).toHaveClass('transform', 'transition-all', 'duration-300');
  });

  it('マウスホバーで自動閉じるが一時停止される', () => {
    render(
      <TestWrapper>
        <TestComponent />
      </TestWrapper>
    );

    act(() => {
      screen.getByText('Show Success').click();
    });

    const toast = screen.getByRole('alert');

    // 3秒経過
    act(() => {
      vi.advanceTimersByTime(3000);
    });

    // ホバー
    act(() => {
      toast.dispatchEvent(new MouseEvent('mouseenter', { bubbles: true }));
    });

    // さらに3秒経過（本来なら閉じるはず）
    act(() => {
      vi.advanceTimersByTime(3000);
    });

    expect(screen.getByText('成功メッセージ')).toBeInTheDocument();

    // ホバー終了
    act(() => {
      toast.dispatchEvent(new MouseEvent('mouseleave', { bubbles: true }));
    });

    // 残り時間経過
    act(() => {
      vi.advanceTimersByTime(2000);
    });

    expect(screen.queryByText('成功メッセージ')).not.toBeInTheDocument();
  });
});