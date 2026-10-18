<script lang="ts">
	import { STATUS } from '$lib/status';
	import type { Status } from '$lib/types';

	// Three distinct shapes so status reads without colour: check disc, half disc, down triangle.
	let { status, size = 14 }: { status: Status | null; size?: number } = $props();
</script>

{#if status}
	<svg width={size} height={size} viewBox="0 0 16 16" class="shrink-0" role="img" aria-label={STATUS[status].label}>
		{#if status === 'green'}
			<circle cx="8" cy="8" r="7.25" class="fill-good" />
			<path d="m4.9 8.2 2.1 2.1 4.1-4.4" fill="none" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
		{:else if status === 'amber'}
			<circle cx="8" cy="8" r="6.4" fill="none" class="stroke-warn" stroke-width="1.7" />
			<path d="M8 1.6a6.4 6.4 0 0 0 0 12.8Z" class="fill-warn" />
		{:else}
			<path d="M2 3.2h12L8 13.6Z" class="fill-bad" stroke-linejoin="round" />
		{/if}
	</svg>
{:else}
	<svg width={size} height={size} viewBox="0 0 16 16" class="shrink-0" aria-hidden="true">
		<circle cx="8" cy="8" r="5.5" fill="none" class="stroke-line-strong" stroke-width="1.5" />
	</svg>
{/if}
