<script lang="ts">
	import { cubicOut } from 'svelte/easing';
	import { prefersReducedMotion } from 'svelte/motion';
	import { scale } from 'svelte/transition';
	import { session } from '$lib/session.svelte';
	import type { Membership } from '$lib/types';
	import Icon from '../Icon.svelte';
	import InviteList from '../InviteList.svelte';

	/** Inviting people by email, in a modal. `preset` starts each row with that access. */
	let { open = $bindable(false), onCreated, preset }: { open?: boolean; onCreated: () => void; preset?: Membership } = $props();

	let dialog: HTMLDialogElement;
	const move = $derived(prefersReducedMotion.current ? 0 : 1);

	$effect(() => {
		if (open && !dialog.open) dialog.showModal();
		if (!open && dialog.open) dialog.close();
	});
</script>

<dialog
	bind:this={dialog}
	class="m-auto max-h-[min(760px,calc(100dvh-32px))] w-[min(680px,calc(100vw-32px))] flex-col overflow-hidden rounded-2xl bg-surface p-0 text-ink shadow-pop open:flex"
	aria-label="Invite people"
	onclose={() => (open = false)}
>
	{#if open}
		<div class="flex min-h-0 flex-1 flex-col" in:scale={{ start: 0.97, duration: 220 * move, easing: cubicOut }}>
			<header class="flex items-start gap-4 border-b border-line px-6 py-5">
				<span class="grid size-11 shrink-0 place-items-center rounded-xl bg-accent-wash text-accent"><Icon name="send" size={20} /></span>
				<div class="min-w-0 flex-1">
					<h2 class="text-xl font-semibold tracking-[-0.015em]">Invite people</h2>
					<p class="mt-1 text-ink-2">Each person gets a link that only works for their email address. They sign in or create an account, then join {session.user?.organization?.name}.</p>
				</div>
				<button class="rounded-md p-1.5 text-muted hover:bg-surface-2 hover:text-ink" onclick={() => (open = false)} aria-label="Close"><Icon name="x" size={18} /></button>
			</header>
			<div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
				<InviteList {onCreated} {preset} />
			</div>
			<footer class="flex justify-end border-t border-line px-6 py-4">
				<button class="btn btn-secondary" onclick={() => (open = false)}>Done</button>
			</footer>
		</div>
	{/if}
</dialog>
