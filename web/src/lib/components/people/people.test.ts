import { describe, expect, it } from 'vitest';
import { accessSummary, avatarTone, coveredBy, initials } from './people';

describe('initials', () => {
	it('takes the first and last word', () => {
		expect(initials('Ana Maria Reyes', 'ana@example.com')).toBe('AR');
		expect(initials('ana', 'ana@example.com')).toBe('A');
	});

	it('falls back to the email', () => {
		expect(initials('', 'ben@example.com')).toBe('B');
		expect(initials(null, 'ben@example.com')).toBe('B');
	});
});

describe('avatarTone', () => {
	it('is the same for the same person', () => {
		expect(avatarTone('ana@example.com')).toBe(avatarTone('ana@example.com'));
	});
});

describe('accessSummary', () => {
	it('describes administrators and members without access', () => {
		expect(accessSummary('admin', [])).toBe('Can see and change everything');
		expect(accessSummary('member', [])).toBe('No access yet');
	});
});

describe('coveredBy', () => {
	// 1 is the organisation, 2 a region inside it, 3 a branch inside the region
	const ancestors = (id: number) => ({ 1: [], 2: [1], 3: [1, 2] })[id] ?? [];

	it('spots access a group above already gives', () => {
		const region = { unit_id: 2, role: 'contributor' as const };
		const branch = { unit_id: 3, role: 'viewer' as const };
		expect(coveredBy(branch, [region, branch], ancestors)).toBe(region);
	});

	it('keeps access that adds something', () => {
		const region = { unit_id: 2, role: 'viewer' as const };
		const branch = { unit_id: 3, role: 'contributor' as const };
		expect(coveredBy(branch, [region, branch], ancestors)).toBeUndefined();
		expect(coveredBy(region, [region, branch], ancestors)).toBeUndefined();
	});
});
