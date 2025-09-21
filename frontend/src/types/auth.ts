export interface User {
  id: number;
  name: string;
  email: string;
  email_verified_at: string | null;
  role?: string;
  profile?: {
    display_name?: string;
    bio?: string | null;
    language: string;
    timezone: string;
  };
}

export interface AuthContextType {
  user: User | null;
  loading: boolean;
  token: string | null;
  login: (
    email: string,
    password: string,
    remember?: boolean,
    turnstileToken?: string | null
  ) => Promise<void>;
  register: (data: RegisterData) => Promise<void>;
  logout: () => Promise<void>;
  refreshToken: () => Promise<void>;
  refreshUser: () => Promise<void>;
  updateProfile: (data: Partial<User>) => Promise<void>;
}

export interface RegisterData {
  name: string;
  email: string;
  password: string;
  language?: string;
  timezone?: string;
  turnstile_token?: string | null;
}
