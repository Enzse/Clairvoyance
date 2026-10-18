<script lang="ts">
	// The original mark (a ring opening onto three rising bars), redrawn as SVG.
	let { size = 28, wordmark = true, onNavy = false }: { size?: number; wordmark?: boolean; onNavy?: boolean } = $props();
</script>

<span class="inline-flex items-center gap-2.5 {onNavy ? 'text-navy-ink' : 'text-ink'}">
	<svg width={size} height={size} viewBox="0 0 32 32" aria-hidden="true">
		<path d="M8.6 23.4A10.2 10.2 0 1 1 13 25.6" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" />
		<rect x="9.6" y="18.5" width="3.2" height="10" rx="1" fill="currentColor" />
		<rect x="15" y="13.5" width="3.2" height="11" rx="1" class={onNavy ? 'fill-navy-ink-2' : 'fill-muted'} />
		<rect x="20.4" y="9" width="3.2" height="13" rx="1" class={onNavy ? 'fill-navy-ink-2' : 'fill-muted'} />
	</svg>
	{#if wordmark}
		<span class="text-md font-semibold tracking-[-0.01em]">Clairvoyance</span>
	{/if}
</span>
