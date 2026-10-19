<script lang="ts">
	import Icon, { type IconName } from './Icon.svelte';

	/** A card's heading: an icon tile, a title and a line about it. */
	let { icon, title, lead, tone = 'bg-accent-wash text-accent' }: { icon: IconName; title: string; lead?: string; tone?: string } = $props();
</script>

<div class="flex items-start gap-3.5">
	<span class="grid size-10 shrink-0 place-items-center rounded-xl {tone}"><Icon name={icon} size={19} /></span>
	<div class="min-w-0">
		<h3 class="font-semibold">{title}</h3>
		{#if lead}<p class="mt-0.5 text-sm text-ink-2">{lead}</p>{/if}
	</div>
</div>
