import { api } from './api';
import type { User } from './types';

/** The logged-in user. `loaded` turns true once the first session check has finished. */
export const session = $state<{ user: User | null; loaded: boolean }>({ user: null, loaded: false });

export async function loadSession(): Promise<void> {
	try {
		session.user = await api.me();
	} catch {
		session.user = null;
	} finally {
		session.loaded = true;
	}
}

export async function login(email: string, password: string): Promise<void> {
	session.user = await api.login(email, password);
}

export async function logout(): Promise<void> {
	try {
		await api.logout();
	} finally {
		session.user = null;
	}
}

export function isAdmin(): boolean {
	return session.user?.role === 'admin';
}
