<script lang="ts">
	import type { Snippet } from 'svelte';
	import { fade } from 'svelte/transition';
	import Logo from './Logo.svelte';

	/** A whole-page message for when a page can't be shown: a missing page, an error, no server. */
	let { code, title, children, actions }: { code?: string; title: string; children: Snippet; actions: Snippet } = $props();
</script>

<main class="flex min-h-screen flex-col items-center justify-center px-4 py-12 text-center" in:fade={{ duration: 200 }}>
	<a href="/" aria-label="Clairvoyance home"><Logo /></a>
	{#if code}<p class="num mt-12 text-6xl font-semibold tracking-[-0.03em] text-line-strong">{code}</p>{/if}
	<h1 class="{code ? 'mt-3' : 'mt-12'} text-2xl font-semibold tracking-[-0.02em]">{title}</h1>
	<div class="mt-2 max-w-md text-ink-2">{@render children()}</div>
	<div class="mt-8 flex flex-wrap justify-center gap-3">{@render actions()}</div>
</main>
