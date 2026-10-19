<script lang="ts">
	import { goto } from '$app/navigation';
	import { page } from '$app/state';
	import { api } from '$lib/api';
	import DataTabs from '$lib/components/DataTabs.svelte';
	import EmptyState from '$lib/components/EmptyState.svelte';
	import EntryForm from '$lib/components/entry/EntryForm.svelte';
	import EntryHub from '$lib/components/entry/EntryHub.svelte';
	import Icon from '$lib/components/Icon.svelte';
	import PageBand, { WIDTH } from '$lib/components/PageBand.svelte';
	import { nextToDo } from '$lib/entry';
	import { href } from '$lib/nav';
	import { align, label, shift, today } from '$lib/period';
	import { term } from '$lib/terms.svelte';
	import type { EntryProgress } from '$lib/types';
	import { units } from '$lib/units.svelte';

	/**
	 * Adding figures. With several units to report for, the page starts with who has entered the
	 * month's figures; picking one opens its form. With only one, its form opens straight away.
	 */
	const enterable = $derived(units.enterable());
	const requested = $derived(Number(page.url.searchParams.get('unit')));
	const unitId = $derived(enterable.some((u) => u.id === requested) ? requested : enterable.length === 1 ? enterable[0].id : null);
	const unit = $derived(units.get(unitId));
	const date = $derived(align(page.url.searchParams.get('date') ?? today(), 'monthly'));
	const month = $derived(label(date, 'monthly'));

	let progress = $state<EntryProgress | null>(null);
	async function loadProgress(at: string) {
		const result = await api.entryProgress(at);
		if (result.month === date) progress = result;
	}
	$effect(() => {
		if (enterable.length) loadProgress(date);
	});

	const next = $derived(progress ? units.get(nextToDo(enterable.map((u) => u.id), progress.units, unitId)) : undefined);
	const go = (params: { unit?: number | null; date?: string }) => href('/enter', { unit: 'unit' in params ? params.unit : unitId, date: params.date ?? date });
	const nextInFuture = $derived(shift(date, 'monthly', 1) > today());

	// The picker, grouped by where each one sits
	const groups = $derived.by(() => {
		const map = new Map<string, typeof enterable>();
		for (const u of enterable) {
			const key = units.pathLabel(u.id) || term.Many;
			map.set(key, [...(map.get(key) ?? []), u]);
		}
		return [...map.entries()];
	});
</script>

<svelte:head><title>Add data | Clairvoyance</title></svelte:head>

<PageBand
	title={unit ? `${unit.name} · ${month}` : 'Add data'}
	lead={unit
		? `Type in ${unit.name}'s figures. Leave a field empty if you don't have that figure yet.`
		: enterable.length
			? `Who has entered their figures for ${month}, and who is still to go.`
			: undefined}
	width="form"
>
	<DataTabs />
	{#if enterable.length}
		<div class="mt-5 flex flex-wrap items-end gap-3">
			{#if unit && enterable.length > 1}
				<a class="flex h-9 items-center gap-1.5 rounded-lg bg-navy-2/70 px-3 text-sm text-navy-ink-2 hover:text-navy-ink" href={go({ unit: null })}>
					<Icon name="chevron-left" size={16} /> All {term.many}
				</a>
				<label class="min-w-56 flex-1">
					<span class="sr-only">{term.One}</span>
					<select class="input" value={unitId} onchange={(e) => goto(go({ unit: Number(e.currentTarget.value) }))}>
						{#each groups as [group, list] (group)}
							<optgroup label={group}>
								{#each list as u (u.id)}<option value={u.id}>{u.name}</option>{/each}
							</optgroup>
						{/each}
					</select>
				</label>
			{/if}
			<div class="flex h-9 items-center rounded-lg bg-navy-2/70 {unit && enterable.length > 1 ? '' : 'mr-auto'}">
				<a class="p-2 text-navy-ink-2 hover:text-navy-ink" href={go({ date: shift(date, 'monthly', -1) })} aria-label="Previous month">
					<Icon name="chevron-left" size={18} />
				</a>
				<span class="min-w-[84px] text-center font-semibold" aria-live="polite">{month}</span>
				{#if nextInFuture}
					<span class="p-2 opacity-35" aria-hidden="true"><Icon name="chevron-right" size={18} /></span>
				{:else}
					<a class="p-2 text-navy-ink-2 hover:text-navy-ink" href={go({ date: shift(date, 'monthly', 1) })} aria-label="Next month">
						<Icon name="chevron-right" size={18} />
					</a>
				{/if}
			</div>
		</div>
	{/if}
</PageBand>

<main class="mx-auto max-w-[1240px] px-4 pb-28 sm:px-6"><div class="mx-auto {WIDTH.form}">
	{#if !enterable.length}
		<div class="-mt-5">
			<EmptyState icon="lock" title="Nothing for you to add yet">
				<p>An administrator can let you enter figures for the {term.many} you report for. Until then you can look at the scorecard.</p>
				{#snippet action()}<a class="btn btn-secondary" href="/">Open the scorecard</a>{/snippet}
			</EmptyState>
		</div>
	{:else if unitId !== null}
		<EntryForm {unitId} {date} {next} onSaved={() => loadProgress(date)} />
	{:else if progress}
		<EntryHub {progress} {date} />
	{/if}
</div></main>
