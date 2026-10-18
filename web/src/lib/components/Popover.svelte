<script lang="ts">
	import type { Snippet } from 'svelte';

	/**
	 * A button that opens a floating panel. Closes on Escape, outside click, or when the
	 * content calls `close`.
	 */
	let {
		trigger,
		children,
		label,
		align = 'start',
		width = 300,
		triggerClass = ''
	}: {
		trigger: Snippet<[boolean]>;
		children: Snippet<[() => void]>;
		label: string;
		align?: 'start' | 'end';
		width?: number;
		triggerClass?: string;
	} = $props();

	let open = $state(false);
	let root: HTMLElement;
	let button: HTMLButtonElement;

	function close() {
		open = false;
		button?.focus();
	}

	function onWindowClick(event: MouseEvent) {
		if (open && !root.contains(event.target as Node)) open = false;
	}

	function onKeydown(event: KeyboardEvent) {
		if (open && event.key === 'Escape') {
			event.preventDefault();
			close();
		}
	}
</script>

<svelte:window onclick={onWindowClick} onkeydown={onKeydown} />

<div class="relative" bind:this={root}>
	<button
		bind:this={button}
		type="button"
		class={triggerClass}
		aria-haspopup="dialog"
		aria-expanded={open}
		aria-label={label}
		onclick={() => (open = !open)}
	>
		{@render trigger(open)}
	</button>
	{#if open}
		<div
			role="dialog"
			aria-label={label}
			class="absolute z-40 mt-2 rounded-xl bg-surface text-ink shadow-pop {align === 'end' ? 'right-0' : 'left-0'}"
			style:width="{width}px"
		>
			{@render children(close)}
		</div>
	{/if}
</div>
