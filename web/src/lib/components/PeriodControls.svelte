<script lang="ts">
	import { align, FREQUENCIES, GRAIN_NAMES, label, shift, today } from '$lib/period';
	import type { Frequency } from '$lib/types';
	import Icon from './Icon.svelte';

	/**
	 * ‹ Jun 2025 › plus a Day / Week / Month / Quarter / Year switch. Day and Week only appear
	 * when something is reported that often. All links, so every view is shareable.
	 */
	let {
		periodStart,
		grain,
		finest = 'monthly',
		hrefFor
	}: {
		periodStart: string;
		grain: Frequency;
		finest?: Frequency;
		hrefFor: (date: string, grain: Frequency) => string;
	} = $props();

	const GRAINS = $derived(FREQUENCIES.slice(Math.min(...[finest, grain, 'monthly' as const].map((g) => FREQUENCIES.indexOf(g)))));

	const start = $derived(align(periodStart, grain));
	const previous = $derived(shift(start, grain, -1));
	const next = $derived(shift(start, grain, 1));
	const nextInFuture = $derived(next > today());
</script>

<div class="flex flex-wrap items-center gap-3">
	<div class="flex items-center rounded-lg bg-navy-2/70">
		<a class="rounded-l-lg p-2 text-navy-ink-2 hover:text-navy-ink" href={hrefFor(previous, grain)} aria-label="Previous: {label(previous, grain)}">
			<Icon name="chevron-left" size={18} />
		</a>
		<span class="min-w-[92px] text-center font-semibold text-navy-ink" aria-live="polite">{label(start, grain)}</span>
		{#if nextInFuture}
			<span class="p-2 text-navy-ink-2 opacity-35" aria-hidden="true"><Icon name="chevron-right" size={18} /></span>
		{:else}
			<a class="rounded-r-lg p-2 text-navy-ink-2 hover:text-navy-ink" href={hrefFor(next, grain)} aria-label="Next: {label(next, grain)}">
				<Icon name="chevron-right" size={18} />
			</a>
		{/if}
	</div>
	<div class="flex rounded-lg bg-navy-2/70 p-0.5" role="group" aria-label="Period length">
		{#each GRAINS as g (g)}
			<a
				href={hrefFor(start, g)}
				aria-current={g === grain ? 'true' : undefined}
				class="rounded-md px-3 py-1.5 text-sm {g === grain ? 'bg-surface font-semibold text-ink' : 'text-navy-ink-2 hover:text-navy-ink'}"
			>
				{GRAIN_NAMES[g].noun}
			</a>
		{/each}
	</div>
</div>
