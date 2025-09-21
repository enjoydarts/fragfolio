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
    const response = await fetch(`${API_BASE_URL}/api/user/two-factor-authentication`, {
      method: 'POST',
      headers: this.getHeaders(token),
    });

    return response.json();
  }

  /**
   * 2FAの有効化を確認（TOTP コードを検証）
   */
  static async confirm(token: string, code: string): Promise<TwoFactorResponse> {
    const response = await fetch(`${API_BASE_URL}/api/user/confirmed-two-factor-authentication`, {
      method: 'POST',
      headers: this.getHeaders(token),
      body: JSON.stringify({ code }),
    });

    return response.json();
  }

  /**
   * 2FAを無効化
   */
  static async disable(token: string): Promise<TwoFactorResponse> {
    const response = await fetch(`${API_BASE_URL}/api/user/two-factor-authentication`, {
      method: 'DELETE',
      headers: this.getHeaders(token),
    });

    return response.json();
  }

  /**
   * QRコードを取得
   */
  static async getQRCode(token: string): Promise<string> {
    const response = await fetch(`${API_BASE_URL}/api/user/two-factor-qr-code`, {
      method: 'GET',
      headers: this.getHeaders(token),
    });

    if (!response.ok) {
      throw new Error('QRコードの取得に失敗しました');
    }

    return response.text(); // SVG形式のQRコード
  }

  /**
   * 秘密鍵を取得
   */
  static async getSecretKey(token: string): Promise<{ secret_key: string }> {
    const response = await fetch(`${API_BASE_URL}/api/user/two-factor-secret-key`, {
      method: 'GET',
      headers: this.getHeaders(token),
    });

    return response.json();
  }

  /**
   * リカバリコードを取得
   */
  static async getRecoveryCodes(token: string): Promise<{ recovery_codes: string[] }> {
    const response = await fetch(`${API_BASE_URL}/api/user/two-factor-recovery-codes`, {
      method: 'GET',
      headers: this.getHeaders(token),
    });

    return response.json();
  }

  /**
   * リカバリコードを再生成
   */
  static async regenerateRecoveryCodes(token: string): Promise<{ recovery_codes: string[] }> {
    const response = await fetch(`${API_BASE_URL}/api/user/two-factor-recovery-codes`, {
      method: 'POST',
      headers: this.getHeaders(token),
    });

    return response.json();
  }
}