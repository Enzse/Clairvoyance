import { describe, expect, it } from 'vitest';
import { ALWAYS, codeFrom, mainTarget, rollupExample, shortRules, stepOfError, withMainTarget, type TargetRow } from './kpi-form';

describe('codeFrom', () => {
	it('makes a spreadsheet-safe code', () => {
		expect(codeFrom('Net revenue (₱)')).toBe('net_revenue');
		expect(codeFrom('2025 sales')).toBe('k_2025_sales');
	});
});

describe('rollupExample', () => {
	it('works through the two rules on the example', () => {
		expect(rollupExample('sum', 'sum')).toEqual({ a: 360, b: 240, total: 600 });
		expect(rollupExample('average', 'average')).toEqual({ a: 120, b: 80, total: 100 });
		expect(rollupExample('last', 'sum')).toEqual({ a: 140, b: 70, total: 210 });
		// The bigger unit counts three times as much
		expect(rollupExample('average', 'weighted_average').total).toBe(110);
	});
});

describe('shortRules', () => {
	it('says it in plain words', () => {
		expect(shortRules({ time_aggregation: 'sum', unit_aggregation: 'sum' }, 'branches')).toBe('Added up over time and across branches');
		expect(shortRules({ time_aggregation: 'last', unit_aggregation: 'sum' }, 'teams')).toBe('Latest figure, added up across teams');
		expect(shortRules({ time_aggregation: 'average', unit_aggregation: 'weighted_average' }, 'stores', 'Customers')).toBe(
			'Averaged over time, weighted by customers across stores'
		);
	});
});

describe('targets', () => {
	const rows: TargetRow[] = [
		{ key: 1, unit_id: null, effective_from: '2025-01-01', value: 100 },
		{ key: 2, unit_id: null, effective_from: '2026-01-01', value: 120 },
		{ key: 3, unit_id: 7, effective_from: ALWAYS, value: 50 }
	];

	it('finds the latest target for everyone', () => {
		expect(mainTarget(rows)?.value).toBe(120);
		expect(mainTarget([rows[2]])).toBeUndefined();
	});

	it('sets, adds and removes it without touching the others', () => {
		expect(withMainTarget(rows, 130, 9).map((r) => r.value)).toEqual([100, 130, 50]);
		expect(withMainTarget([rows[2]], 75, 9)).toEqual([rows[2], { key: 9, unit_id: null, effective_from: ALWAYS, value: 75 }]);
		expect(withMainTarget(rows, null, 9).map((r) => r.key)).toEqual([1, 3]);
	});
});

describe('stepOfError', () => {
	it('sends the person back to the step with the problem', () => {
		expect(stepOfError({ code: 'is taken' })).toBe(0);
		expect(stepOfError({ weight_kpi_id: 'is required' })).toBe(1);
		expect(stepOfError({ 'targets.0': 'bad' })).toBe(2);
		expect(stepOfError({ scope_unit_ids: 'bad' })).toBe(3);
	});
});
