<script lang="ts">
	import { page } from '$app/state';
	import { api, ApiError } from '$lib/api';
	import AuthFooter from '$lib/components/auth/AuthFooter.svelte';
	import AuthHeading from '$lib/components/auth/AuthHeading.svelte';
	import FormAlert from '$lib/components/auth/FormAlert.svelte';
	import { href } from '$lib/nav';

	let email = $state(page.url.searchParams.get('email') ?? '');
	let sentTo = $state('');
	let error = $state('');
	let busy = $state(false);

	async function submit(event: SubmitEvent) {
		event.preventDefault();
		busy = true;
		error = '';
		try {
			await api.forgotPassword(email.trim());
			sentTo = email.trim();
		} catch (e) {
			error = e instanceof ApiError ? (e.field('email') ? `This email ${e.field('email')}` : e.message) : 'Could not send the link';
		} finally {
			busy = false;
		}
	}
</script>

<svelte:head><title>Reset your password | Clairvoyance</title></svelte:head>

{#if sentTo}
	<AuthHeading icon="mail" tone="bg-good-wash text-good-ink" title="Check your email" />
	<p class="mt-2 text-ink-2">
		If there's an account for <strong class="font-medium text-ink">{sentTo}</strong>, it has a link to choose a new password. The link works for 1 hour.
	</p>
	<p class="mt-4 rounded-lg bg-surface-2 px-3.5 py-2.5 text-sm text-ink-2">Nothing after a few minutes? Check your spam folder, or make sure it's the address you signed up with.</p>
	<AuthFooter><a class="font-medium text-accent hover:underline" href={href('/login', { email: sentTo })}>Back to sign in</a></AuthFooter>
{:else}
	<AuthHeading icon="key" title="Forgot your password?" lead="Enter the email you signed up with and we'll send you a link to choose a new one." />
	<form class="mt-8 space-y-5" onsubmit={submit}>
		<div>
			<label class="field-label" for="email">Email</label>
			<!-- svelte-ignore a11y_autofocus -->
			<input id="email" class="input" type="email" autocomplete="email" required autofocus bind:value={email} />
		</div>
		<FormAlert message={error} />
		<button class="btn btn-primary w-full" disabled={busy}>{busy ? 'Sending…' : 'Send reset link'}</button>
	</form>
	<AuthFooter>Remembered it? <a class="font-medium text-accent hover:underline" href={href('/login', { email: email || null })}>Sign in</a></AuthFooter>
{/if}
