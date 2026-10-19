<script lang="ts">
	import { goto } from '$app/navigation';
	import { units } from '$lib/units.svelte';
	import { term } from '$lib/terms.svelte';
	import Icon from './Icon.svelte';
	import Popover from './Popover.svelte';

	/** Breadcrumb of the unit's ancestors, plus a menu to move down the tree or jump anywhere. */
	let { unitId, hrefFor }: { unitId: number; hrefFor: (unit: number) => string } = $props();

	const path = $derived(units.path(unitId));
	const current = $derived(path[path.length - 1]);
	const subUnits = $derived(units.children(unitId).filter((u) => u.is_active));

	let query = $state('');
	const matches = $derived.by(() => {
		const q = query.trim().toLowerCase();
		if (!q) return [];
		return units.list.filter((u) => u.is_active && u.name.toLowerCase().includes(q)).slice(0, 8);
	});

	function onSearchKey(event: KeyboardEvent, close: () => void) {
		if (event.key === 'Enter' && matches[0]) {
			event.preventDefault();
			close();
			query = '';
			goto(hrefFor(matches[0].id));
		}
	}
</script>

<nav class="flex min-w-0 flex-wrap items-center gap-x-1 gap-y-1 text-base text-navy-ink-2" aria-label="Where you are">
	{#each path.slice(0, -1) as ancestor (ancestor.id)}
		<a class="rounded px-1 hover:text-navy-ink hover:underline" href={hrefFor(ancestor.id)}>{ancestor.name}</a>
		<Icon name="chevron-right" size={14} class="opacity-60" />
	{/each}
	<Popover
		label="Go somewhere else in the organisation"
		width={320}
		triggerClass="flex items-center gap-1 rounded-md px-1.5 py-0.5 font-medium text-navy-ink hover:bg-navy-2"
	>
		{#snippet trigger(open)}
			{current?.name}
			<Icon name="chevron-down" size={14} class="transition-transform {open ? 'rotate-180' : ''}" />
		{/snippet}
		{#snippet children(close)}
			<div class="border-b border-line p-2">
				<label class="relative block">
					<span class="sr-only">Find a {term.one} or group</span>
					<Icon name="search" size={15} class="absolute top-1/2 left-2.5 -translate-y-1/2 text-muted" />
					<!-- svelte-ignore a11y_autofocus -->
					<input class="input pl-8" placeholder="Find a {term.one} or group" autofocus bind:value={query} onkeydown={(e) => onSearchKey(e, close)} />
				</label>
			</div>
			<div class="max-h-80 overflow-y-auto p-1.5">
				{#if query.trim()}
					{#each matches as unit (unit.id)}
						<a class="block rounded-md px-2.5 py-2 hover:bg-accent-wash" href={hrefFor(unit.id)} onclick={() => { close(); query = ''; }}>
							<span class="block">{unit.name}</span>
							{#if units.pathLabel(unit.id)}<span class="block text-sm text-muted">{units.pathLabel(unit.id)}</span>{/if}
						</a>
					{:else}
						<p class="px-2.5 py-3 text-sm text-muted">Nothing matches “{query}”.</p>
					{/each}
				{:else if subUnits.length}
					<p class="px-2.5 pt-1.5 pb-1 text-sm text-muted">Inside {current?.name}</p>
					{#each subUnits as unit (unit.id)}
						<a class="flex items-center justify-between rounded-md px-2.5 py-2 hover:bg-accent-wash" href={hrefFor(unit.id)} onclick={close}>
							<span>{unit.name}</span>
							{#if unit.kind}<span class="text-sm text-muted">{unit.kind}</span>{/if}
						</a>
					{/each}
				{:else}
					<p class="px-2.5 py-3 text-sm text-muted">Nothing is inside {current?.name}. Search to go somewhere else.</p>
				{/if}
			</div>
		{/snippet}
	</Popover>
</nav>
