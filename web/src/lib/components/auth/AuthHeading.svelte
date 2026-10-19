<script lang="ts">
	import Icon, { type IconName } from '../Icon.svelte';

	/** The top of a sign-in style card: an icon tile, a title and a line under it. */
	let { icon, title, lead, tone = 'bg-accent-wash text-accent' }: { icon: IconName; title: string; lead?: string; tone?: string } = $props();
</script>

<span class="grid size-12 place-items-center rounded-2xl {tone}"><Icon name={icon} size={22} /></span>
<h1 class="mt-5 text-2xl font-semibold tracking-[-0.02em] text-balance">{title}</h1>
{#if lead}<p class="mt-2 text-ink-2">{lead}</p>{/if}
