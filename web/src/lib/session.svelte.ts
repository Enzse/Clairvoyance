import { api } from './api';
import type { Me } from './types';
import { units } from './units.svelte';

/**
 * The signed-in person and the organisation they're working in. `loaded` turns true once the
 * first session check has finished.
 */
export const session = $state<{ user: Me | null; loaded: boolean }>({ user: null, loaded: false });

export async function loadSession(): Promise<void> {
	try {
		session.user = await api.me();
	} catch {
		session.user = null;
	} finally {
		session.loaded = true;
	}
}

/** Replace the session after anything that returns the current person (login, joining, switching). */
export function setMe(me: Me): void {
	const switched = me.organization?.id !== session.user?.organization?.id;
	session.user = me;
	if (switched) units.reset();
}

export async function login(email: string, password: string): Promise<Me> {
	const me = await api.login(email, password);
	setMe(me);
	return me;
}

export async function logout(): Promise<void> {
	try {
		await api.logout();
	} finally {
		session.user = null;
		units.reset();
	}
}

export function isAdmin(): boolean {
	return session.user?.organization?.role === 'admin';
}

/** Where to go after signing in: the requested page, otherwise onboarding or the scorecard. */
export function landingFor(me: Me, next: string | null): string {
	if (next && next.startsWith('/') && !next.startsWith('//')) return next;
	return me.organization ? '/' : '/onboarding';
}
