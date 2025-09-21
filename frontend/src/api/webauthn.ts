// WebAuthn (FIDO2) 関連API

export interface WebAuthnCredential {
  id: string;
  alias?: string;
  created_at: string;
  disabled_at?: string;
}

export interface WebAuthnResponse {
  success: boolean;
  message?: string;
  credentials?: WebAuthnCredential[];
  errors?: Record<string, string[]>;
}

export interface WebAuthnRegistrationOptions {
  challenge: string;
  rp: {
    name: string;
    id: string;
  };
  user: {
    id: string;
    name: string;
    displayName: string;
  };
  pubKeyCredParams: Array<{
    type: string;
    alg: number;
  }>;
  timeout: number;
  attestation: string;
  excludeCredentials: Array<{
    id: string;
    type: string;
  }>;
}

export interface WebAuthnLoginOptions {
  challenge: string;
  timeout: number;
  rpId: string;
  allowCredentials?: Array<{
    id: string;
    type: string;
  }>;
}

const API_BASE_URL =
  import.meta.env.VITE_API_BASE_URL || 'http://localhost:8002';

export class WebAuthnAPI {
  private static getHeaders(token?: string): HeadersInit {
    const headers: HeadersInit = {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    };

    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    return headers;
  }

  /**
   * WebAuthn登録のオプションを取得
   */
  static async getRegistrationOptions(token: string): Promise<WebAuthnRegistrationOptions> {
    const response = await fetch(`${API_BASE_URL}/api/auth/webauthn/register/options`, {
      method: 'POST',
      headers: this.getHeaders(token),
    });

    if (!response.ok) {
      throw new Error('WebAuthn登録オプションの取得に失敗しました');
    }

    return response.json();
  }

  /**
   * WebAuthn認証器を登録
   */
  static async registerCredential(
    token: string,
    credential: Record<string, unknown>,
    alias?: string
  ): Promise<WebAuthnResponse> {
    const response = await fetch(`${API_BASE_URL}/api/auth/webauthn/register`, {
      method: 'POST',
      headers: this.getHeaders(token),
      body: JSON.stringify({
        ...credential,
        alias,
      }),
    });

    return response.json();
  }

  /**
   * WebAuthnログインのオプションを取得
   */
  static async getLoginOptions(email?: string): Promise<WebAuthnLoginOptions> {
    const response = await fetch(`${API_BASE_URL}/api/auth/webauthn/login/options`, {
      method: 'POST',
      headers: this.getHeaders(),
      body: JSON.stringify(email ? { email } : {}),
    });

    if (!response.ok) {
      throw new Error('WebAuthnログインオプションの取得に失敗しました');
    }

    return response.json();
  }

  /**
   * WebAuthnでログイン
   */
  static async login(credential: Record<string, unknown>): Promise<WebAuthnResponse> {
    const response = await fetch(`${API_BASE_URL}/api/auth/webauthn/login`, {
      method: 'POST',
      headers: this.getHeaders(),
      body: JSON.stringify(credential),
    });

    return response.json();
  }

  /**
   * 登録済みWebAuthn認証器の一覧を取得
   */
  static async getCredentials(token: string): Promise<WebAuthnCredential[]> {
    const response = await fetch(`${API_BASE_URL}/api/auth/webauthn/credentials`, {
      method: 'GET',
      headers: this.getHeaders(token),
    });

    if (!response.ok) {
      throw new Error('WebAuthn認証器一覧の取得に失敗しました');
    }

    const data = await response.json();
    return data.credentials || [];
  }

  /**
   * WebAuthn認証器を無効化
   */
  static async disableCredential(token: string, credentialId: string): Promise<WebAuthnResponse> {
    const response = await fetch(`${API_BASE_URL}/api/auth/webauthn/credentials/${credentialId}`, {
      method: 'DELETE',
      headers: this.getHeaders(token),
    });

    return response.json();
  }

  /**
   * WebAuthn認証器のエイリアスを更新
   */
  static async updateCredentialAlias(
    token: string,
    credentialId: string,
    alias: string
  ): Promise<WebAuthnResponse> {
    const response = await fetch(`${API_BASE_URL}/api/auth/webauthn/credentials/${credentialId}`, {
      method: 'PUT',
      headers: this.getHeaders(token),
      body: JSON.stringify({ alias }),
    });

    return response.json();
  }
}

/**
 * WebAuthn utility functions
 */
export class WebAuthnUtils {
  /**
   * ArrayBufferをBase64URL文字列に変換
   */
  static arrayBufferToBase64Url(buffer: ArrayBuffer): string {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.byteLength; i++) {
      binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary)
      .replace(/\+/g, '-')
      .replace(/\//g, '_')
      .replace(/=/g, '');
  }

  /**
   * Base64URL文字列をArrayBufferに変換
   */
  static base64UrlToArrayBuffer(base64url: string): ArrayBuffer {
    const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
    const paddedBase64 = base64.padEnd(base64.length + (4 - (base64.length % 4)) % 4, '=');
    const binary = atob(paddedBase64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
      bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
  }

  /**
   * 登録オプションを WebAuthn API 用に変換
   */
  static convertRegistrationOptions(options: WebAuthnRegistrationOptions): PublicKeyCredentialCreationOptions {
    return {
      challenge: this.base64UrlToArrayBuffer(options.challenge),
      rp: options.rp,
      user: {
        ...options.user,
        id: this.base64UrlToArrayBuffer(options.user.id),
      },
      pubKeyCredParams: options.pubKeyCredParams,
      timeout: options.timeout,
      attestation: options.attestation as AttestationConveyancePreference,
      excludeCredentials: options.excludeCredentials?.map(cred => ({
        ...cred,
        id: this.base64UrlToArrayBuffer(cred.id),
      })),
    };
  }

  /**
   * ログインオプションを WebAuthn API 用に変換
   */
  static convertLoginOptions(options: WebAuthnLoginOptions): PublicKeyCredentialRequestOptions {
    return {
      challenge: this.base64UrlToArrayBuffer(options.challenge),
      timeout: options.timeout,
      rpId: options.rpId,
      allowCredentials: options.allowCredentials?.map(cred => ({
        ...cred,
        id: this.base64UrlToArrayBuffer(cred.id),
      })),
    };
  }

  /**
   * WebAuthn登録レスポンスをサーバー送信用に変換
   */
  static convertRegistrationResponse(credential: PublicKeyCredential): Record<string, unknown> {
    const response = credential.response as AuthenticatorAttestationResponse;
    return {
      id: credential.id,
      rawId: this.arrayBufferToBase64Url(credential.rawId),
      type: credential.type,
      response: {
        clientDataJSON: this.arrayBufferToBase64Url(response.clientDataJSON),
        attestationObject: this.arrayBufferToBase64Url(response.attestationObject),
      },
    };
  }

  /**
   * WebAuthnログインレスポンスをサーバー送信用に変換
   */
  static convertLoginResponse(credential: PublicKeyCredential): Record<string, unknown> {
    const response = credential.response as AuthenticatorAssertionResponse;
    return {
      id: credential.id,
      rawId: this.arrayBufferToBase64Url(credential.rawId),
      type: credential.type,
      response: {
        clientDataJSON: this.arrayBufferToBase64Url(response.clientDataJSON),
        authenticatorData: this.arrayBufferToBase64Url(response.authenticatorData),
        signature: this.arrayBufferToBase64Url(response.signature),
        userHandle: response.userHandle ? this.arrayBufferToBase64Url(response.userHandle) : null,
      },
    };
  }

  /**
   * WebAuthnサポートチェック
   */
  static isSupported(): boolean {
    return !!(navigator.credentials && navigator.credentials.create && navigator.credentials.get);
  }

  /**
   * プラットフォーム認証器サポートチェック
   */
  static async isPlatformAuthenticatorAvailable(): Promise<boolean> {
    if (!this.isSupported()) return false;

    try {
      return await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
    } catch {
      return false;
    }
  }
}