<script lang="ts">
	import { onMount } from 'svelte';
	import { api, ApiError } from '$lib/api';
	import Drawer from '$lib/components/Drawer.svelte';
	import Icon from '$lib/components/Icon.svelte';
	import { joinNames } from '$lib/format';
	import { session } from '$lib/session.svelte';
	import { toast } from '$lib/toast.svelte';
	import type { Membership, User } from '$lib/types';
	import { units } from '$lib/units.svelte';

	let users = $state<User[]>([]);
	let loaded = $state(false);

	onMount(load);
	async function load() {
		users = await api.users();
		loaded = true;
	}

	function accessSummary(user: User): string {
		if (user.role === 'admin') return 'Everything';
		const list = user.memberships ?? [];
		if (!list.length) return 'No access yet';
		const names = (role: Membership['role']) => list.filter((m) => m.role === role).map((m) => units.get(m.unit_id)?.name ?? 'Unknown unit');
		return [names('contributor').length && `Enters data for ${joinNames(names('contributor'))}`, names('viewer').length && `Views ${joinNames(names('viewer'))}`]
			.filter(Boolean)
			.join('. ');
	}

	// ---- Drawer
	let open = $state(false);
	let editing = $state<User | null>(null);
	let form = $state({ email: '', name: '', password: '', role: 'member' as User['role'] });
	let access = $state<(Membership & { key: number })[]>([]);
	let error = $state<ApiError | null>(null);
	let busy = $state(false);
	let keySeq = 0;

	function startNew() {
		editing = null;
		form = { email: '', name: '', password: '', role: 'member' };
		access = [];
		error = null;
		open = true;
	}

	function startEdit(user: User) {
		editing = user;
		form = { email: user.email, name: user.name ?? '', password: '', role: user.role };
		access = (user.memberships ?? []).map((m) => ({ ...m, key: keySeq++ }));
		error = null;
		open = true;
	}

	const unitOptions = $derived(units.list.filter((u) => u.is_active));
	const isSelf = $derived(editing?.id === session.user?.id);

	async function save(event: SubmitEvent) {
		event.preventDefault();
		busy = true;
		error = null;
		try {
			let user: User;
			if (editing) {
				user = await api.updateUser(editing.id, { name: form.name || null, ...(isSelf ? {} : { role: form.role }), ...(form.password ? { password: form.password } : {}) });
			} else {
				user = await api.createUser({ email: form.email, password: form.password, name: form.name || undefined, role: form.role });
			}
			if (form.role === 'member') await api.saveMemberships(user.id, access.map(({ unit_id, role }) => ({ unit_id, role })));
			toast(editing ? `Saved ${user.email}` : `Added ${user.email}`);
			open = false;
			await load();
		} catch (e) {
			error = e instanceof ApiError ? e : new ApiError(0, 'Could not save');
		} finally {
			busy = false;
		}
	}

	async function remove() {
		if (!editing || !confirm(`Delete the account for ${editing.email}? Their entered figures are kept.`)) return;
		try {
			await api.deleteUser(editing.id);
			toast(`Deleted ${editing.email}`);
			open = false;
			await load();
		} catch (e) {
			toast(e instanceof ApiError ? e.message : 'Could not delete', 'error');
		}
	}
</script>

<svelte:head><title>People | Clairvoyance</title></svelte:head>

<div class="flex flex-wrap items-end justify-between gap-4">
	<div>
		<h2 class="text-lg font-semibold">People</h2>
		<p class="mt-1 max-w-[70ch] text-ink-2">Access to a unit includes every unit inside it. Contributors can also enter and import figures there.</p>
	</div>
	<button class="btn btn-primary" onclick={startNew}><Icon name="plus" size={15} /> Add a person</button>
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
				{#each users as user (user.id)}
					<tr class="border-b border-line last:border-b-0 hover:bg-surface-2">
						<th scope="row" class="px-5 py-3 font-normal">
							<span class="font-semibold">{user.name || user.email}</span>
							{#if user.id === session.user?.id}<span class="ml-1.5 text-sm text-muted">(you)</span>{/if}
							{#if user.name}<span class="block text-sm text-ink-2">{user.email}</span>{/if}
						</th>
						<td class="px-3 py-3">{user.role === 'admin' ? 'Administrator' : 'Member'}</td>
						<td class="px-3 py-3 text-sm {accessSummary(user) === 'No access yet' ? 'text-warn-ink' : 'text-ink-2'}">{accessSummary(user)}</td>
						<td class="px-5 py-3 text-right"><button class="btn btn-ghost btn-sm" onclick={() => startEdit(user)}>Edit</button></td>
					</tr>
				{/each}
			</tbody>
		</table>
	</div>
{/if}

<Drawer bind:open title={editing ? (editing.name || editing.email) : 'Add a person'}>
	<form id="user-form" class="space-y-5" onsubmit={save}>
		{#if !editing}
			<div>
				<label class="field-label" for="p-email">Email</label>
				<input id="p-email" class="input" type="email" required bind:value={form.email} aria-invalid={!!error?.field('email')} />
				{#if error?.field('email')}<p class="field-error">Email {error.field('email')}</p>{/if}
			</div>
		{/if}
		<div>
			<label class="field-label" for="p-name">Name <span class="font-normal text-muted">(optional)</span></label>
			<input id="p-name" class="input" bind:value={form.name} />
		</div>
		<div>
			<label class="field-label" for="p-password">{editing ? 'New password' : 'Password'} {#if editing}<span class="font-normal text-muted">(leave empty to keep)</span>{/if}</label>
			<input id="p-password" class="input" type="password" autocomplete="new-password" minlength="8" required={!editing} bind:value={form.password} aria-invalid={!!error?.field('password')} />
			<p class={error?.field('password') ? 'field-error' : 'field-hint'}>{error?.field('password') ? `Password ${error.field('password')}` : 'At least 8 characters. Share it with them privately.'}</p>
		</div>
		<fieldset disabled={isSelf}>
			<legend class="field-label">Role</legend>
			<div class="flex gap-2">
				{#each [['member', 'Member'], ['admin', 'Administrator']] as [value, text] (value)}
					<label class="flex flex-1 cursor-pointer items-center gap-2.5 rounded-lg px-3.5 py-2.5 ring-1 {form.role === value ? 'bg-accent-wash ring-chart' : 'ring-line-strong'}">
						<input type="radio" name="role" {value} bind:group={form.role} class="accent-[var(--accent)]" /> {text}
					</label>
				{/each}
			</div>
			<p class={error?.field('role') ? 'field-error' : 'field-hint'}>
				{error?.field('role') ? `This is ${error.field('role')}` : isSelf ? "You can't change your own role." : 'Administrators can see and change everything.'}
			</p>
		</fieldset>

		{#if form.role === 'member'}
			<section aria-labelledby="access-heading">
				<h3 id="access-heading" class="field-label">Access</h3>
				{#each access as m (m.key)}
					<div class="mt-2 flex items-center gap-2">
						<select class="input min-w-0 flex-1" bind:value={m.unit_id} aria-label="Unit">
							{#each unitOptions as u (u.id)}<option value={u.id}>{units.pathLabel(u.id) ? `${units.pathLabel(u.id)} › ` : ''}{u.name}</option>{/each}
						</select>
						<select class="input w-36" bind:value={m.role} aria-label="Access level">
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
				{#if error && Object.keys(error.details).some((k) => k.startsWith('memberships'))}
					<p class="field-error">{Object.values(error.details).join(' ')}</p>
				{/if}
			</section>
		{/if}

		{#if error && !Object.keys(error.details).length}<p class="text-sm text-bad-ink" role="alert">{error.message}</p>{/if}
	</form>
	{#snippet footer()}
		{#if editing && !isSelf}
			<button class="btn btn-danger mr-auto" type="button" onclick={remove}><Icon name="trash" size={14} /> Delete</button>
		{/if}
		<button class="btn btn-secondary" type="button" onclick={() => (open = false)}>Cancel</button>
		<button class="btn btn-primary" form="user-form" disabled={busy}>{busy ? 'Saving…' : editing ? 'Save changes' : 'Add person'}</button>
	{/snippet}
</Drawer>
