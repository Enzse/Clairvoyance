import { describe, expect, it } from 'vitest';
import { linePath, niceScale, niceStep, tickFormatter, trimLeading } from './chart';

describe('niceStep', () => {
	it('picks 1, 2 or 5 times a power of ten', () => {
		expect(niceStep(100, 4)).toBe(20);
		expect(niceStep(10, 4)).toBe(2);
		expect(niceStep(3, 4)).toBe(1);
		expect(niceStep(7000, 4)).toBe(2000);
	});
});

describe('niceScale', () => {
	it('rounds the domain out to whole ticks', () => {
		expect(niceScale([94.9, 97.5])).toEqual({ min: 94.5, max: 97.5, ticks: [94.5, 95, 95.5, 96, 96.5, 97, 97.5] });
	});

	it('includes zero for bars', () => {
		const scale = niceScale([1815, 1252], { includeZero: true });
		expect(scale.min).toBe(0);
		expect(scale.max).toBe(2000);
	});

	it('handles a single repeated value', () => {
		const scale = niceScale([5, 5]);
		expect(scale.min).toBeLessThan(5);
		expect(scale.max).toBeGreaterThan(5);
	});

	it('handles negatives', () => {
		const scale = niceScale([-12, 30], { includeZero: true });
		expect(scale.min).toBeLessThanOrEqual(-12);
		expect(scale.ticks).toContain(0);
	});
});

describe('tickFormatter', () => {
	it('never gives neighbouring ticks the same label', () => {
		const scale = niceScale([8344, 8460]);
		const labels = scale.ticks.map(tickFormatter(scale));
		expect(new Set(labels).size).toBe(labels.length);
		expect(labels[0]).toBe('8,340');
	});

	it('uses compact labels for steps of whole thousands', () => {
		const scale = niceScale([0, 3097]);
		expect(scale.ticks.map(tickFormatter(scale))).toEqual(['0', '1K', '2K', '3K', '4K']);
	});

	it('keeps the decimal of half-million steps', () => {
		const scale = niceScale([4_300_000, 6_700_000]);
		expect(scale.ticks.map(tickFormatter(scale))).toEqual(['4M', '4.5M', '5M', '5.5M', '6M', '6.5M', '7M']);
	});

	it('shows decimals for small steps', () => {
		const scale = niceScale([94.9, 97.5]);
		expect(tickFormatter(scale)(95)).toBe('95.0');
	});
});

describe('trimLeading', () => {
	it('starts at the first item with a value', () => {
		expect(trimLeading([null, null, 3, null, 5], (v) => v !== null)).toEqual([3, null, 5]);
		expect(trimLeading([null, null], (v) => v !== null)).toEqual([null, null]);
	});
});

describe('linePath', () => {
	it('breaks the line at missing values', () => {
		expect(linePath([{ x: 0, y: 0 }, { x: 1, y: 1 }, null, { x: 3, y: 3 }])).toBe('M0.0 0.0L1.0 1.0M3.0 3.0');
	});
});
