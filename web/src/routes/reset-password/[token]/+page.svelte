<script lang="ts">
	import { goto } from '$app/navigation';
	import { page } from '$app/state';
	import { api, ApiError } from '$lib/api';
	import AuthHeading from '$lib/components/auth/AuthHeading.svelte';
	import FormAlert from '$lib/components/auth/FormAlert.svelte';
	import PasswordInput from '$lib/components/auth/PasswordInput.svelte';
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
		<AuthHeading icon="alert" tone="bg-warn-wash text-warn-ink" title="This link can't be used" lead="{error}." />
		<a class="btn btn-primary mt-8 w-full" href="/forgot-password">Send a new link</a>
	{:else}
		<AuthHeading icon="lock" title="Choose a new password" lead="You'll be signed in here and signed out everywhere else." />
		<form class="mt-8 space-y-5" onsubmit={submit}>
			<div>
				<label class="field-label" for="password">New password</label>
				<PasswordInput bind:value={password} autocomplete="new-password" rule />
			</div>
			<FormAlert message={error} />
			<button class="btn btn-primary w-full" disabled={busy}>{busy ? 'Saving…' : 'Save password and sign in'}</button>
		</form>
	{/if}
</AuthShell>
