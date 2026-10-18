<script lang="ts">
	import { goto } from '$app/navigation';
	import { page } from '$app/state';
	import { ApiError } from '$lib/api';
	import { login } from '$lib/session.svelte';

	let email = $state('');
	let password = $state('');
	let error = $state('');
	let busy = $state(false);

	async function submit(event: SubmitEvent) {
		event.preventDefault();
		busy = true;
		error = '';
		try {
			await login(email, password);
			const next = page.url.searchParams.get('next');
			goto(next && next.startsWith('/') && !next.startsWith('//') ? next : '/', { replaceState: true });
		} catch (e) {
			error = e instanceof ApiError ? e.message : 'Sign-in failed';
		} finally {
			busy = false;
		}
	}
</script>

<svelte:head><title>Sign in | Clairvoyance</title></svelte:head>

<h1 class="text-xl font-semibold tracking-[-0.015em]">Sign in</h1>
<p class="mt-2 text-ink-2">Use the email your administrator registered for you.</p>

<form class="mt-8 space-y-5" onsubmit={submit}>
	<div>
		<label class="field-label" for="email">Email</label>
		<input id="email" class="input" type="email" autocomplete="email" required bind:value={email} />
	</div>
	<div>
		<label class="field-label" for="password">Password</label>
		<input id="password" class="input" type="password" autocomplete="current-password" required bind:value={password} />
	</div>
	{#if error}
		<p class="text-sm text-bad-ink" role="alert">{error}</p>
	{/if}
	<button class="btn btn-primary w-full" disabled={busy}>{busy ? 'Signing in…' : 'Sign in'}</button>
</form>

<p class="mt-8 text-sm text-ink-2">
	New here? <a class="font-medium text-accent hover:underline" href="/register">Create an account</a>
</p>
