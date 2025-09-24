import { describe, it, expect } from 'vitest';
import { render, screen } from '../../utils';
import { LoadingSpinner } from '../../../src/components/layout/LoadingSpinner';

describe('LoadingSpinner', () => {
  it('デフォルトのローディングスピナーが表示される', () => {
    render(<LoadingSpinner />);

    // 実際の実装に合わせてテスト
    const spinner = document.querySelector('.animate-spin');
    expect(spinner).toBeInTheDocument();
    expect(spinner).toHaveClass('animate-spin', 'rounded-full', 'h-32', 'w-32', 'border-b-2', 'border-blue-600');
  });

  it('読み込み中のメッセージが表示される', () => {
    render(<LoadingSpinner />);

    expect(screen.getByText('読み込み中...')).toBeInTheDocument();
  });

  it('コンテナが適切なレイアウトクラスを持つ', () => {
    render(<LoadingSpinner />);

    const container = document.querySelector('.min-h-screen');
    expect(container).toHaveClass('min-h-screen', 'flex', 'items-center', 'justify-center', 'bg-gray-50');
  });

  it('スピナーが中央に配置されている', () => {
    render(<LoadingSpinner />);

    const innerContainer = document.querySelector('.flex.flex-col.items-center');
    expect(innerContainer).toBeInTheDocument();
    expect(innerContainer).toHaveClass('flex', 'flex-col', 'items-center');
  });

  it('メッセージのスタイルが正しい', () => {
    render(<LoadingSpinner />);

    const message = screen.getByText('読み込み中...');
    expect(message).toHaveClass('mt-4', 'text-gray-600');
  });
});