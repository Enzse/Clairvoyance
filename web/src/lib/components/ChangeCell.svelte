<script lang="ts">
	import { formatDelta } from '$lib/format';
	import type { ScorecardRow } from '$lib/types';
	import Icon from './Icon.svelte';

	/**
	 * Change vs the previous period: arrow for the sign, colour for better/worse, words for the
	 * period. A like-for-like change (some units hadn't reported) says how many units it covers.
	 */
	let { row }: { row: ScorecardRow } = $props();

	const change = $derived(row.change);
	const tone = $derived(change?.direction === 'better' ? 'text-good-ink' : change?.direction === 'worse' ? 'text-bad-ink' : 'text-ink-2');
	const partial = $derived(change?.basis === 'like_for_like');
</script>

{#if change && row.previous}
	<span class="inline-flex items-center gap-1 num {tone}">
		{#if change.value !== 0}
			<Icon name={change.value > 0 ? 'arrow-up' : 'arrow-down'} size={13} stroke={2.5} />
		{/if}
		{formatDelta(row.kpi, change.value)}
	</span>
	<span
		class="block text-xs text-muted"
		title={partial ? `Compares only the ${change.units_compared} of ${change.units_total} units that reported both periods` : undefined}
	>
		vs {row.previous.period_label}{#if partial}, {change.units_compared} of {change.units_total} units{:else if change.direction === 'better' || change.direction === 'worse'}, {change.direction}{/if}
	</span>
{:else if row.previous?.value != null && row.value !== null}
	<span class="text-muted" title="No unit has reported both periods yet">Not comparable yet</span>
{:else}
	<span class="text-muted">–</span>
{/if}
