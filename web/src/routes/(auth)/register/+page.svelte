<script lang="ts">
	import { page } from '$app/state';
	import { api, ApiError } from '$lib/api';
	import { href } from '$lib/nav';
	import { setMe } from '$lib/session.svelte';

	let name = $state('');
	let email = $state(page.url.searchParams.get('email') ?? '');
	let password = $state('');
	let error = $state<ApiError | null>(null);
	let busy = $state(false);

	const next = $derived(page.url.searchParams.get('next'));
	const joining = $derived(next?.startsWith('/invite/') ?? false);

	// The (auth) layout moves on to onboarding (or the invitation) once signed in
	async function submit(event: SubmitEvent) {
		event.preventDefault();
		busy = true;
		error = null;
		try {
			setMe(await api.register({ name: name || undefined, email, password }));
		} catch (e) {
			error = e instanceof ApiError ? e : new ApiError(0, 'Could not create the account');
		} finally {
			busy = false;
		}
	}
</script>

<svelte:head><title>Create account | Clairvoyance</title></svelte:head>

<h1 class="text-xl font-semibold tracking-[-0.015em]">Create your account</h1>
<p class="mt-2 text-ink-2">
	{joining ? "Then you'll join the organisation that invited you." : "Next you'll set up your organisation. It takes a few minutes."}
</p>

<form class="mt-8 space-y-5" onsubmit={submit} novalidate>
	<div>
		<label class="field-label" for="name">Your name</label>
		<input id="name" class="input" autocomplete="name" bind:value={name} />
	</div>
	<div>
		<label class="field-label" for="email">Work email</label>
		<input id="email" class="input" type="email" autocomplete="email" required bind:value={email} aria-invalid={!!error?.field('email')} />
		{#if error?.field('email')}<p class="field-error">This email {error.field('email')}</p>{/if}
	</div>
	<div>
		<label class="field-label" for="password">Password</label>
		<input
			id="password"
			class="input"
			type="password"
			autocomplete="new-password"
			minlength="8"
			required
			bind:value={password}
			aria-invalid={!!error?.field('password')}
		/>
		<p class={error?.field('password') ? 'field-error' : 'field-hint'}>At least 8 characters.</p>
	</div>
	{#if error && !error.field('email') && !error.field('password')}
		<p class="text-sm text-bad-ink" role="alert">{error.message}</p>
	{/if}
	<button class="btn btn-primary w-full" disabled={busy}>{busy ? 'Creating account…' : 'Create account'}</button>
</form>

<p class="mt-8 text-sm text-ink-2">
	Already have an account? <a class="font-medium text-accent hover:underline" href={href('/login', { next, email: email || null })}>Sign in</a>
</p>
