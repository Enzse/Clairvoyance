import type { Status } from './types';

/**
 * Status always shows as icon shape + label, never colour alone: check = on target,
 * half-filled circle = close, down-triangle = behind.
 */
export const STATUS: Record<Status, { label: string; icon: 'check' | 'half' | 'down'; tone: string }> = {
	green: { label: 'On target', icon: 'check', tone: 'good' },
	amber: { label: 'Close', icon: 'half', tone: 'warn' },
	red: { label: 'Behind', icon: 'down', tone: 'bad' }
};
