<script lang="ts">
	import { page } from '$app/state';
	import { api, ApiError } from '$lib/api';
	import AuthHeading from '$lib/components/auth/AuthHeading.svelte';
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
		<div class="flex items-center gap-3 text-ink-2" aria-live="polite">
			<span class="size-5 animate-spin rounded-full border-2 border-line-strong border-t-chart"></span>
			Confirming your email address…
		</div>
	{:else if status === 'done'}
		<AuthHeading icon="check" tone="bg-good-wash text-good-ink" title="Email address confirmed" lead="Thanks. Invitations you send are now emailed to people directly." />
		<a class="btn btn-primary mt-8 w-full" href={session.user ? '/' : '/login'}>{session.user ? 'Go to Clairvoyance' : 'Sign in'}</a>
	{:else}
		<AuthHeading icon="alert" tone="bg-warn-wash text-warn-ink" title="This link can't be used" lead="{error}." />
		<a class="btn btn-primary mt-8 w-full" href={session.user ? '/' : '/login'}>{session.user ? 'Go to Clairvoyance' : 'Sign in'}</a>
	{/if}
</AuthShell>
