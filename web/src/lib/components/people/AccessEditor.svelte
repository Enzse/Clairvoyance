<script lang="ts" module>
	import type { Membership } from '$lib/types';

	export type AccessRow = Membership & { key: number };
</script>

<script lang="ts">
	import { cubicOut } from 'svelte/easing';
	import { prefersReducedMotion } from 'svelte/motion';
	import { fly } from 'svelte/transition';
	import { iconFor } from '$lib/unit-icons';
	import { units } from '$lib/units.svelte';
	import Icon from '../Icon.svelte';
	import Tooltip from '../Tooltip.svelte';
	import LevelSwitch from './LevelSwitch.svelte';
	import { coveredBy } from './people';

	/** Which parts of the organisation someone sees or enters data for, one row each. */
	let { rows = $bindable(), nextKey }: { rows: AccessRow[]; nextKey: () => number } = $props();

	const options = $derived(units.list.filter((u) => u.is_active));
	const move = $derived(prefersReducedMotion.current ? 0 : 1);

	const ancestors = (id: number) => units.path(id).slice(0, -1).map((u) => u.id);

	function add() {
		// Start from something not given yet, so the new row isn't a duplicate
		const free = options.find((u) => !rows.some((r) => r.unit_id === u.id)) ?? options[0];
		rows = [...rows, { key: nextKey(), unit_id: free?.id ?? 0, role: 'viewer' }];
	}
</script>

<div class="space-y-2">
	{#each rows as row (row.key)}
		{@const unit = units.get(row.unit_id)}
		{@const covering = coveredBy(row, rows, ancestors)}
		<div class="flex flex-wrap items-center gap-2 rounded-xl bg-surface px-3 py-2.5 ring-1 ring-line" in:fly={{ y: -6 * move, duration: 200, easing: cubicOut }}>
			<span class="grid size-8 shrink-0 place-items-center rounded-lg bg-surface-2 text-ink-2 ring-1 ring-line">
				<Icon name={unit ? iconFor(unit, units.children(unit.id).length > 0) : 'pin'} size={15} />
			</span>
			<select class="input h-9 min-w-0 flex-1 basis-40" bind:value={row.unit_id} aria-label="Part of the organisation">
				{#each options as u (u.id)}<option value={u.id}>{units.pathLabel(u.id) ? `${units.pathLabel(u.id)} › ` : ''}{u.name}</option>{/each}
			</select>
			<LevelSwitch value={row.role} onchange={(level) => (row.role = level)} />
			<Tooltip text="Remove" side="left">
				<button type="button" class="rounded-md p-1.5 text-muted hover:bg-bad-wash hover:text-bad-ink" aria-label="Remove access to {unit?.name}" onclick={() => (rows = rows.filter((r) => r.key !== row.key))}>
					<Icon name="x" size={15} />
				</button>
			</Tooltip>
			{#if covering}
				<p class="w-full pl-10 text-xs text-warn-ink">Already included in {units.get(covering.unit_id)?.name}, so this row isn't needed.</p>
			{/if}
		</div>
	{:else}
		<p class="rounded-xl border border-dashed border-line-strong px-4 py-3 text-sm text-ink-2">No access yet: they'd see an empty scorecard.</p>
	{/each}
	<button type="button" class="btn btn-ghost btn-sm -ml-2.5" onclick={add}><Icon name="plus" size={14} /> Give access to more</button>
	<p class="field-hint">Access to a group includes everything inside it. People who enter data can also upload spreadsheets.</p>
</div>
