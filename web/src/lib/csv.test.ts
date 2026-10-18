import { describe, expect, it } from 'vitest';
import { detectDateFormat, parseCsv, profileColumn, suggestKpi, suggestUnitColumns } from './csv';
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
