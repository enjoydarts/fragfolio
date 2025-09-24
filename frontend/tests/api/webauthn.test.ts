import { describe, it, expect, beforeEach } from 'vitest';
import { http, HttpResponse } from 'msw';
import { server } from '../mocks/server';
import {
  getRegistrationOptions,
  registerCredential,
  getCredentials,
  deleteCredential,
  updateCredentialAlias,
  disableCredential,
  enableCredential,
} from '../../src/api/webauthn';

const mockToken = 'mock-jwt-token';

describe('WebAuthn API', () => {
  beforeEach(() => {
    localStorage.setItem('auth_token', mockToken);
    server.resetHandlers();
  });

  afterEach(() => {
    localStorage.clear();
  });

  describe('getRegistrationOptions', () => {
    it('WebAuthn登録オプションを取得できる', async () => {
      const result = await getRegistrationOptions();

      expect(result.success).toBe(true);
      expect(result.challenge).toBe('Y2hhbGxlbmdl');
      expect(result.user).toMatchObject({ name: 'test@example.com' });
      expect(Array.isArray(result.pubKeyCredParams)).toBe(true);
    });
  });

  describe('registerCredential', () => {
    it('WebAuthnクレデンシャルを登録できる', async () => {
      const mockCredential = {
        id: 'credential-id',
        rawId: new ArrayBuffer(32),
        type: 'public-key' as const,
        response: {
          attestationObject: new ArrayBuffer(100),
          clientDataJSON: new ArrayBuffer(50),
        },
      };

      server.use(
        http.post(
          'http://localhost:8002/api/auth/webauthn/register',
          ({ request }) => {
            const authHeader = request.headers.get('Authorization');
            if (!authHeader || !authHeader.startsWith('Bearer ')) {
              return HttpResponse.json(
                { success: false, message: '認証が必要です' },
                { status: 401 }
              );
            }

            return HttpResponse.json({
              success: true,
              message: 'WebAuthn key registered successfully',
              messageKey: 'settings.security.webauthn.register_success',
            });
          }
        )
      );

      const result = await registerCredential(mockCredential);

      expect(result.success).toBe(true);
      expect(result.message).toBe('WebAuthn key registered successfully');
      expect(result.messageKey).toBe(
        'settings.security.webauthn.register_success'
      );
    });
  });

  describe('getCredentials', () => {
    it('WebAuthnクレデンシャル一覧を取得できる', async () => {
      const mockCredentials = [
        {
          id: 'cred1',
          alias: 'iPhone Touch ID',
          created_at: '2024-01-01T00:00:00Z',
          disabled_at: null,
        },
        {
          id: 'cred2',
          alias: 'Security Key',
          created_at: '2024-01-02T00:00:00Z',
          disabled_at: null,
        },
      ];

      server.use(
        http.get('http://localhost:8002/api/auth/webauthn/credentials', () => {
          return HttpResponse.json({
            success: true,
            credentials: mockCredentials,
          });
        })
      );

      const result = await getCredentials();

      expect(result.success).toBe(true);
      expect(result.credentials).toHaveLength(2);
      expect(result.credentials[0].alias).toBe('iPhone Touch ID');
    });
  });

  describe('deleteCredential', () => {
    it('WebAuthnクレデンシャルを削除できる', async () => {
      const credentialId = 'test-credential-id';

      const result = await deleteCredential(credentialId);

      expect(result.success).toBe(true);
      expect(result.message).toBe('WebAuthn key deleted successfully');
      expect(result.messageKey).toBe(
        'settings.security.webauthn.delete_success'
      );
    });

    it('存在しないクレデンシャルの削除でエラー', async () => {
      const credentialId = 'non-existent-id';

      server.use(
        http.delete(
          `http://localhost:8002/api/auth/webauthn/credentials/${credentialId}`,
          () => {
            return HttpResponse.json(
              {
                success: false,
                message: 'Credential not found',
                messageKey: 'settings.security.webauthn.delete_failed',
              },
              { status: 404 }
            );
          }
        )
      );

      const result = await deleteCredential(credentialId);

      expect(result.success).toBe(false);
      expect(result.message).toBe('WebAuthn credential not found');
      expect(result.messageKey).toBe(
        'settings.security.webauthn.delete_failed'
      );
    });
  });

  describe('updateCredentialAlias', () => {
    it('WebAuthnクレデンシャルのエイリアスを更新できる', async () => {
      const credentialId = 'test-credential-id';
      const newAlias = 'New Security Key';

      server.use(
        http.put(
          `http://localhost:8002/api/auth/webauthn/credentials/${credentialId}`,
          async ({ request }) => {
            const body = (await request.json()) as { alias: string };

            return HttpResponse.json({
              success: true,
              credential: {
                id: credentialId,
                alias: body.alias,
                created_at: '2024-01-01T00:00:00Z',
                disabled_at: null,
              },
              message: 'Alias updated successfully',
              messageKey: 'settings.security.webauthn.alias_update_success',
            });
          }
        )
      );

      const result = await updateCredentialAlias(credentialId, newAlias);

      expect(result.success).toBe(true);
      expect(result.credential.alias).toBe(newAlias);
      expect(result.message).toBe('Alias updated successfully');
      expect(result.messageKey).toBe(
        'settings.security.webauthn.alias_update_success'
      );
    });

    it('空のエイリアスでバリデーションエラー', async () => {
      const credentialId = 'test-credential-id';

      server.use(
        http.put(
          `http://localhost:8002/api/auth/webauthn/credentials/${credentialId}`,
          () => {
            return HttpResponse.json(
              {
                success: false,
                message: 'Alias is required',
                messageKey: 'settings.security.webauthn.alias_required',
                errors: {
                  alias: ['Alias is required'],
                },
              },
              { status: 422 }
            );
          }
        )
      );

      const result = await updateCredentialAlias(credentialId, '');

      expect(result.success).toBe(false);
      expect(result.message).toBe('Alias is required');
      expect(result.messageKey).toBe(
        'settings.security.webauthn.alias_required'
      );
    });
  });

  describe('disableCredential', () => {
    it('WebAuthnクレデンシャルを無効化できる', async () => {
      const credentialId = 'test-credential-id';

      const result = await disableCredential(credentialId);

      expect(result.success).toBe(true);
      expect(result.message).toBe('WebAuthn key disabled successfully');
      expect(result.messageKey).toBe(
        'settings.security.webauthn.disable_success'
      );
    });
  });

  describe('enableCredential', () => {
    it('WebAuthnクレデンシャルを有効化できる', async () => {
      const credentialId = 'test-credential-id';

      server.use(
        http.post(
          `http://localhost:8002/api/auth/webauthn/credentials/${credentialId}/enable`,
          () => {
            return HttpResponse.json({
              success: true,
              message: 'WebAuthn key enabled successfully',
              messageKey: 'settings.security.webauthn.enable_success',
            });
          }
        )
      );

      const result = await enableCredential(credentialId);

      expect(result.success).toBe(true);
      expect(result.message).toBe('WebAuthn key enabled successfully');
      expect(result.messageKey).toBe(
        'settings.security.webauthn.enable_success'
      );
    });
  });

  describe('エラーハンドリング', () => {
    it('ネットワークエラーの場合は適切なエラーが投げられる', async () => {
      server.use(
        http.post(
          'http://localhost:8002/api/auth/webauthn/register/options',
          () => {
            return HttpResponse.error();
          }
        )
      );

      await expect(getRegistrationOptions()).rejects.toThrow();
    });

    it('サーバーエラーの場合は適切なエラーが投げられる', async () => {
      server.use(
        http.get('http://localhost:8002/api/auth/webauthn/credentials', () => {
          return new Response(
            JSON.stringify({
              success: false,
              message: 'Internal Server Error',
            }),
            {
              status: 500,
              statusText: 'Internal Server Error',
              headers: { 'Content-Type': 'application/json' },
            }
          );
        })
      );

      await expect(getCredentials()).rejects.toThrow(
        'Failed to get WebAuthn credentials list'
      );
    });

    it('認証トークンが設定されていない場合は適切なエラーが投げられる', async () => {
      localStorage.removeItem('auth_token');

      await expect(getCredentials()).rejects.toThrow(
        'Authentication token is not set'
      );
    });
  });

  describe('認証ヘッダー', () => {
    it('認証が必要なエンドポイントで正しい認証ヘッダーが送信される', async () => {
      let requestHeaders: Headers | undefined;

      server.use(
        http.get(
          'http://localhost:8002/api/auth/webauthn/credentials',
          ({ request }) => {
            requestHeaders = request.headers;
            return HttpResponse.json({ success: true, credentials: [] });
          }
        )
      );

      await getCredentials();

      expect(requestHeaders?.get('Authorization')).toBe(`Bearer ${mockToken}`);
      expect(requestHeaders?.get('Content-Type')).toBe('application/json');
      expect(requestHeaders?.get('Accept')).toBe('application/json');
    });

    it('WebAuthn登録オプション取得では認証ヘッダーが送信される', async () => {
      let requestHeaders: Headers | undefined;

      server.use(
        http.post(
          'http://localhost:8002/api/auth/webauthn/register/options',
          ({ request }) => {
            requestHeaders = request.headers;
            return HttpResponse.json({
              success: true,
              options: {
                challenge: 'Y2hhbGxlbmdl',
                rp: { name: 'fragfolio', id: 'localhost' },
                user: {
                  id: 'dGVzdHVzZXI=',
                  name: 'test@example.com',
                  displayName: 'Test User',
                },
                pubKeyCredParams: [{ type: 'public-key', alg: -7 }],
                timeout: 60000,
                attestation: 'none',
              },
            });
          }
        )
      );

      const result = await getRegistrationOptions();

      expect(requestHeaders?.get('Authorization')).toBe(`Bearer ${mockToken}`);
      expect(result.success).toBe(true);
    });
  });

  describe('データ変換', () => {
    it('ArrayBufferを正しくBase64に変換する', async () => {
      const mockCredential = {
        id: 'credential-id',
        rawId: new Uint8Array([1, 2, 3, 4]).buffer,
        type: 'public-key' as const,
        response: {
          attestationObject: new Uint8Array([5, 6, 7, 8]).buffer,
          clientDataJSON: new Uint8Array([9, 10, 11, 12]).buffer,
        },
      };

      let sentData: unknown;

      server.use(
        http.post(
          'http://localhost:8002/api/auth/webauthn/register',
          async ({ request }) => {
            sentData = await request.json();
            return HttpResponse.json({ success: true });
          }
        )
      );

      await registerCredential(mockCredential);

      // ArrayBufferがBase64に変換されていることを確認
      expect(typeof sentData.rawId).toBe('string');
      expect(typeof sentData.response.attestationObject).toBe('string');
      expect(typeof sentData.response.clientDataJSON).toBe('string');
    });
  });
});
