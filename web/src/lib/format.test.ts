import { describe, expect, it } from 'vitest';
import { formatDelta, formatValue, joinNames, plural } from './format';

describe('plural', () => {
	it('pluralises the unit kinds people type', () => {
		expect(plural(3, 'branch')).toBe('3 branches');
		expect(plural(2, 'company')).toBe('2 companies');
		expect(plural(4, 'store')).toBe('4 stores');
		expect(plural(2, 'day')).toBe('2 days');
		expect(plural(1, 'branch')).toBe('1 branch');
		expect(plural(1200, 'figure')).toBe('1,200 figures');
	});
});

describe('formatValue', () => {
	const peso = { prefix: '₱', suffix: '', decimals: 0 };
	it('applies prefix, suffix and decimals', () => {
		expect(formatValue(peso, 1234.5)).toBe('₱1,235');
		expect(formatValue({ prefix: '', suffix: '%', decimals: 1 }, 95.25)).toBe('95.3%');
		expect(formatValue(peso, -500)).toBe('−₱500');
		expect(formatValue(peso, null)).toBe('–');
	});

	it('signs deltas', () => {
		expect(formatDelta({ prefix: '', suffix: '%', decimals: 1 }, -0.4)).toBe('−0.4%');
		expect(formatDelta(peso, 0)).toBe('±₱0');
	});
});

describe('joinNames', () => {
	it('lists a few names and counts the rest', () => {
		expect(joinNames(['Sales'])).toBe('Sales');
		expect(joinNames(['Sales', 'Support'])).toBe('Sales and Support');
		expect(joinNames(['A', 'B', 'C', 'D'])).toBe('A, B and 2 others');
	});
});
