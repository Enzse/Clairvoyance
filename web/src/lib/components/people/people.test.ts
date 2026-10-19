import { describe, expect, it } from 'vitest';
import { accessSummary, avatarTone, initials } from './people';

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
