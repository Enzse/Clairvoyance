<script lang="ts">
	import { page } from '$app/state';
	import { api, ApiError } from '$lib/api';
	import AuthShell from '$lib/components/AuthShell.svelte';
	import { session, setMe } from '$lib/session.svelte';

	/** Opened from the confirmation email, possibly on a device where nobody is signed in. */
	let status = $state<'checking' | 'done' | 'failed'>('checking');
	let error = $state('');

	$effect(() => {
		api.verifyEmail(page.params.token ?? '').then(
			(me) => {
				if (!Array.isArray(me)) setMe(me);
				status = 'done';
			},
			(e) => {
				error = e instanceof ApiError ? e.message : 'Could not confirm the address';
				status = 'failed';
			}
		);
	});
</script>

<svelte:head><title>Confirm your email | Clairvoyance</title></svelte:head>

<AuthShell>
	{#if status === 'checking'}
		<p class="text-ink-2" aria-live="polite">Confirming your email address…</p>
	{:else if status === 'done'}
		<h1 class="text-xl font-semibold tracking-[-0.015em]">Email address confirmed</h1>
		<p class="mt-2 text-ink-2">Thanks. Invitations you send are now emailed to people directly.</p>
		<a class="btn btn-primary mt-8 w-full" href={session.user ? '/' : '/login'}>{session.user ? 'Go to Clairvoyance' : 'Sign in'}</a>
	{:else}
		<h1 class="text-xl font-semibold tracking-[-0.015em]">This link can't be used</h1>
		<p class="mt-2 text-ink-2">{error}.</p>
		<a class="btn btn-primary mt-8 w-full" href={session.user ? '/' : '/login'}>{session.user ? 'Go to Clairvoyance' : 'Sign in'}</a>
	{/if}
</AuthShell>
