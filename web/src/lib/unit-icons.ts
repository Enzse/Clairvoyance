import type { IconName } from './components/Icon.svelte';
import type { Unit } from './types';

/** Icons a unit can have, in picker order. */
export const UNIT_ICONS: { name: IconName; label: string }[] = [
	{ name: 'pin', label: 'Place' },
	{ name: 'store', label: 'Store' },
	{ name: 'coffee', label: 'Café' },
	{ name: 'cart', label: 'Shop' },
	{ name: 'building', label: 'Office' },
	{ name: 'home', label: 'Home' },
	{ name: 'factory', label: 'Factory' },
	{ name: 'box', label: 'Warehouse' },
	{ name: 'truck', label: 'Delivery' },
	{ name: 'briefcase', label: 'Department' },
	{ name: 'users', label: 'Team' },
	{ name: 'phone', label: 'Support' },
	{ name: 'heart', label: 'Clinic' },
	{ name: 'school', label: 'School' },
	{ name: 'folder', label: 'Group' },
	{ name: 'map', label: 'Region' },
	{ name: 'globe', label: 'Country' },
	{ name: 'flag', label: 'Area' },
	{ name: 'star', label: 'Flagship' }
];

const KNOWN = new Set<string>(UNIT_ICONS.map((i) => i.name));

/** The unit's chosen icon, or the default: a building for the organisation, a folder for groups, a pin for the rest. */
export function iconFor(unit: Pick<Unit, 'icon' | 'parent_id'>, isGroup: boolean): IconName {
	if (unit.icon && KNOWN.has(unit.icon)) return unit.icon as IconName;
	return unit.parent_id === null ? 'building' : isGroup ? 'folder' : 'pin';
}
