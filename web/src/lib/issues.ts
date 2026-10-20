import type { ImportIssue } from './types';

export interface IssueGroup {
	message: string;
	column: string | null;
	rows: number[];
}

/** The same problem on many rows, listed once with its rows, most frequent first. */
export function groupIssues(issues: ImportIssue[]): IssueGroup[] {
	const groups = new Map<string, IssueGroup>();
	for (const issue of issues) {
		const key = `${issue.column ?? ''}\u0000${issue.message}`;
		const group = groups.get(key) ?? { message: issue.message, column: issue.column ?? null, rows: [] };
		group.rows.push(issue.row);
		groups.set(key, group);
	}
	return [...groups.values()].sort((a, b) => b.rows.length - a.rows.length);
}

/** "row 4", "rows 2, 6 and 10", "rows 2, 6, 10 and 8 more". */
export function describeRows(rows: number[], shown = 3): string {
	if (rows.length === 1) return `row ${rows[0]}`;
	if (rows.length <= shown + 1) return `rows ${rows.slice(0, -1).join(', ')} and ${rows[rows.length - 1]}`;
	return `rows ${rows.slice(0, shown).join(', ')} and ${rows.length - shown} more`;
}
