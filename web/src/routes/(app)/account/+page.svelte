<script lang="ts">
	import { goto } from '$app/navigation';
	import { api, ApiError } from '$lib/api';
	import PageBand, { WIDTH } from '$lib/components/PageBand.svelte';
	import { session, setMe } from '$lib/session.svelte';
	import { toast } from '$lib/toast.svelte';
	import type { OrganizationSummary } from '$lib/types';

	let name = $state(session.user?.name ?? '');
	let current = $state('');
	let next = $state('');
	let nameBusy = $state(false);
	let passwordBusy = $state(false);
	let passwordError = $state<ApiError | null>(null);

	async function saveName(event: SubmitEvent) {
		event.preventDefault();
		nameBusy = true;
		try {
			const user = await api.updateAccount({ name: name.trim() || null });
			if (session.user) session.user.name = user.name;
			toast('Name saved');
		} catch (e) {
			toast(e instanceof ApiError ? e.message : 'Could not save', 'error');
		} finally {
			nameBusy = false;
		}
	}

	async function savePassword(event: SubmitEvent) {
		event.preventDefault();
		passwordBusy = true;
		passwordError = null;
		try {
			await api.updateAccount({ current_password: current, new_password: next });
			current = next = '';
			toast('Password changed. Other devices have been signed out');
		} catch (e) {
			passwordError = e instanceof ApiError ? e : new ApiError(0, 'Could not change the password');
		} finally {
			passwordBusy = false;
		}
	}

	let leaving = $state<number | null>(null);

	/** Leaving works on the organisation you're in, so switch to it first if needed. */
	async function leave(o: OrganizationSummary) {
		if (!confirm(`Leave ${o.name}? You'll lose access until someone invites you again.`)) return;
		leaving = o.id;
		try {
			if (session.user?.organization?.id !== o.id) setMe(await api.selectOrganization(o.id));
			const me = await api.leaveOrganization();
			setMe(me);
			toast(`You've left ${o.name}`);
			if (!me.organization) goto('/onboarding');
		} catch (e) {
			toast(e instanceof ApiError ? e.message : 'Could not leave', 'error');
		} finally {
			leaving = null;
		}
	}

	let deletePassword = $state('');
	let deleting = $state(false);
	let deleteError = $state<ApiError | null>(null);
	const soloOrganizations = $derived((session.user?.organizations ?? []).filter((o) => o.role === 'admin'));

	async function deleteAccount(event: SubmitEvent) {
		event.preventDefault();
		if (!confirm('Delete your account for good?')) return;
		deleting = true;
		deleteError = null;
		try {
			await api.deleteAccount(deletePassword);
			session.user = null;
			toast('Your account has been deleted');
			goto('/login');
		} catch (e) {
			deleteError = e instanceof ApiError ? e : new ApiError(0, 'Could not delete the account');
		} finally {
			deleting = false;
		}
	}
</script>

<svelte:head><title>Account | Clairvoyance</title></svelte:head>

<PageBand title="Your account" lead={session.user?.email} width="narrow" />

<main class="mx-auto max-w-[1240px] px-4 pb-16 sm:px-6">
	<div class="mx-auto -mt-5 grid gap-6 {WIDTH.narrow}">
		<form class="panel p-6" onsubmit={saveName}>
			<h2 class="font-semibold">Name</h2>
			<p class="mt-1 text-sm text-ink-2">Shown to people in your organisations.</p>
			<div class="mt-4 flex flex-wrap gap-3">
				<input class="input max-w-sm flex-1" aria-label="Name" autocomplete="name" bind:value={name} />
				<button class="btn btn-secondary" disabled={nameBusy || name === (session.user?.name ?? '')}>Save name</button>
			</div>
		</form>

		<form class="panel p-6" onsubmit={savePassword}>
			<h2 class="font-semibold">Password</h2>
			<div class="mt-4 grid max-w-xl gap-4 sm:grid-cols-2">
				<div>
					<label class="field-label" for="current-password">Current password</label>
					<input id="current-password" class="input" type="password" autocomplete="current-password" required bind:value={current} aria-invalid={!!passwordError?.field('current_password')} />
					{#if passwordError?.field('current_password')}<p class="field-error">That {passwordError.field('current_password')}</p>{/if}
				</div>
				<div>
					<label class="field-label" for="new-password">New password</label>
					<input id="new-password" class="input" type="password" autocomplete="new-password" minlength="8" required bind:value={next} aria-invalid={!!passwordError?.field('new_password')} />
					<p class={passwordError?.field('new_password') ? 'field-error' : 'field-hint'}>At least 8 characters.</p>
				</div>
			</div>
			<button class="btn btn-secondary mt-5" disabled={passwordBusy || !current || next.length < 8}>{passwordBusy ? 'Changing…' : 'Change password'}</button>
		</form>

		<section class="panel p-6">
			<h2 class="font-semibold">Organisations</h2>
			<ul class="mt-3 divide-y divide-line">
				{#each session.user?.organizations ?? [] as o (o.id)}
					<li class="flex items-center gap-3 py-2.5">
						<span class="min-w-0 flex-1 truncate font-medium">{o.name}</span>
						<span class="text-sm text-ink-2">{o.role === 'admin' ? 'Administrator' : 'Member'}</span>
						<button class="btn btn-ghost btn-sm" disabled={leaving !== null} onclick={() => leave(o)}>{leaving === o.id ? 'Leaving…' : 'Leave'}</button>
					</li>
				{/each}
			</ul>
			<a class="btn btn-ghost btn-sm mt-3 -ml-2.5" href="/onboarding?new=1">Create a new organisation</a>
		</section>

		<form class="panel p-6 ring-1 ring-bad/30" onsubmit={deleteAccount}>
			<h2 class="font-semibold text-bad-ink">Delete your account</h2>
			<p class="mt-1 text-sm text-ink-2">
				Removes your sign-in and your access everywhere. Figures you entered stay, without your name.
				{#if soloOrganizations.length}
					Organisations where you're the only person are deleted with it. If others are in one you run, make one of them an administrator
					first.
				{/if}
			</p>
			<div class="mt-4 flex flex-wrap items-start gap-3">
				<div class="max-w-xs flex-1">
					<label class="sr-only" for="delete-password">Your password</label>
					<input id="delete-password" class="input" type="password" placeholder="Your password" autocomplete="current-password" bind:value={deletePassword} aria-invalid={!!deleteError} />
				</div>
				<button class="btn btn-danger" disabled={deleting || !deletePassword}>{deleting ? 'Deleting…' : 'Delete account'}</button>
			</div>
			{#if deleteError}
				<p class="field-error" role="alert">{deleteError.field('password') ? `That password ${deleteError.field('password')}` : deleteError.message}</p>
			{/if}
		</form>
	</div>
</main>
