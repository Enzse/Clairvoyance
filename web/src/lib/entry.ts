import type { EntryProgress, EntryRow, Kpi } from './types';

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

/** "1,250" and " 95.3 " are fine; empty is null; anything else is undefined (not a number). */
export function parseFigure(text: string): number | null | undefined {
	const cleaned = text.trim().replace(/,/g, '');
	if (cleaned === '') return null;
	return /^-?(\d+\.?\d*|\.\d+)$/.test(cleaned) ? Number(cleaned) : undefined;
}

/** One input per KPI and period. */
export const rowKey = (row: Pick<EntryRow, 'kpi' | 'period_start'>) => `${row.kpi.id}|${row.period_start}`;

/** The period's name on the form: "Week of 2 Jun", "Mon 2", or the usual label for longer ones. */
export function periodName(row: Pick<EntryRow, 'kpi' | 'period_start' | 'period_label'>): string {
	const [y, m, d] = row.period_start.split('-').map(Number);
	if (row.kpi.frequency === 'weekly') return `Week of ${d} ${MONTHS[m - 1]}`;
	if (row.kpi.frequency === 'daily') return `${DAYS[new Date(Date.UTC(y, m - 1, d)).getUTCDay()]} ${d}`;
	return row.period_label;
}

/**
 * Which day or week a daily or weekly KPI opens on: the one containing today, otherwise (a past
 * month) the first without a figure, otherwise the last.
 */
export function focusIndex(rows: Pick<EntryRow, 'period_start' | 'value'>[], current: string): number {
	const now = rows.findIndex((r) => r.period_start === current);
	if (now >= 0) return now;
	const empty = rows.findIndex((r) => r.value === null);
	return empty >= 0 ? empty : rows.length - 1;
}

/** "Today", "Yesterday", "This week", or the period's own name. */
export function focusName(row: Pick<EntryRow, 'kpi' | 'period_start' | 'period_label'>, current: string, previous: string): string {
	const weekly = row.kpi.frequency === 'weekly';
	if (row.period_start === current) return weekly ? 'This week' : 'Today';
	if (row.period_start === previous) return weekly ? 'Last week' : 'Yesterday';
	return weekly ? periodName(row) : `${periodName(row)} ${MONTHS[Number(row.period_start.slice(5, 7)) - 1]}`;
}

/** The form's rows grouped by KPI, keeping the server's order. */
export function byKpi(rows: EntryRow[]): { kpi: Kpi; rows: EntryRow[] }[] {
	const groups: { kpi: Kpi; rows: EntryRow[] }[] = [];
	for (const row of rows) {
		const last = groups[groups.length - 1];
		if (last && last.kpi.id === row.kpi.id) last.rows.push(row);
		else groups.push({ kpi: row.kpi, rows: [row] });
	}
	return groups;
}

export type UnitProgress = EntryProgress['units'][number];
export type ProgressState = 'done' | 'partly' | 'none' | 'nothing';

export function progressState(p: UnitProgress | undefined): ProgressState {
	if (!p || p.expected === 0) return 'nothing';
	return p.entered >= p.expected ? 'done' : p.entered > 0 ? 'partly' : 'none';
}

/** The next unit after `currentId` (in list order, wrapping round) that still has figures to enter. */
export function nextToDo(order: number[], progress: UnitProgress[], currentId: number | null): number | null {
	const byId = new Map(progress.map((p) => [p.unit_id, p]));
	const start = currentId === null ? -1 : order.indexOf(currentId);
	for (let i = 1; i <= order.length; i++) {
		const id = order[(start + i) % order.length];
		const state = progressState(byId.get(id));
		if (id !== currentId && (state === 'none' || state === 'partly')) return id;
	}
	return null;
}
