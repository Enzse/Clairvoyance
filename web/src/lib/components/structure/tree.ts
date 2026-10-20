import type { Member, Membership, Unit } from '$lib/types';

/** What every row of the structure tree shares: selection, folding, adding. */
export type Tree = {
	selectedId: number | null;
	collapsed: Set<number>;
	addingUnder: number | null;
	addingGroup: boolean;
	fresh: Set<number>;
	select: (id: number) => void;
	toggle: (id: number) => void;
	startAdd: (parentId: number) => void;
	stopAdd: () => void;
	added: (ids: number[]) => void;
	kindFor: (parentId: number) => string | null;
	/** People given access on the unit itself */
	assigned: (id: number) => { member: Member; role: Membership['role'] }[];
	/** Whether rows keep a column for those people */
	showAccess: boolean;
	/** The unit being dragged, if any */
	draggingId: number | null;
	startDrag: (id: number) => void;
	endDrag: () => void;
	/** Whether the dragged unit may be dropped into this one */
	canDrop: (targetId: number) => boolean;
	drop: (targetId: number) => void;
};

type UnitLookup = { get: (id: number) => Unit | undefined; path: (id: number) => Unit[]; children: (id: number) => Unit[] };

/**
 * Whether a unit may be moved into another by dragging: only into a group or the organisation
 * itself (dropping on a plain unit would quietly turn it into a group), never into a unit that
 * holds figures, never into itself or anything inside it, and not where it already is.
 */
export function canMoveInto(movingId: number, targetId: number, units: UnitLookup): boolean {
	const moving = units.get(movingId);
	const target = units.get(targetId);
	if (!moving || !target || moving.parent_id === null || movingId === targetId || moving.parent_id === targetId) return false;
	if (target.has_values || units.path(targetId).some((u) => u.id === movingId)) return false;
	return target.parent_id === null || units.children(targetId).length > 0 || !!target.is_group;
}
