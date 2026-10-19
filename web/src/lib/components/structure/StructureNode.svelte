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
	import InlineEdit from './InlineEdit.svelte';
	import QuickAdd from './QuickAdd.svelte';
	import Tooltip from '../Tooltip.svelte';
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
	const isGroup = $derived(kids.length > 0);
	const open = $derived(!tree.collapsed.has(unit.id));
	const selected = $derived(tree.selectedId === unit.id);
	const adding = $derived(tree.addingUnder === unit.id);
	const reporting = $derived(isGroup ? units.reporting(unit.id) : []);
	const isFresh = $derived(tree.fresh.has(unit.id));
	const move = $derived(prefersReducedMotion.current ? 0 : 1);
</script>

<li role="none" in:fly={{ y: isFresh ? -6 * move : 0, duration: isFresh ? 300 : 0, easing: cubicOut }}>
	<!-- svelte-ignore a11y_click_events_have_key_events -->
	<div
		role="treeitem"
		aria-selected={selected}
		aria-expanded={isGroup ? open : undefined}
		tabindex="-1"
		class="group flex cursor-pointer items-center gap-2 rounded-lg px-1.5 py-1.5 transition-colors
			{selected ? 'bg-accent-wash ring-1 ring-chart/40' : 'hover:bg-surface-2'} {isFresh ? 'animate-[flash_1.6s_ease-out]' : ''}"
		onclick={() => tree.select(unit.id)}
	>
		{#if isGroup}
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
			<span class="flex w-full min-w-0 items-center gap-1 text-xs text-muted">
				{#if !isRoot}
					<InlineEdit value={unit.kind} label="Term" tip="Change term" tipSide="right" placeholder="Add a term" optional onSave={(kind) => saveUnit(unit, { kind: kind || null })} />
				{/if}
				<span class="shrink-0">
					{isRoot ? '' : '·'}
					{#if !unit.is_active}
						switched off
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
