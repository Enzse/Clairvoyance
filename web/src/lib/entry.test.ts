import { describe, expect, it } from 'vitest';
import { byKpi, nextToDo, parseFigure, periodName, progressState } from './entry';
import type { EntryRow, Kpi } from './types';

const kpi = (id: number, frequency: Kpi['frequency']) => ({ id, frequency }) as Kpi;
const row = (k: Kpi, period_start: string, period_label = '') => ({ kpi: k, period_start, period_label }) as EntryRow;

describe('parseFigure', () => {
	it('reads numbers with thousands separators', () => {
		expect(parseFigure(' 1,250 ')).toBe(1250);
		expect(parseFigure('-.5')).toBe(-0.5);
		expect(parseFigure('')).toBe(null);
		expect(parseFigure('12a')).toBe(undefined);
	});
});

describe('periodName', () => {
	it('names weeks and days plainly', () => {
		expect(periodName(row(kpi(1, 'weekly'), '2025-06-02'))).toBe('Week of 2 Jun');
		expect(periodName(row(kpi(1, 'daily'), '2025-06-02'))).toBe('Mon 2');
		expect(periodName(row(kpi(1, 'monthly'), '2025-06-01', 'Jun 2025'))).toBe('Jun 2025');
	});
});

describe('byKpi', () => {
	it('groups consecutive rows of one KPI', () => {
		const weekly = kpi(2, 'weekly');
		const groups = byKpi([row(kpi(1, 'monthly'), '2025-06-01'), row(weekly, '2025-06-02'), row(weekly, '2025-06-09')]);
		expect(groups.map((g) => [g.kpi.id, g.rows.length])).toEqual([[1, 1], [2, 2]]);
	});
});

describe('progress', () => {
	const progress = [
		{ unit_id: 1, expected: 2, entered: 2 },
		{ unit_id: 2, expected: 2, entered: 0 },
		{ unit_id: 3, expected: 2, entered: 1 },
		{ unit_id: 4, expected: 0, entered: 0 }
	];

	it('says how far a unit has got', () => {
		expect(progress.map(progressState)).toEqual(['done', 'none', 'partly', 'nothing']);
	});

	it('finds the next unit still to do, wrapping round', () => {
		expect(nextToDo([1, 2, 3, 4], progress, 1)).toBe(2);
		expect(nextToDo([1, 2, 3, 4], progress, 3)).toBe(2);
		expect(nextToDo([1, 2, 3, 4], progress, null)).toBe(2);
		expect(nextToDo([1, 4], progress, 1)).toBe(null);
	});
});
