import type { StarterKpi } from './starter-kpis';
import type { Kpi, Target } from './types';

/** Everything the KPI editor edits, in the shape the form needs. */
export interface KpiForm {
	name: string;
	code: string;
	description: string;
	frequency: Kpi['frequency'];
	time_aggregation: Kpi['time_aggregation'];
	unit_aggregation: Kpi['unit_aggregation'];
	weight_kpi_id: number | null;
	direction: Kpi['direction'];
	tolerance_pct: number;
	applies_to_kind: string;
	scope_unit_ids: number[];
	decimals: number;
	prefix: string;
	suffix: string;
	is_active: boolean;
}

export type TargetRow = Target & { key: number };

/** Start date for a target that has always applied. */
export const ALWAYS = '2000-01-01';

export function blankForm(): KpiForm {
	return {
		name: '', code: '', description: '', frequency: 'monthly', time_aggregation: 'sum', unit_aggregation: 'sum',
		weight_kpi_id: null, direction: 'higher_is_better', tolerance_pct: 10, applies_to_kind: '', scope_unit_ids: [],
		decimals: 0, prefix: '', suffix: '', is_active: true
	};
}

export function formFromKpi(kpi: Kpi): KpiForm {
	return {
		name: kpi.name, code: kpi.code, description: kpi.description ?? '', frequency: kpi.frequency,
		time_aggregation: kpi.time_aggregation, unit_aggregation: kpi.unit_aggregation, weight_kpi_id: kpi.weight_kpi_id,
		direction: kpi.direction, tolerance_pct: kpi.tolerance_pct, applies_to_kind: kpi.applies_to_kind ?? '',
		scope_unit_ids: [...kpi.scope_unit_ids], decimals: kpi.decimals, prefix: kpi.prefix, suffix: kpi.suffix, is_active: kpi.is_active
	};
}

/** The spreadsheet code made from a name: "Net revenue (₱)" → "net_revenue". */
export function codeFrom(name: string): string {
	return name.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '').replace(/^(\d)/, 'k_$1').slice(0, 64);
}

export function toBody(form: KpiForm): Partial<Kpi> {
	return {
		...form,
		name: form.name.trim(),
		description: form.description.trim() || null,
		applies_to_kind: form.applies_to_kind || null,
		weight_kpi_id: form.unit_aggregation === 'weighted_average' ? form.weight_kpi_id : null
	};
}

/** A starter KPI's definition as form fields. */
export function fromStarter(starter: StarterKpi, currency: string, weightId: number | null): Partial<KpiForm> {
	const weighted = starter.unit_aggregation === 'weighted_average' && weightId !== null;
	return {
		name: starter.name,
		code: starter.key,
		description: starter.description,
		time_aggregation: starter.time_aggregation,
		unit_aggregation: weighted ? 'weighted_average' : starter.unit_aggregation === 'weighted_average' ? 'average' : starter.unit_aggregation,
		weight_kpi_id: weighted ? weightId : null,
		direction: starter.direction,
		tolerance_pct: starter.tolerance_pct,
		decimals: starter.decimals,
		prefix: starter.money ? currency : '',
		suffix: starter.suffix ?? ''
	};
}

/** Which step of the new-KPI guide a server error belongs to. */
export function stepOfError(details: Record<string, unknown>): number {
	const fields = Object.keys(details);
	const has = (...names: string[]) => fields.some((f) => names.some((n) => f === n || f.startsWith(`${n}.`)));
	if (has('name', 'code', 'description', 'frequency')) return 0;
	if (has('time_aggregation', 'unit_aggregation', 'weight_kpi_id')) return 1;
	if (has('direction', 'tolerance_pct', 'decimals', 'prefix', 'suffix', 'targets')) return 2;
	return 3;
}

/** The target for every unit that applies now: the default row with the latest start date. */
export function mainTarget(rows: TargetRow[]): TargetRow | undefined {
	return rows.filter((r) => r.unit_id === null).sort((a, b) => a.effective_from.localeCompare(b.effective_from)).at(-1);
}

/** Sets (or with null, removes) the target for every unit, keeping any others. */
export function withMainTarget(rows: TargetRow[], value: number | null, key: number): TargetRow[] {
	const main = mainTarget(rows);
	if (value === null) return rows.filter((r) => r !== main);
	if (main) return rows.map((r) => (r === main ? { ...r, value } : r));
	return [...rows, { key, unit_id: null, effective_from: ALWAYS, value }];
}

/**
 * A worked example of the rules, with two units over three periods. Unit A is bigger
 * (300 customers to B's 100), which only matters for a weighted average.
 */
export const EXAMPLE = { a: [120, 100, 140], b: [80, 90, 70], weights: [300, 100] };

export function rollupExample(time: KpiForm['time_aggregation'], unitRule: KpiForm['unit_aggregation']) {
	const overTime = (xs: number[]) => (time === 'sum' ? xs.reduce((s, x) => s + x, 0) : time === 'average' ? xs.reduce((s, x) => s + x, 0) / xs.length : xs[xs.length - 1]);
	const a = overTime(EXAMPLE.a);
	const b = overTime(EXAMPLE.b);
	const [wa, wb] = EXAMPLE.weights;
	const total = unitRule === 'sum' ? a + b : unitRule === 'average' ? (a + b) / 2 : (a * wa + b * wb) / (wa + wb);
	return { a, b, total };
}

/** The rules in a few plain words, e.g. "Added up over time and across branches". */
export function shortRules(kpi: Pick<Kpi, 'time_aggregation' | 'unit_aggregation'>, many: string, weightName?: string): string {
	const time = { sum: 'added up', average: 'averaged', last: 'latest figure' }[kpi.time_aggregation];
	const across = {
		sum: 'added up',
		average: 'averaged',
		weighted_average: `weighted by ${weightName?.toLowerCase() ?? 'another KPI'}`
	}[kpi.unit_aggregation];
	const sentence =
		time === across
			? `${time} over time and across ${many}`
			: kpi.time_aggregation === 'last'
				? `latest figure, ${across} across ${many}`
				: `${time} over time, ${across} across ${many}`;
	return sentence.charAt(0).toUpperCase() + sentence.slice(1);
}
