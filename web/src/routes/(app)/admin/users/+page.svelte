<script lang="ts">
	import { onMount } from 'svelte';
	import { api, ApiError } from '$lib/api';
	import Drawer from '$lib/components/Drawer.svelte';
	import Icon from '$lib/components/Icon.svelte';
	import InviteList from '$lib/components/InviteList.svelte';
	import { joinNames } from '$lib/format';
	import { session, setMe } from '$lib/session.svelte';
	import { toast } from '$lib/toast.svelte';
	import type { Invitation, Member, Membership, Role } from '$lib/types';
	import { units } from '$lib/units.svelte';

	let members = $state<Member[]>([]);
	let invitations = $state<Invitation[]>([]);
	let loaded = $state(false);

	onMount(load);
	async function load() {
		[members, invitations] = await Promise.all([api.members(), api.invitations()]);
		loaded = true;
	}

	function accessSummary(role: Role, list: Membership[]): string {
		if (role === 'admin') return 'Everything';
		if (!list.length) return 'No access yet';
		const names = (r: Membership['role']) => list.filter((m) => m.role === r).map((m) => units.get(m.unit_id)?.name ?? 'a removed unit');
		return [names('contributor').length && `Enters data for ${joinNames(names('contributor'))}`, names('viewer').length && `Views ${joinNames(names('viewer'))}`]
			.filter(Boolean)
			.join('. ');
	}

	// ---- Invite drawer
	let inviting = $state(false);

	// ---- Member drawer
	let open = $state(false);
	let editing = $state<Member | null>(null);
	let role = $state<Role>('member');
	let access = $state<(Membership & { key: number })[]>([]);
	let error = $state<ApiError | null>(null);
	let busy = $state(false);
	let keySeq = 0;

	const isSelf = $derived(editing?.id === session.user?.id);
	const unitOptions = $derived(units.list.filter((u) => u.is_active));

	function startEdit(member: Member) {
		editing = member;
		role = member.role;
		access = member.memberships.map((m) => ({ ...m, key: keySeq++ }));
		error = null;
		open = true;
	}

	async function save(event: SubmitEvent) {
		event.preventDefault();
		if (!editing) return;
		busy = true;
		error = null;
		try {
			if (role !== editing.role) await api.setMemberRole(editing.id, role);
			if (role === 'member') await api.saveMemberships(editing.id, access.map(({ unit_id, role }) => ({ unit_id, role })));
			toast(`Saved ${editing.name || editing.email}`);
			open = false;
			if (isSelf) setMe(await api.me());
			await load();
		} catch (e) {
			error = e instanceof ApiError ? e : new ApiError(0, 'Could not save');
		} finally {
			busy = false;
		}
	}

	async function remove() {
		if (!editing) return;
		const who = editing.name || editing.email;
		if (!confirm(`Remove ${who} from ${session.user?.organization?.name}? Their account and the figures they entered are kept.`)) return;
		try {
			await api.removeMember(editing.id);
			toast(`Removed ${who}`);
			open = false;
			await load();
		} catch (e) {
			toast(e instanceof ApiError ? e.message : 'Could not remove', 'error');
		}
	}

	/** Sends a fresh link by email; the old link stops working. */
	async function resend(invite: Invitation) {
		try {
			const sent = await api.invite({ email: invite.email, role: invite.role, access: invite.access });
			if (sent.emailed) toast(`Emailed a new link to ${invite.email}`);
			else toast(sent.email_problem === 'unverified' ? 'Confirm your email address first' : `Couldn't email ${invite.email}`, 'error');
			await load();
		} catch (e) {
			toast(e instanceof ApiError ? e.message : 'Could not resend', 'error');
		}
	}

	async function revoke(invite: Invitation) {
		try {
			await api.revokeInvitation(invite.id);
			toast(`Cancelled the invitation for ${invite.email}`);
			await load();
		} catch (e) {
			toast(e instanceof ApiError ? e.message : 'Could not cancel', 'error');
		}
	}
</script>

<svelte:head><title>People | Clairvoyance</title></svelte:head>

<div class="flex flex-wrap items-end justify-between gap-4">
	<div>
		<h2 class="text-lg font-semibold">People</h2>
		<p class="mt-1 max-w-[70ch] text-ink-2">Access to a unit includes every unit inside it. People who can enter data can also import it.</p>
	</div>
	<button class="btn btn-primary" onclick={() => (inviting = true)}><Icon name="plus" size={15} /> Invite people</button>
</div>

{#if loaded}
	<div class="panel mt-5 overflow-x-auto">
		<table class="w-full min-w-[640px] text-left">
			<thead class="bg-surface-2 text-sm text-ink-2">
				<tr class="border-b border-line">
					<th scope="col" class="px-5 py-2.5 font-medium">Person</th>
					<th scope="col" class="px-3 py-2.5 font-medium">Role</th>
					<th scope="col" class="px-3 py-2.5 font-medium">Access</th>
					<th scope="col" class="px-5 py-2.5"><span class="sr-only">Actions</span></th>
				</tr>
			</thead>
			<tbody>
				{#each members as m (m.id)}
					{@const summary = accessSummary(m.role, m.memberships)}
					<tr class="border-b border-line last:border-b-0 hover:bg-surface-2">
						<th scope="row" class="px-5 py-3 font-normal">
							<span class="font-semibold">{m.name || m.email}</span>
							{#if m.id === session.user?.id}<span class="ml-1.5 text-sm text-muted">(you)</span>{/if}
							{#if m.name}<span class="block text-sm text-ink-2">{m.email}</span>{/if}
						</th>
						<td class="px-3 py-3">{m.role === 'admin' ? 'Administrator' : 'Member'}</td>
						<td class="px-3 py-3 text-sm {summary === 'No access yet' ? 'text-warn-ink' : 'text-ink-2'}">{summary}</td>
						<td class="px-5 py-3 text-right"><button class="btn btn-ghost btn-sm" onclick={() => startEdit(m)}>Edit</button></td>
					</tr>
				{/each}
				{#each invitations as invite (invite.id)}
					<tr class="border-b border-line bg-surface-2/60 last:border-b-0">
						<th scope="row" class="px-5 py-3 font-normal">
							<span class="font-semibold text-ink-2">{invite.email}</span>
							<span class="block text-sm text-muted">
								{invite.emailed_at ? 'Emailed' : 'Invited'}{invite.invited_by ? ` by ${invite.invited_by}` : ''}, link valid until {invite.expires_at.slice(0, 10)}
							</span>
						</th>
						<td class="px-3 py-3 text-ink-2">{invite.role === 'admin' ? 'Administrator' : 'Member'}</td>
						<td class="px-3 py-3 text-sm text-ink-2">{accessSummary(invite.role, invite.access)}</td>
						<td class="px-5 py-3 text-right whitespace-nowrap">
							{#if session.user?.email_delivery}
								<button class="btn btn-ghost btn-sm" onclick={() => resend(invite)}>{invite.emailed_at ? 'Resend' : 'Email it'}</button>
							{/if}
							<button class="btn btn-ghost btn-sm" onclick={() => revoke(invite)}>Cancel</button>
						</td>
					</tr>
				{/each}
			</tbody>
		</table>
	</div>
{/if}

<Drawer bind:open={inviting} title="Invite people" width={640}>
	<p class="mb-5 text-ink-2">Each person gets a link that only works for their email address. They sign in or create an account, then join {session.user?.organization?.name}.</p>
	<InviteList onCreated={load} />
</Drawer>

<Drawer bind:open title={editing ? editing.name || editing.email : ''}>
	<form id="member-form" class="space-y-6" onsubmit={save}>
		<fieldset disabled={isSelf}>
			<legend class="field-label">Role</legend>
			<div class="flex gap-2">
				{#each [['member', 'Member'], ['admin', 'Administrator']] as [value, text] (value)}
					<label class="flex flex-1 cursor-pointer items-center gap-2.5 rounded-lg px-3.5 py-2.5 ring-1 {role === value ? 'bg-accent-wash ring-chart' : 'ring-line-strong'}">
						<input type="radio" name="role" {value} bind:group={role} class="accent-[var(--accent)]" /> {text}
					</label>
				{/each}
			</div>
			<p class="field-hint">{isSelf ? "You can't change your own role." : 'Administrators can see and change everything in this organisation.'}</p>
		</fieldset>

		{#if role === 'member'}
			<section aria-labelledby="access-heading">
				<h3 id="access-heading" class="field-label">Access</h3>
				{#each access as m (m.key)}
					<div class="mt-2 flex items-center gap-2">
						<select class="input min-w-0 flex-1" bind:value={m.unit_id} aria-label="Unit">
							{#each unitOptions as u (u.id)}<option value={u.id}>{units.pathLabel(u.id) ? `${units.pathLabel(u.id)} › ` : ''}{u.name}</option>{/each}
						</select>
						<select class="input w-40" bind:value={m.role} aria-label="Access level">
							<option value="viewer">Can view</option>
							<option value="contributor">Can enter data</option>
						</select>
						<button type="button" class="rounded p-1.5 text-muted hover:bg-bad-wash hover:text-bad-ink" aria-label="Remove access" onclick={() => (access = access.filter((x) => x.key !== m.key))}>
							<Icon name="x" size={15} />
						</button>
					</div>
				{:else}
					<p class="text-sm text-ink-2">No access yet: they'll see an empty scorecard.</p>
				{/each}
				<button type="button" class="btn btn-ghost btn-sm mt-2 -ml-2.5" onclick={() => (access = [...access, { key: keySeq++, unit_id: units.home()?.id ?? 0, role: 'viewer' }])}>
					<Icon name="plus" size={14} /> Give access to a unit
				</button>
			</section>
		{/if}

		{#if error}<p class="text-sm text-bad-ink" role="alert">{Object.values(error.details)[0] ?? error.message}</p>{/if}
	</form>
	{#snippet footer()}
		{#if editing && !isSelf}
			<button class="btn btn-danger mr-auto" type="button" onclick={remove}>Remove from organisation</button>
		{/if}
		<button class="btn btn-secondary" type="button" onclick={() => (open = false)}>Cancel</button>
		<button class="btn btn-primary" form="member-form" disabled={busy}>{busy ? 'Saving…' : 'Save changes'}</button>
	{/snippet}
</Drawer>
