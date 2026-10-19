<script lang="ts">
	import { onMount } from 'svelte';
	import { flip } from 'svelte/animate';
	import { cubicOut } from 'svelte/easing';
	import { prefersReducedMotion } from 'svelte/motion';
	import { fly } from 'svelte/transition';
	import { page } from '$app/state';
	import { api } from '$lib/api';
	import HelpButton from '$lib/components/HelpButton.svelte';
	import Icon from '$lib/components/Icon.svelte';
	import Avatar from '$lib/components/people/Avatar.svelte';
	import InviteModal from '$lib/components/people/InviteModal.svelte';
	import PeopleTour from '$lib/components/people/PeopleTour.svelte';
	import { accessSummary, shortDate } from '$lib/components/people/people';
	import PersonPanel from '$lib/components/people/PersonPanel.svelte';
	import Tooltip from '$lib/components/Tooltip.svelte';
	import { markTourSeen, tourSeen } from '$lib/components/Tour.svelte';
	import { plural } from '$lib/format';
	import { problem } from '$lib/problem';
	import { session } from '$lib/session.svelte';
	import { toast } from '$lib/toast.svelte';
	import type { Invitation, Member } from '$lib/types';

	/** Everyone in the organisation and who's still to join. Clicking someone slides in their role and access. */
	let members = $state<Member[]>([]);
	let invitations = $state<Invitation[]>([]);
	let loaded = $state(false);
	let selectedId = $state<number | null>(null);
	let query = $state('');
	// The getting-started guide links here with ?invite to open the invite form
	let inviting = $state(page.url.searchParams.has('invite'));
	// Arriving to invite someone: the introduction can wait for the next visit
	let touring = $state(!tourSeen('people') && !page.url.searchParams.has('invite'));
	const move = $derived(prefersReducedMotion.current ? 0 : 1);

	onMount(load);
	async function load() {
		[members, invitations] = await Promise.all([api.members(), api.invitations()]);
		loaded = true;
	}

	const selected = $derived(members.find((m) => m.id === selectedId));
	const shown = $derived.by(() => {
		const q = query.trim().toLowerCase();
		return q ? members.filter((m) => `${m.name} ${m.email}`.toLowerCase().includes(q)) : members;
	});
	const admins = $derived(members.filter((m) => m.role === 'admin').length);

	/** Sends a fresh link by email; the old link stops working. */
	async function resend(invite: Invitation) {
		try {
			const sent = await api.invite({ email: invite.email, role: invite.role, access: invite.access });
			if (sent.emailed) toast(`Emailed a new link to ${invite.email}`);
			else toast(sent.email_problem === 'unverified' ? 'Confirm your email address first' : `Couldn't email ${invite.email}`, 'error');
			await load();
		} catch (e) {
			toast(problem(e, 'Could not resend'), 'error');
		}
	}

	async function revoke(invite: Invitation) {
		try {
			await api.revokeInvitation(invite.id);
			toast(`Cancelled the invitation for ${invite.email}`);
			await load();
		} catch (e) {
			toast(problem(e, 'Could not cancel'), 'error');
		}
	}
</script>

<svelte:head><title>People | Clairvoyance</title></svelte:head>
<svelte:window onkeydown={(e) => e.key === 'Escape' && selected && !inviting && (selectedId = null)} />

<div class="transition-[padding] duration-300 {selected ? 'lg:pr-[max(0px,calc(524px-(100vw-1240px)/2))]' : ''}">
	<div class="flex flex-wrap items-end justify-between gap-4">
		<div class="min-w-0">
			<h2 class="text-lg font-semibold">People</h2>
			<p class="mt-1 max-w-[68ch] text-ink-2">
				{#if loaded}
					{plural(members.length, 'person', 'people')} in {session.user?.organization?.name}, {plural(admins, 'administrator')}{invitations.length ? `, ${invitations.length} waiting to join` : ''}.
				{/if}
				Click someone to change what they can see.
			</p>
		</div>
		<div class="flex flex-wrap gap-2">
			{#if members.length > 8}
				<label class="relative">
					<span class="sr-only">Find someone</span>
					<Icon name="search" size={15} class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted" />
					<input class="input h-10 w-52 pl-9" type="search" placeholder="Find someone" bind:value={query} />
				</label>
			{/if}
			<button class="btn btn-primary" onclick={() => (inviting = true)}><Icon name="send" size={15} /> Invite people</button>
		</div>
	</div>

	{#if loaded}
		<ul class="panel mt-5 divide-y divide-line overflow-hidden" aria-label="People">
			{#each shown as m (m.id)}
				{@const summary = accessSummary(m.role, m.memberships)}
				{@const on = m.id === selectedId}
				<li animate:flip={{ duration: 250 * move }} in:fly={{ y: 8 * move, duration: 250, easing: cubicOut }}>
					<button
						class="group flex w-full items-center gap-3 px-4 py-3 text-left transition-colors sm:px-5 {on ? 'bg-accent-wash' : 'hover:bg-surface-2'}"
						aria-pressed={on}
						onclick={() => (selectedId = on ? null : m.id)}
					>
						<Avatar name={m.name} email={m.email} />
						<span class="min-w-0 flex-1">
							<span class="block truncate font-semibold">{m.name || m.email}{#if m.id === session.user?.id}<span class="ml-1.5 font-normal text-muted">(you)</span>{/if}</span>
							<span class="block truncate text-sm {summary === 'No access yet' ? 'text-warn-ink' : 'text-ink-2'}">{summary}</span>
						</span>
						<span class="hidden items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium sm:inline-flex {m.role === 'admin' ? 'bg-accent-wash text-accent' : 'bg-surface-2 text-ink-2 ring-1 ring-line'}">
							<Icon name={m.role === 'admin' ? 'shield' : 'user'} size={12} />{m.role === 'admin' ? 'Administrator' : 'Member'}
						</span>
						<Icon name="chevron-right" size={16} class="text-muted transition-transform group-hover:translate-x-0.5" />
					</button>
				</li>
			{:else}
				<li class="px-5 py-8 text-center text-ink-2">Nobody here is called “{query}”.</li>
			{/each}
		</ul>

		{#if invitations.length}
			<section class="mt-8" aria-labelledby="waiting-h">
				<h3 id="waiting-h" class="mb-3 text-sm font-semibold text-ink-2">Waiting to join <span class="font-normal text-muted">· invited, not signed up yet</span></h3>
				<ul class="space-y-2">
					{#each invitations as invite (invite.id)}
						<li class="flex flex-wrap items-center gap-3 rounded-xl border border-dashed border-line-strong px-4 py-3 sm:px-5" animate:flip={{ duration: 250 * move }} transition:fly={{ y: 8 * move, duration: 220 }}>
							<span class="grid size-9 shrink-0 place-items-center rounded-full bg-surface-2 text-muted ring-1 ring-line"><Icon name="mail" size={16} /></span>
							<span class="min-w-0 flex-1">
								<span class="block truncate font-medium">{invite.email}</span>
								<span class="flex items-center gap-1 text-sm text-ink-2">
									<Icon name="clock" size={12} class="text-muted" />
									{invite.emailed_at ? 'Emailed' : 'Invited'}{invite.invited_by ? ` by ${invite.invited_by}` : ''} · link works until {shortDate(invite.expires_at)} · {accessSummary(invite.role, invite.access)}
								</span>
							</span>
							<span class="flex gap-1">
								{#if session.user?.email_delivery}
									<button class="btn btn-ghost btn-sm" onclick={() => resend(invite)}><Icon name="send" size={13} /> {invite.emailed_at ? 'Resend' : 'Email it'}</button>
								{/if}
								<Tooltip text="Cancel invitation" side="left">
									<button class="rounded-md p-1.5 text-muted hover:bg-bad-wash hover:text-bad-ink" aria-label="Cancel the invitation for {invite.email}" onclick={() => revoke(invite)}><Icon name="x" size={16} /></button>
								</Tooltip>
							</span>
						</li>
					{/each}
				</ul>
			</section>
		{/if}
	{/if}
</div>

{#if selected}
	<aside
		class="fixed inset-y-0 right-0 z-40 w-full max-w-[500px] border-l border-line bg-surface shadow-pop"
		aria-label="{selected.name || selected.email}"
		transition:fly={{ x: 520 * move, duration: 280, easing: cubicOut, opacity: 1 }}
	>
		<PersonPanel member={selected} onClose={() => (selectedId = null)} onSaved={load} />
	</aside>
{/if}

<InviteModal bind:open={inviting} onCreated={load} />
<PeopleTour bind:open={touring} onClose={() => markTourSeen('people')} />
{#if !selected}
	<HelpButton label="How People works" onclick={() => (touring = true)} />
{/if}
