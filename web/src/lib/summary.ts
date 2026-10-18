import { joinNames } from './format';
import type { ScorecardRow } from './types';

/**
 * The masthead's one-line reading of a scorecard, e.g.
 * "On target for 4 of 5 KPIs with a target in Q2 2025. Behind on Teacher absences. Some figures are
 * still missing for Attendance rate."
 */
export function summarize(rows: ScorecardRow[]): string {
	if (!rows.length) return 'No KPIs are set up yet.';
	const period = rows[0].period_label;
	if (rows.every((r) => r.reported === 0)) return `Nothing has been reported for ${period} yet.`;
	const incomplete = rows.filter((r) => r.reported < r.expected).map((r) => r.kpi.name);

	const judged = rows.filter((r) => r.status !== null);
	const names = (status: string) => rows.filter((r) => r.status === status).map((r) => r.kpi.name);
	const parts: string[] = [];

	if (judged.length) {
		const green = names('green').length;
		parts.push(
			green === judged.length
				? `On target for ${judged.length === 1 ? 'the one KPI' : `all ${judged.length} KPIs`} with a target in ${period}.`
				: `On target for ${green} of ${judged.length} KPIs with a target in ${period}.`
		);
		const red = names('red');
		const amber = names('amber');
		if (red.length) parts.push(`Behind on ${joinNames(red)}.`);
		if (amber.length) parts.push(`Close on ${joinNames(amber)}.`);
	} else {
		parts.push(`${period}: none of these KPIs has a target yet.`);
	}

	parts.push(incomplete.length ? `Some figures are still missing for ${joinNames(incomplete)}.` : 'Every unit has reported.');
	return parts.join(' ');
}
