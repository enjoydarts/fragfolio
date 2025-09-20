import { setupServer } from 'msw/node';
import { handlers } from './handlers';

// テスト用のMSWサーバーを設定
export const server = setupServer(...handlers);
