<script lang="ts">
	import { onMount } from 'svelte';
	import { cubicOut } from 'svelte/easing';
	import { prefersReducedMotion } from 'svelte/motion';
	import { fly } from 'svelte/transition';
	import { flip } from 'svelte/animate';
	import { page } from '$app/state';
	import { api } from '$lib/api';
	import HelpButton from '$lib/components/HelpButton.svelte';
	import Icon from '$lib/components/Icon.svelte';
	import KpiCard from '$lib/components/kpis/KpiCard.svelte';
	import KpiPanel from '$lib/components/kpis/KpiPanel.svelte';
	import KpiTour from '$lib/components/kpis/KpiTour.svelte';
	import NewKpi from '$lib/components/kpis/NewKpi.svelte';
	import { markTourSeen, tourSeen } from '$lib/components/Tour.svelte';
	import { plural } from '$lib/format';
	import { term } from '$lib/terms.svelte';
	import { toast } from '$lib/toast.svelte';
	import type { Kpi } from '$lib/types';

	/**
	 * The organisation's KPIs as cards. "New KPI" walks through making one; clicking a card
	 * slides in everything about it.
	 */
	let kpis = $state<Kpi[]>([]);
	let loaded = $state(false);
	let creating = $state(false);
	let selectedId = $state<number | null>(null);
	let fresh = $state<number | null>(null);
	let query = $state('');
	// Arriving to add one: the introduction can wait for the next visit
	let touring = $state(!tourSeen('kpis') && !page.url.searchParams.has('new'));
	const move = $derived(prefersReducedMotion.current ? 0 : 1);

	// The getting-started guide links here with ?new to open the guide straight away
	onMount(async () => {
		await load();
		if (page.url.searchParams.has('new')) creating = true;
	});
	async function load() {
		kpis = await api.kpis(true);
		loaded = true;
	}

	const selected = $derived(kpis.find((k) => k.id === selectedId));
	const shown = $derived(query.trim() ? kpis.filter((k) => k.name.toLowerCase().includes(query.trim().toLowerCase())) : kpis);
	const active = $derived(shown.filter((k) => k.is_active));
	const paused = $derived(shown.filter((k) => !k.is_active));
	const activeCount = $derived(kpis.filter((k) => k.is_active).length);

	async function created(kpi: Kpi) {
		toast(`Added ${kpi.name}`);
		query = '';
		await load();
		fresh = kpi.id;
		setTimeout(() => (fresh = null), 1800);
	}

	const select = (id: number) => (selectedId = selectedId === id ? null : id);
</script>

<svelte:head><title>KPIs | Clairvoyance</title></svelte:head>
<svelte:window onkeydown={(e) => e.key === 'Escape' && selected && !creating && (selectedId = null)} />

{#snippet grid(list: Kpi[])}
	<ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
		{#each list as kpi (kpi.id)}
			<li animate:flip={{ duration: 250 * move }} in:fly={{ y: 12 * move, duration: 250, easing: cubicOut }}>
				<KpiCard {kpi} {kpis} selected={kpi.id === selectedId} fresh={kpi.id === fresh} onclick={() => select(kpi.id)} />
			</li>
		{/each}
	</ul>
{/snippet}

<div class="transition-[padding] duration-300 {selected ? 'lg:pr-[max(0px,calc(584px-(100vw-1240px)/2))]' : ''}">
	{#if loaded && kpis.length}
		<div class="flex flex-wrap items-end justify-between gap-4">
			<div class="min-w-0">
				<h2 class="text-lg font-semibold">KPIs</h2>
				<p class="mt-1 max-w-[68ch] text-ink-2">
					The numbers your {term.many} report. {plural(activeCount, 'KPI')} in use{paused.length && !query ? `, ${paused.length} paused` : ''}. Click one to change it.
				</p>
			</div>
			<div class="flex flex-wrap gap-2">
				{#if kpis.length > 6}
					<label class="relative">
						<span class="sr-only">Find a KPI</span>
						<Icon name="search" size={15} class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted" />
						<input class="input h-10 w-52 pl-9" type="search" placeholder="Find a KPI" bind:value={query} />
					</label>
				{/if}
				<button class="btn btn-primary" onclick={() => (creating = true)}><Icon name="plus" size={15} /> New KPI</button>
			</div>
		</div>

		<section class="mt-5" aria-label="KPIs in use">
			{@render grid(active)}
		</section>
		{#if paused.length}
			<section class="mt-8" aria-labelledby="paused-h">
				<h3 id="paused-h" class="mb-3 text-sm font-semibold text-ink-2">Paused <span class="font-normal text-muted">· not on the scorecard or in data entry</span></h3>
				{@render grid(paused)}
			</section>
		{/if}
		{#if !shown.length}
			<p class="mt-8 text-center text-ink-2">No KPI is called “{query}”.</p>
		{/if}
	{:else if loaded}
		<div class="mx-auto flex max-w-md flex-col items-center py-16 text-center" in:fly={{ y: 12 * move, duration: 300, easing: cubicOut }}>
			<span class="grid size-14 place-items-center rounded-2xl bg-accent-wash text-accent"><Icon name="chart" size={26} /></span>
			<h2 class="mt-4 text-xl font-semibold tracking-[-0.015em]">No KPIs yet</h2>
			<p class="mt-2 text-ink-2">A KPI is a number your {term.many} report, like sales or attendance. Add the first one; it takes about a minute.</p>
			<div class="mt-6 flex flex-wrap justify-center gap-2">
				<button class="btn btn-primary" onclick={() => (creating = true)}><Icon name="plus" size={15} /> New KPI</button>
				<button class="btn btn-ghost" onclick={() => (touring = true)}>How KPIs work</button>
			</div>
		</div>
	{/if}
</div>

{#if selected}
	<aside
		class="fixed inset-y-0 right-0 z-40 w-full max-w-[560px] border-l border-line bg-surface shadow-pop"
		aria-label="{selected.name} details"
		transition:fly={{ x: 580 * move, duration: 280, easing: cubicOut, opacity: 1 }}
	>
		<KpiPanel kpi={selected} {kpis} onClose={() => (selectedId = null)} onSaved={load} />
	</aside>
{/if}

<NewKpi bind:open={creating} {kpis} onCreated={created} />
<KpiTour bind:open={touring} onClose={() => markTourSeen('kpis')} />
{#if !selected}
	<HelpButton label="How KPIs work" onclick={() => (touring = true)} />
{/if}
