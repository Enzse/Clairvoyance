<script lang="ts">
	import { page } from '$app/state';
	import { ApiError } from '$lib/api';
	import { href } from '$lib/nav';
	import { login } from '$lib/session.svelte';

	let email = $state(page.url.searchParams.get('email') ?? '');
	let password = $state('');
	let error = $state('');
	let busy = $state(false);

	const next = $derived(page.url.searchParams.get('next'));

	// The (auth) layout moves on as soon as the session has a user
	async function submit(event: SubmitEvent) {
		event.preventDefault();
		busy = true;
		error = '';
		try {
			await login(email, password);
		} catch (e) {
			error = e instanceof ApiError ? e.message : 'Sign-in failed';
		} finally {
			busy = false;
		}
	}
</script>

<svelte:head><title>Sign in | Clairvoyance</title></svelte:head>

<h1 class="text-xl font-semibold tracking-[-0.015em]">Sign in</h1>
<p class="mt-2 text-ink-2">Welcome back.</p>

<form class="mt-8 space-y-5" onsubmit={submit}>
	<div>
		<label class="field-label" for="email">Email</label>
		<input id="email" class="input" type="email" autocomplete="email" required bind:value={email} />
	</div>
	<div>
		<div class="flex items-baseline justify-between gap-3">
			<label class="field-label" for="password">Password</label>
			<a class="text-sm text-accent hover:underline" href={href('/forgot-password', { email: email || null })}>Forgot password?</a>
		</div>
		<input id="password" class="input" type="password" autocomplete="current-password" required bind:value={password} />
	</div>
	{#if error}
		<p class="text-sm text-bad-ink" role="alert">{error}</p>
	{/if}
	<button class="btn btn-primary w-full" disabled={busy}>{busy ? 'Signing in…' : 'Sign in'}</button>
</form>

<p class="mt-8 text-sm text-ink-2">
	New to Clairvoyance? <a class="font-medium text-accent hover:underline" href={href('/register', { next, email: email || null })}>Create an account</a>
</p>
