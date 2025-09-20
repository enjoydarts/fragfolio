import React, { ReactElement } from 'react';
import { render, RenderOptions } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import { I18nextProvider } from 'react-i18next';
import i18n from '../i18n';

// カスタムレンダー関数：Router、i18nなどのプロバイダーでラップ
const AllTheProviders = ({ children }: { children: React.ReactNode }) => {
  // テスト時に言語を日本語に固定
  if (!i18n.isInitialized) {
    i18n.changeLanguage('ja');
  }

  return (
    <BrowserRouter>
      <I18nextProvider i18n={i18n}>
        {children}
      </I18nextProvider>
    </BrowserRouter>
  );
};

const customRender = (
  ui: ReactElement,
  options?: Omit<RenderOptions, 'wrapper'>
) => render(ui, { wrapper: AllTheProviders, ...options });

export * from '@testing-library/react';
export { customRender as render };