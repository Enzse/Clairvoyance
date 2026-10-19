<script lang="ts">
	import { goto } from '$app/navigation';
	import { api, ApiError } from '$lib/api';
	import { pluralize } from '$lib/format';
	import { session, setMe } from '$lib/session.svelte';
	import { term } from '$lib/terms.svelte';
	import { toast } from '$lib/toast.svelte';
	import { units } from '$lib/units.svelte';

	const current = $derived(session.user?.organization?.name ?? '');

	let name = $state(session.user?.organization?.name ?? '');
	let label = $state(session.user?.organization?.unit_label ?? 'team');
	let saving = $state<'name' | 'label' | null>(null);
	const SUGGESTIONS = ['branch', 'store', 'department', 'team', 'office', 'site', 'school', 'clinic'];

	let confirmName = $state('');
	let password = $state('');
	let deleting = $state(false);
	let deleteError = $state<ApiError | null>(null);

	async function save(what: 'name' | 'label', event: SubmitEvent) {
		event.preventDefault();
		saving = what;
		try {
			setMe(await api.updateOrganization(what === 'name' ? { name: name.trim() } : { unit_label: label.trim() }));
			if (what === 'name') await units.load(true); // the top of the structure carries the organisation's name
			toast(what === 'name' ? 'Organisation renamed' : `Saved. The app now says “${term.many}”`);
		} catch (e) {
			toast(e instanceof ApiError ? (e.field('unit_label') ? `The word ${e.field('unit_label')}` : e.message) : 'Could not save', 'error');
		} finally {
			saving = null;
		}
	}

	async function showGuide() {
		try {
			await api.dismissSetup(false);
			goto('/');
		} catch (e) {
			toast(e instanceof ApiError ? e.message : 'Could not show the guide', 'error');
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

<div class="grid gap-6 lg:grid-cols-2">
	<form class="panel p-6" onsubmit={(e) => save('name', e)}>
		<h2 class="font-semibold">Organisation name</h2>
		<p class="mt-1 text-sm text-ink-2">Shown in the top bar, on the scorecard and in invitation emails.</p>
		<div class="mt-4 flex flex-wrap gap-3">
			<input class="input min-w-0 flex-1" aria-label="Organisation name" required bind:value={name} />
			<button class="btn btn-secondary" disabled={saving !== null || !name.trim() || name.trim() === current}>Save</button>
		</div>
	</form>

	<form class="panel p-6" onsubmit={(e) => save('label', e)}>
		<h2 class="font-semibold">What you call the parts that report</h2>
		<p class="mt-1 text-sm text-ink-2">
			The app uses this word everywhere, e.g. “2 of 3 {pluralize(label.trim().toLowerCase() || 'team')} have reported”.
		</p>
		<div class="mt-4 flex flex-wrap gap-3">
			<input class="input min-w-0 flex-1" aria-label="Word for the parts that report" list="unit-words" required maxlength="40" bind:value={label} />
			<datalist id="unit-words">{#each SUGGESTIONS as word (word)}<option value={word}></option>{/each}</datalist>
			<button class="btn btn-secondary" disabled={saving !== null || !label.trim() || label.trim().toLowerCase() === term.one}>Save</button>
		</div>
	</form>

	<div class="panel flex flex-wrap items-center gap-4 p-6 lg:col-span-2">
		<div class="min-w-0 flex-1">
			<h2 class="font-semibold">Getting started guide</h2>
			<p class="mt-1 text-sm text-ink-2">The step-by-step guide on the scorecard. Bring it back if you hid it, or to finish skipped steps.</p>
		</div>
		<button class="btn btn-secondary" onclick={showGuide}>Show it on the scorecard</button>
	</div>

	<form class="panel p-6 ring-1 ring-bad/30 lg:col-span-2" onsubmit={remove}>
		<h2 class="font-semibold text-bad-ink">Delete this organisation</h2>
		<p class="mt-1 max-w-[80ch] text-sm text-ink-2">
			Deletes {current} for everyone: its {term.many}, KPIs, targets, every figure and its history, and pending invitations.
			People keep their accounts. This can't be undone, so export anything you want to keep first.
		</p>
		<div class="mt-5 grid gap-4 sm:grid-cols-2 lg:max-w-3xl">
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
