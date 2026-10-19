import { detectDateFormat, parseCsv, profileColumn, suggestKpi, suggestUnitColumns, writeCsv } from './csv';
import type { DateFormat, ImportMapping, Kpi } from './types';

export const DATE_FORMATS: DateFormat[] = ['YYYY-MM-DD', 'DD/MM/YYYY', 'MM/DD/YYYY', 'YYYY-MM'];
const MAX_BYTES = 5 * 1024 * 1024;

/** The column matching worth keeping between uploads of a file with the same columns. */
type Saved = Pick<ImportUpload, 'unitColumns' | 'unitMatch' | 'unitKinds' | 'dateColumn' | 'dateFormat' | 'kpiFor' | 'createMissing'>;

/** An Excel cell as CSV text: dates as YYYY-MM-DD, everything else as it reads. */
function cellText(value: unknown): string | number | null {
	if (value === null || value === undefined) return null;
	if (value instanceof Date) return value.toISOString().slice(0, 10);
	return typeof value === 'number' ? value : String(value);
}

/** The first sheet of an Excel file as a CSV file, so it goes through the same import as a CSV. */
async function excelAsCsv(file: File): Promise<File> {
	const { readSheet } = await import('read-excel-file/browser');
	const rows = (await readSheet(file)) as unknown[][];
	return new File([writeCsv(rows.map((row) => row.map(cellText)))], file.name.replace(/\.xlsx$/i, '.csv'), { type: 'text/csv' });
}

/**
 * One spreadsheet being imported: the file, a sample of its rows, and which columns hold the
 * unit, the date and each KPI. The matching is guessed from the file, or reused from the last
 * upload with the same columns.
 */
export class ImportUpload {
	file = $state<File | null>(null);
	headers = $state<string[]>([]);
	sample = $state<string[][]>([]);
	unitColumns = $state<string[]>([]);
	unitMatch = $state<ImportMapping['unit_match']>('path');
	unitKinds = $state<Record<string, string>>({});
	parentUnit = $state<number | null>(null);
	createMissing = $state(false);
	dateColumn = $state('');
	dateFormat = $state<DateFormat | ''>('');
	kpiFor = $state<Record<string, string>>({});
	/** The matching came from the last upload with these columns */
	remembered = $state(false);

	constructor(
		private orgId: number,
		private kpis: () => Kpi[]
	) {}

	/** Reads a CSV or Excel file. Returns a problem to show, or '' when it's ready to match. */
	async read(chosen: File): Promise<string> {
		if (chosen.size > MAX_BYTES) return 'That file is over 5 MB. Split it into smaller files.';
		let file = chosen;
		if (/\.xlsx$/i.test(chosen.name)) {
			try {
				file = await excelAsCsv(chosen);
			} catch {
				return "That Excel file couldn't be read. Try saving it again, or as a CSV.";
			}
		} else if (/\.xls$/i.test(chosen.name)) {
			return 'That is an older Excel file (.xls). Save it as .xlsx or CSV first.';
		}
		const parsed = parseCsv(await file.text());
		if (!parsed.headers.length) return 'That file is empty.';
		this.file = file;
		this.headers = parsed.headers;
		this.sample = parsed.rows;
		this.suggest();
		this.remembered = this.recall();
		return '';
	}

	column(header: string): string[] {
		const i = this.headers.indexOf(header);
		return this.sample.map((r) => r[i] ?? '');
	}

	/** Guesses from the column names and their contents. */
	suggest(): void {
		const profiles = this.headers.map((h) => profileColumn(this.column(h)));
		this.unitColumns = suggestUnitColumns(this.headers, profiles);
		this.unitMatch = this.unitColumns.length > 1 ? 'path' : 'name';
		this.unitKinds = Object.fromEntries(this.unitColumns.map((c) => [c, c.replace(/_name$/i, '').replace(/_/g, ' ')]));
		const dates = this.headers.filter((_, i) => profiles[i].dateLike);
		this.dateColumn = dates.find((h) => /date|period/i.test(h)) ?? dates[0] ?? '';
		this.dateFormat = this.dateColumn ? (detectDateFormat(this.column(this.dateColumn)) ?? '') : '';
		this.kpiFor = Object.fromEntries(this.headers.map((h) => [h, suggestKpi(h, this.kpis())?.code ?? '']));
		this.createMissing = false;
		this.remembered = false;
	}

	toggleUnitColumn(header: string): void {
		this.unitColumns = this.unitColumns.includes(header)
			? this.unitColumns.filter((c) => c !== header)
			: this.headers.filter((c) => c === header || this.unitColumns.includes(c));
		this.unitKinds[header] ??= header.replace(/_name$/i, '').replace(/_/g, ' ');
		if (this.unitColumns.length > 1) this.unitMatch = 'path';
	}

	get otherColumns(): string[] {
		return this.headers.filter((h) => !this.unitColumns.includes(h) && h !== this.dateColumn);
	}

	mapping(isAdmin: boolean): ImportMapping {
		return {
			unit_columns: this.unitColumns,
			unit_match: this.unitColumns.length > 1 ? 'path' : this.unitMatch,
			unit_kinds: this.unitColumns.map((c) => this.unitKinds[c] ?? c),
			parent_unit_id: this.parentUnit ?? undefined,
			create_missing_units: isAdmin && this.createMissing,
			date_column: this.dateColumn,
			date_format: this.dateFormat as DateFormat,
			columns: Object.fromEntries(this.otherColumns.filter((h) => this.kpiFor[h]).map((h) => [h, this.kpiFor[h]]))
		};
	}

	private get key(): string {
		return `clairvoyance.import.${this.orgId}.${this.headers.join('|')}`;
	}

	/** Keeps the matching for the next file with the same columns (call after a successful import). */
	remember(): void {
		const saved: Saved = {
			unitColumns: this.unitColumns,
			unitMatch: this.unitMatch,
			unitKinds: this.unitKinds,
			dateColumn: this.dateColumn,
			dateFormat: this.dateFormat,
			kpiFor: this.kpiFor,
			createMissing: this.createMissing
		};
		try {
			localStorage.setItem(this.key, JSON.stringify(saved));
		} catch {
			// Private browsing: it will be matched again next time
		}
	}

	private recall(): boolean {
		try {
			const raw = localStorage.getItem(this.key);
			if (!raw) return false;
			const saved = JSON.parse(raw) as Saved;
			// Only KPIs that still exist
			const codes = new Set(this.kpis().map((k) => k.code));
			Object.assign(this, { ...saved, kpiFor: Object.fromEntries(Object.entries(saved.kpiFor).map(([h, c]) => [h, codes.has(c) ? c : ''])) });
			return true;
		} catch {
			return false;
		}
	}
}
