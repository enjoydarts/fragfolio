import { http, HttpResponse } from 'msw';

export const handlers = [
  // Register API
  http.post('http://localhost:8002/api/register', async ({ request }) => {
    const body = await request.json();
    if (body.email === 'existing@example.com') {
      return HttpResponse.json(
        {
          success: false,
          message: 'Validation error',
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
        user: {
          id: 1,
          name: body.name,
          email: body.email,
          email_verified_at: null,
          created_at: '2024-01-01T00:00:00Z',
          updated_at: '2024-01-01T00:00:00Z',
          profile: {
            bio: null,
            language: body.language || 'ja',
            timezone: body.timezone || 'Asia/Tokyo',
            date_of_birth: null,
            gender: null,
            country: null,
          },
          roles: ['user'],
          two_factor_enabled: false,
        },
        token: 'mock-jwt-token',
        message: 'Registration successful',
      },
      { status: 201 }
    );
  }),

  // Login API
  http.post('http://localhost:8002/api/login', async ({ request }) => {
    const body = await request.json();
    if (
      body.email === 'invalid@example.com' &&
      body.password === 'wrong-password'
    ) {
      return HttpResponse.json(
        {
          success: false,
          message: 'メールアドレスまたはパスワードが正しくありません',
        },
        { status: 422 }
      );
    }
    return HttpResponse.json({
      success: true,
      user: {
        id: 1,
        name: 'Test User',
        email: body.email,
        email_verified_at: '2024-01-01T00:00:00Z',
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
        profile: {
          bio: null,
          language: 'ja',
          timezone: 'Asia/Tokyo',
          date_of_birth: null,
          gender: null,
          country: null,
        },
        roles: ['user'],
        two_factor_enabled: false,
      },
      token: 'mock-jwt-token',
      message: 'Login successful',
    });
  }),

  // Logout API
  http.post('http://localhost:8002/api/logout', () => {
    return HttpResponse.json({
      success: true,
      message: 'ログアウトしました',
    });
  }),

  // Auth API
  http.get('http://localhost:8002/api/auth/me', ({ request }) => {
    const authHeader = request.headers.get('authorization');
    if (
      !authHeader ||
      !authHeader.startsWith('Bearer ') ||
      authHeader === 'Bearer invalid-token'
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
        name: 'Test User',
        email: 'test@example.com',
        email_verified_at: '2024-01-01T00:00:00Z',
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
        profile: {
          bio: null,
          language: 'ja',
          timezone: 'Asia/Tokyo',
          date_of_birth: null,
          gender: null,
          country: null,
        },
        roles: ['user'],
        two_factor_enabled: false,
      },
    });
  }),

  http.put('http://localhost:8002/api/auth/profile', () => {
    return HttpResponse.json({
      success: true,
      user: {
        id: 1,
        name: '更新されたユーザー名',
        email: 'test@example.com',
        email_verified_at: '2024-01-01T00:00:00Z',
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
        profile: {
          bio: '新しい自己紹介',
          language: 'en',
          timezone: 'America/New_York',
          date_of_birth: null,
          gender: null,
          country: 'US',
        },
        roles: ['user'],
        two_factor_enabled: false,
      },
      message: 'Profile updated successfully',
    });
  }),

  http.post('http://localhost:8002/api/auth/refresh', () => {
    return HttpResponse.json({
      success: true,
      user: {
        id: 1,
        name: 'Test User',
        email: 'test@example.com',
        email_verified_at: '2024-01-01T00:00:00Z',
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
        profile: {
          bio: null,
          language: 'ja',
          timezone: 'Asia/Tokyo',
          date_of_birth: null,
          gender: null,
          country: null,
        },
        roles: ['user'],
        two_factor_enabled: false,
      },
      token: 'new-refresh-token',
      message: 'Token refreshed successfully',
    });
  }),

  // Password Change API
  http.put('http://localhost:8002/api/auth/password', async ({ request }) => {
    const body = await request.json() as {
      current_password: string;
      new_password: string;
      new_password_confirmation: string;
    };

    // 現在のパスワードが間違っている場合
    if (body.current_password === 'WrongPassword123!') {
      return HttpResponse.json(
        {
          success: false,
          message: '現在のパスワードが正しくありません',
        },
        { status: 422 }
      );
    }

    // パスワードが弱い場合
    if (body.new_password === 'weak') {
      return HttpResponse.json(
        {
          success: false,
          message: 'パスワードに正しい形式を指定してください。',
          errors: {
            new_password: ['パスワードに正しい形式を指定してください。'],
          },
        },
        { status: 422 }
      );
    }

    // パスワード確認が一致しない場合
    if (body.new_password !== body.new_password_confirmation) {
      return HttpResponse.json(
        {
          success: false,
          message: 'パスワードの確認が一致しません',
          errors: {
            new_password: ['パスワードの確認が一致しません'],
          },
        },
        { status: 422 }
      );
    }

    // 正常な場合
    return HttpResponse.json({
      success: true,
      message: 'パスワードが正常に変更されました',
    });
  }),

  // Two Factor API
  http.post('http://localhost:8002/api/two-factor/enable', () => {
    return HttpResponse.json({
      success: true,
      secret: 'JBSWY3DPEHPK3PXP',
      qr_code_url:
        'otpauth://totp/fragfolio:test@example.com?secret=JBSWY3DPEHPK3PXP&issuer=fragfolio',
    });
  }),

  http.post('http://localhost:8002/api/two-factor/confirm', () => {
    return HttpResponse.json({
      success: true,
      recovery_codes: [
        '12345678',
        '87654321',
        '11223344',
        '44332211',
        '55667788',
        '88776655',
        '99001122',
        '22110099',
      ],
    });
  }),

  http.delete('http://localhost:8002/api/two-factor/disable', () => {
    return HttpResponse.json({
      success: true,
      message: 'Two-factor authentication disabled',
    });
  }),

  http.get('http://localhost:8002/api/two-factor/qr-code', () => {
    return HttpResponse.json({
      success: true,
      qr_code_url:
        'otpauth://totp/fragfolio:test@example.com?secret=JBSWY3DPEHPK3PXP&issuer=fragfolio',
      secret: 'JBSWY3DPEHPK3PXP',
    });
  }),

  http.get('http://localhost:8002/api/two-factor/recovery-codes', () => {
    return HttpResponse.json({
      success: true,
      recovery_codes: ['12345678', '87654321', '11223344', '44332211'],
    });
  }),

  http.post('http://localhost:8002/api/two-factor/recovery-codes', () => {
    return HttpResponse.json({
      success: true,
      recovery_codes: ['98765432', '23456789', '34567890', '45678901'],
    });
  }),

  // WebAuthn API
  http.get('http://localhost:8002/api/webauthn/registration-options', () => {
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
        pubKeyCredParams: [
          { type: 'public-key', alg: -7 },
          { type: 'public-key', alg: -257 },
        ],
        timeout: 60000,
        attestation: 'none',
      },
    });
  }),

  http.post('http://localhost:8002/api/webauthn/register', () => {
    return HttpResponse.json({
      success: true,
      message: 'WebAuthn credential registered successfully',
    });
  }),

  http.get('http://localhost:8002/api/webauthn/credentials', () => {
    return HttpResponse.json({
      success: true,
      credentials: [
        {
          id: 'credential-1',
          alias: 'Security Key 1',
          created_at: '2024-01-01T00:00:00Z',
          last_used_at: '2024-01-02T00:00:00Z',
          disabled_at: null,
        },
      ],
    });
  }),

  http.put('http://localhost:8002/api/webauthn/credentials/:id/alias', () => {
    return HttpResponse.json({
      success: true,
      message: 'Credential alias updated successfully',
    });
  }),

  http.put('http://localhost:8002/api/webauthn/credentials/:id/enable', () => {
    return HttpResponse.json({
      success: true,
      message: 'Credential enabled successfully',
    });
  }),

  http.put('http://localhost:8002/api/webauthn/credentials/:id/disable', () => {
    return HttpResponse.json({
      success: true,
      message: 'Credential disabled successfully',
    });
  }),

  http.delete('http://localhost:8002/api/webauthn/credentials/:id', () => {
    return HttpResponse.json({
      success: true,
      message: 'Credential deleted successfully',
    });
  }),
];
