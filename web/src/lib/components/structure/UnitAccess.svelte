<script lang="ts">
	import { cubicOut } from 'svelte/easing';
	import { prefersReducedMotion } from 'svelte/motion';
	import { fly } from 'svelte/transition';
	import { api } from '$lib/api';
	import { plural } from '$lib/format';
	import { members } from '$lib/members.svelte';
	import { problem } from '$lib/problem';
	import { toast } from '$lib/toast.svelte';
	import type { Member, Membership, Unit } from '$lib/types';
	import { units } from '$lib/units.svelte';
	import Icon from '../Icon.svelte';
	import Tooltip from '../Tooltip.svelte';
	import Avatar from '../people/Avatar.svelte';
	import InviteModal from '../people/InviteModal.svelte';
	import LevelSwitch from '../people/LevelSwitch.svelte';

	/**
	 * Who can see or enter data for this unit: people given it directly (changeable here),
	 * people who have it through a group above (changeable on that group), and invitations
	 * still waiting.
	 */
	let { unit, onOpen }: { unit: Unit; onOpen: (unitId: number) => void } = $props();

	let inviting = $state(false);
	const move = $derived(prefersReducedMotion.current ? 0 : 1);

	const RANK = { viewer: 1, contributor: 2 };
	const verb = (level: Membership['role']) => (level === 'contributor' ? 'enters data' : 'views');
	const isGroup = $derived(unit.parent_id === null || units.children(unit.id).length > 0);
	const above = $derived(units.path(unit.id).slice(0, -1));
	const people = $derived(members.list.filter((m) => m.role === 'member'));
	const admins = $derived(members.list.length - people.length);

	const direct = $derived(people.flatMap((m) => m.memberships.filter((a) => a.unit_id === unit.id).map((access) => ({ member: m, access }))));
	const inherited = $derived(
		people.flatMap((m) => {
			if (direct.some((d) => d.member.id === m.id)) return [];
			const best = m.memberships.filter((a) => above.some((u) => u.id === a.unit_id)).sort((a, b) => RANK[b.role] - RANK[a.role])[0];
			const via = best && units.get(best.unit_id);
			return best && via ? [{ member: m, access: best, via }] : [];
		})
	);
	const waiting = $derived(members.invitations.filter((i) => i.access.some((a) => a.unit_id === unit.id)));
	const addable = $derived(people.filter((m) => !direct.some((d) => d.member.id === m.id)));

	async function setAccess(member: Member, level: Membership['role'] | null) {
		const who = member.name || member.email;
		const next = member.memberships.filter((a) => a.unit_id !== unit.id);
		if (level) next.push({ unit_id: unit.id, role: level });
		try {
			member.memberships = await api.saveMemberships(member.id, next);
			toast(level ? `${who} now ${verb(level)} for ${unit.name}` : `${who} no longer has access to ${unit.name}`);
		} catch (e) {
			toast(problem(e, 'Could not change access'), 'error');
		}
	}

	function add(event: Event & { currentTarget: HTMLSelectElement }) {
		const member = people.find((m) => m.id === Number(event.currentTarget.value));
		event.currentTarget.value = '';
		// Someone added to a single unit usually reports for it; to a group, usually follows it
		if (member) setAccess(member, isGroup ? 'viewer' : 'contributor');
	}
</script>

<section class="border-t border-line pt-5" aria-labelledby="u-access">
	<div class="flex items-center justify-between gap-3">
		<h3 id="u-access" class="text-sm font-semibold">Who has access</h3>
		<button class="btn btn-ghost btn-sm -mr-2" onclick={() => (inviting = true)}><Icon name="send" size={13} /> Invite</button>
	</div>
	<p class="text-xs text-muted">
		{plural(admins, 'administrator')} {admins === 1 ? 'sees' : 'see'} everything.{isGroup ? ` Access here includes everything inside ${unit.name}.` : ''}
	</p>

	<ul class="mt-3 space-y-1.5">
		{#each direct as { member, access } (member.id)}
			<li class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 ring-1 ring-line" in:fly={{ y: -6 * move, duration: 200, easing: cubicOut }}>
				<Avatar name={member.name} email={member.email} size="sm" />
				<span class="min-w-0 flex-1 truncate text-sm font-medium">{member.name || member.email}</span>
				<LevelSwitch value={access.role} compact onchange={(level) => setAccess(member, level)} />
				<Tooltip text="Remove access" side="left">
					<button class="rounded-md p-1 text-muted hover:bg-bad-wash hover:text-bad-ink" aria-label="Remove {member.name || member.email}'s access" onclick={() => setAccess(member, null)}>
						<Icon name="x" size={14} />
					</button>
				</Tooltip>
			</li>
		{/each}
		{#each inherited as { member, access, via } (member.id)}
			<li class="flex items-center gap-2.5 rounded-lg border border-dashed border-line-strong px-2 py-1.5">
				<Avatar name={member.name} email={member.email} size="sm" />
				<span class="min-w-0 flex-1 truncate text-sm">{member.name || member.email}</span>
				<Tooltip text="Their access comes from {via.name}. Change it there" side="left">
					<button class="flex shrink-0 items-center gap-1 rounded-md px-1.5 py-0.5 text-xs text-ink-2 hover:bg-accent-wash hover:text-accent" onclick={() => onOpen(via.id)}>
						{access.role === 'contributor' ? 'Enters data' : 'Views'} through <span class="font-medium">{via.name}</span>
						<Icon name="chevron-right" size={12} />
					</button>
				</Tooltip>
			</li>
		{/each}
		{#each waiting as invite (invite.id)}
			<li class="flex items-center gap-2.5 px-2 py-1.5">
				<span class="grid size-7 shrink-0 place-items-center rounded-full bg-surface-2 text-muted ring-1 ring-line"><Icon name="mail" size={13} /></span>
				<span class="min-w-0 flex-1 truncate text-sm text-ink-2">{invite.email}</span>
				<span class="shrink-0 text-xs text-muted">Invited, waiting to join</span>
			</li>
		{/each}
	</ul>

	{#if !direct.length && !inherited.length && !waiting.length}
		<p class="mt-1 rounded-lg border border-dashed border-line-strong px-3 py-2.5 text-sm text-ink-2">Only administrators can see {unit.name} so far.</p>
	{/if}
	{#if inherited.length}
		<p class="mt-2 text-xs text-muted">Access given on a group above is changed on that group. You can also give someone more here.</p>
	{/if}

	{#if addable.length}
		<select class="input mt-2 h-9 text-sm" onchange={add} aria-label="Give someone access to {unit.name}">
			<option value="">Give someone access…</option>
			{#each addable as m (m.id)}<option value={m.id}>{m.name || m.email}</option>{/each}
		</select>
	{/if}
</section>

<InviteModal bind:open={inviting} onCreated={() => members.load()} preset={{ unit_id: unit.id, role: isGroup ? 'viewer' : 'contributor' }} />
