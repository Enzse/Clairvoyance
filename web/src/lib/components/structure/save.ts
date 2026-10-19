import { api, ApiError } from '$lib/api';
import { setMe } from '$lib/session.svelte';
import type { Unit } from '$lib/types';
import { units } from '$lib/units.svelte';

type Fields = Parameters<typeof api.updateUnit>[1];

/** Saves changes to a unit and reloads the tree. Renaming the top also renames the organisation. */
export async function saveUnit(unit: Unit, fields: Fields): Promise<void> {
	await api.updateUnit(unit.id, fields);
	await units.load(true);
	if (unit.parent_id === null && 'name' in fields) setMe(await api.me());
}

/** The most useful message from a failed save. */
export function problem(e: unknown, fallback = 'Could not save'): string {
	if (!(e instanceof ApiError)) return fallback;
	const [field, message] = Object.entries(e.details)[0] ?? [];
	if (typeof message !== 'string') return e.message;
	return `${field === 'name' ? 'That name' : field === 'code' ? 'That code' : 'It'} ${message}`;
}
