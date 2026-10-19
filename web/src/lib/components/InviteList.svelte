<script lang="ts">
	import { api, ApiError, invitationLink } from '$lib/api';
	import { plural } from '$lib/format';
	import { session } from '$lib/session.svelte';
	import type { Invitation, Membership, Role } from '$lib/types';
	import { units } from '$lib/units.svelte';
	import Icon from './Icon.svelte';

	/**
	 * Invite people by email with one level of access each. The server emails the link when it
	 * can; the link is always shown too, to send another way.
	 */
	let { onCreated }: { onCreated?: (count: number) => void } = $props();

	type Row = { key: number; email: string; access: string; error?: string };
	let seq = 0;
	const blank = (): Row => ({ key: seq++, email: '', access: units.home() ? `view:${units.home()!.id}` : 'admin' });

	let rows = $state<Row[]>([blank()]);
	type Sent = { email: string; link: string; copied: boolean; emailed: boolean; problem: Invitation['email_problem'] };
	let links = $state<Sent[]>([]);
	const emailing = $derived(!!session.user?.email_delivery);
	const allEmailed = $derived(links.length > 0 && links.every((l) => l.emailed));
	const unverified = $derived(links.some((l) => l.problem === 'unverified'));
	let busy = $state(false);

	const root = $derived(units.home());
	const others = $derived(units.list.filter((u) => u.parent_id !== null && u.is_active));

	function decode(access: string): { role: Role; access: Membership[] } {
		if (access === 'admin') return { role: 'admin', access: [] };
		const [mode, id] = access.split(':');
		return { role: 'member', access: [{ unit_id: Number(id), role: mode === 'enter' ? 'contributor' : 'viewer' }] };
	}

	async function submit(event: SubmitEvent) {
		event.preventDefault();
		busy = true;
		let made = 0;
		for (const row of rows.filter((r) => r.email.trim())) {
			row.error = undefined;
			try {
				const invite = await api.invite({ email: row.email.trim(), ...decode(row.access) });
				links.push({
					email: invite.email,
					link: invitationLink(invite.token!),
					copied: false,
					emailed: !!invite.emailed,
					problem: invite.email_problem ?? null
				});
				row.email = '';
				made++;
			} catch (e) {
				row.error = e instanceof ApiError ? (e.field('email') ? `This email ${e.field('email')}` : e.message) : 'Could not invite';
			}
		}
		rows = rows.filter((r) => r.error || rows.length === 1);
		if (!rows.length) rows = [blank()];
		busy = false;
		if (made) onCreated?.(made);
	}

	async function copy(entry: (typeof links)[number]) {
		await navigator.clipboard.writeText(entry.link);
		links = links.map((l) => ({ ...l, copied: l === entry }));
	}
</script>

<form onsubmit={submit} class="space-y-3">
	{#each rows as row, i (row.key)}
		<div>
			<div class="flex flex-wrap items-center gap-2">
				<input
					class="input min-w-0 flex-1 basis-56"
					type="email"
					placeholder="name@company.com"
					aria-label="Email {i + 1}"
					bind:value={row.email}
					aria-invalid={!!row.error}
				/>
				<select class="input w-auto max-w-full flex-1 basis-56" bind:value={row.access} aria-label="Access for person {i + 1}">
					<option value="admin">Administrator: sets everything up</option>
					{#if root}
						<option value="view:{root.id}">Can view everything</option>
						<option value="enter:{root.id}">Can enter data everywhere</option>
					{/if}
					{#if others.length}
						<optgroup label="Enters data for">
							{#each others as u (u.id)}<option value="enter:{u.id}">{u.name}</option>{/each}
						</optgroup>
						<optgroup label="Views">
							{#each others as u (u.id)}<option value="view:{u.id}">{u.name}</option>{/each}
						</optgroup>
					{/if}
				</select>
				{#if rows.length > 1}
					<button type="button" class="rounded p-1.5 text-muted hover:bg-bad-wash hover:text-bad-ink" aria-label="Remove row" onclick={() => (rows = rows.filter((r) => r.key !== row.key))}>
						<Icon name="x" size={15} />
					</button>
				{/if}
			</div>
			{#if row.error}<p class="field-error">{row.error}</p>{/if}
		</div>
	{/each}
	<div class="flex flex-wrap items-center gap-3 pt-1">
		<button type="button" class="btn btn-ghost btn-sm -ml-2.5" onclick={() => (rows = [...rows, blank()])}><Icon name="plus" size={14} /> Add another person</button>
		<button class="btn btn-secondary ml-auto" disabled={busy || !rows.some((r) => r.email.trim())}>{busy ? 'Inviting…' : emailing ? 'Send invitations' : 'Create invitation links'}</button>
	</div>
</form>

{#if links.length}
	<section class="mt-6 rounded-xl bg-surface-2 p-4 ring-1 ring-line" aria-live="polite">
		<p class="font-semibold">{allEmailed ? `${plural(links.length, 'invitation')} emailed` : `${plural(links.length, 'invitation link')} ready`}</p>
		<p class="mt-0.5 text-sm text-ink-2">
			{#if allEmailed}
				Each person got an email with their link. You can also send it another way.
			{:else if unverified}
				Once you confirm your email address, invitations are emailed for you. For now, send each person their link.
			{:else}
				Send each person their link.
			{/if}
			It works once, for that email address, for 14 days.
		</p>
		<ul class="mt-3 space-y-2">
			{#each links as entry (entry.link)}
				<li class="flex flex-wrap items-center gap-2">
					<span class="w-48 truncate text-sm font-medium">
						{entry.email}
						<span class="block text-xs font-normal text-ink-2">
							{entry.emailed ? 'Emailed' : entry.problem === 'failed' ? "Couldn't be emailed: send the link" : 'Not emailed'}
						</span>
					</span>
					<input class="input h-8 min-w-0 flex-1 font-mono text-xs" readonly value={entry.link} aria-label="Invitation link for {entry.email}" onfocus={(e) => e.currentTarget.select()} />
					<button type="button" class="btn btn-secondary btn-sm" onclick={() => copy(entry)}>
						<Icon name={entry.copied ? 'check' : 'file'} size={13} /> {entry.copied ? 'Copied' : 'Copy'}
					</button>
				</li>
			{/each}
		</ul>
	</section>
{/if}
