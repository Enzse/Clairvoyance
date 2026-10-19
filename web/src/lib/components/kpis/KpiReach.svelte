<script lang="ts">
	import { slide } from 'svelte/transition';
	import { pluralize } from '$lib/format';
	import type { KpiForm } from '$lib/kpi-form';
	import { term } from '$lib/terms.svelte';
	import type { Unit } from '$lib/types';
	import { iconFor } from '$lib/unit-icons';
	import { units } from '$lib/units.svelte';
	import Icon from '../Icon.svelte';
	import OptionCards from '../OptionCards.svelte';

	/** Who reports the KPI: everyone, or only the units under some parts of the organisation. */
	let { form = $bindable(), errors = {} }: { form: KpiForm; errors?: Record<string, string> } = $props();

	// "Only some" stays chosen even before anything is ticked
	let mode = $state<'all' | 'some'>(form.scope_unit_ids.length ? 'some' : 'all');
	$effect(() => {
		if (mode === 'all' && form.scope_unit_ids.length) form.scope_unit_ids = [];
	});

	const choices = $derived.by(() => {
		const out: { unit: Unit; depth: number }[] = [];
		const walk = (id: number, depth: number) => {
			for (const u of units.children(id)) {
				if (!u.is_active) continue;
				out.push({ unit: u, depth });
				walk(u.id, depth + 1);
			}
		};
		const root = units.home();
		if (root) walk(root.id, 0);
		return out;
	});
	const kinds = $derived([...new Set(units.list.filter((u) => u.is_leaf && u.kind && u.parent_id !== null).map((u) => u.kind!))].sort());

	function toggle(id: number) {
		form.scope_unit_ids = form.scope_unit_ids.includes(id) ? form.scope_unit_ids.filter((x) => x !== id) : [...form.scope_unit_ids, id];
	}
</script>

<div class="space-y-5">
	{#if choices.length}
		<OptionCards
			name="k-reach"
			legend="Who reports it?"
			bind:value={mode}
			options={[
				{ value: 'all', label: 'Everyone', hint: `Every ${term.one} reports it.`, icon: 'users' },
				{ value: 'some', label: 'Only some', hint: 'Like revenue for Sales only.', icon: 'pin' }
			]}
		/>
		{#if mode === 'some'}
			<div transition:slide={{ duration: 180 }}>
				<div class="max-h-64 overflow-y-auto rounded-xl py-1 ring-1 {errors.scope_unit_ids ? 'ring-bad' : 'ring-line-strong'}">
					{#each choices as { unit, depth } (unit.id)}
						<label class="flex cursor-pointer items-center gap-2.5 py-1.5 pr-3 hover:bg-surface-2" style:padding-left="{12 + depth * 20}px">
							<input type="checkbox" class="size-4 accent-[var(--accent)]" checked={form.scope_unit_ids.includes(unit.id)} onchange={() => toggle(unit.id)} />
							<Icon name={iconFor(unit, units.children(unit.id).length > 0)} size={14} class="text-muted" />
							{unit.name}
							{#if unit.kind}<span class="text-sm text-muted">{unit.kind}</span>{/if}
						</label>
					{/each}
				</div>
				<p class={errors.scope_unit_ids ? 'field-error' : 'field-hint'}>
					{errors.scope_unit_ids ?? 'Ticking a group includes everything inside it. The rest never see this KPI as missing.'}
				</p>
			</div>
		{/if}
	{:else}
		<p class="rounded-xl bg-surface-2 p-4 text-sm text-ink-2 ring-1 ring-line">
			Everyone in your organisation reports it. Once you add {term.many} in Structure, you can limit a KPI to some of them.
		</p>
	{/if}

	{#if kinds.length > 1}
		<label class="block">
			<span class="field-label">Only from one kind of unit <span class="font-normal text-muted">(optional)</span></span>
			<select class="input" bind:value={form.applies_to_kind}>
				<option value="">Any kind</option>
				{#each kinds as k (k)}<option value={k}>{pluralize(k)} only</option>{/each}
			</select>
		</label>
	{/if}
</div>
