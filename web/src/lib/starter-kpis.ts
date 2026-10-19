import type { Kpi } from './types';

/**
 * Common business KPIs offered during onboarding, with the aggregation rules already chosen
 * (the part people most often get wrong). Definitions only: no figures, no targets.
 */
export interface StarterKpi {
	key: string;
	area: 'Money' | 'Customers' | 'People' | 'Operations';
	name: string;
	description: string;
	/** Shown under the name so the choice makes sense without knowing the rules; {many} stands for the organisation's word ("branches") */
	summary: string;
	time_aggregation: Kpi['time_aggregation'];
	unit_aggregation: Kpi['unit_aggregation'];
	/** For weighted averages: the starter KPI to weight by (falls back to a plain average) */
	weightKey?: string;
	direction: Kpi['direction'];
	tolerance_pct: number;
	decimals: number;
	money?: boolean;
	suffix?: string;
}

export const STARTER_KPIS: StarterKpi[] = [
	{ key: 'revenue', area: 'Money', name: 'Revenue', description: 'Sales income for the month', summary: 'Added up across months and {many}', time_aggregation: 'sum', unit_aggregation: 'sum', direction: 'higher_is_better', tolerance_pct: 5, decimals: 0, money: true },
	{ key: 'expenses', area: 'Money', name: 'Operating expenses', description: 'Running costs for the month', summary: 'Added up; lower is better', time_aggregation: 'sum', unit_aggregation: 'sum', direction: 'lower_is_better', tolerance_pct: 5, decimals: 0, money: true },
	{ key: 'gross_margin', area: 'Money', name: 'Gross margin', description: 'Revenue left after the cost of what was sold', summary: 'Averaged, bigger sellers count more', time_aggregation: 'average', unit_aggregation: 'weighted_average', weightKey: 'revenue', direction: 'higher_is_better', tolerance_pct: 5, decimals: 1, suffix: '%' },
	{ key: 'new_customers', area: 'Customers', name: 'New customers', description: 'Customers who bought for the first time', summary: 'Added up across months and {many}', time_aggregation: 'sum', unit_aggregation: 'sum', direction: 'higher_is_better', tolerance_pct: 10, decimals: 0 },
	{ key: 'customer_satisfaction', area: 'Customers', name: 'Customer satisfaction', description: 'Share of surveyed customers who were satisfied', summary: 'Averaged across months and {many}', time_aggregation: 'average', unit_aggregation: 'average', direction: 'higher_is_better', tolerance_pct: 5, decimals: 0, suffix: '%' },
	{ key: 'complaints', area: 'Customers', name: 'Complaints', description: 'Customer complaints received', summary: 'Added up; lower is better', time_aggregation: 'sum', unit_aggregation: 'sum', direction: 'lower_is_better', tolerance_pct: 20, decimals: 0 },
	{ key: 'headcount', area: 'People', name: 'Headcount', description: 'People employed at month end', summary: 'Latest month, added up across {many}', time_aggregation: 'last', unit_aggregation: 'sum', direction: 'none', tolerance_pct: 10, decimals: 0 },
	{ key: 'staff_turnover', area: 'People', name: 'Staff turnover', description: 'Share of staff who left during the month', summary: 'Averaged, bigger teams count more', time_aggregation: 'average', unit_aggregation: 'weighted_average', weightKey: 'headcount', direction: 'lower_is_better', tolerance_pct: 20, decimals: 1, suffix: '%' },
	{ key: 'absenteeism', area: 'People', name: 'Absenteeism', description: 'Share of scheduled work days missed', summary: 'Averaged; lower is better', time_aggregation: 'average', unit_aggregation: 'average', direction: 'lower_is_better', tolerance_pct: 20, decimals: 1, suffix: '%' },
	{ key: 'orders', area: 'Operations', name: 'Orders', description: 'Orders or transactions completed', summary: 'Added up across months and {many}', time_aggregation: 'sum', unit_aggregation: 'sum', direction: 'higher_is_better', tolerance_pct: 10, decimals: 0 },
	{ key: 'on_time_delivery', area: 'Operations', name: 'On-time delivery', description: 'Share of orders delivered when promised', summary: 'Averaged, busier {many} count more', time_aggregation: 'average', unit_aggregation: 'weighted_average', weightKey: 'orders', direction: 'higher_is_better', tolerance_pct: 3, decimals: 1, suffix: '%' },
	{ key: 'stock_outs', area: 'Operations', name: 'Stock-outs', description: 'Times an item ran out', summary: 'Added up; lower is better', time_aggregation: 'sum', unit_aggregation: 'sum', direction: 'lower_is_better', tolerance_pct: 20, decimals: 0 }
];

export const AREAS = ['Money', 'Customers', 'People', 'Operations'] as const;

/** Currency symbol guessed from the browser's region, for money KPIs. */
export function guessCurrency(): string {
	const region = (navigator.language.split('-')[1] ?? '').toUpperCase();
	const byRegion: Record<string, string> = { PH: '₱', US: '$', CA: '$', AU: '$', NZ: '$', SG: '$', GB: '£', JP: '¥', CN: '¥', IN: '₹', KR: '₩' };
	const euro = ['DE', 'FR', 'ES', 'IT', 'NL', 'BE', 'IE', 'PT', 'AT', 'FI', 'GR'];
	return byRegion[region] ?? (euro.includes(region) ? '€' : '$');
}

export const CURRENCIES = ['₱', '$', '€', '£', '¥', '₹', '₩', ''];
