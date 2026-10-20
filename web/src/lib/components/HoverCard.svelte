<script lang="ts">
	import type { Snippet } from 'svelte';
	import { fade } from 'svelte/transition';

	/**
	 * Details that open under something on hover or keyboard focus, and on tap for touch screens.
	 * Escape or moving away closes them.
	 */
	let {
		children,
		card,
		align = 'start',
		class: className = ''
	}: { children: Snippet; card: Snippet; align?: 'start' | 'end'; class?: string } = $props();

	const id = $props.id();
	let open = $state(false);
	let timer: ReturnType<typeof setTimeout> | undefined;

	function show(now = false) {
		clearTimeout(timer);
		if (now) open = true;
		else timer = setTimeout(() => (open = true), 150);
	}

	function hide() {
		clearTimeout(timer);
		open = false;
	}
</script>

<div
	class="relative h-full"
	role="presentation"
	onmouseenter={() => show()}
	onmouseleave={hide}
	onfocusin={() => show(true)}
	onfocusout={hide}
	onkeydown={(e) => e.key === 'Escape' && hide()}
>
	<button type="button" class="w-full text-left {className}" aria-expanded={open} aria-controls={id} onclick={() => (open ? hide() : show(true))}>
		{@render children()}
	</button>
	{#if open}
		<div
			{id}
			class="absolute top-full z-40 mt-2 w-64 rounded-xl bg-surface p-3.5 text-sm text-ink shadow-pop ring-1 ring-line {align === 'end' ? 'right-0' : 'left-0'}"
			transition:fade={{ duration: 120 }}
		>
			{@render card()}
		</div>
	{/if}
</div>
