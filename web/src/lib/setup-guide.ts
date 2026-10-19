import { plural } from './format';
import type { Setup, SetupStep } from './types';

export type StepState = 'done' | 'skipped' | 'todo';

export interface GuideStep {
	key: SetupStep;
	state: StepState;
	title: string;
	/** Why the step matters, in a sentence or two */
	why: string;
	/** What's there already, e.g. "3 KPIs" (shown once done) */
	progress: string;
	actions: { label: string; href: string }[];
	/** Label for skipping, when the step can wait */
	skip: string | null;
}

/**
 * The guide's five steps, worded with the organisation's own word for its units
 * (one: "branch", many: "branches").
 */
export function guideSteps(setup: Setup, one: string, many: string, emailed: boolean): GuideStep[] {
	const c = setup.counts;
	const joined = c.members - 1;
	const steps: Omit<GuideStep, 'state'>[] = [
		{
			key: 'structure',
			title: `Add your ${many}`,
			why: `Figures are entered by each ${one} and added up for the whole organisation. You can group them later, for example by region.`,
			progress: `${c.units} added`,
			actions: [{ label: `Add ${many}`, href: '/admin/units' }],
			skip: 'We report as one team'
		},
		{
			key: 'kpis',
			title: 'Choose what you measure',
			why: `Each KPI knows how its figures add up over time and across ${many}, so every total and average comes out right.`,
			progress: plural(c.kpis, 'KPI'),
			actions: [{ label: 'Add a KPI', href: '/admin/kpis?new=1' }],
			skip: null
		},
		{
			key: 'targets',
			title: 'Set targets',
			why: 'A target turns a number into a verdict: on target, close or behind. KPIs without one are simply tracked.',
			progress: `${plural(c.targets, 'target')} set`,
			actions: [{ label: 'Set targets', href: '/admin/kpis' }],
			skip: 'Track without targets for now'
		},
		{
			key: 'data',
			title: 'Add your first figures',
			why: `Type in one ${one}'s figures for a month, or upload a spreadsheet with many at once. The scorecard fills in as they arrive.`,
			progress: plural(c.values, 'figure'),
			actions: [
				{ label: 'Type in figures', href: '/enter' },
				{ label: 'Upload a spreadsheet', href: '/import' }
			],
			skip: null
		},
		{
			key: 'people',
			title: 'Invite your team',
			why: `Give each person access to the ${many} they report for or review. They get ${emailed ? 'an email with a link' : 'a link'} to join.`,
			progress: [joined > 0 && `${joined} joined`, c.invitations > 0 && `${c.invitations} invited`].filter(Boolean).join(', '),
			actions: [{ label: 'Invite people', href: '/admin/users?invite=1' }],
			skip: "I'm on my own for now"
		}
	];
	return steps.map((s) => ({
		...s,
		skip: setup.skippable.includes(s.key) ? s.skip : null,
		state: setup.steps[s.key] ? 'done' : setup.skipped.includes(s.key) ? 'skipped' : 'todo'
	}));
}

/** Steps finished (done or skipped) since the keys remembered from the last visit. */
export function newlyFinished(steps: GuideStep[], seen: string[]): GuideStep[] {
	return steps.filter((s) => s.state === 'done' && !seen.includes(s.key));
}
