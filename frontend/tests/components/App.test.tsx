import { describe, it, expect, beforeEach } from 'vitest';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { I18nextProvider } from 'react-i18next';
import { vi } from 'vitest';
import i18n from '../../src/i18n';
import App from '../../src/App';

describe('App', () => {
  beforeEach(() => {
    // 各テスト前に言語をリセット
    localStorage.clear();
  });

  it('アプリのロゴが表示される', async () => {
    render(
      <I18nextProvider i18n={i18n}>
        <App />
      </I18nextProvider>
    );

    expect(screen.getByAltText('fragfolio')).toBeInTheDocument();
  });

  it('ログインボタンが表示される', () => {
    render(
      <I18nextProvider i18n={i18n}>
        <App />
      </I18nextProvider>
    );

    expect(screen.getByRole('link', { name: 'ログイン' })).toBeInTheDocument();
  });

  it('3つの機能カードが表示される', () => {
    render(
      <I18nextProvider i18n={i18n}>
        <App />
      </I18nextProvider>
    );

    expect(screen.getByText('コレクション管理')).toBeInTheDocument();
    expect(screen.getByText('AI検索')).toBeInTheDocument();
    expect(screen.getByText('着用ログ')).toBeInTheDocument();
  });

  it('言語切り替えボタンで英語に切り替えられる', async () => {
    const user = userEvent.setup();
    render(
      <I18nextProvider i18n={i18n}>
        <App />
      </I18nextProvider>
    );

    // 初期状態は日本語
    expect(screen.getByAltText('fragfolio')).toBeInTheDocument();
    expect(screen.getByText('🇺🇸 English')).toBeInTheDocument();

    // 英語に切り替え
    await user.click(screen.getByText('🇺🇸 English'));

    // 英語表示に変更される
    expect(screen.getByAltText('fragfolio')).toBeInTheDocument();
    expect(screen.getByText('🇯🇵 日本語')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Login' })).toBeInTheDocument();
  });

  it('言語切り替えボタンで日本語に戻せる', async () => {
    const user = userEvent.setup();
    render(
      <I18nextProvider i18n={i18n}>
        <App />
      </I18nextProvider>
    );

    // 英語に切り替え
    await user.click(screen.getByText('🇺🇸 English'));
    expect(screen.getByRole('link', { name: 'Login' })).toBeInTheDocument();

    // 日本語に戻す
    await user.click(screen.getByText('🇯🇵 日本語'));
    expect(screen.getByRole('link', { name: 'ログイン' })).toBeInTheDocument();
  });

  it('メインコンテンツエリアが表示される', () => {
    render(
      <I18nextProvider i18n={i18n}>
        <App />
      </I18nextProvider>
    );

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
    render(
      <I18nextProvider i18n={i18n}>
        <App />
      </I18nextProvider>
    );

    const header = screen.getByAltText('fragfolio').closest('header');
    expect(header).toHaveClass('header-nav', 'sticky', 'top-0', 'z-50');

    const mainContent = screen
      .getByText('香水ポートフォリオを美しく管理')
      .closest('main');
    expect(mainContent).toHaveClass('max-w-7xl', 'mx-auto');
  });
});
