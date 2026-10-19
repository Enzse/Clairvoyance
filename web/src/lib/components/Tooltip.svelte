<script lang="ts">
	import type { Snippet } from 'svelte';

	/**
	 * A short label that appears above (or below) what it wraps after a moment's hover, or when
	 * a button inside it gets keyboard focus. Screen readers use the wrapped element's own label.
	 */
	let {
		text,
		side = 'top',
		class: className = '',
		children
	}: { text: string; side?: 'top' | 'bottom' | 'left' | 'right'; class?: string; children: Snippet } = $props();

	const place = {
		top: 'bottom-full left-1/2 mb-1.5 -translate-x-1/2',
		bottom: 'top-full left-1/2 mt-1.5 -translate-x-1/2',
		left: 'top-1/2 right-full mr-2 -translate-y-1/2',
		right: 'top-1/2 left-full ml-2 -translate-y-1/2'
	};
</script>

<span class="group/tip relative inline-flex max-w-full min-w-0 {className}">
	{@render children()}
	<span
		aria-hidden="true"
		class="pointer-events-none absolute z-50 rounded-md bg-navy px-2 py-1 text-xs font-medium whitespace-nowrap text-navy-ink opacity-0 shadow-pop transition-opacity duration-150
			group-hover/tip:opacity-100 group-hover/tip:delay-500 group-has-[:focus-visible]/tip:opacity-100
			{place[side]}"
	>
		{text}
	</span>
</span>
