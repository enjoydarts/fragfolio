// 2FA (TOTP) 関連API

export interface TwoFactorStatus {
  two_factor_enabled: boolean;
}

export interface TwoFactorResponse {
  success: boolean;
  message?: string;
  qr_code?: string;
  secret_key?: string;
  recovery_codes?: string[];
  errors?: Record<string, string[]>;
}

const API_BASE_URL =
  import.meta.env.VITE_API_BASE_URL || 'http://localhost:8002';

export class TwoFactorAPI {
  /**
   * 2FA状態確認
   */
  static async getStatus(token: string): Promise<{
    enabled: boolean;
    confirmed: boolean;
    has_recovery_codes: boolean;
  }> {
    const response = await fetch(`${API_BASE_URL}/api/auth/two-factor-status`, {
      method: 'GET',
      headers: this.getHeaders(token),
    });

    if (!response.ok) {
      throw new Error('2FA状態の取得に失敗しました');
    }

    return response.json();
  }

  private static getHeaders(token: string): HeadersInit {
    return {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    };
  }

  /**
   * 2FAを有効化（秘密鍵とQRコードを生成）
   */
  static async enable(token: string): Promise<TwoFactorResponse> {
    const response = await fetch(
      `${API_BASE_URL}/api/auth/two-factor-authentication`,
      {
        method: 'POST',
        headers: this.getHeaders(token),
      }
    );

    if (!response.ok) {
      console.error('2FA enable failed:', response.status, response.statusText);
      if (response.status === 401) {
        throw new Error('認証が無効です。再ログインしてください。');
      }
    }

    return response.json();
  }

  /**
   * 2FAの有効化を確認（TOTP コードを検証）
   */
  static async confirm(
    token: string,
    code: string
  ): Promise<TwoFactorResponse> {
    const response = await fetch(
      `${API_BASE_URL}/api/auth/confirmed-two-factor-authentication`,
      {
        method: 'POST',
        headers: this.getHeaders(token),
        body: JSON.stringify({ code }),
      }
    );

    if (!response.ok) {
      console.error(
        '2FA confirm failed:',
        response.status,
        response.statusText
      );
      if (response.status === 401) {
        throw new Error('認証が無効です。再ログインしてください。');
      }
    }

    return response.json();
  }

  /**
   * 2FAを無効化
   */
  static async disable(token: string): Promise<TwoFactorResponse> {
    const response = await fetch(
      `${API_BASE_URL}/api/auth/two-factor-authentication`,
      {
        method: 'DELETE',
        headers: this.getHeaders(token),
      }
    );

    return response.json();
  }

  /**
   * QRコードを取得
   */
  static async getQRCode(token: string): Promise<string> {
    const response = await fetch(
      `${API_BASE_URL}/api/auth/two-factor-qr-code`,
      {
        method: 'GET',
        headers: this.getHeaders(token),
      }
    );

    if (!response.ok) {
      throw new Error('QRコードの取得に失敗しました');
    }

    return response.text(); // SVG形式のQRコード
  }

  /**
   * 秘密鍵を取得
   */
  static async getSecretKey(token: string): Promise<{ secret_key: string }> {
    const response = await fetch(
      `${API_BASE_URL}/api/auth/two-factor-secret-key`,
      {
        method: 'GET',
        headers: this.getHeaders(token),
      }
    );

    return response.json();
  }

  /**
   * リカバリコードを取得
   */
  static async getRecoveryCodes(
    token: string
  ): Promise<{ recovery_codes: string[] }> {
    const response = await fetch(
      `${API_BASE_URL}/api/auth/two-factor-recovery-codes`,
      {
        method: 'GET',
        headers: this.getHeaders(token),
      }
    );

    return response.json();
  }

  /**
   * リカバリコードを再生成
   */
  static async regenerateRecoveryCodes(
    token: string
  ): Promise<{ recovery_codes: string[] }> {
    const response = await fetch(
      `${API_BASE_URL}/api/auth/two-factor-recovery-codes`,
      {
        method: 'POST',
        headers: this.getHeaders(token),
      }
    );

    return response.json();
  }
}

// テスト用の関数エクスポート
export const enableTwoFactor = async (): Promise<TwoFactorResponse> => {
  const token = localStorage.getItem('auth_token');
  if (!token) {
    throw new Error('認証トークンが設定されていません');
  }
  return TwoFactorAPI.enable(token);
};

export const confirmTwoFactor = async (
  code: string
): Promise<TwoFactorResponse> => {
  const token = localStorage.getItem('auth_token');
  if (!token) {
    throw new Error('認証トークンが設定されていません');
  }
  return TwoFactorAPI.confirm(token, code);
};

export const disableTwoFactor = async (): Promise<TwoFactorResponse> => {
  const token = localStorage.getItem('auth_token');
  if (!token) {
    throw new Error('認証トークンが設定されていません');
  }
  return TwoFactorAPI.disable(token);
};

export const getQrCode = async (): Promise<{
  success: boolean;
  qr_code_url?: string;
  secret?: string;
  message?: string;
}> => {
  const token = localStorage.getItem('auth_token');
  if (!token) {
    throw new Error('認証トークンが設定されていません');
  }

  try {
    const qrCode = await TwoFactorAPI.getQRCode(token);
    const secretKey = await TwoFactorAPI.getSecretKey(token);
    return {
      success: true,
      qr_code_url: qrCode,
      secret: secretKey.secret_key,
    };
  } catch (error) {
    return {
      success: false,
      message:
        error instanceof Error
          ? error.message
          : '2段階認証が有効化されていません',
    };
  }
};

export const getRecoveryCodes = async (): Promise<{
  success: boolean;
  recovery_codes?: string[];
  message?: string;
}> => {
  const token = localStorage.getItem('auth_token');
  if (!token) {
    throw new Error('認証トークンが設定されていません');
  }

  try {
    const result = await TwoFactorAPI.getRecoveryCodes(token);
    return {
      success: true,
      recovery_codes: result.recovery_codes,
    };
  } catch (error) {
    return {
      success: false,
      message:
        error instanceof Error
          ? error.message
          : 'リカバリーコードの取得に失敗しました',
    };
  }
};

export const regenerateRecoveryCodes = async (): Promise<{
  success: boolean;
  recovery_codes?: string[];
  message?: string;
}> => {
  const token = localStorage.getItem('auth_token');
  if (!token) {
    throw new Error('認証トークンが設定されていません');
  }

  try {
    const result = await TwoFactorAPI.regenerateRecoveryCodes(token);
    return {
      success: true,
      recovery_codes: result.recovery_codes,
      message: 'リカバリーコードを再生成しました',
    };
  } catch (error) {
    return {
      success: false,
      message:
        error instanceof Error
          ? error.message
          : 'リカバリーコードの再生成に失敗しました',
    };
  }
};
