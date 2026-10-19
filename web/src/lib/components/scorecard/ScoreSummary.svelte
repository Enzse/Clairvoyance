<script lang="ts">
	import { cubicOut } from 'svelte/easing';
	import { prefersReducedMotion } from 'svelte/motion';
	import { fly } from 'svelte/transition';
	import { statusCounts, type RowFilter } from '$lib/summary';
	import type { ScorecardRow, Status } from '$lib/types';
	import ProgressRing from '../ProgressRing.svelte';
	import StatusMark from '../StatusMark.svelte';

	/**
	 * The period at a glance: how many KPIs are on target, close and behind, and how many figures
	 * are in. Each tile filters the KPI list below; clicking it again shows everything.
	 */
	let { rows, filter = $bindable() }: { rows: ScorecardRow[]; filter: RowFilter } = $props();

	const counts = $derived(statusCounts(rows));
	const move = $derived(prefersReducedMotion.current ? 0 : 1);
	const TILES: { status: Status; label: string; tone: string; ring: string }[] = [
		{ status: 'green', label: 'On target', tone: 'text-good-ink', ring: 'ring-good/60 bg-good-wash' },
		{ status: 'amber', label: 'Close', tone: 'text-warn-ink', ring: 'ring-warn/60 bg-warn-wash' },
		{ status: 'red', label: 'Behind', tone: 'text-bad-ink', ring: 'ring-bad/60 bg-bad-wash' }
	];
	const toggle = (to: RowFilter) => (filter = filter === to ? 'all' : to);
</script>

<div class="grid grid-cols-2 gap-3 sm:grid-cols-4" role="group" aria-label="At a glance">
	{#each TILES as tile, i (tile.status)}
		{@const n = counts[tile.status]}
		{@const on = filter === tile.status}
		<button
			class="flex items-center gap-3 rounded-xl px-4 py-3 text-left ring-1 transition-all hover:-translate-y-0.5 motion-reduce:hover:translate-y-0 disabled:pointer-events-none
				{on ? `ring-2 ${tile.ring}` : 'bg-surface ring-line hover:ring-line-strong'}"
			aria-pressed={on}
			disabled={!n && !on}
			onclick={() => toggle(tile.status)}
			in:fly={{ y: 8 * move, duration: 300, delay: i * 60 * move, easing: cubicOut }}
		>
			<StatusMark status={tile.status} size={20} />
			<span>
				<span class="num block text-2xl leading-none font-semibold {n ? tile.tone : 'text-muted'}">{n}</span>
				<span class="mt-1 block text-sm text-ink-2">{tile.label}</span>
			</span>
		</button>
	{/each}
	<button
		class="flex items-center gap-3 rounded-xl px-4 py-3 text-left ring-1 transition-all hover:-translate-y-0.5 motion-reduce:hover:translate-y-0 disabled:pointer-events-none
			{filter === 'missing' ? 'bg-accent-wash ring-2 ring-chart/60' : 'bg-surface ring-line hover:ring-line-strong'}"
		aria-pressed={filter === 'missing'}
		disabled={!counts.missing && filter !== 'missing'}
		onclick={() => toggle('missing')}
		in:fly={{ y: 8 * move, duration: 300, delay: 180 * move, easing: cubicOut }}
	>
		<ProgressRing value={counts.reported} total={Math.max(counts.expected, 1)} from={0} size={42} />
		<span>
			<span class="block text-sm font-medium">{counts.missing ? 'Figures in' : 'Everyone reported'}</span>
			<span class="block text-sm text-ink-2">{counts.missing ? `${counts.missing} KPI${counts.missing === 1 ? '' : 's'} still waiting` : 'Nothing missing'}</span>
		</span>
	</button>
</div>
