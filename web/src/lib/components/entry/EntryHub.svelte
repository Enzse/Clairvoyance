<script lang="ts">
	import { flip } from 'svelte/animate';
	import { cubicOut } from 'svelte/easing';
	import { prefersReducedMotion } from 'svelte/motion';
	import { fly } from 'svelte/transition';
	import { progressState, type UnitProgress } from '$lib/entry';
	import { href } from '$lib/nav';
	import { session } from '$lib/session.svelte';
	import { term } from '$lib/terms.svelte';
	import type { EntryProgress } from '$lib/types';
	import { iconFor } from '$lib/unit-icons';
	import { units } from '$lib/units.svelte';
	import Icon from '../Icon.svelte';
	import ProgressRing from '../ProgressRing.svelte';

	/** Who has entered the month's figures and who hasn't: pick a unit to type in its figures. */
	let { progress, date }: { progress: EntryProgress; date: string } = $props();

	const move = $derived(prefersReducedMotion.current ? 0 : 1);
	const byId = $derived(new Map(progress.units.map((p) => [p.unit_id, p])));
	const reporting = $derived(units.enterable().filter((u) => progressState(byId.get(u.id)) !== 'nothing'));
	const done = $derived(reporting.filter((u) => progressState(byId.get(u.id)) === 'done'));
	const todo = $derived(reporting.length - done.length);
	const noKpis = $derived(progress.units.every((p) => p.expected === 0));

	let show = $state<'todo' | 'all'>('todo');
	const listed = $derived(units.enterable().filter((u) => show === 'all' || ['none', 'partly'].includes(progressState(byId.get(u.id)))));
	const groups = $derived.by(() => {
		const map = new Map<string, typeof listed>();
		for (const u of listed) {
			const key = units.pathLabel(u.id);
			map.set(key, [...(map.get(key) ?? []), u]);
		}
		return [...map.entries()];
	});

	// Nothing left to do: show everyone rather than an empty list
	$effect(() => {
		if (todo === 0) show = 'all';
	});
</script>

{#snippet status(p: UnitProgress | undefined)}
	{@const state = progressState(p)}
	{#if state === 'done'}
		<span class="inline-flex items-center gap-1 rounded-full bg-good-wash px-2.5 py-1 text-xs font-medium text-good-ink"><Icon name="check" size={12} stroke={3} /> All in</span>
	{:else if state === 'partly' && p}
		<span class="flex items-center gap-2 text-xs text-ink-2">
			<span class="h-1.5 w-16 overflow-hidden rounded-full bg-line-strong"><span class="block h-full rounded-full bg-chart" style:width="{(p.entered / p.expected) * 100}%"></span></span>
			<span class="num">{p.entered} of {p.expected}</span>
		</span>
	{:else if state === 'none'}
		<span class="rounded-full border border-dashed border-line-strong px-2.5 py-1 text-xs text-ink-2">Not started</span>
	{:else}
		<span class="text-xs text-muted">Nothing to report</span>
	{/if}
{/snippet}

{#if noKpis}
	<div class="panel -mt-5 flex flex-col items-center px-6 py-14 text-center">
		<span class="grid size-14 place-items-center rounded-2xl bg-accent-wash text-accent"><Icon name="chart" size={26} /></span>
		<h2 class="mt-4 text-xl font-semibold tracking-[-0.015em]">Nothing to report yet</h2>
		<p class="mt-2 max-w-md text-ink-2">Figures are entered for KPIs, and there aren't any yet.</p>
		{#if session.user?.organization?.role === 'admin'}
			<a class="btn btn-primary mt-6" href="/admin/kpis?new=1"><Icon name="plus" size={15} /> Add a KPI</a>
		{/if}
	</div>
{:else}
	<section class="panel -mt-5 flex flex-wrap items-center gap-5 p-5 sm:p-6" aria-live="polite">
		<ProgressRing value={done.length} total={Math.max(reporting.length, 1)} size={56} />
		<div class="min-w-0 flex-1 basis-56">
			<h2 class="text-lg font-semibold tracking-[-0.01em]">
				{todo === 0 ? `Everything is in for ${progress.label}` : `${done.length} of ${reporting.length} ${reporting.length === 1 ? term.one : term.many} done for ${progress.label}`}
			</h2>
			<p class="mt-0.5 text-ink-2">
				{#if todo === 0}
					Every figure for the month has been entered. You can still open any of them to make changes.
				{:else}
					Pick a {term.one} to type in its figures, or <a class="font-medium text-accent hover:underline" href="/import">upload a spreadsheet</a> to add them all at once.
				{/if}
			</p>
		</div>
	</section>

	<div class="mt-6 flex items-center justify-between gap-3">
		<div class="flex rounded-lg bg-surface-2 p-0.5 ring-1 ring-line" role="radiogroup" aria-label="Show">
			{#each [['todo', `Still to do · ${todo}`], ['all', `Everyone · ${units.enterable().length}`]] as [value, text] (value)}
				<button
					type="button"
					role="radio"
					aria-checked={show === value}
					class="rounded-md px-3 py-1 text-sm transition-colors {show === value ? 'bg-surface font-medium shadow-sm' : 'text-ink-2 hover:text-ink'}"
					onclick={() => (show = value as 'todo' | 'all')}
				>
					{text}
				</button>
			{/each}
		</div>
	</div>

	<div class="mt-3 space-y-5">
		{#each groups as [path, list] (path)}
			<section aria-label={path || term.Many}>
				{#if path && groups.length > 1}<h3 class="mb-2 text-sm font-semibold text-ink-2">{path}</h3>{/if}
				<ul class="panel divide-y divide-line overflow-hidden">
					{#each list as unit (unit.id)}
						<li animate:flip={{ duration: 250 * move }} in:fly={{ y: 8 * move, duration: 220, easing: cubicOut }}>
							<a class="group flex items-center gap-3 px-4 py-3 hover:bg-surface-2 sm:px-5" href={href('/enter', { unit: unit.id, date })}>
								<span class="grid size-9 shrink-0 place-items-center rounded-lg bg-surface-2 text-ink-2 ring-1 ring-line"><Icon name={iconFor(unit, false)} size={16} /></span>
								<span class="min-w-0 flex-1 truncate font-medium">{unit.name}</span>
								{@render status(byId.get(unit.id))}
								<Icon name="chevron-right" size={16} class="text-muted transition-transform group-hover:translate-x-0.5" />
							</a>
						</li>
					{/each}
				</ul>
			</section>
		{/each}
	</div>
{/if}
