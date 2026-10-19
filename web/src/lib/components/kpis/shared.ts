import type { KpiForm } from '$lib/kpi-form';
import type { Kpi } from '$lib/types';
import { units } from '$lib/units.svelte';

/** KPIs this one can be weighted by: active, same frequency, not weighted themselves. */
export function weightChoicesFor(kpis: Kpi[], form: KpiForm, selfId: number | null): Kpi[] {
	return kpis.filter((k) => k.id !== selfId && k.is_active && k.frequency === form.frequency && k.unit_aggregation !== 'weighted_average');
}

/** Two of the organisation's own units to use in the worked example. */
export function exampleNames(): [string, string] {
	const leaves = units.list.filter((u) => u.parent_id !== null && u.is_active && units.children(u.id).length === 0).map((u) => u.name);
	return [leaves[0] ?? 'Downtown', leaves[1] ?? 'Airport'];
}

/** Server field errors as plain strings, keyed by field. */
export function fieldErrors(details: Record<string, unknown>): Record<string, string> {
	return Object.fromEntries(Object.entries(details).map(([k, v]) => [k, String(v)]));
}

/** An icon for the kind of figure: money, a percentage, or a count. */
export function kpiIcon(kpi: Pick<Kpi, 'prefix' | 'suffix'>): 'coins' | 'percent' | 'hash' {
	return kpi.prefix ? 'coins' : kpi.suffix.includes('%') ? 'percent' : 'hash';
}
