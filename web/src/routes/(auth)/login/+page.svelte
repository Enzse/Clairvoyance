<script lang="ts">
	import { page } from '$app/state';
	import { ApiError } from '$lib/api';
	import AuthFooter from '$lib/components/auth/AuthFooter.svelte';
	import AuthHeading from '$lib/components/auth/AuthHeading.svelte';
	import FormAlert from '$lib/components/auth/FormAlert.svelte';
	import PasswordInput from '$lib/components/auth/PasswordInput.svelte';
	import { href } from '$lib/nav';
	import { login } from '$lib/session.svelte';

	let email = $state(page.url.searchParams.get('email') ?? '');
	let password = $state('');
	let error = $state('');
	let busy = $state(false);

	const next = $derived(page.url.searchParams.get('next'));
	const joining = $derived(next?.startsWith('/invite/') ?? false);

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

<AuthHeading icon="log-in" title="Welcome back" lead={joining ? 'Sign in to accept your invitation.' : 'Sign in to see how things are going.'} />

<form class="mt-8 space-y-5" onsubmit={submit}>
	<div>
		<label class="field-label" for="email">Email</label>
		<!-- svelte-ignore a11y_autofocus -->
		<input id="email" class="input" type="email" autocomplete="email" required autofocus={!email} bind:value={email} />
	</div>
	<div>
		<div class="flex items-baseline justify-between gap-3">
			<label class="field-label" for="password">Password</label>
			<a class="text-sm text-accent hover:underline" href={href('/forgot-password', { email: email || null })}>Forgot it?</a>
		</div>
		<PasswordInput bind:value={password} />
	</div>
	<FormAlert message={error} />
	<button class="btn btn-primary w-full" disabled={busy}>{busy ? 'Signing in…' : 'Sign in'}</button>
</form>

<AuthFooter>
	New to Clairvoyance? <a class="font-medium text-accent hover:underline" href={href('/register', { next, email: email || null })}>Create an account</a>
</AuthFooter>
