import { api } from './api';
import type { Invitation, Member, Membership } from './types';

/**
 * The organisation's people and pending invitations, for screens that show who can see what.
 * Updating a member's memberships in `list` shows on every screen using it.
 */
class MemberStore {
	list = $state<Member[]>([]);
	invitations = $state<Invitation[]>([]);

	async load(): Promise<void> {
		[this.list, this.invitations] = await Promise.all([api.members(), api.invitations()]);
	}

	/** Members given access on this unit itself (not through a group above), with their level. */
	assignedTo(unitId: number): { member: Member; role: Membership['role'] }[] {
		return this.list.flatMap((m) =>
			m.role === 'member' ? m.memberships.filter((a) => a.unit_id === unitId).map((a) => ({ member: m, role: a.role })) : []
		);
	}

	/** Whether anyone has been given access to a particular unit. */
	get anyAssigned(): boolean {
		return this.list.some((m) => m.role === 'member' && m.memberships.length > 0);
	}
}

export const members = new MemberStore();
