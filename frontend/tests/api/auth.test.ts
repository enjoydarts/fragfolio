import { describe, it, expect } from 'vitest';
import { AuthAPI } from '../../src/api/auth';

describe('AuthAPI', () => {
  describe('register', () => {
    it('正常にユーザー登録ができる', async () => {
      const result = await AuthAPI.register({
        name: 'テストユーザー',
        email: 'test@example.com',
        password: 'password123',
        password_confirmation: 'password123',
        language: 'ja',
        timezone: 'Asia/Tokyo',
      });

      expect(result.success).toBe(true);
      expect(result.user?.name).toBe('テストユーザー');
      expect(result.token).toBe('mock-jwt-token');
    });

    it('バリデーションエラーが正しく処理される', async () => {
      const result = await AuthAPI.register({
        name: 'テストユーザー',
        email: 'existing@example.com',
        password: 'password123',
        password_confirmation: 'password123',
      });

      expect(result.success).toBe(false);
      expect(result.errors?.email).toContain(
        'The email has already been taken.'
      );
    });
  });

  describe('login', () => {
    it('正常にログインができる', async () => {
      const result = await AuthAPI.login({
        email: 'test@example.com',
        password: 'password123',
      });

      expect(result.success).toBe(true);
      expect(result.user?.email).toBe('test@example.com');
      expect(result.token).toBe('mock-jwt-token');
    });

    it('認証失敗が正しく処理される', async () => {
      const result = await AuthAPI.login({
        email: 'invalid@example.com',
        password: 'wrong-password',
      });

      expect(result.success).toBe(false);
      expect(result.message).toBe(
        'メールアドレスまたはパスワードが正しくありません'
      );
    });
  });

  describe('logout', () => {
    it('正常にログアウトができる', async () => {
      const result = await AuthAPI.logout('mock-jwt-token');

      expect(result.success).toBe(true);
      expect(result.message).toBe('ログアウトしました');
    });
  });

  describe('me', () => {
    it('認証されたユーザー情報を取得できる', async () => {
      const result = await AuthAPI.me('mock-jwt-token');

      expect(result.success).toBe(true);
      expect(result.user?.id).toBe(1);
    });

    it('未認証でアクセスした場合エラーが返される', async () => {
      const result = await AuthAPI.me('invalid-token');

      expect(result.success).toBe(false);
      expect(result.message).toBe('認証が必要です');
    });
  });

  describe('updateProfile', () => {
    it('プロフィール更新ができる', async () => {
      const result = await AuthAPI.updateProfile('mock-jwt-token', {
        name: '更新されたユーザー名',
        bio: '新しい自己紹介',
        language: 'en',
        timezone: 'America/New_York',
        country: 'US',
      });

      expect(result.success).toBe(true);
      expect(result.user?.name).toBe('更新されたユーザー名');
      expect(result.user?.profile.bio).toBe('新しい自己紹介');
    });
  });

  describe('refreshToken', () => {
    it('トークンリフレッシュができる', async () => {
      const result = await AuthAPI.refreshToken('mock-jwt-token');

      expect(result.success).toBe(true);
      expect(result.token).toBe('new-refresh-token');
    });
  });

  describe('changePassword', () => {
    it('パスワード変更ができる', async () => {
      const result = await AuthAPI.changePassword('mock-jwt-token', {
        current_password: 'Password123!',
        new_password: 'NewPassword123!',
        new_password_confirmation: 'NewPassword123!',
      });

      expect(result.success).toBe(true);
      expect(result.message).toBe('パスワードが正常に変更されました');
    });

    it('現在のパスワードが間違っている場合はエラー', async () => {
      // MSWでエラーレスポンスをモック
      const result = await AuthAPI.changePassword('mock-jwt-token', {
        current_password: 'WrongPassword123!',
        new_password: 'NewPassword123!',
        new_password_confirmation: 'NewPassword123!',
      });

      expect(result.success).toBe(false);
      expect(result.message).toBe('現在のパスワードが正しくありません');
    });

    it('弱いパスワードの場合はバリデーションエラー', async () => {
      const result = await AuthAPI.changePassword('mock-jwt-token', {
        current_password: 'Password123!',
        new_password: 'weak',
        new_password_confirmation: 'weak',
      });

      expect(result.success).toBe(false);
      expect(result.errors?.new_password).toBeDefined();
    });

    it('パスワード確認が一致しない場合はバリデーションエラー', async () => {
      const result = await AuthAPI.changePassword('mock-jwt-token', {
        current_password: 'Password123!',
        new_password: 'NewPassword123!',
        new_password_confirmation: 'DifferentPassword123!',
      });

      expect(result.success).toBe(false);
      expect(result.errors?.new_password).toBeDefined();
    });
  });
});
