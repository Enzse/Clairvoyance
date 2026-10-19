<script lang="ts">
	import { formatValue, joinNames } from '$lib/format';
	import { shortRules, statusBands } from '$lib/kpi-form';
	import { GRAIN_NAMES } from '$lib/period';
	import { session } from '$lib/session.svelte';
	import { term } from '$lib/terms.svelte';
	import type { Kpi } from '$lib/types';
	import { units } from '$lib/units.svelte';
	import Icon, { type IconName } from '../Icon.svelte';
	import StatusMark from '../StatusMark.svelte';

	/** How the KPI works: who reports it and how often, how it adds up, and what each status means. */
	let { kpi, weightName, target }: { kpi: Kpi; weightName?: string; target: number | null } = $props();

	const who = $derived(
		(kpi.applies_to_kind ? `Each ${kpi.applies_to_kind.toLowerCase()}` : `Each ${term.one}`) +
			(kpi.scope_unit_ids.length ? ` in ${joinNames(kpi.scope_unit_ids.map((id) => units.get(id)?.name ?? '?'))}` : '')
	);
	const bands = $derived(target === null ? null : statusBands(kpi.direction, target, kpi.tolerance_pct, (n) => formatValue(kpi, n)));
	const isAdmin = $derived(session.user?.organization?.role === 'admin');
</script>

{#snippet row(icon: IconName, title: string, text: string)}
	<div class="flex items-start gap-3">
		<span class="grid size-8 shrink-0 place-items-center rounded-lg bg-surface-2 text-ink-2 ring-1 ring-line"><Icon name={icon} size={15} /></span>
		<div class="min-w-0">
			<dt class="text-xs font-semibold tracking-wide text-muted uppercase">{title}</dt>
			<dd class="mt-0.5 text-sm">{text}</dd>
		</div>
	</div>
{/snippet}

<aside class="panel h-fit min-w-0 p-5 sm:p-6" aria-labelledby="about-heading">
	<h2 id="about-heading" class="text-md font-semibold">How it's calculated</h2>
	{#if kpi.description}<p class="mt-1 text-sm text-ink-2">{kpi.description}</p>{/if}
	<dl class="mt-5 space-y-4">
		{@render row('calendar', 'Reported', `${who}, ${GRAIN_NAMES[kpi.frequency].adjective.toLowerCase()}`)}
		{@render row('layers', 'Adds up', shortRules(kpi, term.many, weightName))}
		{#if kpi.direction === 'none'}
			{@render row('eye', 'Status', 'Tracked without a status')}
		{:else}
			{@render row(kpi.direction === 'higher_is_better' ? 'arrow-up' : 'arrow-down', 'Status', `${kpi.direction === 'higher_is_better' ? 'Higher' : 'Lower'} is better. Within ${kpi.tolerance_pct}% of target counts as close`)}
		{/if}
	</dl>
	{#if bands}
		<ul class="mt-4 space-y-1.5 rounded-xl bg-surface-2 p-3 text-sm ring-1 ring-line" aria-label="What each status means here">
			<li class="flex items-center gap-2"><StatusMark status="green" /> <span class="flex-1">On target</span><span class="num text-ink-2">{bands.good}</span></li>
			<li class="flex items-center gap-2"><StatusMark status="amber" /> <span class="flex-1">Close</span><span class="num text-ink-2">{bands.close}</span></li>
			<li class="flex items-center gap-2"><StatusMark status="red" /> <span class="flex-1">Behind</span><span class="num text-ink-2">{bands.bad}</span></li>
		</ul>
	{/if}
	{#if isAdmin}
		<a class="btn btn-ghost btn-sm mt-4 -ml-2.5" href="/admin/kpis?kpi={kpi.id}"><Icon name="sliders" size={14} /> Change how it works</a>
	{/if}
</aside>
