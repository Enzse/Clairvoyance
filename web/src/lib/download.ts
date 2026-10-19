import { writeCsv } from './csv';

/** Saves rows as a CSV file in the browser's downloads. */
export function downloadCsv(rows: (string | number | null)[][], filename: string): void {
	const blob = new Blob([writeCsv(rows)], { type: 'text/csv;charset=utf-8' });
	const link = document.createElement('a');
	link.href = URL.createObjectURL(blob);
	link.download = filename;
	link.click();
	URL.revokeObjectURL(link.href);
}
