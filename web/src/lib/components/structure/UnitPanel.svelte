<script lang="ts">
	import { api } from '$lib/api';
	import { joinNames, plural } from '$lib/format';
	import { wordFor } from '$lib/terms.svelte';
	import { toast } from '$lib/toast.svelte';
	import type { Unit } from '$lib/types';
	import { units } from '$lib/units.svelte';
	import { slide } from 'svelte/transition';
	import Icon from '../Icon.svelte';
	import Tooltip from '../Tooltip.svelte';
	import IconPicker from './IconPicker.svelte';
	import InlineEdit from '../InlineEdit.svelte';
	import { problem, saveUnit } from './save';
	import UnitAccess from './UnitAccess.svelte';

	/**
	 * Everything about the selected unit. Each change saves as it's made: name and kind in
	 * place, moving, a code for spreadsheet imports, switching off, who has access, gathering
	 * others in, deleting.
	 */
	let {
		unit,
		gatherFirst = false,
		onClose,
		onDeleted
	}: { unit: Unit; gatherFirst?: boolean; onClose: () => void; onDeleted: (parentId: number | null) => void } = $props();

	const isRoot = $derived(unit.parent_id === null);
	const kids = $derived(units.children(unit.id));
	const reporting = $derived(units.reporting(unit.id));
	const isGroup = $derived(isRoot || kids.length > 0 || unit.kind?.toLowerCase() === 'group');

	let hasCode = $state(false);
	let code = $state('');
	let gathering = $state(false);
	let picked = $state<number[]>([]);
	let busy = $state(false);

	// A different unit selected: start from its saved values
	$effect(() => {
		void unit.id;
		hasCode = !!unit.code;
		code = unit.code ?? '';
		gathering = gatherFirst;
		picked = [];
	});

	// Somewhere it can move to: anywhere outside itself that doesn't hold figures of its own
	const destinations = $derived.by(() => {
		const own = new Set(units.subtree(unit.id).map((u) => u.id));
		return units.list.filter((u) => !own.has(u.id) && !u.has_values);
	});
	// Others that could be gathered into this group
	const candidates = $derived.by(() => {
		if (unit.has_values || !isGroup) return [];
		const above = new Set(units.path(unit.id).map((u) => u.id));
		return units.list.filter((u) => u.parent_id !== null && u.parent_id !== unit.id && !above.has(u.id));
	});
	const where = (u: Unit) => units.pathLabel(u.id) || units.list.find((x) => x.parent_id === null)?.name || '';
	const label = (u: Unit) => (units.pathLabel(u.id) ? `${units.pathLabel(u.id)} › ${u.name}` : u.name);

	async function save(fields: Parameters<typeof saveUnit>[1], done?: string) {
		const current = unit;
		busy = true;
		try {
			await saveUnit(current, fields);
			if (done) toast(done);
		} catch (e) {
			toast(problem(e), 'error');
			code = current.code ?? '';
			hasCode = !!current.code;
		} finally {
			busy = false;
		}
	}

	function toggleCode() {
		hasCode = !hasCode;
		if (!hasCode && unit.code) save({ code: null }, `Removed the code from ${unit.name}`);
	}

	async function gather() {
		// Copied first: once moved, their checkboxes disappear and take their values with them
		const ids = [...picked];
		const names = ids.map((id) => units.get(id)?.name ?? '');
		// Also the target: the panel may be closed while the moves are running
		const target = { id: unit.id, name: unit.name };
		busy = true;
		try {
			for (const id of ids) await api.updateUnit(id, { parent_id: target.id });
			toast(`Moved ${joinNames(names)} into ${target.name}`);
			gathering = false;
			picked = [];
		} catch (e) {
			toast(problem(e, 'Could not move'), 'error');
		} finally {
			await units.load(true);
			busy = false;
		}
	}

	async function remove() {
		if (!confirm(`Delete ${unit.name}? This can't be undone.`)) return;
		const { id, name, parent_id: parent } = unit;
		try {
			await api.deleteUnit(id);
			await units.load(true);
			toast(`Deleted ${name}`);
			onDeleted(parent);
		} catch (e) {
			toast(problem(e, 'Could not delete'), 'error');
		}
	}
</script>

<div class="flex h-full flex-col">
	<header class="flex items-start gap-3 border-b border-line px-5 py-4">
		<IconPicker {unit} isGroup={kids.length > 0} size="lg" />
		<div class="min-w-0 flex-1">
			<p class="text-xs font-semibold tracking-wide text-muted uppercase">{isRoot ? 'Organisation' : kids.length ? 'Group of units' : 'Unit'}</p>
			<h2 class="text-lg font-semibold tracking-[-0.01em]">
				<InlineEdit value={unit.name} label="Name" tip="Rename" onSave={(name) => saveUnit(unit, { name })} inputClass="w-full" />
			</h2>
			<p class="truncate text-sm text-ink-2">
				{#if isRoot}
					Your organisation{reporting.length ? ` · ${plural(reporting.length, wordFor(reporting, false), wordFor(reporting))}` : ''}
				{:else if kids.length}
					Adds up {plural(reporting.length, wordFor(reporting, false), wordFor(reporting))}
				{:else}
					{unit.has_values ? 'Reports its own figures' : 'No figures yet'}
				{/if}
			</p>
		</div>
		<Tooltip text="Close" side="bottom">
			<button class="rounded-md p-1.5 text-muted hover:bg-surface-2 hover:text-ink" onclick={onClose} aria-label="Close">
				<Icon name="x" size={18} />
			</button>
		</Tooltip>
	</header>

	<div class="flex-1 space-y-5 overflow-y-auto px-5 py-5">
		<dl class="space-y-4">
			{#if !isRoot}
				<div class="flex items-center justify-between gap-4">
					<dt class="text-sm text-ink-2">Term <span class="block text-xs text-muted">What you call it, e.g. Branch or Region</span></dt>
					<dd class="min-w-0 flex-1 text-right text-sm font-medium">
						<InlineEdit value={unit.kind} label="Term" tip="Change term" placeholder="Add a term" optional onSave={(kind) => saveUnit(unit, { kind: kind || null })} />
					</dd>
				</div>
				<div>
					<dt><label class="text-sm text-ink-2" for="u-parent">Inside</label></dt>
					<dd class="mt-1.5">
						<select
							id="u-parent"
							class="input"
							value={unit.parent_id}
							disabled={busy}
							onchange={(e) => {
								const to = units.get(Number(e.currentTarget.value));
								save({ parent_id: Number(e.currentTarget.value) }, `Moved ${unit.name} into ${to?.name}`);
							}}
						>
							{#each destinations as u (u.id)}<option value={u.id}>{label(u)}</option>{/each}
						</select>
					</dd>
				</div>
			{/if}
		</dl>

		<div>
			<label class="flex cursor-pointer items-center gap-2.5 text-sm">
				<input type="checkbox" class="size-4 accent-[var(--accent)]" checked={hasCode} onchange={toggleCode} disabled={busy} />
				Has a code for spreadsheet imports
			</label>
			{#if hasCode}
				<div class="mt-2 flex gap-2 pl-6" transition:slide={{ duration: 160 }}>
					<input
						class="input h-9 font-mono"
						aria-label="Code"
						placeholder="e.g. BR-001"
						bind:value={code}
						onkeydown={(e) => e.key === 'Enter' && (e.preventDefault(), e.currentTarget.blur())}
						onblur={() => code.trim() && code.trim() !== (unit.code ?? '') && save({ code: code.trim() }, `Saved the code for ${unit.name}`)}
					/>
				</div>
			{/if}
		</div>

		{#if !isRoot}
			<label class="flex cursor-pointer items-start justify-between gap-4 rounded-lg bg-surface-2 px-3.5 py-3 ring-1 ring-line">
				<span>
					<span class="block text-sm font-medium">{unit.is_active ? 'Switched on' : 'Switched off'}</span>
					<span class="block text-xs text-ink-2">Switched off, it's left out of scorecards and data entry. Figures are kept.</span>
				</span>
				<input
					type="checkbox"
					role="switch"
					class="peer sr-only"
					checked={unit.is_active}
					disabled={busy}
					onchange={(e) => save({ is_active: e.currentTarget.checked }, `${unit.name} is switched ${e.currentTarget.checked ? 'on' : 'off'}`)}
				/>
				<span class="relative mt-0.5 h-5 w-9 shrink-0 rounded-full bg-line-strong transition-colors peer-checked:bg-chart peer-focus-visible:ring-2 peer-focus-visible:ring-chart after:absolute after:top-0.5 after:left-0.5 after:size-4 after:rounded-full after:bg-white after:transition-transform peer-checked:after:translate-x-4" aria-hidden="true"></span>
			</label>
		{/if}

		<UnitAccess {unit} />

		{#if candidates.length}
			<section class="border-t border-line pt-5">
				{#if !gathering}
					<button class="btn btn-secondary btn-sm" onclick={() => (gathering = true)}><Icon name="move" size={14} /> Move others into {unit.name}</button>
				{:else}
					<div transition:slide={{ duration: 200 }}>
						<p class="text-sm font-medium">Move into {unit.name}</p>
						<ul class="mt-2 max-h-64 space-y-0.5 overflow-y-auto rounded-lg p-1 ring-1 ring-line">
							{#each candidates as u (u.id)}
								<li>
									<label class="flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 hover:bg-surface-2">
										<input type="checkbox" class="size-4 accent-[var(--accent)]" bind:group={picked} value={u.id} />
										<span class="min-w-0 truncate text-sm">{u.name}</span>
										<span class="ml-auto shrink-0 truncate text-xs text-muted">{where(u)}</span>
									</label>
								</li>
							{/each}
						</ul>
						<div class="mt-3 flex gap-2">
							<button class="btn btn-primary btn-sm" disabled={busy || !picked.length} onclick={gather}>{picked.length ? `Move ${picked.length} here` : 'Pick some first'}</button>
							<button class="btn btn-ghost btn-sm" onclick={() => { gathering = false; picked = []; }}>Cancel</button>
						</div>
					</div>
				{/if}
			</section>
		{/if}
	</div>

	{#if !isRoot}
		<footer class="border-t border-line px-5 py-3.5">
			{#if kids.length}
				<p class="text-xs text-muted">To delete {unit.name}, move or delete what's inside it first.</p>
			{:else if unit.has_values}
				<p class="text-xs text-muted">{unit.name} has figures, so it can't be deleted. Switch it off to hide it and keep its history.</p>
			{:else}
				<button class="btn btn-ghost btn-sm -ml-2.5 text-bad-ink hover:bg-bad-wash" onclick={remove}><Icon name="trash" size={14} /> Delete {unit.name}</button>
			{/if}
		</footer>
	{/if}
</div>
