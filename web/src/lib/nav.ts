import { FREQUENCIES } from './period';
import type { Frequency } from './types';

type Params = Record<string, string | number | null | undefined>;

/** A path with only the non-empty query parameters, e.g. href('/', { unit: 3, date: null }) -> "/?unit=3". */
export function href(path: string, params: Params = {}): string {
	const search = new URLSearchParams();
	for (const [key, value] of Object.entries(params)) {
		if (value !== null && value !== undefined && value !== '') search.set(key, String(value));
	}
	const query = search.toString();
	return query ? `${path}?${query}` : path;
}

/** The report context (where and when) shared by the scorecard and KPI pages via the URL. */
export interface ReportContext {
	unit: number | null;
	date: string | null;
	grain: Frequency;
}

export function readContext(url: URL): ReportContext {
	const unit = Number(url.searchParams.get('unit'));
	const date = url.searchParams.get('date');
	const grain = url.searchParams.get('grain') as Frequency;
	return {
		unit: Number.isInteger(unit) && unit > 0 ? unit : null,
		date: date && /^\d{4}-\d{2}-\d{2}$/.test(date) ? date : null,
		grain: FREQUENCIES.includes(grain) ? grain : 'monthly'
	};
}

/** Only non-default values go into the URL, so links stay short. */
export function contextParams(ctx: Partial<ReportContext>): Params {
	return { unit: ctx.unit, date: ctx.date, grain: ctx.grain === 'monthly' ? null : ctx.grain };
}
