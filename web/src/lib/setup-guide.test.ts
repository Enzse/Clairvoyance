import { describe, expect, it } from 'vitest';
import { guideSteps, newlyFinished } from './setup-guide';
import type { Setup } from './types';

const setup = (over: Partial<Setup> = {}): Setup => ({
	dismissed: false,
	steps: { structure: true, kpis: true, targets: false, data: false, people: false },
	skipped: ['targets'],
	skippable: ['structure', 'targets', 'people'],
	complete: false,
	counts: { units: 2, kpis: 3, targets: 0, values: 0, members: 1, invitations: 0 },
	...over
});

describe('guideSteps', () => {
	it('words every step with the organisation’s own word and knows each state', () => {
		const steps = guideSteps(setup(), 'branch', 'branches', true);
		expect(steps.map((s) => s.state)).toEqual(['done', 'done', 'skipped', 'todo', 'todo']);
		expect(steps[0].title).toBe('Add your branches');
		expect(steps[1].progress).toBe('3 KPIs');
		expect(steps[3].why).toContain("one branch's figures");
	});

	it('only offers skipping where the server allows it', () => {
		const steps = guideSteps(setup(), 'team', 'teams', false);
		expect(steps.filter((s) => s.skip).map((s) => s.key)).toEqual(['structure', 'targets', 'people']);
		expect(steps[4].why).toContain('They get a link to join.');
	});

	it('describes who has joined and who is invited', () => {
		const people = guideSteps(setup({ counts: { units: 0, kpis: 0, targets: 0, values: 0, members: 3, invitations: 1 } }), 'team', 'teams', true)[4];
		expect(people.progress).toBe('2 joined, 1 invited');
	});
});

describe('newlyFinished', () => {
	it('finds steps done since the last visit', () => {
		const steps = guideSteps(setup(), 'team', 'teams', true);
		expect(newlyFinished(steps, ['structure']).map((s) => s.key)).toEqual(['kpis']);
		expect(newlyFinished(steps, ['structure', 'kpis'])).toEqual([]);
	});
});
