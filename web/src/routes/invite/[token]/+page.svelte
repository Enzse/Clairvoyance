<script lang="ts">
	import { goto } from '$app/navigation';
	import { page } from '$app/state';
	import { api, ApiError } from '$lib/api';
	import AuthFooter from '$lib/components/auth/AuthFooter.svelte';
	import AuthHeading from '$lib/components/auth/AuthHeading.svelte';
	import FormAlert from '$lib/components/auth/FormAlert.svelte';
	import AuthShell from '$lib/components/AuthShell.svelte';
	import Icon from '$lib/components/Icon.svelte';
	import { href } from '$lib/nav';
	import { logout, session, setMe } from '$lib/session.svelte';
	import { toast } from '$lib/toast.svelte';
	import type { InvitationLookup } from '$lib/types';

	const token = $derived(page.params.token ?? '');
	const here = $derived(`/invite/${token}`);

	let invite = $state<InvitationLookup | null>(null);
	let error = $state('');
	let joinError = $state('');
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
		joinError = '';
		try {
			setMe(await api.acceptInvitation(token));
			toast(`You've joined ${invite?.organization_name}`);
			goto('/');
		} catch (e) {
			joinError = e instanceof ApiError ? e.message : 'Could not accept the invitation';
		} finally {
			busy = false;
		}
	}
</script>

<svelte:head><title>Invitation | Clairvoyance</title></svelte:head>

<AuthShell>
	{#if error}
		<AuthHeading icon="alert" tone="bg-warn-wash text-warn-ink" title="This invitation can't be used" lead="{error}." />
		<AuthFooter>
			{#if session.user}
				<a class="font-medium text-accent hover:underline" href="/">Go to Clairvoyance</a>
			{:else}
				<a class="font-medium text-accent hover:underline" href="/login">Sign in</a> or
				<a class="font-medium text-accent hover:underline" href="/register">create an account</a>
			{/if}
		</AuthFooter>
	{:else if invite && session.loaded}
		<AuthHeading icon="send" title="Join {invite.organization_name}" lead={invite.invited_by ? `${invite.invited_by} invited you.` : "You've been invited."} />

		<div class="mt-6 flex items-start gap-3 rounded-xl bg-surface-2 px-4 py-3 ring-1 ring-line">
			<Icon name={invite.role === 'admin' ? 'shield' : 'user'} size={18} class="mt-0.5 shrink-0 text-accent" />
			<p class="text-sm">
				<strong class="font-medium">{invite.role === 'admin' ? 'Administrator' : 'Member'}.</strong>
				<span class="text-ink-2">
					{invite.role === 'admin'
						? 'You can set up the structure, KPIs and people.'
						: "You'll see and enter the figures for the parts of the organisation you've been given."}
				</span>
			</p>
		</div>

		<div class="mt-6 space-y-4">
			<FormAlert message={joinError} />
			{#if rightPerson}
				<button class="btn btn-primary w-full" disabled={busy} onclick={accept}>{busy ? 'Joining…' : `Join ${invite.organization_name}`}</button>
			{:else if signedInAs}
				<p class="rounded-lg bg-warn-wash px-3.5 py-2.5 text-sm">
					This invitation is for <strong class="font-medium">{invite.email}</strong>, but you're signed in as <strong class="font-medium">{signedInAs}</strong>.
				</p>
				<button class="btn btn-secondary w-full" onclick={async () => { await logout(); goto(href('/login', { next: here, email: invite?.email })); }}>
					Sign in as {invite.email}
				</button>
			{:else if invite.has_account}
				<a class="btn btn-primary w-full" href={href('/login', { next: here, email: invite.email })}>Sign in as {invite.email} to join</a>
			{:else}
				<a class="btn btn-primary w-full" href={href('/register', { next: here, email: invite.email })}>Create your account to join</a>
				<p class="text-center text-sm text-ink-2">Use {invite.email}, the address the invitation was sent to.</p>
			{/if}
		</div>
	{:else}
		<div class="flex items-center gap-3 text-ink-2" aria-live="polite">
			<span class="size-5 animate-spin rounded-full border-2 border-line-strong border-t-chart"></span>
			Opening your invitation…
		</div>
	{/if}
</AuthShell>
