import type { Member, Membership } from '$lib/types';

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
};
