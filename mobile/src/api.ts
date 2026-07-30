import { Preferences } from '@capacitor/preferences';
import type { User } from './types';

const TOKEN_KEY = 'tml_mobile_token';
const API_KEY = 'tml_mobile_api';
const USER_KEY = 'tml_mobile_user';

/** По умолчанию — прод; для эмулятора Android: http://10.0.2.2:8000 */
export const DEFAULT_API_URL = 'https://tasks.crewdev.ru';

let token: string | null = null;
let apiBase = DEFAULT_API_URL;
let user: User | null = null;

export async function bootSession(): Promise<void> {
  const [t, a, u] = await Promise.all([
    Preferences.get({ key: TOKEN_KEY }),
    Preferences.get({ key: API_KEY }),
    Preferences.get({ key: USER_KEY }),
  ]);
  token = t.value;
  apiBase = (a.value || DEFAULT_API_URL).replace(/\/$/, '');
  user = u.value ? (JSON.parse(u.value) as User) : null;
}

export function getToken(): string | null {
  return token;
}

export function getApiBase(): string {
  return apiBase;
}

export function getUser(): User | null {
  return user;
}

export async function setApiBase(url: string): Promise<void> {
  apiBase = url.replace(/\/$/, '');
  await Preferences.set({ key: API_KEY, value: apiBase });
}

export async function setSession(nextToken: string, nextUser: User): Promise<void> {
  token = nextToken;
  user = nextUser;
  await Preferences.set({ key: TOKEN_KEY, value: nextToken });
  await Preferences.set({ key: USER_KEY, value: JSON.stringify(nextUser) });
}

export async function clearSession(): Promise<void> {
  token = null;
  user = null;
  await Preferences.remove({ key: TOKEN_KEY });
  await Preferences.remove({ key: USER_KEY });
}

export class ApiError extends Error {
  status: number;
  constructor(status: number, message: string) {
    super(message);
    this.status = status;
  }
}

export async function api<T = unknown>(
  path: string,
  options: RequestInit & { formData?: FormData } = {},
): Promise<T> {
  const headers = new Headers(options.headers || {});
  if (token) headers.set('Authorization', `Bearer ${token}`);
  headers.set('Accept', 'application/json');

  let body: BodyInit | null | undefined = options.body as BodyInit | null | undefined;
  if (options.formData) {
    body = options.formData;
  } else if (
    body &&
    typeof body === 'object' &&
    !(body instanceof FormData) &&
    !(body instanceof Blob) &&
    !(body instanceof ArrayBuffer) &&
    typeof body !== 'string'
  ) {
    headers.set('Content-Type', 'application/json');
    body = JSON.stringify(body);
  }

  const res = await fetch(`${apiBase}/api/mobile${path}`, {
    method: options.method || 'GET',
    headers,
    body: options.method && options.method.toUpperCase() !== 'GET' ? body : body,
  });

  if (res.status === 401) {
    await clearSession();
    throw new ApiError(401, 'Требуется вход');
  }

  const text = await res.text();
  let data: any = null;
  try {
    data = text ? JSON.parse(text) : null;
  } catch {
    data = { message: text };
  }

  if (!res.ok) {
    const firstError = data?.errors
      ? (Object.values(data.errors as Record<string, string[]>)[0] || [])[0]
      : undefined;
    const msg = data?.message || data?.errors?.email?.[0] || firstError || `Ошибка ${res.status}`;
    throw new ApiError(res.status, String(msg));
  }

  return data as T;
}

/** Медиа с Bearer → blob URL */
export async function mediaUrl(url: string): Promise<string> {
  if (!url) return '';
  if (url.startsWith('blob:')) return url;
  const headers: HeadersInit = {};
  if (token) headers['Authorization'] = `Bearer ${token}`;
  // Absolute or relative
  const abs = url.startsWith('http') ? url : `${apiBase}${url.startsWith('/') ? '' : '/'}${url}`;
  const res = await fetch(abs, { headers });
  if (!res.ok) return abs;
  const blob = await res.blob();
  return URL.createObjectURL(blob);
}
