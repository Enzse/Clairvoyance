<script lang="ts">
	import { goto } from '$app/navigation';
	import { api, ApiError } from '$lib/api';
	import { session, setMe } from '$lib/session.svelte';
	import { toast } from '$lib/toast.svelte';
	import { units } from '$lib/units.svelte';

	const current = $derived(session.user?.organization?.name ?? '');

	let name = $state(session.user?.organization?.name ?? '');
	let renaming = $state(false);

	let confirmName = $state('');
	let password = $state('');
	let deleting = $state(false);
	let deleteError = $state<ApiError | null>(null);

	async function rename(event: SubmitEvent) {
		event.preventDefault();
		renaming = true;
		try {
			setMe(await api.renameOrganization(name.trim()));
			await units.load(true); // the top unit carries the organisation's name
			toast('Organisation renamed');
		} catch (e) {
			toast(e instanceof ApiError ? e.message : 'Could not rename', 'error');
		} finally {
			renaming = false;
		}
	}

	async function remove(event: SubmitEvent) {
		event.preventDefault();
		deleting = true;
		deleteError = null;
		const gone = current;
		try {
			const me = await api.deleteOrganization(confirmName, password);
			setMe(me);
			toast(`Deleted ${gone}`);
			goto(me.organization ? '/' : '/onboarding');
		} catch (e) {
			deleteError = e instanceof ApiError ? e : new ApiError(0, 'Could not delete the organisation');
		} finally {
			deleting = false;
		}
	}
</script>

<svelte:head><title>Settings | Clairvoyance</title></svelte:head>

<div class="grid max-w-3xl gap-6">
	<form class="panel p-6" onsubmit={rename}>
		<h2 class="font-semibold">Organisation name</h2>
		<p class="mt-1 text-sm text-ink-2">Shown in the top bar, on the scorecard and in invitation emails.</p>
		<div class="mt-4 flex flex-wrap gap-3">
			<input class="input max-w-sm flex-1" aria-label="Organisation name" required bind:value={name} />
			<button class="btn btn-secondary" disabled={renaming || !name.trim() || name.trim() === current}>Save name</button>
		</div>
	</form>

	<form class="panel p-6 ring-1 ring-bad/30" onsubmit={remove}>
		<h2 class="font-semibold text-bad-ink">Delete this organisation</h2>
		<p class="mt-1 text-sm text-ink-2">
			Deletes {current} for everyone: its units, KPIs, targets, every figure and its history, and pending invitations. People
			keep their accounts. This can't be undone, so export anything you want to keep first.
		</p>
		<div class="mt-5 grid max-w-xl gap-4 sm:grid-cols-2">
			<div>
				<label class="field-label" for="confirm-name">Type <strong>{current}</strong> to confirm</label>
				<input id="confirm-name" class="input" autocomplete="off" bind:value={confirmName} aria-invalid={!!deleteError?.field('name')} />
				{#if deleteError?.field('name')}<p class="field-error">The name {deleteError.field('name')}</p>{/if}
			</div>
			<div>
				<label class="field-label" for="delete-password">Your password</label>
				<input id="delete-password" class="input" type="password" autocomplete="current-password" bind:value={password} aria-invalid={!!deleteError?.field('password')} />
				{#if deleteError?.field('password')}<p class="field-error">That password {deleteError.field('password')}</p>{/if}
			</div>
		</div>
		{#if deleteError && !deleteError.field('name') && !deleteError.field('password')}
			<p class="mt-3 text-sm text-bad-ink" role="alert">{deleteError.message}</p>
		{/if}
		<button class="btn btn-danger mt-5" disabled={deleting || confirmName !== current || !password}>
			{deleting ? 'Deleting…' : `Delete ${current}`}
		</button>
	</form>
</div>
