<script lang="ts">
	import type { Snippet } from 'svelte';
	import type { Frequency } from '$lib/types';
	import PeriodControls from './PeriodControls.svelte';
	import UnitSwitcher from './UnitSwitcher.svelte';

	/**
	 * The navy "where and when" band under the top bar: which unit, which period, and a
	 * one-line reading of the page. The page owns how links are built.
	 */
	let {
		unitId,
		periodStart,
		grain,
		unitHref,
		periodHref,
		title,
		children
	}: {
		unitId: number;
		periodStart: string | null;
		grain: Frequency;
		unitHref: (unit: number) => string;
		periodHref: (date: string, grain: Frequency) => string;
		title: Snippet;
		children?: Snippet;
	} = $props();
</script>

<section class="bg-navy text-navy-ink">
	<div class="mx-auto max-w-[1240px] px-4 pt-5 pb-9 sm:px-6">
		<div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3 border-t border-navy-2 pt-5">
			<UnitSwitcher {unitId} hrefFor={unitHref} />
			{#if periodStart}
				<PeriodControls {periodStart} {grain} hrefFor={periodHref} />
			{/if}
		</div>
		<h1 class="mt-6 text-2xl font-semibold tracking-[-0.025em] text-balance">{@render title()}</h1>
		{#if children}
			<div class="mt-3 max-w-[72ch] text-md text-navy-ink-2">{@render children()}</div>
		{/if}
	</div>
</section>
