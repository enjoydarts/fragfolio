import { http, HttpResponse } from 'msw';

// バックエンドAPIのモックハンドラー
export const handlers = [
  // 認証関連API
  http.post('http://localhost:8002/api/auth/register', async ({ request }) => {
    const body = await request.json();

    // バリデーションエラーのモック
    if (body.email === 'existing@example.com') {
      return HttpResponse.json({
        success: false,
        message: 'バリデーションエラー',
        errors: {
          email: ['The email has already been taken.'],
        },
      }, { status: 422 });
    }

    return HttpResponse.json({
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
    }, { status: 201 });
  }),

  http.post('http://localhost:8002/api/auth/login', async ({ request }) => {
    const body = await request.json();

    // モック用の認証失敗パターン
    if (body.email === 'invalid@example.com' || body.password === 'wrong-password') {
      return HttpResponse.json({
        success: false,
        message: 'メールアドレスまたはパスワードが正しくありません',
      }, { status: 401 });
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

  http.post('http://localhost:8002/api/auth/logout', () => {
    return HttpResponse.json({
      success: true,
      message: 'ログアウトしました',
    });
  }),

  http.get('http://localhost:8002/api/auth/me', ({ request }) => {
    const authHeader = request.headers.get('Authorization');

    if (!authHeader || !authHeader.startsWith('Bearer ') || authHeader.includes('invalid-token')) {
      return HttpResponse.json({
        success: false,
        message: '認証が必要です',
      }, { status: 401 });
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

  http.put('http://localhost:8002/api/auth/profile', async ({ request }) => {
    const authHeader = request.headers.get('Authorization');

    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return HttpResponse.json({
        success: false,
        message: '認証が必要です',
      }, { status: 401 });
    }

    const body = await request.json();

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

  http.post('http://localhost:8002/api/auth/refresh', ({ request }) => {
    const authHeader = request.headers.get('Authorization');

    if (!authHeader || !authHeader.startsWith('Bearer ') || authHeader.includes('invalid-token')) {
      return HttpResponse.json({
        success: false,
        message: '認証が必要です',
      }, { status: 401 });
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
];