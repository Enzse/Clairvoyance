import { describe, expect, it } from 'vitest';
import { describeRows, groupIssues } from './issues';

describe('groupIssues', () => {
	it('lists each problem once with its rows, most frequent first', () => {
		const groups = groupIssues([
			{ row: 2, column: 'Branch', message: "No branch is called 'Cubao,'" },
			{ row: 3, column: 'Sales', message: "'abc' is not a number" },
			{ row: 6, column: 'Branch', message: "No branch is called 'Cubao,'" }
		]);
		expect(groups.map((g) => [g.message, g.rows])).toEqual([
			["No branch is called 'Cubao,'", [2, 6]],
			["'abc' is not a number", [3]]
		]);
	});
});

describe('describeRows', () => {
	it('names a few rows and counts the rest', () => {
		expect(describeRows([4])).toBe('row 4');
		expect(describeRows([2, 6, 10])).toBe('rows 2, 6 and 10');
		expect(describeRows([2, 6, 10, 14])).toBe('rows 2, 6, 10 and 14');
		expect(describeRows([2, 6, 10, 14, 18])).toBe('rows 2, 6, 10 and 2 more');
	});
});
