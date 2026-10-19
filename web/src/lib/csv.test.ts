import { describe, expect, it } from 'vitest';
import { detectDateFormat, exportRows, parseCsv, profileColumn, suggestKpi, suggestUnitColumns, writeCsv } from './csv';
import type { Kpi } from './types';

describe('parseCsv', () => {
	it('handles quotes, escaped quotes, CRLF, BOM and blank lines', () => {
		const { headers, rows } = parseCsv('﻿Unit,Note,Value\r\n"Store, North","He said ""hi""",1250\r\n\r\nStore B,,"1,300"\n');
		expect(headers).toEqual(['Unit', 'Note', 'Value']);
		expect(rows).toEqual([
			['Store, North', 'He said "hi"', '1250'],
			['Store B', '', '1,300']
		]);
	});
});

describe('detectDateFormat', () => {
	it('recognises unambiguous formats', () => {
		expect(detectDateFormat(['2025-01-31', '2025-02-28'])).toBe('YYYY-MM-DD');
		expect(detectDateFormat(['2025-01', '2025-02'])).toBe('YYYY-MM');
		expect(detectDateFormat(['05/06/2025', '25/06/2025'])).toBe('DD/MM/YYYY');
		expect(detectDateFormat(['05/06/2025', '06/25/2025'])).toBe('MM/DD/YYYY');
	});

	it('refuses to guess day-first versus month-first', () => {
		expect(detectDateFormat(['05/06/2025', '01/02/2025'])).toBeNull();
	});
});

describe('suggestions', () => {
	const kpis = [{ id: 1, code: 'total_students', name: 'Total students' }, { id: 2, code: 'att', name: 'Attendance rate' }] as Kpi[];

	it('matches KPIs by code or name', () => {
		expect(suggestKpi('Total_Students', kpis)?.id).toBe(1);
		expect(suggestKpi('Attendance Rate', kpis)?.id).toBe(2);
		expect(suggestKpi('Remarks', kpis)).toBeUndefined();
	});

	it('finds unit columns but not ids, dates or numbers', () => {
		const headers = ['Report_ID', 'Region', 'Division', 'School_Name', 'Month', 'Report_Date', 'Total_Students'];
		const samples = [['DEPED-1', 'DEPED-2'], ['NCR', 'NCR'], ['Manila', 'Cebu'], ['A', 'B'], ['January', 'January'], ['2025-01-31', '2025-01-31'], ['1785', '1240']];
		expect(suggestUnitColumns(headers, samples.map(profileColumn))).toEqual(['Region', 'Division', 'School_Name']);
	});
});

describe('export', () => {
	const data = {
		kpis: [
			{ id: 1, code: 'sales', name: 'Sales', decimals: 0 },
			{ id: 2, code: 'unused', name: 'Unused', decimals: 0 },
			{ id: 3, code: 'nps', name: 'Score, "net"', decimals: 1 }
		],
		units: [
			{ id: 10, name: 'Store A', code: null, path: ['North', 'Store A'] },
			{ id: 11, name: 'Store B', code: null, path: ['North', 'Store B'] }
		],
		values: [
			{ kpi_id: 1, unit_id: 11, period_start: '2025-06-01', value: 5 },
			{ kpi_id: 3, unit_id: 10, period_start: '2025-07-01', value: 7.5 },
			{ kpi_id: 1, unit_id: 10, period_start: '2025-06-01', value: 1250 }
		]
	};

	it('writes one row per unit and period, with only KPIs that have figures', () => {
		expect(exportRows(data)).toEqual([
			['Unit', 'Belongs to', 'Period', 'Sales', 'Score, "net"'],
			['Store A', 'North', '2025-06-01', 1250, null],
			['Store A', 'North', '2025-07-01', null, 7.5],
			['Store B', 'North', '2025-06-01', 5, null]
		]);
	});

	it('quotes what needs quoting and reads back the same', () => {
		const text = writeCsv(exportRows(data));
		expect(text.startsWith('﻿Unit,Belongs to,Period,Sales,"Score, ""net"""')).toBe(true);
		const parsed = parseCsv(text);
		expect(parsed.headers).toEqual(['Unit', 'Belongs to', 'Period', 'Sales', 'Score, "net"']);
		expect(parsed.rows[0]).toEqual(['Store A', 'North', '2025-06-01', '1250', '']);
		expect(suggestUnitColumns(parsed.headers, parsed.headers.map((_, i) => profileColumn(parsed.rows.map((r) => r[i]))))).toEqual(['Unit']);
	});
});
