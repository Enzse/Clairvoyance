import { describe, expect, it } from 'vitest';
import { align, axisLabel, isValidDate, label, shift } from './period';

// Same cases as api/tests/Unit/PeriodTest.php, so client and server agree.
describe('period', () => {
	it('aligns dates to period starts', () => {
		expect(align('2025-06-15', 'weekly')).toBe('2025-06-09');
		expect(align('2025-01-05', 'weekly')).toBe('2024-12-30');
		expect(align('2025-06-30', 'monthly')).toBe('2025-06-01');
		expect(align('2025-08-15', 'quarterly')).toBe('2025-07-01');
		expect(align('2025-12-31', 'yearly')).toBe('2025-01-01');
	});

	it('shifts periods', () => {
		expect(shift('2025-01-01', 'monthly', 1)).toBe('2025-02-01');
		expect(shift('2025-01-01', 'quarterly', -1)).toBe('2024-10-01');
		expect(shift('2024-12-30', 'weekly', 1)).toBe('2025-01-06');
		expect(shift('2025-01-01', 'monthly', -12)).toBe('2024-01-01');
	});

	it('labels periods like the server', () => {
		expect(label('2025-06-01', 'monthly')).toBe('Jun 2025');
		expect(label('2025-07-01', 'quarterly')).toBe('Q3 2025');
		expect(label('2025-06-30', 'weekly')).toBe('W27 2025');
		expect(label('2024-12-30', 'weekly')).toBe('W01 2025');
		expect(label('2025-01-01', 'yearly')).toBe('2025');
	});

	it('adds the year to axis labels only when it changes', () => {
		expect(axisLabel('2025-01-01', 'monthly', '2024-12-01')).toBe('Jan ’25');
		expect(axisLabel('2025-02-01', 'monthly', '2025-01-01')).toBe('Feb');
	});

	it('validates dates strictly', () => {
		expect(isValidDate('2025-02-30')).toBe(false);
		expect(isValidDate('2024-02-29')).toBe(true);
	});
});
