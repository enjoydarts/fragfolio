import { http, HttpResponse } from 'msw';

interface RegisterRequest {
  name: string;
  email: string;
  password: string;
  language?: string;
  timezone?: string;
}

interface LoginRequest {
  email: string;
  password: string;
}

interface ProfileUpdateRequest {
  name: string;
  language: string;
  timezone: string;
  bio?: string;
  date_of_birth?: string;
  gender?: string;
  country?: string;
}

// バックエンドAPIのモックハンドラー
export const handlers = [
  // 認証関連API
  http.post('http://localhost:8002/api/register', async ({ request }) => {
    const body = (await request.json()) as RegisterRequest;

    // バリデーションエラーのモック
    if (body.email === 'existing@example.com') {
      return HttpResponse.json(
        {
          success: false,
          message: 'バリデーションエラー',
          errors: {
            email: ['The email has already been taken.'],
          },
        },
        { status: 422 }
      );
    }

    return HttpResponse.json(
      {
        success: true,
        message: 'ユーザー登録が完了しました',
        user: {
          id: 1,
          name: body.name,
          email: body.email,
          profile: {
            language: body.language || 'ja',
            timezone: body.timezone || 'Asia/Tokyo',
            bio: null,
            date_of_birth: null,
            gender: null,
            country: null,
          },
          roles: ['user'],
        },
        token: 'mock-jwt-token',
      },
      { status: 201 }
    );
  }),

  http.post('http://localhost:8002/api/login', async ({ request }) => {
    const body = (await request.json()) as LoginRequest;

    // モック用の認証失敗パターン
    if (
      body.email === 'invalid@example.com' ||
      body.password === 'wrong-password'
    ) {
      return HttpResponse.json(
        {
          success: false,
          message: 'メールアドレスまたはパスワードが正しくありません',
        },
        { status: 401 }
      );
    }

    return HttpResponse.json({
      success: true,
      message: 'ログインしました',
      user: {
        id: 1,
        name: 'テストユーザー',
        email: body.email,
        profile: {
          language: 'ja',
          timezone: 'Asia/Tokyo',
          bio: 'テスト用の自己紹介',
          date_of_birth: null,
          gender: null,
          country: 'JP',
        },
        roles: ['user'],
      },
      token: 'mock-jwt-token',
    });
  }),

  http.post('http://localhost:8002/api/logout', () => {
    return HttpResponse.json({
      success: true,
      message: 'ログアウトしました',
    });
  }),

  http.get('http://localhost:8002/api/me', ({ request }) => {
    const authHeader = request.headers.get('Authorization');

    if (
      !authHeader ||
      !authHeader.startsWith('Bearer ') ||
      authHeader.includes('invalid-token')
    ) {
      return HttpResponse.json(
        {
          success: false,
          message: '認証が必要です',
        },
        { status: 401 }
      );
    }

    return HttpResponse.json({
      success: true,
      user: {
        id: 1,
        name: 'テストユーザー',
        email: 'test@example.com',
        profile: {
          language: 'ja',
          timezone: 'Asia/Tokyo',
          bio: 'テスト用の自己紹介',
          date_of_birth: null,
          gender: null,
          country: 'JP',
        },
        roles: ['user'],
      },
    });
  }),

  http.put('http://localhost:8002/api/profile', async ({ request }) => {
    const authHeader = request.headers.get('Authorization');

    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return HttpResponse.json(
        {
          success: false,
          message: '認証が必要です',
        },
        { status: 401 }
      );
    }

    const body = (await request.json()) as ProfileUpdateRequest;

    return HttpResponse.json({
      success: true,
      message: 'プロフィールを更新しました',
      user: {
        id: 1,
        name: body.name || 'テストユーザー',
        email: 'test@example.com',
        profile: {
          language: body.language || 'ja',
          timezone: body.timezone || 'Asia/Tokyo',
          bio: body.bio || 'テスト用の自己紹介',
          date_of_birth: body.date_of_birth || null,
          gender: body.gender || null,
          country: body.country || 'JP',
        },
        roles: ['user'],
      },
    });
  }),

  http.post('http://localhost:8002/api/refresh', ({ request }) => {
    const authHeader = request.headers.get('Authorization');

    if (
      !authHeader ||
      !authHeader.startsWith('Bearer ') ||
      authHeader.includes('invalid-token')
    ) {
      return HttpResponse.json(
        {
          success: false,
          message: '認証が必要です',
        },
        { status: 401 }
      );
    }

    return HttpResponse.json({
      success: true,
      message: 'トークンを更新しました',
      token: 'new-mock-jwt-token',
      user: {
        id: 1,
        name: 'テストユーザー',
        email: 'test@example.com',
        profile: {
          language: 'ja',
          timezone: 'Asia/Tokyo',
          bio: 'テスト用の自己紹介',
          date_of_birth: null,
          gender: null,
          country: 'JP',
        },
        roles: ['user'],
      },
    });
  }),

  // TwoFactor API
  http.post(
    'http://localhost:8002/api/auth/two-factor-authentication',
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
        secret_key: 'ABCDEFGHIJKLMNOP',
        qr_code:
          'otpauth://totp/fragfolio:test@example.com?secret=ABCDEFGHIJKLMNOP&issuer=fragfolio',
        message: '2段階認証を有効にしました',
      });
    }
  ),

  http.post(
    'http://localhost:8002/api/auth/confirmed-two-factor-authentication',
    async ({ request }) => {
      const body = (await request.json()) as { code: string };

      if (body.code === '123456') {
        return HttpResponse.json({
          success: true,
          recovery_codes: ['code1', 'code2', 'code3'],
          message: '2段階認証を確認しました',
        });
      }

      return HttpResponse.json(
        {
          success: false,
          message: '認証コードが無効です',
        },
        { status: 422 }
      );
    }
  ),

  http.delete(
    'http://localhost:8002/api/auth/two-factor-authentication',
    () => {
      return HttpResponse.json({
        success: true,
        message: '2段階認証を無効にしました',
      });
    }
  ),

  http.get(
    'http://localhost:8002/api/auth/two-factor-secret-key',
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
        qr_code_url:
          'otpauth://totp/fragfolio:test@example.com?secret=ABCDEFGHIJKLMNOP&issuer=fragfolio',
        secret: 'ABCDEFGHIJKLMNOP',
      });
    }
  ),

  // QRコード取得（成功パターン）
  http.get(
    'http://localhost:8002/api/auth/two-factor-qr-code',
    ({ request }) => {
      const authHeader = request.headers.get('Authorization');

      if (!authHeader || !authHeader.startsWith('Bearer ')) {
        return HttpResponse.json(
          {
            success: false,
            message: '認証が必要です',
          },
          { status: 401 }
        );
      }

      return HttpResponse.text('<svg>QRCode</svg>');
    }
  ),

  http.get('http://localhost:8002/api/auth/two-factor-recovery-codes', () => {
    return HttpResponse.json({
      recovery_codes: ['code1', 'code2', 'code3'],
    });
  }),

  http.post('http://localhost:8002/api/auth/two-factor-recovery-codes', () => {
    return HttpResponse.json({
      recovery_codes: ['new1', 'new2', 'new3'],
    });
  }),

  // WebAuthn API - Registration Options (with auth required)
  http.post(
    'http://localhost:8002/api/auth/webauthn/register/options',
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
        options: {
          challenge: 'test-challenge',
          rp: {
            name: 'fragfolio',
            id: 'localhost',
          },
          user: {
            id: 'test-user-id',
            name: 'test@example.com',
            displayName: 'Test User',
          },
          pubKeyCredParams: [{ type: 'public-key', alg: -7 }],
          timeout: 60000,
          attestation: 'none',
        },
      });
    }
  ),

  // WebAuthn Registration (with auth required)
  http.post(
    'http://localhost:8002/api/auth/webauthn/register',
    async ({ request }) => {
      const authHeader = request.headers.get('Authorization');

      if (!authHeader || !authHeader.startsWith('Bearer ')) {
        return HttpResponse.json(
          { success: false, message: '認証が必要です' },
          { status: 401 }
        );
      }

      const body = (await request.json()) as { id?: string };

      return HttpResponse.json({
        success: true,
        message: 'WebAuthnキーを登録しました',
        credential: {
          id: body?.id || 'credential-id',
          alias: 'Test Credential',
          created_at: '2024-01-01T00:00:00Z',
        },
      });
    }
  ),

  http.get(
    'http://localhost:8002/api/auth/webauthn/credentials',
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
        credentials: [
          {
            id: 'test-credential-id',
            alias: 'Test Credential',
            last_used_at: '2023-01-01T00:00:00.000Z',
            created_at: '2023-01-01T00:00:00.000Z',
            is_active: true,
          },
        ],
      });
    }
  ),

  // Update credential alias
  http.put(
    'http://localhost:8002/api/auth/webauthn/credentials/:id',
    async ({ params, request }) => {
      const authHeader = request.headers.get('Authorization');

      if (!authHeader || !authHeader.startsWith('Bearer ')) {
        return HttpResponse.json(
          { success: false, message: '認証が必要です' },
          { status: 401 }
        );
      }

      const body = (await request.json()) as { alias: string };

      if (!body.alias || body.alias.trim() === '') {
        return HttpResponse.json(
          {
            success: false,
            message: 'エイリアスは必須です',
            errors: {
              alias: ['エイリアスは必須です'],
            },
          },
          { status: 422 }
        );
      }

      return HttpResponse.json({
        success: true,
        message: 'エイリアスを更新しました',
        credential: {
          id: params.id,
          alias: body.alias,
          created_at: '2024-01-01T00:00:00Z',
          disabled_at: null,
        },
      });
    }
  ),

  // Delete credential (this is the actual endpoint used by the implementation)
  http.delete(
    'http://localhost:8002/api/auth/webauthn/credentials/:id',
    ({ request, params }) => {
      const authHeader = request.headers.get('Authorization');

      if (!authHeader || !authHeader.startsWith('Bearer ')) {
        return HttpResponse.json(
          { success: false, message: '認証が必要です' },
          { status: 401 }
        );
      }

      if (params.id === 'non-existent-id') {
        return HttpResponse.json(
          {
            success: false,
            message: 'クレデンシャルが見つかりません',
          },
          { status: 404 }
        );
      }

      return HttpResponse.json({
        success: true,
        message: 'WebAuthnキーを削除しました',
      });
    }
  ),

  // Disable credential
  http.post(
    'http://localhost:8002/api/auth/webauthn/credentials/:id/disable',
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
        message: 'WebAuthnキーを無効化しました',
      });
    }
  ),

  // Enable credential
  http.post(
    'http://localhost:8002/api/auth/webauthn/credentials/:id/enable',
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
        message: 'WebAuthnキーを有効化しました',
      });
    }
  ),
];
