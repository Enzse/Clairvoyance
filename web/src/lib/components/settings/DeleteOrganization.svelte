<script lang="ts">
	import { cubicOut } from 'svelte/easing';
	import { prefersReducedMotion } from 'svelte/motion';
	import { scale } from 'svelte/transition';
	import { goto } from '$app/navigation';
	import { api, ApiError } from '$lib/api';
	import { session, setMe } from '$lib/session.svelte';
	import { term } from '$lib/terms.svelte';
	import { toast } from '$lib/toast.svelte';
	import Icon from '../Icon.svelte';

	/** Deleting the organisation, confirmed by typing its name and a password. */
	let { open = $bindable(false) }: { open?: boolean } = $props();

	let dialog: HTMLDialogElement;
	let confirmName = $state('');
	let password = $state('');
	let busy = $state(false);
	let error = $state<ApiError | null>(null);
	const current = $derived(session.user?.organization?.name ?? '');
	const move = $derived(prefersReducedMotion.current ? 0 : 1);

	$effect(() => {
		if (open && !dialog.open) {
			confirmName = '';
			password = '';
			error = null;
			dialog.showModal();
		}
		if (!open && dialog.open) dialog.close();
	});

	async function remove(event: SubmitEvent) {
		event.preventDefault();
		busy = true;
		error = null;
		const gone = current;
		try {
			const me = await api.deleteOrganization(confirmName, password);
			open = false;
			setMe(me);
			toast(`Deleted ${gone}`);
			goto(me.organization ? '/' : '/onboarding');
		} catch (e) {
			error = e instanceof ApiError ? e : new ApiError(0, 'Could not delete the organisation');
		} finally {
			busy = false;
		}
	}
</script>

<dialog
	bind:this={dialog}
	class="m-auto w-[min(520px,calc(100vw-32px))] overflow-hidden rounded-2xl bg-surface p-0 text-ink shadow-pop"
	aria-label="Delete {current}"
	onclose={() => (open = false)}
>
	{#if open}
		<form class="p-6" onsubmit={remove} in:scale={{ start: 0.97, duration: 220 * move, easing: cubicOut }}>
			<span class="grid size-11 place-items-center rounded-xl bg-bad-wash text-bad-ink"><Icon name="trash" size={20} /></span>
			<h2 class="mt-4 text-xl font-semibold tracking-[-0.015em]">Delete {current}?</h2>
			<p class="mt-2 text-ink-2">
				This deletes it for everyone: its {term.many}, KPIs, targets, every figure and its history, and pending invitations. People keep their accounts.
				<strong class="font-medium text-ink">It can't be undone</strong>, so export anything you want to keep first.
			</p>
			<div class="mt-5 space-y-4">
				<div>
					<label class="field-label" for="confirm-name">Type <strong>{current}</strong> to confirm</label>
					<input id="confirm-name" class="input" autocomplete="off" bind:value={confirmName} aria-invalid={!!error?.field('name')} />
					{#if error?.field('name')}<p class="field-error">The name {error.field('name')}</p>{/if}
				</div>
				<div>
					<label class="field-label" for="delete-password">Your password</label>
					<input id="delete-password" class="input" type="password" autocomplete="current-password" bind:value={password} aria-invalid={!!error?.field('password')} />
					{#if error?.field('password')}<p class="field-error">That password {error.field('password')}</p>{/if}
				</div>
			</div>
			{#if error && !error.field('name') && !error.field('password')}
				<p class="mt-3 text-sm text-bad-ink" role="alert">{error.message}</p>
			{/if}
			<div class="mt-6 flex justify-end gap-2">
				<button type="button" class="btn btn-ghost" onclick={() => (open = false)}>Keep it</button>
				<button class="btn btn-danger" disabled={busy || confirmName !== current || !password}>{busy ? 'Deleting…' : 'Delete for everyone'}</button>
			</div>
		</form>
	{/if}
</dialog>
