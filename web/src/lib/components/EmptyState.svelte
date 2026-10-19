<script lang="ts">
	import type { Snippet } from 'svelte';
	import { fade } from 'svelte/transition';
	import Icon, { type IconName } from './Icon.svelte';

	/** A centred card for "nothing here yet" or "not for you": an icon, a title, a few words, maybe a button. */
	let { icon, title, children, action }: { icon: IconName; title: string; children: Snippet; action?: Snippet } = $props();
</script>

<div class="panel mx-auto flex max-w-xl flex-col items-center p-8 text-center sm:p-10" in:fade={{ duration: 250 }}>
	<span class="grid size-14 place-items-center rounded-2xl bg-accent-wash text-accent"><Icon name={icon} size={26} /></span>
	<h2 class="mt-4 text-xl font-semibold tracking-[-0.015em]">{title}</h2>
	<div class="mt-2 text-ink-2">{@render children()}</div>
	{#if action}<div class="mt-6">{@render action()}</div>{/if}
</div>
