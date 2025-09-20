import { describe, it, expect, beforeEach } from 'vitest';
import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { render } from '../../src/test/utils';
import App from '../../src/App';

describe('App', () => {
  beforeEach(() => {
    // 各テスト前に言語をリセット
    localStorage.clear();
  });

  it('アプリのタイトルが表示される', async () => {
    render(<App />);

    expect(screen.getByText('FragFolio')).toBeInTheDocument();
  });

  it('ログインボタンが表示される', () => {
    render(<App />);

    expect(screen.getByText('ログイン')).toBeInTheDocument();
  });

  it('3つの機能カードが表示される', () => {
    render(<App />);

    expect(screen.getByText('コレクション管理')).toBeInTheDocument();
    expect(screen.getByText('AI検索')).toBeInTheDocument();
    expect(screen.getByText('着用ログ')).toBeInTheDocument();
  });

  it('言語切り替えボタンで英語に切り替えられる', async () => {
    const user = userEvent.setup();
    render(<App />);

    // 初期状態は日本語
    expect(screen.getByText('FragFolio')).toBeInTheDocument();
    expect(screen.getByText('English')).toBeInTheDocument();

    // 英語に切り替え
    await user.click(screen.getByText('English'));

    // 英語表示に変更される
    expect(screen.getByText('FragFolio')).toBeInTheDocument();
    expect(screen.getByText('日本語')).toBeInTheDocument();
    expect(screen.getByText('Login')).toBeInTheDocument();
  });

  it('言語切り替えボタンで日本語に戻せる', async () => {
    const user = userEvent.setup();
    render(<App />);

    // 英語に切り替え
    await user.click(screen.getByText('English'));
    expect(screen.getByText('Login')).toBeInTheDocument();

    // 日本語に戻す
    await user.click(screen.getByText('日本語'));
    expect(screen.getByText('ログイン')).toBeInTheDocument();
  });

  it('メインコンテンツエリアが表示される', () => {
    render(<App />);

    expect(
      screen.getByText('香水ポートフォリオを美しく管理')
    ).toBeInTheDocument();
    expect(
      screen.getByText(
        'あなたの香水コレクションを記録し、発見し、共有しましょう。'
      )
    ).toBeInTheDocument();
  });

  it('正しいCSSクラスが適用されている', () => {
    render(<App />);

    const header = screen.getByText('FragFolio').closest('header');
    expect(header).toHaveClass('bg-white', 'shadow');

    const mainContent = screen
      .getByText('香水ポートフォリオを美しく管理')
      .closest('main');
    expect(mainContent).toHaveClass('max-w-7xl', 'mx-auto');
  });
});
