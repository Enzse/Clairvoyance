<script lang="ts">
	import { goto } from '$app/navigation';
	import { page } from '$app/state';
	import { api, ApiError } from '$lib/api';
	import AuthShell from '$lib/components/AuthShell.svelte';
	import { href } from '$lib/nav';
	import { logout, session, setMe } from '$lib/session.svelte';
	import { toast } from '$lib/toast.svelte';
	import type { InvitationLookup } from '$lib/types';

	const token = $derived(page.params.token);
	const here = $derived(`/invite/${page.params.token}`);

	let invite = $state<InvitationLookup | null>(null);
	let error = $state('');
	let busy = $state(false);

	$effect(() => {
		api.lookupInvitation(token).then(
			(found) => (invite = found),
			(e) => (error = e instanceof ApiError ? e.message : 'Could not open this invitation')
		);
	});

	const signedInAs = $derived(session.user?.email ?? null);
	const rightPerson = $derived(!!invite && !!signedInAs && invite.email.toLowerCase() === signedInAs.toLowerCase());

	async function accept() {
		busy = true;
		try {
			setMe(await api.acceptInvitation(token));
			toast(`You've joined ${invite?.organization_name}`);
			goto('/');
		} catch (e) {
			error = e instanceof ApiError ? e.message : 'Could not accept the invitation';
		} finally {
			busy = false;
		}
	}
</script>

<svelte:head><title>Invitation | Clairvoyance</title></svelte:head>

<AuthShell>
	{#if error}
		<h1 class="text-xl font-semibold tracking-[-0.015em]">This invitation can't be used</h1>
		<p class="mt-2 text-ink-2">{error}.</p>
		<p class="mt-8 text-sm text-ink-2">
			{#if session.user}
				<a class="font-medium text-accent hover:underline" href="/">Go to Clairvoyance</a>
			{:else}
				<a class="font-medium text-accent hover:underline" href="/login">Sign in</a> or
				<a class="font-medium text-accent hover:underline" href="/register">create an account</a>
			{/if}
		</p>
	{:else if invite && session.loaded}
		<p class="text-sm text-ink-2">{invite.invited_by ? `${invite.invited_by} invited you to` : "You're invited to"}</p>
		<h1 class="mt-1 text-2xl font-semibold tracking-[-0.02em] text-balance">{invite.organization_name}</h1>
		<p class="mt-3 text-ink-2">
			{invite.role === 'admin'
				? "You'll be an administrator: you can set up KPIs, units and people."
				: "You'll see and enter the figures for the units they've given you."}
		</p>

		<div class="mt-8">
			{#if rightPerson}
				<button class="btn btn-primary w-full" disabled={busy} onclick={accept}>{busy ? 'Joining…' : `Join ${invite.organization_name}`}</button>
			{:else if signedInAs}
				<p class="rounded-lg bg-warn-wash p-4 text-sm">
					This invitation is for <strong>{invite.email}</strong>, but you're signed in as <strong>{signedInAs}</strong>.
				</p>
				<button class="btn btn-secondary mt-4 w-full" onclick={async () => { await logout(); goto(href('/login', { next: here, email: invite?.email })); }}>
					Sign in as {invite.email}
				</button>
			{:else if invite.has_account}
				<a class="btn btn-primary w-full" href={href('/login', { next: here, email: invite.email })}>Sign in as {invite.email} to join</a>
			{:else}
				<a class="btn btn-primary w-full" href={href('/register', { next: here, email: invite.email })}>Create your account to join</a>
				<p class="mt-3 text-sm text-ink-2">Use {invite.email}, the address the invitation was sent to.</p>
			{/if}
		</div>
	{/if}
</AuthShell>
