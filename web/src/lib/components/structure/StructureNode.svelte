<script lang="ts">
	import { cubicOut } from 'svelte/easing';
	import { prefersReducedMotion } from 'svelte/motion';
	import { fly } from 'svelte/transition';
	import { plural } from '$lib/format';
	import { term, wordFor } from '$lib/terms.svelte';
	import type { Unit } from '$lib/types';
	import { units } from '$lib/units.svelte';
	import Icon from '../Icon.svelte';
	import IconPicker from './IconPicker.svelte';
	import InlineEdit from '../InlineEdit.svelte';
	import QuickAdd from './QuickAdd.svelte';
	import Tooltip from '../Tooltip.svelte';
	import Avatar from '../people/Avatar.svelte';
	import { saveUnit } from './save';
	import Self from './StructureNode.svelte';
	import type { Tree } from './tree';

	/**
	 * One row of the structure tree: icon (click to choose another), name and kind (click to
	 * edit in place), and its children. Clicking the rest of the row opens its details.
	 */
	let { unit, tree }: { unit: Unit; tree: Tree } = $props();

	const kids = $derived(units.children(unit.id));
	const isRoot = $derived(unit.parent_id === null);
	// A unit with units inside, or one made as a group that is still empty
	const isGroup = $derived(kids.length > 0 || unit.is_group);
	const open = $derived(!tree.collapsed.has(unit.id));
	const selected = $derived(tree.selectedId === unit.id);
	const adding = $derived(tree.addingUnder === unit.id);
	const reporting = $derived(isGroup ? units.reporting(unit.id) : []);
	const people = $derived(tree.assigned(unit.id));
	// "Lee enters data, Ana views (and everything inside)"
	const whoHere = $derived(
		people.map(({ member, role }) => `${member.name || member.email} ${role === 'contributor' ? 'enters data' : 'views'}`).join(', ') +
			(isGroup ? ' here and for everything inside' : ' here')
	);
	const isFresh = $derived(tree.fresh.has(unit.id));
	const move = $derived(prefersReducedMotion.current ? 0 : 1);

	// Drag and drop: a row can be dragged onto a group (or the organisation) to move it there
	const dragging = $derived(tree.draggingId === unit.id);
	let over = $state(false);
	let openTimer: ReturnType<typeof setTimeout> | undefined;

	function ondragstart(event: DragEvent) {
		event.dataTransfer?.setData('text/plain', unit.name);
		if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
		tree.startDrag(unit.id);
	}

	function ondragover(event: DragEvent) {
		if (!tree.canDrop(unit.id)) return;
		event.preventDefault();
		if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
		if (!over) {
			over = true;
			// Hovering over a closed group opens it, so a unit can go deeper
			if (kids.length && !open) openTimer = setTimeout(() => tree.toggle(unit.id), 700);
		}
	}

	function leave(event: DragEvent) {
		if ((event.currentTarget as Element).contains(event.relatedTarget as Node | null)) return;
		over = false;
		clearTimeout(openTimer);
	}

	function ondrop(event: DragEvent) {
		event.preventDefault();
		over = false;
		clearTimeout(openTimer);
		tree.drop(unit.id);
	}
</script>

<li role="none" in:fly={{ y: isFresh ? -6 * move : 0, duration: isFresh ? 300 : 0, easing: cubicOut }}>
	<!-- svelte-ignore a11y_click_events_have_key_events -->
	<div
		role="treeitem"
		aria-selected={selected}
		aria-expanded={kids.length ? open : undefined}
		tabindex="-1"
		class="group relative flex cursor-pointer items-center gap-2 rounded-lg px-1.5 py-1.5 transition-colors
			{over ? 'bg-accent-wash ring-2 ring-chart' : selected ? 'bg-accent-wash ring-1 ring-chart/40' : 'hover:bg-surface-2'}
			{isFresh ? 'animate-[flash_1.6s_ease-out]' : ''} {dragging ? 'opacity-40' : ''}"
		draggable={!isRoot}
		{ondragstart}
		ondragend={() => tree.endDrag()}
		{ondragover}
		ondragleave={leave}
		{ondrop}
		onclick={() => tree.select(unit.id)}
	>
		{#if over}
			<span class="pointer-events-none absolute -top-2.5 right-3 rounded-full bg-chart px-2 py-0.5 text-[11px] font-medium text-white shadow-sm">Move here</span>
		{/if}
		{#if kids.length}
			<Tooltip text={open ? 'Collapse' : 'Expand'}>
				<button class="rounded p-1 text-muted hover:text-ink" onclick={(e) => { e.stopPropagation(); tree.toggle(unit.id); }} aria-label="{open ? 'Collapse' : 'Expand'} {unit.name}">
					<Icon name="chevron-down" size={14} class="transition-transform {open ? '' : '-rotate-90'}" />
				</button>
			</Tooltip>
		{:else}
			<span class="w-[22px] shrink-0"></span>
		{/if}

		<!-- svelte-ignore a11y_no_static_element_interactions -->
		<span onclick={(e) => e.stopPropagation()}><IconPicker {unit} {isGroup} /></span>

		<span class="flex min-w-0 flex-1 flex-col items-start">
			<span class="block w-full {isRoot ? 'font-semibold' : 'font-medium'} {unit.is_active ? '' : 'text-muted line-through'}">
				<InlineEdit value={unit.name} label="Name" tip="Rename" tipSide="right" onSave={(name) => saveUnit(unit, { name })} />
			</span>
			<!-- The term (first) stays whole; the summary after it shortens on narrow screens -->
			<span class="flex w-full min-w-0 items-center gap-1 text-xs text-muted [&>:first-child]:shrink-0">
				{#if !isRoot}
					<InlineEdit value={unit.kind} label="Term" tip="Change term" tipSide="right" placeholder="Add a term" optional onSave={(kind) => saveUnit(unit, { kind: kind || null })} />
				{/if}
				<span class="min-w-0 truncate">
					{isRoot ? '' : '·'}
					{#if !unit.is_active}
						switched off
					{:else if isGroup && !kids.length}
						empty, move units in
					{:else if isGroup}
						adds up {plural(reporting.length, wordFor(reporting, false), wordFor(reporting))}
					{:else if isRoot}
						reports as one {term.one}
					{:else}
						{unit.has_values ? 'reports figures' : 'no figures yet'}
					{/if}
				</span>
			</span>
		</span>

		<span class="flex shrink-0 items-center gap-0.5 {selected || adding ? '' : 'opacity-0 group-hover:opacity-100 focus-within:opacity-100'}">
			{#if !unit.has_values}
				<Tooltip text="Add a unit inside">
					<button class="btn btn-ghost btn-sm" onclick={(e) => { e.stopPropagation(); tree.startAdd(unit.id); }} aria-label="Add a unit inside {unit.name}">
						<Icon name="plus" size={14} /><span class="hidden md:inline">Add inside</span>
					</button>
				</Tooltip>
			{/if}
			<Tooltip text="Details">
				<button class="rounded-md p-1.5 text-muted hover:bg-surface hover:text-ink" onclick={(e) => { e.stopPropagation(); tree.select(unit.id); }} aria-label="Details of {unit.name}">
					<Icon name="sliders" size={15} />
				</button>
			</Tooltip>
		</span>

		{#if tree.showAccess}
			<!-- People given access here, in a column that lines up on every row -->
			<span class="flex w-[76px] shrink-0 justify-end">
				{#if people.length}
					<Tooltip text={whoHere} side="left">
						<span class="flex -space-x-1.5">
							{#each people.slice(0, 3) as { member } (member.id)}
								<Avatar name={member.name} email={member.email} size="sm" class="ring-2 ring-surface" />
							{/each}
							{#if people.length > 3}
								<span class="grid size-7 place-items-center rounded-full bg-surface-2 text-xs font-semibold text-ink-2 ring-2 ring-surface">+{people.length - 3}</span>
							{/if}
						</span>
					</Tooltip>
				{/if}
			</span>
		{/if}
	</div>

	{#if (isGroup && open) || adding}
		<ul role="group" class="ml-[19px] border-l border-line pl-2.5">
			{#if isGroup && open}
				{#each kids as child (child.id)}
					<Self unit={child} {tree} />
				{/each}
			{/if}
			{#if adding}
				<li role="none" class="pt-0.5" in:fly={{ y: -4 * move, duration: 200 }}>
					<QuickAdd
						parentId={unit.id}
						kind={tree.kindFor(unit.id)}
						group={tree.addingGroup}
						label="Name of the new one inside {unit.name}"
						placeholder={tree.addingGroup
							? 'Name of the new group, e.g. North region'
							: kids.length
								? `Another ${wordFor(kids, false)} in ${unit.name}`
								: `First unit inside ${unit.name}, e.g. Downtown`}
						onAdded={tree.added}
						onClose={tree.stopAdd}
					/>
				</li>
			{/if}
		</ul>
	{/if}
</li>
