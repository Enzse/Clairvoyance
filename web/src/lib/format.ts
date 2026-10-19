import type { Kpi } from './types';

type Formattable = Pick<Kpi, 'decimals' | 'prefix' | 'suffix'>;

const DASH = '–';

function number(value: number, decimals: number): string {
	return value.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

/** A KPI value with its prefix, suffix and decimals, e.g. "96.0%", "₱1,250". */
export function formatValue(kpi: Formattable, value: number | null | undefined): string {
	if (value === null || value === undefined) return DASH;
	const sign = value < 0 ? '−' : '';
	return `${sign}${kpi.prefix}${number(Math.abs(value), kpi.decimals)}${kpi.suffix}`;
}

/** A signed difference in the KPI's own units, e.g. "+1.2%" or "−3". */
export function formatDelta(kpi: Formattable, delta: number): string {
	const sign = delta > 0 ? '+' : delta < 0 ? '−' : '±';
	return `${sign}${kpi.prefix}${number(Math.abs(delta), kpi.decimals)}${kpi.suffix}`;
}

/** Compact number for axis ticks: 950, 1.2K, 3.4M. */
export function compact(value: number): string {
	return value.toLocaleString('en-US', { notation: 'compact', maximumFractionDigits: 1 });
}

/** English plural for a noun people type themselves (unit kinds): branch → branches, company → companies. */
export function pluralize(word: string): string {
	if (/(s|x|z|ch|sh)$/i.test(word)) return `${word}es`;
	if (/[^aeiou]y$/i.test(word)) return `${word.slice(0, -1)}ies`;
	return `${word}s`;
}

export function plural(count: number, one: string, many = pluralize(one)): string {
	return `${count.toLocaleString('en-US')} ${count === 1 ? one : many}`;
}

/** "North Branch, South Branch and 2 others" style list joining. */
export function joinNames(names: string[], max = 2): string {
	if (names.length <= max) {
		return names.length === 2 ? `${names[0]} and ${names[1]}` : names.join(', ');
	}
	return `${names.slice(0, max).join(', ')} and ${plural(names.length - max, 'other')}`;
}

/** "just now", "5 minutes ago", "3 hours ago", "yesterday", "4 days ago", then the date. */
export function timeAgo(at: string, now: Date = new Date()): string {
	const then = new Date(at.replace(' ', 'T'));
	const minutes = Math.floor((now.getTime() - then.getTime()) / 60000);
	if (minutes < 1) return 'just now';
	if (minutes < 60) return `${plural(minutes, 'minute')} ago`;
	const hours = Math.floor(minutes / 60);
	if (hours < 24) return `${plural(hours, 'hour')} ago`;
	const days = Math.floor(hours / 24);
	if (days === 1) return 'yesterday';
	if (days < 7) return `${days} days ago`;
	return then.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: then.getFullYear() === now.getFullYear() ? undefined : 'numeric' });
}
