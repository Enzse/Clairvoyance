import { pluralize } from './format';
import { session } from './session.svelte';
import type { Unit } from './types';

export const cap = (word: string) => word.charAt(0).toUpperCase() + word.slice(1);

/**
 * The organisation's own word for the parts that report figures ("branch", "department",
 * "team"), chosen in onboarding and changeable in Admin › Settings. Read it in templates
 * ({term.many}) so it updates when the organisation changes.
 */
export const term = {
	get one(): string {
		return session.user?.organization?.unit_label || 'team';
	},
	get many(): string {
		return pluralize(this.one);
	},
	get One(): string {
		return cap(this.one);
	},
	get Many(): string {
		return cap(this.many);
	}
};

/**
 * The word for a particular list of units: their shared kind ("regions" for a list of
 * regions), otherwise the organisation's word.
 */
export function wordFor(units: Pick<Unit, 'kind'>[], plural = true): string {
	const kinds = new Set(units.map((u) => u.kind?.trim().toLowerCase() ?? ''));
	const [only] = kinds;
	const word = kinds.size === 1 && only ? only : term.one;
	return plural ? pluralize(word) : word;
}
