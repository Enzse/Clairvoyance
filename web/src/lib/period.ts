import type { Frequency } from './types';

/**
 * Calendar periods identified by their start date (YYYY-MM-DD), mirroring api/domain/Period.php
 * so the period stepper and labels agree with the server. All math is in UTC.
 */

export const FREQUENCIES: Frequency[] = ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'];

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

function parse(date: string): Date {
	const [y, m, d] = date.split('-').map(Number);
	return new Date(Date.UTC(y, m - 1, d));
}

function iso(d: Date): string {
	return d.toISOString().slice(0, 10);
}

export function today(): string {
	return iso(new Date());
}

export function isValidDate(date: string): boolean {
	return /^\d{4}-\d{2}-\d{2}$/.test(date) && iso(parse(date)) === date;
}

export function align(date: string, frequency: Frequency): string {
	const d = parse(date);
	const y = d.getUTCFullYear();
	const m = d.getUTCMonth();
	switch (frequency) {
		case 'daily':
			return iso(d);
		case 'weekly': {
			const weekday = (d.getUTCDay() + 6) % 7; // Monday = 0
			return iso(new Date(Date.UTC(y, m, d.getUTCDate() - weekday)));
		}
		case 'monthly':
			return iso(new Date(Date.UTC(y, m, 1)));
		case 'quarterly':
			return iso(new Date(Date.UTC(y, Math.floor(m / 3) * 3, 1)));
		case 'yearly':
			return iso(new Date(Date.UTC(y, 0, 1)));
	}
}

export function shift(start: string, frequency: Frequency, count: number): string {
	const d = parse(start);
	const y = d.getUTCFullYear();
	const m = d.getUTCMonth();
	const day = d.getUTCDate();
	switch (frequency) {
		case 'daily':
			return iso(new Date(Date.UTC(y, m, day + count)));
		case 'weekly':
			return iso(new Date(Date.UTC(y, m, day + 7 * count)));
		case 'monthly':
			return iso(new Date(Date.UTC(y, m + count, day)));
		case 'quarterly':
			return iso(new Date(Date.UTC(y, m + 3 * count, day)));
		case 'yearly':
			return iso(new Date(Date.UTC(y + count, m, day)));
	}
}

/** ISO week number and week-year of a date. */
function isoWeek(d: Date): [number, number] {
	const t = new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), d.getUTCDate()));
	const weekday = (t.getUTCDay() + 6) % 7;
	t.setUTCDate(t.getUTCDate() - weekday + 3); // Thursday of this week decides the year
	const yearStart = new Date(Date.UTC(t.getUTCFullYear(), 0, 4));
	const week = 1 + Math.round(((t.getTime() - yearStart.getTime()) / 86400000 - 3 + ((yearStart.getUTCDay() + 6) % 7)) / 7);
	return [week, t.getUTCFullYear()];
}

/** "Jun 2025", "Q2 2025", "W27 2025", "2025", "5 Jun 2025". */
export function label(start: string, frequency: Frequency): string {
	const d = parse(start);
	const y = d.getUTCFullYear();
	switch (frequency) {
		case 'daily':
			return `${d.getUTCDate()} ${MONTHS[d.getUTCMonth()]} ${y}`;
		case 'weekly': {
			const [week, year] = isoWeek(d);
			return `W${String(week).padStart(2, '0')} ${year}`;
		}
		case 'monthly':
			return `${MONTHS[d.getUTCMonth()]} ${y}`;
		case 'quarterly':
			return `Q${Math.floor(d.getUTCMonth() / 3) + 1} ${y}`;
		case 'yearly':
			return String(y);
	}
}

/** Short axis label: "Jun", "Q2", "W27", "2025"; the year is added when it changes. */
export function axisLabel(start: string, frequency: Frequency, previous?: string): string {
	const d = parse(start);
	const newYear = !previous || parse(previous).getUTCFullYear() !== d.getUTCFullYear();
	const year = newYear ? ` ’${String(d.getUTCFullYear()).slice(2)}` : '';
	switch (frequency) {
		case 'monthly':
			return MONTHS[d.getUTCMonth()] + year;
		case 'quarterly':
			return `Q${Math.floor(d.getUTCMonth() / 3) + 1}${year}`;
		case 'weekly':
			return `W${isoWeek(d)[0]}${year}`;
		case 'yearly':
			return String(d.getUTCFullYear());
		case 'daily':
			return `${d.getUTCDate()} ${MONTHS[d.getUTCMonth()]}`;
	}
}

export const GRAIN_NAMES: Record<Frequency, { noun: string; adjective: string }> = {
	daily: { noun: 'Day', adjective: 'Daily' },
	weekly: { noun: 'Week', adjective: 'Weekly' },
	monthly: { noun: 'Month', adjective: 'Monthly' },
	quarterly: { noun: 'Quarter', adjective: 'Quarterly' },
	yearly: { noun: 'Year', adjective: 'Yearly' }
};
