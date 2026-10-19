import { formatDelta, joinNames } from './format';
import { GRAIN_NAMES } from './period';
import type { Change, Frequency, Kpi, ScorecardRow } from './types';

/**
 * The masthead's one-line reading of a scorecard, e.g.
 * "On target for 4 of 5 KPIs with a target in Q2 2025. Behind on Customer churn. Some figures are
 * still missing for Net revenue."
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

	parts.push(incomplete.length ? `Some figures are still missing for ${joinNames(incomplete)}.` : 'Everyone has reported.');
	return parts.join(' ');
}

/**
 * The change sentence for a KPI, e.g. "Down $2,500 from Aug 2025." When some units hadn't
 * reported, the comparison covers only those that reported both periods, and says so.
 */
export function describeChange(
	kpi: Kpi,
	change: Change | null,
	previous: { period_label: string; value: number | null } | null,
	grain: Frequency,
	units = 'units'
): string {
	if (!previous || previous.value === null) return '';
	if (!change) return ` Not comparable with ${previous.period_label} yet: nothing has been reported for both.`;
	const amount = formatDelta(kpi, Math.abs(change.value)).slice(1);
	const movement = change.value === 0 ? `Unchanged from ${previous.period_label}` : `${change.value > 0 ? 'Up' : 'Down'} ${amount} from ${previous.period_label}`;
	if (change.basis === 'all') return ` ${movement}.`;
	const periods = `${GRAIN_NAMES[grain].noun.toLowerCase()}s`;
	return ` ${movement} for the ${change.units_compared} of ${change.units_total} ${units} that reported both ${periods}.`;
}
