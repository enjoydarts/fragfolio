// 認証関連API

export interface User {
  id: number;
  name: string;
  email: string;
  profile: {
    language: string;
    timezone: string;
    bio: string | null;
    date_of_birth: string | null;
    gender: string | null;
    country: string | null;
  };
  roles: string[];
}

export interface AuthResponse {
  success: boolean;
  message?: string;
  user?: User;
  token?: string;
  errors?: Record<string, string[]>;
}

const API_BASE_URL =
  import.meta.env.VITE_API_BASE_URL || 'http://localhost:8002';

export class AuthAPI {
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

  static async register(data: {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    language?: string;
    timezone?: string;
    turnstile_token?: string;
  }): Promise<AuthResponse> {
    const response = await fetch(`${API_BASE_URL}/api/auth/register`, {
      method: 'POST',
      headers: this.getHeaders(),
      body: JSON.stringify(data),
    });

    return response.json();
  }

  static async login(data: {
    email: string;
    password: string;
    remember?: boolean;
    turnstile_token?: string;
  }): Promise<AuthResponse> {
    const response = await fetch(`${API_BASE_URL}/api/auth/login`, {
      method: 'POST',
      headers: this.getHeaders(),
      body: JSON.stringify(data),
    });

    return response.json();
  }

  static async logout(token: string): Promise<AuthResponse> {
    const response = await fetch(`${API_BASE_URL}/api/auth/logout`, {
      method: 'POST',
      headers: this.getHeaders(token),
    });

    return response.json();
  }

  static async me(token: string): Promise<AuthResponse> {
    const response = await fetch(`${API_BASE_URL}/api/auth/me`, {
      method: 'GET',
      headers: this.getHeaders(token),
    });

    return response.json();
  }

  static async updateProfile(
    token: string,
    data: {
      name?: string;
      bio?: string;
      language?: string;
      timezone?: string;
      date_of_birth?: string;
      gender?: string;
      country?: string;
    }
  ): Promise<AuthResponse> {
    const response = await fetch(`${API_BASE_URL}/api/auth/profile`, {
      method: 'PUT',
      headers: this.getHeaders(token),
      body: JSON.stringify(data),
    });

    return response.json();
  }

  static async refreshToken(token: string): Promise<AuthResponse> {
    const response = await fetch(`${API_BASE_URL}/api/auth/refresh`, {
      method: 'POST',
      headers: this.getHeaders(token),
    });

    return response.json();
  }
}
