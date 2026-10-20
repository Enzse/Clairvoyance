<script lang="ts">
	import { onMount } from 'svelte';
	import { cubicOut } from 'svelte/easing';
	import { prefersReducedMotion } from 'svelte/motion';
	import { fly } from 'svelte/transition';
	import Icon from '$lib/components/Icon.svelte';
	import HelpButton from '$lib/components/HelpButton.svelte';
	import StructureNode from '$lib/components/structure/StructureNode.svelte';
	import StructureTour from '$lib/components/structure/StructureTour.svelte';
	import { markTourSeen, tourSeen } from '$lib/components/Tour.svelte';
	import { canMoveInto, type Tree } from '$lib/components/structure/tree';
	import { problem, saveUnit } from '$lib/components/structure/save';
	import UnitPanel from '$lib/components/structure/UnitPanel.svelte';
	import { plural } from '$lib/format';
	import { members } from '$lib/members.svelte';
	import { term } from '$lib/terms.svelte';
	import { toast } from '$lib/toast.svelte';
	import { units } from '$lib/units.svelte';

	/**
	 * The organisation's structure as a tree. Names, kinds and icons are edited right in the
	 * tree; selecting a row slides in its details. Typing names adds them; "New group" makes a
	 * group and offers to move others into it.
	 */
	const root = $derived(units.list.find((u) => u.parent_id === null));
	let selectedId = $state<number | null>(null);
	let collapsed = $state(new Set<number>());
	let addingUnder = $state<number | null>(null);
	let addingGroup = $state(false);
	let fresh = $state(new Set<number>());
	let gatherNext = $state(false);
	let draggingId = $state<number | null>(null);

	const selected = $derived(selectedId === null ? undefined : units.get(selectedId));
	const reporting = $derived(root ? units.reporting(root.id) : []);
	const groupCount = $derived(units.list.filter((u) => u.parent_id !== null && units.children(u.id).length > 0).length);
	const empty = $derived(!!root && units.children(root.id).length === 0);
	const locked = $derived(!!root && root.has_values && empty);
	const move = $derived(prefersReducedMotion.current ? 0 : 1);

	// The introduction opens on the first visit; the help button brings it back
	let touring = $state(!tourSeen('structure'));

	// Nothing set up yet: open the add box straight away
	let opened = false;
	$effect(() => {
		if (!opened && root && empty && !root.has_values) {
			opened = true;
			addingUnder = root.id;
		}
	});

	function startAdd(parentId: number, group = false) {
		addingGroup = group;
		addingUnder = parentId;
		const next = new Set(collapsed);
		next.delete(parentId);
		collapsed = next;
	}

	function close() {
		selectedId = null;
		gatherNext = false;
	}

	// Where "Add a branch" goes: inside the selected group, otherwise next to the selected one
	const addTarget = $derived.by(() => {
		if (!root || !selected) return root?.has_values ? undefined : root;
		if (units.children(selected.id).length || selected.parent_id === null) return selected.has_values ? undefined : selected;
		return units.get(selected.parent_id) ?? root;
	});

	const tree: Tree = {
		get selectedId() { return selectedId; },
		get collapsed() { return collapsed; },
		get addingUnder() { return addingUnder; },
		get addingGroup() { return addingGroup; },
		get fresh() { return fresh; },
		select: (id) => {
			gatherNext = false;
			selectedId = selectedId === id ? null : id;
		},
		toggle: (id) => {
			const next = new Set(collapsed);
			if (next.has(id)) next.delete(id);
			else next.add(id);
			collapsed = next;
		},
		startAdd: (parentId) => startAdd(parentId),
		stopAdd: () => {
			addingUnder = null;
			addingGroup = false;
		},
		added: (ids) => {
			fresh = new Set(ids);
			setTimeout(() => (fresh = new Set()), 1800);
			if (addingGroup) {
				// A new group: open it and offer to move others in
				selectedId = ids[0];
				gatherNext = true;
				addingUnder = null;
				addingGroup = false;
			}
		},
		// New ones take the kind of what's already there, otherwise the organisation's word
		kindFor: (parentId) => (addingGroup ? 'Group' : (units.children(parentId).find((u) => u.kind)?.kind ?? term.One)),
		assigned: (id) => members.assignedTo(id),
		get showAccess() { return members.anyAssigned; },
		get draggingId() { return draggingId; },
		startDrag: (id) => (draggingId = id),
		endDrag: () => (draggingId = null),
		canDrop: (targetId) => draggingId !== null && canMoveInto(draggingId, targetId, units),
		drop: (targetId) => {
			if (draggingId !== null && canMoveInto(draggingId, targetId, units)) moveInto(draggingId, targetId);
			draggingId = null;
		}
	};

	/** Drag and drop: moves a unit into a group (the panel's "Inside" does the same). */
	async function moveInto(id: number, targetId: number) {
		const moving = units.get(id);
		const target = units.get(targetId);
		if (!moving || !target) return;
		try {
			await saveUnit(moving, { parent_id: targetId });
			toast(`Moved ${moving.name} into ${target.name}`);
			const next = new Set(collapsed);
			next.delete(targetId);
			collapsed = next;
			fresh = new Set([id]);
			setTimeout(() => (fresh = new Set()), 1800);
		} catch (e) {
			toast(problem(e, 'Could not move'), 'error');
		}
	}

	onMount(() => members.load());

	// Clicking empty space in the tree closes the details
	function onTreeClick(event: MouseEvent) {
		if (!(event.target as Element).closest('[role=treeitem], button, input, [role=dialog]')) close();
	}
</script>

<svelte:head><title>Structure | Clairvoyance</title></svelte:head>
<!-- Escape in a dialog over the panel closes only the dialog -->
<svelte:window onkeydown={(e) => e.key === 'Escape' && selected && !document.querySelector('dialog[open]') && close()} />

<div class="transition-[padding] duration-300 {selected ? 'lg:pr-[max(0px,calc(444px-(100vw-1240px)/2))]' : ''}">
	<div class="flex flex-wrap items-end justify-between gap-4">
		<div class="min-w-0">
			<h2 class="text-lg font-semibold">Structure</h2>
			<p class="mt-1 max-w-[68ch] text-ink-2">
				Each part of {root?.name} is a <strong class="font-medium text-ink">unit</strong>: {term.many} that report figures, and groups that add them up.
				{#if reporting.length}
					{plural(reporting.length, term.one, term.many)} report now{groupCount ? `, in ${plural(groupCount, 'group')}` : ''}.
				{:else}
					Start by adding your {term.many}.
				{/if}
			</p>
		</div>
		{#if !locked && root}
			<div class="flex flex-wrap gap-2">
				<button class="btn btn-secondary" onclick={() => startAdd(root.id, true)}><Icon name="folder" size={15} /> New group</button>
				{#if addTarget}
					<button class="btn btn-primary" onclick={() => startAdd(addTarget.id)}><Icon name="plus" size={15} /> Add {term.one}</button>
				{/if}
			</div>
		{/if}
	</div>

	<!-- svelte-ignore a11y_click_events_have_key_events, a11y_no_noninteractive_element_interactions -->
	<section class="panel mt-5 min-h-48 p-3 sm:p-4" aria-label="Structure" onclick={onTreeClick}>
		{#if root}
			<ul role="tree" aria-label="{root.name} structure">
				<StructureNode unit={root} {tree} />
			</ul>
		{/if}
		{#if locked}
			<p class="mt-3 rounded-lg bg-surface-2 px-4 py-3 text-sm text-ink-2">
				{root?.name} already has figures of its own, so it reports as one {term.one} and nothing can go inside it.
			</p>
		{/if}
	</section>
</div>

{#if selected}
	<aside
		class="fixed inset-y-0 right-0 z-40 w-full max-w-[420px] border-l border-line bg-surface shadow-pop"
		aria-label="{selected.name} details"
		transition:fly={{ x: 440 * move, duration: 280, easing: cubicOut, opacity: 1 }}
	>
		<UnitPanel unit={selected} gatherFirst={gatherNext} onClose={close} onDeleted={(parentId) => (selectedId = parentId)} onSelect={(id) => (selectedId = id)} />
	</aside>
{/if}

<StructureTour bind:open={touring} onClose={() => markTourSeen('structure')} />
{#if !selected}
	<HelpButton label="How Structure works" onclick={() => (touring = true)} />
{/if}
