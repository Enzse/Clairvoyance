import { describe, expect, it } from 'vitest';
import type { Unit } from '$lib/types';
import { canMoveInto } from './tree';

// Org (1) › North group (2) › Downtown (3), Airport (4, has figures); South (5, empty group); Harbour (6)
const list = [
	{ id: 1, parent_id: null, name: 'Org', kind: null, has_values: false },
	{ id: 2, parent_id: 1, name: 'North', kind: 'Region', has_values: false },
	{ id: 3, parent_id: 2, name: 'Downtown', kind: 'Branch', has_values: false },
	{ id: 4, parent_id: 2, name: 'Airport', kind: 'Branch', has_values: true },
	{ id: 5, parent_id: 1, name: 'South', kind: 'Group', has_values: false },
	{ id: 6, parent_id: 1, name: 'Harbour', kind: 'Branch', has_values: false }
] as Unit[];
const units = {
	get: (id: number) => list.find((u) => u.id === id),
	children: (id: number) => list.filter((u) => u.parent_id === id),
	path: (id: number) => {
		const out: Unit[] = [];
		for (let u = list.find((x) => x.id === id); u; u = list.find((x) => x.id === u!.parent_id)) out.unshift(u);
		return out;
	}
};

describe('canMoveInto', () => {
	it('lets a unit go into another group, an empty group or the top', () => {
		expect(canMoveInto(6, 2, units)).toBe(true);
		expect(canMoveInto(6, 5, units)).toBe(true);
		expect(canMoveInto(3, 1, units)).toBe(true);
	});

	it('refuses plain units, units with figures, itself, its own inside, and where it already is', () => {
		expect(canMoveInto(3, 6, units)).toBe(false);
		expect(canMoveInto(6, 4, units)).toBe(false);
		expect(canMoveInto(2, 2, units)).toBe(false);
		expect(canMoveInto(2, 3, units)).toBe(false);
		expect(canMoveInto(3, 2, units)).toBe(false);
		expect(canMoveInto(1, 2, units)).toBe(false);
	});
});
