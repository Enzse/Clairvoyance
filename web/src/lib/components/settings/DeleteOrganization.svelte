<script lang="ts">
	import { goto } from '$app/navigation';
	import { api, ApiError } from '$lib/api';
	import { session, setMe } from '$lib/session.svelte';
	import { term } from '$lib/terms.svelte';
	import { toast } from '$lib/toast.svelte';
	import ConfirmDialog from '../ConfirmDialog.svelte';

	/** Deleting the organisation, confirmed by typing its name and a password. */
	let { open = $bindable(false) }: { open?: boolean } = $props();

	let confirmName = $state('');
	let password = $state('');
	let busy = $state(false);
	let error = $state<ApiError | null>(null);
	const current = $derived(session.user?.organization?.name ?? '');

	$effect(() => {
		if (open) {
			confirmName = '';
			password = '';
			error = null;
		}
	});

	async function remove() {
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

<ConfirmDialog bind:open title="Delete {current}?" icon="trash" confirmLabel="Delete for everyone" cancelLabel="Keep it" {busy} disabled={confirmName !== current || !password} onConfirm={remove}>
	<p>
		This deletes it for everyone: its {term.many}, KPIs, targets, every figure and its history, and pending invitations. People keep their accounts.
		<strong class="font-medium text-ink">It can't be undone</strong>, so export anything you want to keep first.
	</p>
	<div class="mt-5 space-y-4 text-ink">
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
		{#if error && !error.field('name') && !error.field('password')}<p class="text-sm text-bad-ink" role="alert">{error.message}</p>{/if}
	</div>
</ConfirmDialog>
