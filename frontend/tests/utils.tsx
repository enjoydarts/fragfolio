import React from 'react';
import { render, RenderOptions } from '@testing-library/react';
import { vi } from 'vitest';
import { MemoryRouter } from 'react-router-dom';
import { AuthProvider } from '../src/contexts/AuthContext';
import { ToastProvider } from '../src/contexts/ToastContext';
import { I18nextProvider } from 'react-i18next';
import i18n from '../src/i18n';

// カスタムレンダーを作成
interface CustomRenderOptions extends Omit<RenderOptions, 'wrapper'> {
  initialEntries?: string[];
  authContext?: unknown;
}

function AllTheProviders({
  children,
  authContext,
}: {
  children: React.ReactNode;
  authContext?: unknown;
}) {
  // AuthContextの値をモック
  const defaultAuthContext = {
    user: null,
    isLoading: false,
    isLoggedIn: false,
    isEmailVerified: false,
    login: vi.fn(),
    register: vi.fn(),
    logout: vi.fn(),
    updateProfile: vi.fn(),
    refreshToken: vi.fn(),
    ...authContext,
  };

  return (
    <MemoryRouter initialEntries={['/']}>
      <I18nextProvider i18n={i18n}>
        <AuthProvider value={defaultAuthContext}>
          <ToastProvider>{children}</ToastProvider>
        </AuthProvider>
      </I18nextProvider>
    </MemoryRouter>
  );
}

const customRender = (
  ui: React.ReactElement,
  options: CustomRenderOptions = {}
) => {
  const { authContext, ...renderOptions } = options;

  return render(ui, {
    wrapper: (props) => (
      <AllTheProviders {...props} authContext={authContext} />
    ),
    ...renderOptions,
  });
};

// re-export everything
export * from '@testing-library/react';

// override render method
export { customRender as render };
