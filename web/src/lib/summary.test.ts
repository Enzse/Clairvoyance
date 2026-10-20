import { describe, expect, it } from 'vitest';
import { describeChange } from './summary';
import type { Change, Kpi } from './types';

const kpi = { prefix: '$', suffix: '', decimals: 0 } as Kpi;
const change = (value: number, partial = false): Change => ({
	value,
	percent: null,
	direction: null,
	basis: partial ? 'like_for_like' : 'all',
	units_compared: partial ? 1 : null,
	units_total: partial ? 2 : null,
	left_out: partial ? ['Airport'] : []
});
const aug = { period_label: 'Aug 2026', value: 790000 };

describe('describeChange', () => {
	it('compares totals when every unit reported', () => {
		expect(describeChange(kpi, change(-2500), aug, 'monthly')).toBe(' Down $2,500 from Aug 2026.');
		expect(describeChange(kpi, change(0), aug, 'monthly')).toBe(' Unchanged from Aug 2026.');
	});

	it('says when only some units are compared', () => {
		expect(describeChange(kpi, change(42500, true), aug, 'monthly', 'branches')).toBe(' Up $42,500 from Aug 2026 for the 1 of 2 branches that reported both months.');
	});

	it('explains when nothing can be compared, and stays quiet without a previous figure', () => {
		expect(describeChange(kpi, null, aug, 'quarterly')).toBe(' Not comparable with Aug 2026 yet: nothing has been reported for both.');
		expect(describeChange(kpi, null, { period_label: 'Aug 2026', value: null }, 'monthly')).toBe('');
	});
});

