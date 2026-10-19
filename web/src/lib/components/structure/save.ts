import { api } from '$lib/api';
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

export { problem } from '$lib/problem';
