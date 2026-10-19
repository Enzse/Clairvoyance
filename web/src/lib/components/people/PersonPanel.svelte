<script lang="ts">
	import { slide } from 'svelte/transition';
	import { api } from '$lib/api';
	import { problem } from '$lib/problem';
	import { session, setMe } from '$lib/session.svelte';
	import { toast } from '$lib/toast.svelte';
	import type { Member, Role } from '$lib/types';
	import Icon from '../Icon.svelte';
	import OptionCards from '../OptionCards.svelte';
	import Tooltip from '../Tooltip.svelte';
	import AccessEditor, { type AccessRow } from './AccessEditor.svelte';
	import Avatar from './Avatar.svelte';
	import { shortDate } from './people';

	/** One person's role and access. Changes save together from the bar that appears once anything differs. */
	let { member, onClose, onSaved }: { member: Member; onClose: () => void; onSaved: () => Promise<void> } = $props();

	let role = $state<Role>(member.role);
	let rows = $state<AccessRow[]>([]);
	let busy = $state(false);
	let confirming = $state(false);
	let seq = 0;
	const nextKey = () => seq++;

	const isSelf = $derived(member.id === session.user?.id);
	const org = $derived(session.user?.organization?.name);
	const who = $derived(member.name || member.email);
	const plain = (list: AccessRow[]) => JSON.stringify(list.map(({ unit_id, role }) => ({ unit_id, role })));

	// Another person opened: start from their saved values
	$effect(() => {
		void member.id;
		reset();
		confirming = false;
	});

	function reset() {
		role = member.role;
		rows = member.memberships.map((m) => ({ ...m, key: nextKey() }));
	}

	const dirty = $derived(role !== member.role || (role === 'member' && plain(rows) !== plain(member.memberships.map((m) => ({ ...m, key: 0 })))));

	async function save() {
		busy = true;
		try {
			if (role !== member.role) await api.setMemberRole(member.id, role);
			if (role === 'member') await api.saveMemberships(member.id, JSON.parse(plain(rows)));
			toast(`Saved ${who}`);
			if (isSelf) setMe(await api.me());
			await onSaved();
		} catch (e) {
			toast(problem(e, 'Could not save'), 'error');
		} finally {
			busy = false;
		}
	}

	async function remove() {
		busy = true;
		try {
			await api.removeMember(member.id);
			toast(`Removed ${who} from ${org}`);
			onClose();
			await onSaved();
		} catch (e) {
			toast(problem(e, 'Could not remove'), 'error');
		} finally {
			busy = false;
		}
	}
</script>

<div class="flex h-full flex-col">
	<header class="flex items-center gap-3 border-b border-line px-5 py-4">
		<Avatar name={member.name} email={member.email} size="lg" />
		<div class="min-w-0 flex-1">
			<h2 class="truncate text-lg font-semibold tracking-[-0.01em]">{who}{isSelf ? ' (you)' : ''}</h2>
			<p class="truncate text-sm text-ink-2">{member.name ? `${member.email} · ` : ''}joined {shortDate(member.joined_at)}</p>
		</div>
		<Tooltip text="Close" side="bottom">
			<button class="rounded-md p-1.5 text-muted hover:bg-surface-2 hover:text-ink" onclick={onClose} aria-label="Close"><Icon name="x" size={18} /></button>
		</Tooltip>
	</header>

	<div class="flex-1 space-y-8 overflow-y-auto px-5 py-5">
		{#if isSelf}
			<div class="flex items-start gap-3 rounded-xl bg-surface-2 px-4 py-3 text-sm ring-1 ring-line">
				<Icon name="shield" size={18} class="mt-0.5 shrink-0 text-accent" />
				<p><strong class="font-medium">You're an administrator.</strong> <span class="text-ink-2">Another administrator can change your role, so {org} is never left without one.</span></p>
			</div>
		{:else}
			<OptionCards
				name="p-role"
				legend="Role"
				bind:value={role}
				options={[
					{ value: 'member', label: 'Member', hint: 'Sees and enters data only where you allow.', icon: 'user' },
					{ value: 'admin', label: 'Administrator', hint: 'Sees and changes everything, including people.', icon: 'shield' }
				]}
			/>
		{/if}

		{#if role === 'member'}
			<section aria-labelledby="p-access" transition:slide={{ duration: 180 }}>
				<h3 id="p-access" class="field-label">Access</h3>
				<AccessEditor bind:rows {nextKey} />
			</section>
		{/if}

		{#if !isSelf}
			<section class="border-t border-line pt-6">
				{#if confirming}
					<div class="rounded-xl bg-bad-wash p-4" transition:slide={{ duration: 180 }}>
						<p class="font-medium text-bad-ink">Remove {who} from {org}?</p>
						<p class="mt-1 text-sm text-ink-2">They lose access straight away. Their account and the figures they entered are kept.</p>
						<div class="mt-3 flex gap-2">
							<button class="btn btn-danger" onclick={remove} disabled={busy}>Remove</button>
							<button class="btn btn-ghost" onclick={() => (confirming = false)}>Keep them</button>
						</div>
					</div>
				{:else}
					<button class="btn btn-ghost -ml-2.5 text-bad-ink" onclick={() => (confirming = true)}><Icon name="log-out" size={15} /> Remove from {org}</button>
				{/if}
			</section>
		{/if}
	</div>

	{#if dirty}
		<footer class="flex items-center gap-2 border-t border-line bg-surface px-5 py-3" transition:slide={{ duration: 180 }}>
			<span class="mr-auto text-sm text-ink-2">Unsaved changes</span>
			<button class="btn btn-ghost" onclick={reset} disabled={busy}>Undo</button>
			<button class="btn btn-primary" onclick={save} disabled={busy}>{busy ? 'Saving…' : 'Save changes'}</button>
		</footer>
	{/if}
</div>
