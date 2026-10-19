<script lang="ts">
	import { page } from '$app/state';
	import { api, ApiError } from '$lib/api';
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
	<h1 class="text-xl font-semibold tracking-[-0.015em]">Check your email</h1>
	<p class="mt-2 text-ink-2">
		If there's an account for <strong class="text-ink">{sentTo}</strong>, we've sent it a link to choose a new password. The link works for
		1 hour.
	</p>
	<p class="mt-4 text-sm text-ink-2">Nothing after a few minutes? Check your spam folder, or make sure you typed the address you signed up with.</p>
	<p class="mt-8 text-sm"><a class="font-medium text-accent hover:underline" href={href('/login', { email: sentTo })}>Back to sign in</a></p>
{:else}
	<h1 class="text-xl font-semibold tracking-[-0.015em]">Reset your password</h1>
	<p class="mt-2 text-ink-2">Enter the email you signed up with and we'll send you a link to choose a new password.</p>

	<form class="mt-8 space-y-5" onsubmit={submit}>
		<div>
			<label class="field-label" for="email">Email</label>
			<input id="email" class="input" type="email" autocomplete="email" required bind:value={email} />
		</div>
		{#if error}
			<p class="text-sm text-bad-ink" role="alert">{error}</p>
		{/if}
		<button class="btn btn-primary w-full" disabled={busy}>{busy ? 'Sending…' : 'Send reset link'}</button>
	</form>

	<p class="mt-8 text-sm text-ink-2">
		Remembered it? <a class="font-medium text-accent hover:underline" href={href('/login', { email: email || null })}>Sign in</a>
	</p>
{/if}
