import type { Kpi, Result, Status } from './types';

/**
 * Status always shows as icon shape + label, never colour alone: check = on target,
 * half-filled circle = close, down-triangle = behind.
 */
export const STATUS: Record<Status, { label: string; icon: 'check' | 'half' | 'down'; tone: string }> = {
	green: { label: 'On target', icon: 'check', tone: 'good' },
	amber: { label: 'Close', icon: 'half', tone: 'warn' },
	red: { label: 'Behind', icon: 'down', tone: 'bad' }
};

/**
 * Whether a status can still change because figures are missing. Not for a KPI that counts a
 * missing figure as 0: its status already includes them.
 */
export function soFar(kpi: Pick<Kpi, 'missing_is_zero'>, r: Pick<Result, 'value' | 'reported' | 'expected'>): boolean {
	return r.value !== null && r.reported < r.expected && !kpi.missing_is_zero;
}
