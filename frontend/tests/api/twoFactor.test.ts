import { describe, it, expect, beforeEach, vi } from 'vitest';
import { http, HttpResponse } from 'msw';
import { server } from '../mocks/server';
import {
  enableTwoFactor,
  confirmTwoFactor,
  disableTwoFactor,
  getQrCode,
  getRecoveryCodes,
  regenerateRecoveryCodes,
} from '../../src/api/twoFactor';

// モックトークンを設定
const mockToken = 'mock-jwt-token';

describe('TwoFactor API', () => {
  beforeEach(() => {
    localStorage.setItem('auth_token', mockToken);
    server.resetHandlers();
  });

  afterEach(() => {
    localStorage.clear();
  });

  describe('enableTwoFactor', () => {
    it('2段階認証の有効化ができる', async () => {
      server.use(
        http.post('http://localhost:8002/api/auth/two-factor-authentication', ({ request }) => {
          const authHeader = request.headers.get('Authorization');
          if (authHeader !== `Bearer ${mockToken}`) {
            return HttpResponse.json({ success: false, message: '認証が必要です' }, { status: 401 });
          }

          return HttpResponse.json({
            success: true,
            secret_key: 'ABCDEFGHIJKLMNOP',
            qr_code: 'otpauth://totp/fragfolio:test@example.com?secret=ABCDEFGHIJKLMNOP&issuer=fragfolio',
            message: '2段階認証を有効にしました',
          });
        })
      );

      const result = await enableTwoFactor();

      expect(result.success).toBe(true);
      expect(result.secret_key).toBe('ABCDEFGHIJKLMNOP');
      expect(result.qr_code).toContain('otpauth://totp/');
    });

    it('未認証の場合はエラーが返される', async () => {
      localStorage.removeItem('auth_token');

      await expect(enableTwoFactor()).rejects.toThrow('認証トークンが設定されていません');
    });
  });

  describe('confirmTwoFactor', () => {
    it('2段階認証の確認ができる', async () => {
      server.use(
        http.post('http://localhost:8002/api/auth/confirmed-two-factor-authentication', async ({ request }) => {
          const body = await request.json() as { code: string };

          if (body.code === '123456') {
            return HttpResponse.json({
              success: true,
              recovery_codes: ['code1', 'code2', 'code3'],
              message: '2段階認証を確認しました',
            });
          }

          return HttpResponse.json({
            success: false,
            message: '認証コードが無効です',
          }, { status: 422 });
        })
      );

      const result = await confirmTwoFactor('123456');

      expect(result.success).toBe(true);
      expect(result.recovery_codes).toEqual(['code1', 'code2', 'code3']);
    });

    it('無効なコードの場合はエラー', async () => {
      server.use(
        http.post('http://localhost:8002/api/auth/confirmed-two-factor-authentication', async ({ request }) => {
          const body = await request.json() as { code: string };

          return HttpResponse.json({
            success: false,
            message: '認証コードが無効です',
          }, { status: 422 });
        })
      );

      const result = await confirmTwoFactor('000000');

      expect(result.success).toBe(false);
      expect(result.message).toBe('認証コードが無効です');
    });
  });

  describe('disableTwoFactor', () => {
    it('2段階認証の無効化ができる', async () => {
      server.use(
        http.delete('http://localhost:8002/api/auth/two-factor-authentication', () => {
          return HttpResponse.json({
            success: true,
            message: '2段階認証を無効にしました',
          });
        })
      );

      const result = await disableTwoFactor();

      expect(result.success).toBe(true);
      expect(result.message).toBe('2段階認証を無効にしました');
    });
  });

  describe('getQrCode', () => {
    it('QRコードを取得できる', async () => {
      server.use(
        http.get('http://localhost:8002/api/auth/two-factor-qr-code', () => {
          return HttpResponse.text('<svg>QRCode</svg>'); // QRコードはSVGで返される
        }),
        http.get('http://localhost:8002/api/auth/two-factor-secret-key', () => {
          return HttpResponse.json({
            secret_key: 'ABCDEFGHIJKLMNOP'
          });
        })
      );

      const result = await getQrCode();

      expect(result.success).toBe(true);
      expect(result.qr_code_url).toBe('<svg>QRCode</svg>');
      expect(result.secret).toBe('ABCDEFGHIJKLMNOP');
    });

    it('2段階認証が無効な場合はエラー', async () => {
      server.use(
        http.get('http://localhost:8002/api/auth/two-factor-qr-code', () => {
          return HttpResponse.json({
            success: false,
            message: '2段階認証が有効化されていません',
          }, { status: 422 });
        })
      );

      const result = await getQrCode();

      expect(result.success).toBe(false);
      expect(result.message).toBe('QRコードの取得に失敗しました');
    });
  });

  describe('getRecoveryCodes', () => {
    it('リカバリーコードを取得できる', async () => {
      server.use(
        http.get('http://localhost:8002/api/auth/two-factor-recovery-codes', () => {
          return HttpResponse.json({
            recovery_codes: ['code1', 'code2', 'code3'],
          });
        })
      );

      const result = await getRecoveryCodes();

      expect(result.success).toBe(true);
      expect(result.recovery_codes).toEqual(['code1', 'code2', 'code3']);
    });
  });

  describe('regenerateRecoveryCodes', () => {
    it('リカバリーコードを再生成できる', async () => {
      server.use(
        http.post('http://localhost:8002/api/auth/two-factor-recovery-codes', () => {
          return HttpResponse.json({
            recovery_codes: ['new1', 'new2', 'new3'],
          });
        })
      );

      const result = await regenerateRecoveryCodes();

      expect(result.success).toBe(true);
      expect(result.recovery_codes).toEqual(['new1', 'new2', 'new3']);
      expect(result.message).toBe('リカバリーコードを再生成しました');
    });
  });

  describe('エラーハンドリング', () => {
    it('ネットワークエラーの場合は適切なエラーが投げられる', async () => {
      server.use(
        http.post('http://localhost:8002/api/auth/two-factor-authentication', () => {
          return HttpResponse.error();
        })
      );

      await expect(enableTwoFactor()).rejects.toThrow();
    });

    it('サーバーエラーの場合は適切なエラーが投げられる', async () => {
      server.use(
        http.post('http://localhost:8002/api/auth/two-factor-authentication', () => {
          return HttpResponse.json(
            { success: false, message: 'Internal Server Error' },
            { status: 500 }
          );
        })
      );

      const result = await enableTwoFactor();
      expect(result.success).toBe(false);
      expect(result.message).toBe('Internal Server Error');
    });

    it('認証トークンが設定されていない場合は適切なエラーが投げられる', async () => {
      localStorage.removeItem('auth_token');

      await expect(enableTwoFactor()).rejects.toThrow('認証トークンが設定されていません');
    });
  });

  describe('認証ヘッダー', () => {
    it('正しい認証ヘッダーが送信される', async () => {
      let requestHeaders: Headers | undefined;

      server.use(
        http.post('http://localhost:8002/api/auth/two-factor-authentication', ({ request }) => {
          requestHeaders = request.headers;
          return HttpResponse.json({ success: true });
        })
      );

      await enableTwoFactor();

      expect(requestHeaders?.get('Authorization')).toBe(`Bearer ${mockToken}`);
      expect(requestHeaders?.get('Content-Type')).toBe('application/json');
      expect(requestHeaders?.get('Accept')).toBe('application/json');
    });
  });
});