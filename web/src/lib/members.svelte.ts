import { api } from './api';
import type { Invitation, Member } from './types';
import { units } from './units.svelte';

/**
 * The organisation's people and pending invitations, for screens that show who can see what.
 * Changing someone's access through `setAccess` updates every screen using it.
 */
class MemberStore {
	list = $state<Member[]>([]);
	invitations = $state<Invitation[]>([]);

	async load(): Promise<void> {
		[this.list, this.invitations] = await Promise.all([api.members(), api.invitations()]);
	}

	/** Members (not administrators) who can see the unit, given directly or through a group above. */
	withAccess(unitId: number): Member[] {
		const ids = new Set(units.path(unitId).map((u) => u.id));
		return this.list.filter((m) => m.role === 'member' && m.memberships.some((a) => ids.has(a.unit_id)));
	}
}

export const members = new MemberStore();
