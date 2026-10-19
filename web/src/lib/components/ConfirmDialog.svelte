<script lang="ts">
	import type { Snippet } from 'svelte';
	import { cubicOut } from 'svelte/easing';
	import { prefersReducedMotion } from 'svelte/motion';
	import { scale } from 'svelte/transition';
	import Icon, { type IconName } from './Icon.svelte';

	/**
	 * Asking before something that can't be undone or would lose work, in the app's own style
	 * rather than the browser's. The body can hold fields, such as a password.
	 */
	let {
		open = $bindable(false),
		title,
		icon = 'alert',
		tone = 'bg-bad-wash text-bad-ink',
		confirmLabel,
		cancelLabel = 'Cancel',
		danger = true,
		busy = false,
		disabled = false,
		onConfirm,
		children
	}: {
		open?: boolean;
		title: string;
		icon?: IconName;
		tone?: string;
		confirmLabel: string;
		cancelLabel?: string;
		danger?: boolean;
		busy?: boolean;
		disabled?: boolean;
		onConfirm: () => void;
		children?: Snippet;
	} = $props();

	let dialog: HTMLDialogElement;
	const move = $derived(prefersReducedMotion.current ? 0 : 1);

	$effect(() => {
		if (open && !dialog.open) dialog.showModal();
		if (!open && dialog.open) dialog.close();
	});
</script>

<dialog bind:this={dialog} class="m-auto w-[min(500px,calc(100vw-32px))] overflow-hidden rounded-2xl bg-surface p-0 text-ink shadow-pop" aria-label={title} onclose={() => (open = false)}>
	{#if open}
		<form class="p-6" onsubmit={(e) => { e.preventDefault(); onConfirm(); }} in:scale={{ start: 0.97, duration: 220 * move, easing: cubicOut }}>
			<span class="grid size-11 place-items-center rounded-xl {tone}"><Icon name={icon} size={20} /></span>
			<h2 class="mt-4 text-xl font-semibold tracking-[-0.015em]">{title}</h2>
			<div class="mt-2 text-ink-2">{@render children?.()}</div>
			<div class="mt-6 flex justify-end gap-2">
				<!-- svelte-ignore a11y_autofocus -->
				<button type="button" class="btn btn-ghost" autofocus onclick={() => (open = false)}>{cancelLabel}</button>
				<button class="btn {danger ? 'btn-danger' : 'btn-primary'}" disabled={busy || disabled}>{busy ? 'Working…' : confirmLabel}</button>
			</div>
		</form>
	{/if}
</dialog>
