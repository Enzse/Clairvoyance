import type { DateFormat, ExportData, Kpi } from './types';

/** Minimal RFC 4180 parser (quotes, escaped quotes, CRLF) for previewing a file in the browser. */
export function parseCsv(text: string, maxRows = 500): { headers: string[]; rows: string[][] } {
	const records: string[][] = [];
	let field = '';
	let record: string[] = [];
	let quoted = false;
	text = text.replace(/^﻿/, '');

	for (let i = 0; i < text.length && records.length <= maxRows; i++) {
		const c = text[i];
		if (quoted) {
			if (c === '"' && text[i + 1] === '"') {
				field += '"';
				i++;
			} else if (c === '"') {
				quoted = false;
			} else {
				field += c;
			}
		} else if (c === '"') {
			quoted = true;
		} else if (c === ',') {
			record.push(field);
			field = '';
		} else if (c === '\n' || c === '\r') {
			if (c === '\r' && text[i + 1] === '\n') i++;
			record.push(field);
			if (record.some((f) => f.trim() !== '')) records.push(record);
			record = [];
			field = '';
		} else {
			field += c;
		}
	}
	record.push(field);
	if (record.some((f) => f.trim() !== '')) records.push(record);

	const [headers = [], ...rows] = records;
	return { headers: headers.map((h) => h.trim()), rows: rows.slice(0, maxRows) };
}

const DATE_PATTERNS: [DateFormat, RegExp][] = [
	['YYYY-MM-DD', /^\d{4}-\d{2}-\d{2}$/],
	['YYYY-MM', /^\d{4}-\d{2}$/]
];
const SLASHED = /^(\d{1,2})\/(\d{1,2})\/\d{4}$/;

/** How a column's sample values look. */
export function profileColumn(values: string[]) {
	const filled = values.map((v) => v.trim()).filter((v) => v !== '');
	const share = (test: (v: string) => boolean) => (filled.length ? filled.filter(test).length / filled.length : 0);
	return {
		numeric: share((v) => /^-?(\d{1,3}(,\d{3})+|\d+)?(\.\d+)?%?$/.test(v.replace(/\s/g, '')) && /\d/.test(v)) >= 0.8,
		dateLike: share((v) => DATE_PATTERNS.some(([, re]) => re.test(v)) || SLASHED.test(v)) >= 0.8,
		unique: new Set(filled).size === filled.length && filled.length > 1
	};
}

/**
 * The date format of a column, or null when it can't be told (e.g. every value is like
 * 05/06/2025, which could be 5 June or May 6). Never guesses between day-first and month-first.
 */
export function detectDateFormat(values: string[]): DateFormat | null {
	const filled = values.map((v) => v.trim()).filter(Boolean);
	for (const [format, re] of DATE_PATTERNS) if (filled.length && filled.every((v) => re.test(v))) return format;
	if (filled.length && filled.every((v) => SLASHED.test(v))) {
		const parts = filled.map((v) => v.match(SLASHED)!.slice(1, 3).map(Number));
		if (parts.some(([a]) => a > 12)) return 'DD/MM/YYYY';
		if (parts.some(([, b]) => b > 12)) return 'MM/DD/YYYY';
	}
	return null;
}

const normalize = (s: string) => s.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');

/** A KPI whose code or name matches the column header, e.g. "Total_Students" -> total_students. */
export function suggestKpi(header: string, kpis: Kpi[]): Kpi | undefined {
	const key = normalize(header);
	return kpis.find((k) => k.code === key || normalize(k.name) === key);
}

const UNIT_HEADER = /region|division|district|province|school|branch|store|shop|unit|department|dept|office|site|location|clinic|area/i;

/** Columns that probably name units, in file order (a path such as Region, Division, School). */
export function suggestUnitColumns(headers: string[], profiles: ReturnType<typeof profileColumn>[]): string[] {
	return headers.filter((h, i) => UNIT_HEADER.test(h) && !profiles[i].numeric && !profiles[i].dateLike && !/(^|_)id$/i.test(h));
}

/** RFC 4180 text, with a byte-order mark so Excel reads accents correctly. */
export function writeCsv(rows: (string | number | null)[][]): string {
	const cell = (value: string | number | null) => {
		const text = value === null ? '' : String(value);
		return /[",\r\n]/.test(text) || /^\s|\s$/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
	};
	return '﻿' + rows.map((row) => row.map(cell).join(',')).join('\r\n') + '\r\n';
}

type SheetUnit = ExportData['units'][number];

/** The unit columns shared by exports and templates: name, then code and where it sits when useful. */
function unitLayout(units: SheetUnit[]) {
	const hasCodes = units.some((u) => u.code);
	const nested = units.some((u) => u.path.length > 1);
	return {
		header: ['Unit', ...(hasCodes ? ['Code'] : []), ...(nested ? ['Belongs to'] : [])],
		cells: (unit: SheetUnit) => [unit.name, ...(hasCodes ? [unit.code] : []), ...(nested ? [unit.path.slice(0, -1).join(' › ')] : [])]
	};
}

/**
 * Exported figures as rows the importer reads back: one per unit and period, then one column
 * per KPI (named as in the app, which the import wizard matches). Match units by name, or by
 * code when they have codes; dates are YYYY-MM-DD period starts.
 */
export function exportRows(data: ExportData): (string | number | null)[][] {
	const withValues = new Set(data.values.map((v) => v.kpi_id));
	const kpis = data.kpis.filter((k) => withValues.has(k.id));
	const units = new Map(data.units.map((u) => [u.id, u]));
	const layout = unitLayout(data.units);

	const rows = new Map<string, Map<number, number>>();
	for (const v of data.values) {
		const key = `${v.unit_id}|${v.period_start}`;
		if (!rows.has(key)) rows.set(key, new Map());
		rows.get(key)!.set(v.kpi_id, v.value);
	}
	const order = data.units.map((u) => u.id);
	const keys = [...rows.keys()].sort((a, b) => {
		const [ua, pa] = a.split('|');
		const [ub, pb] = b.split('|');
		return order.indexOf(Number(ua)) - order.indexOf(Number(ub)) || pa.localeCompare(pb);
	});

	return [
		[...layout.header, 'Period', ...kpis.map((k) => k.name)],
		...keys.map((key) => {
			const [unitId, period] = key.split('|');
			const values = rows.get(key)!;
			return [...layout.cells(units.get(Number(unitId))!), period, ...kpis.map((k) => values.get(k.id) ?? null)];
		})
	];
}

/** An empty sheet to fill in: one row per unit for the period, one column per KPI. */
export function templateRows(units: SheetUnit[], kpis: { name: string }[], period: string): (string | number | null)[][] {
	const layout = unitLayout(units);
	return [[...layout.header, 'Period', ...kpis.map((k) => k.name)], ...units.map((u) => [...layout.cells(u), period, ...kpis.map(() => null)])];
}
