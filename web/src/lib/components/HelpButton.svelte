<script lang="ts">
	import { cubicOut } from 'svelte/easing';
	import { prefersReducedMotion } from 'svelte/motion';
	import { scale } from 'svelte/transition';
	import Icon from './Icon.svelte';
	import Tooltip from './Tooltip.svelte';

	/** A round "?" in the bottom-right corner that brings back the page's introduction. */
	let { label = 'How this page works', onclick }: { label?: string; onclick: () => void } = $props();
</script>

<div class="fixed right-5 bottom-5 z-30" in:scale={{ start: 0.6, duration: prefersReducedMotion.current ? 0 : 250, easing: cubicOut }}>
	<Tooltip text={label} side="left">
		<button
			class="grid size-11 place-items-center rounded-full bg-navy text-navy-ink shadow-pop transition-transform hover:scale-105 focus-visible:ring-2 focus-visible:ring-chart"
			aria-label={label}
			{onclick}
		>
			<Icon name="help" size={22} />
		</button>
	</Tooltip>
</div>
