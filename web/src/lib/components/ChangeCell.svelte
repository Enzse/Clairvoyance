<script lang="ts">
	import { formatDelta } from '$lib/format';
	import type { ScorecardRow } from '$lib/types';
	import Icon from './Icon.svelte';

	/** Change vs the previous period: arrow for the sign, colour for better/worse, words for the period. */
	let { row }: { row: ScorecardRow } = $props();

	const change = $derived(row.change);
	const tone = $derived(change?.direction === 'better' ? 'text-good-ink' : change?.direction === 'worse' ? 'text-bad-ink' : 'text-ink-2');
</script>

{#if change && row.previous}
	<span class="inline-flex items-center gap-1 num {tone}">
		{#if change.value !== 0}
			<Icon name={change.value > 0 ? 'arrow-up' : 'arrow-down'} size={13} stroke={2.5} />
		{/if}
		{formatDelta(row.kpi, change.value)}
	</span>
	<span class="block text-xs text-muted">
		vs {row.previous.period_label}{#if change.direction === 'better' || change.direction === 'worse'}, {change.direction}{/if}
	</span>
{:else}
	<span class="text-muted">–</span>
{/if}
