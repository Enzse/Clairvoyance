<script lang="ts">
	import { goto } from '$app/navigation';
	import { page } from '$app/state';
	import { api, ApiError } from '$lib/api';
	import AuthShell from '$lib/components/AuthShell.svelte';
	import { landingFor, setMe } from '$lib/session.svelte';
	import { toast } from '$lib/toast.svelte';

	/** Opened from the reset email. Works whether or not someone is signed in on this browser. */
	let password = $state('');
	let error = $state('');
	let expired = $state(false);
	let busy = $state(false);

	async function submit(event: SubmitEvent) {
		event.preventDefault();
		busy = true;
		error = '';
		try {
			const me = await api.resetPassword(page.params.token ?? '', password);
			setMe(me);
			toast('Password changed. Other devices have been signed out');
			goto(landingFor(me, null), { replaceState: true });
		} catch (e) {
			if (e instanceof ApiError && e.status === 410) expired = true;
			error = e instanceof ApiError ? (e.field('password') ? `Password ${e.field('password')}` : e.message) : 'Could not change the password';
		} finally {
			busy = false;
		}
	}
</script>

<svelte:head><title>Choose a new password | Clairvoyance</title></svelte:head>

<AuthShell>
	{#if expired}
		<h1 class="text-xl font-semibold tracking-[-0.015em]">This link can't be used</h1>
		<p class="mt-2 text-ink-2">{error}.</p>
		<a class="btn btn-primary mt-8 w-full" href="/forgot-password">Send a new link</a>
	{:else}
		<h1 class="text-xl font-semibold tracking-[-0.015em]">Choose a new password</h1>
		<p class="mt-2 text-ink-2">You'll be signed in here and signed out everywhere else.</p>

		<form class="mt-8 space-y-5" onsubmit={submit}>
			<div>
				<label class="field-label" for="password">New password</label>
				<input id="password" class="input" type="password" autocomplete="new-password" minlength="8" required bind:value={password} aria-describedby="password-hint" />
				<p id="password-hint" class="field-hint">At least 8 characters.</p>
			</div>
			{#if error}
				<p class="text-sm text-bad-ink" role="alert">{error}</p>
			{/if}
			<button class="btn btn-primary w-full" disabled={busy}>{busy ? 'Saving…' : 'Save password and sign in'}</button>
		</form>
	{/if}
</AuthShell>
