<script lang="ts">
	import type { Snippet } from 'svelte';
	import Icon from './Icon.svelte';

	/** Side sheet on a native <dialog>: focus is trapped, Escape closes, the page behind is inert. */
	let {
		open = $bindable(false),
		title,
		width = 520,
		children,
		footer
	}: { open?: boolean; title: string; width?: number; children: Snippet; footer?: Snippet } = $props();

	let dialog: HTMLDialogElement;

	$effect(() => {
		if (open && !dialog.open) dialog.showModal();
		if (!open && dialog.open) dialog.close();
	});
</script>

<dialog
	bind:this={dialog}
	onclose={() => (open = false)}
	onclick={(e) => { if (e.target === dialog) open = false; }}
	class="m-0 ml-auto h-dvh max-h-none w-full bg-surface p-0 text-ink shadow-pop"
	style:max-width="{width}px"
	aria-label={title}
>
	{#if open}
		<div class="flex h-full flex-col">
			<header class="flex items-center justify-between gap-4 border-b border-line px-6 py-4">
				<h2 class="text-lg font-semibold">{title}</h2>
				<button class="rounded-md p-1.5 text-muted hover:bg-accent-wash hover:text-ink" onclick={() => (open = false)} aria-label="Close">
					<Icon name="x" size={18} />
				</button>
			</header>
			<div class="flex-1 overflow-y-auto px-6 py-5">{@render children()}</div>
			{#if footer}
				<footer class="flex items-center justify-end gap-3 border-t border-line px-6 py-4">{@render footer()}</footer>
			{/if}
		</div>
	{/if}
</dialog>
