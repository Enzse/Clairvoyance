<script lang="ts">
	import { cubicOut } from 'svelte/easing';
	import { prefersReducedMotion, Tween } from 'svelte/motion';
	import { scale } from 'svelte/transition';
	import Icon from './Icon.svelte';

	/** A ring that fills to value/total, animating from `from` (e.g. the last visit) on arrival. */
	let { value, total, from = value, size = 48 }: { value: number; total: number; from?: number; size?: number } = $props();

	const shown = new Tween(from, { duration: 900, easing: cubicOut });
	$effect(() => {
		shown.set(value, prefersReducedMotion.current ? { duration: 0 } : undefined);
	});

	const stroke = 5;
	const r = $derived((size - stroke) / 2);
	const circumference = $derived(2 * Math.PI * r);
	const complete = $derived(value >= total);
</script>

<span class="relative grid shrink-0 place-items-center" style:width="{size}px" style:height="{size}px" role="img" aria-label="{value} of {total} done">
	<svg width={size} height={size} viewBox="0 0 {size} {size}" class="-rotate-90" aria-hidden="true">
		<circle cx={size / 2} cy={size / 2} {r} fill="none" stroke="var(--accent-wash)" stroke-width={stroke} />
		<circle
			cx={size / 2}
			cy={size / 2}
			{r}
			fill="none"
			stroke={complete ? 'var(--good)' : 'var(--chart)'}
			stroke-width={stroke}
			stroke-linecap="round"
			stroke-dasharray={circumference}
			stroke-dashoffset={circumference * (1 - Math.min(1, shown.current / total))}
		/>
	</svg>
	{#if complete && size >= 64}
		<span class="absolute text-good" aria-hidden="true" in:scale={{ start: 0.3, delay: prefersReducedMotion.current ? 0 : 600, duration: 350 }}>
			<Icon name="check" size={Math.round(size / 2.4)} stroke={3} />
		</span>
	{:else}
		<span class="absolute text-sm font-semibold num" aria-hidden="true">{Math.round(shown.current)}/{total}</span>
	{/if}
</span>
