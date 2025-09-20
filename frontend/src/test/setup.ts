import '@testing-library/jest-dom';
import { beforeAll, afterEach, afterAll, beforeEach } from 'vitest';
import { server } from './mocks/server';
import i18n from '../i18n';

// テスト開始前にMSWサーバーを起動
beforeAll(() => {
  server.listen();
});

// 各テスト前にi18nを日本語に設定
beforeEach(async () => {
  if (i18n.isInitialized) {
    await i18n.changeLanguage('ja');
  }
  localStorage.clear();
});

// 各テスト後にハンドラーをリセット
afterEach(() => {
  server.resetHandlers();
});

// テスト終了後にサーバーを停止
afterAll(() => server.close());