import { joinNames } from '$lib/format';
import type { Membership, Role } from '$lib/types';
import { units } from '$lib/units.svelte';

/** Up to two letters for an avatar: "Ana Reyes" → "AR", "ana@x.com" → "A". */
export function initials(name: string | null | undefined, email: string): string {
	const words = (name ?? '').trim().split(/\s+/).filter(Boolean);
	if (!words.length) return email.charAt(0).toUpperCase();
	return (words[0].charAt(0) + (words.length > 1 ? words[words.length - 1].charAt(0) : '')).toUpperCase();
}

const TONES = ['bg-accent-wash text-accent', 'bg-good-wash text-good-ink', 'bg-warn-wash text-warn-ink', 'bg-surface-2 text-ink-2 ring-1 ring-line'];

/** A steady colour per person, so the same person always looks the same. */
export function avatarTone(seed: string): string {
	let sum = 0;
	for (const ch of seed) sum = (sum + ch.charCodeAt(0)) % 997;
	return TONES[sum % TONES.length];
}

/** What someone can do, in a few words: "Everything", "Enters data for Downtown. Views North". */
export function accessSummary(role: Role, list: Membership[]): string {
	if (role === 'admin') return 'Can see and change everything';
	if (!list.length) return 'No access yet';
	const names = (r: Membership['role']) => list.filter((m) => m.role === r).map((m) => units.get(m.unit_id)?.name ?? 'something deleted');
	return [names('contributor').length && `Enters data for ${joinNames(names('contributor'))}`, names('viewer').length && `Views ${joinNames(names('viewer'))}`]
		.filter(Boolean)
		.join('. ');
}

/** "Oct 31", or "Oct 31, 2027" when it isn't this year. */
export function shortDate(iso: string): string {
	const date = new Date(iso.replace(' ', 'T'));
	const sameYear = date.getFullYear() === new Date().getFullYear();
	return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', ...(sameYear ? {} : { year: 'numeric' }) });
}
