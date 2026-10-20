import { api } from './api';
import type { Unit } from './types';

/**
 * The units the current user can see, loaded once and shared by every page. For members
 * the units they were granted are the tops of their tree ("roots" here).
 */
class UnitStore {
	list = $state<Unit[]>([]);
	loaded = $state(false);

	byId = $derived(new Map(this.list.map((u) => [u.id, u])));
	childrenOf = $derived.by(() => {
		const map = new Map<number, Unit[]>();
		for (const u of this.list) {
			if (u.parent_id === null) continue;
			const siblings = map.get(u.parent_id) ?? [];
			siblings.push(u);
			map.set(u.parent_id, siblings);
		}
		for (const siblings of map.values()) siblings.sort((a, b) => a.name.localeCompare(b.name));
		return map;
	});
	roots = $derived(this.list.filter((u) => u.parent_id === null || !this.byId.has(u.parent_id)));

	async load(force = false): Promise<void> {
		if (this.loaded && !force) return;
		this.list = await api.units();
		this.loaded = true;
	}

	reset(): void {
		this.list = [];
		this.loaded = false;
	}

	get(id: number | null | undefined): Unit | undefined {
		return id == null ? undefined : this.byId.get(id);
	}

	children(id: number): Unit[] {
		return this.childrenOf.get(id) ?? [];
	}

	isLeaf(id: number): boolean {
		return this.get(id)?.is_leaf ?? !this.childrenOf.has(id);
	}

	/** Visible ancestors from the top down, ending with the unit itself. */
	path(id: number): Unit[] {
		const path: Unit[] = [];
		for (let u = this.get(id); u; u = this.get(u.parent_id)) path.unshift(u);
		return path;
	}

	/** "NCR › Manila" for a unit, without the organisation root. */
	pathLabel(id: number): string {
		return this.path(id)
			.filter((u) => u.parent_id !== null)
			.slice(0, -1)
			.map((u) => u.name)
			.join(' › ');
	}

	/** Where a user lands by default: the root for admins, otherwise their first granted unit. */
	home(): Unit | undefined {
		return this.roots[0];
	}

	/** The unit and everything inside it. */
	subtree(id: number): Unit[] {
		const unit = this.get(id);
		return unit ? [unit, ...this.children(id).flatMap((c) => this.subtree(c.id))] : [];
	}

	/** The units that report figures inside this one (the bottom of its tree, not empty groups). */
	reporting(id: number): Unit[] {
		return this.subtree(id).filter((u) => u.parent_id !== null && this.children(u.id).length === 0 && !u.is_group);
	}

	/** Active leaf units the user can enter data for. */
	enterable(): Unit[] {
		return this.list
			.filter((u) => u.is_leaf && u.is_active && (u.access === 'admin' || u.access === 'contributor'))
			.sort((a, b) => this.pathLabel(a.id).localeCompare(this.pathLabel(b.id)) || a.name.localeCompare(b.name));
	}
}

export const units = new UnitStore();
