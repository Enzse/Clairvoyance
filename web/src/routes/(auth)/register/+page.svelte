<script lang="ts">
	import { page } from '$app/state';
	import { api, ApiError } from '$lib/api';
	import AuthFooter from '$lib/components/auth/AuthFooter.svelte';
	import AuthHeading from '$lib/components/auth/AuthHeading.svelte';
	import FormAlert from '$lib/components/auth/FormAlert.svelte';
	import PasswordInput from '$lib/components/auth/PasswordInput.svelte';
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

<AuthHeading
	icon="user-plus"
	title="Create your account"
	lead={joining ? "Then you'll join the organisation that invited you." : "Next you'll set up your organisation. It takes a few minutes."}
/>

<form class="mt-8 space-y-5" onsubmit={submit} novalidate>
	<div>
		<label class="field-label" for="name">Your name</label>
		<!-- svelte-ignore a11y_autofocus -->
		<input id="name" class="input" autocomplete="name" placeholder="e.g. Ana Reyes" autofocus bind:value={name} />
	</div>
	<div>
		<label class="field-label" for="email">Work email</label>
		<input id="email" class="input" type="email" autocomplete="email" required bind:value={email} aria-invalid={!!error?.field('email')} />
		{#if error?.field('email')}<p class="field-error">This email {error.field('email')}</p>{/if}
	</div>
	<div>
		<label class="field-label" for="password">Password</label>
		<PasswordInput bind:value={password} autocomplete="new-password" rule error={error?.field('password') ? `Password ${error.field('password')}` : ''} />
	</div>
	<FormAlert message={error && !error.field('email') && !error.field('password') ? error.message : ''} />
	<button class="btn btn-primary w-full" disabled={busy}>{busy ? 'Creating your account…' : 'Create account'}</button>
</form>

<AuthFooter>
	Already have an account? <a class="font-medium text-accent hover:underline" href={href('/login', { next, email: email || null })}>Sign in</a>
</AuthFooter>
