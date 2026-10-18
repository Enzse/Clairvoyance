<script lang="ts">
	import { api, ApiError } from '$lib/api';
	import Drawer from '$lib/components/Drawer.svelte';
	import Icon from '$lib/components/Icon.svelte';
	import { plural } from '$lib/format';
	import { toast } from '$lib/toast.svelte';
	import type { Unit } from '$lib/types';
	import { units } from '$lib/units.svelte';

	const root = $derived(units.list.find((u) => u.parent_id === null));
	const kinds = $derived([...new Set(units.list.map((u) => u.kind).filter(Boolean))] as string[]);

	let collapsed = $state(new Set<number>());

	/** Depth-first rows of the tree, skipping collapsed branches. */
	const rows = $derived.by(() => {
		const out: { unit: Unit; depth: number }[] = [];
		const walk = (u: Unit, depth: number) => {
			out.push({ unit: u, depth });
			if (!collapsed.has(u.id)) for (const c of units.children(u.id)) walk(c, depth + 1);
		};
		if (root) walk(root, 0);
		return out;
	});

	function toggle(id: number) {
		const next = new Set(collapsed);
		if (next.has(id)) next.delete(id);
		else next.add(id);
		collapsed = next;
	}

	// One drawer for both adding and editing
	let open = $state(false);
	let editing = $state<Unit | null>(null);
	let form = $state({ name: '', code: '', kind: '', parent_id: 0, is_active: true });
	let error = $state<ApiError | null>(null);
	let busy = $state(false);

	function startAdd(parent: Unit) {
		editing = null;
		const sibling = units.children(parent.id)[0];
		form = { name: '', code: '', kind: sibling?.kind ?? '', parent_id: parent.id, is_active: true };
		error = null;
		open = true;
	}

	function startEdit(unit: Unit) {
		editing = unit;
		form = { name: unit.name, code: unit.code ?? '', kind: unit.kind ?? '', parent_id: unit.parent_id ?? 0, is_active: unit.is_active };
		error = null;
		open = true;
	}

	// A unit can move under any non-leaf-with-data unit outside its own subtree
	const parentChoices = $derived.by(() => {
		if (!editing) return [];
		const own = new Set<number>();
		const collect = (id: number) => { own.add(id); units.children(id).forEach((c) => collect(c.id)); };
		collect(editing.id);
		return rows.map((r) => r.unit).filter((u) => !own.has(u.id) && !u.has_values);
	});

	async function save(event: SubmitEvent) {
		event.preventDefault();
		busy = true;
		error = null;
		const body = { name: form.name, code: form.code || null, kind: form.kind || null };
		try {
			if (editing) {
				await api.updateUnit(editing.id, editing.parent_id === null ? body : { ...body, parent_id: form.parent_id, is_active: form.is_active });
				toast(`Saved ${form.name}`);
			} else {
				await api.createUnit({ ...body, parent_id: form.parent_id });
				toast(`Added ${form.name}`);
			}
			await units.load(true);
			open = false;
		} catch (e) {
			error = e instanceof ApiError ? e : new ApiError(0, 'Could not save');
		} finally {
			busy = false;
		}
	}

	async function remove(unit: Unit) {
		if (!confirm(`Delete ${unit.name}? This can't be undone.`)) return;
		try {
			await api.deleteUnit(unit.id);
			await units.load(true);
			toast(`Deleted ${unit.name}`);
			open = false;
		} catch (e) {
			toast(e instanceof ApiError ? e.message : 'Could not delete', 'error');
		}
	}

	const leafCount = $derived(units.list.filter((u) => u.is_leaf && u.is_active).length);
</script>

<svelte:head><title>Organisation | Clairvoyance</title></svelte:head>

<div class="flex flex-wrap items-end justify-between gap-4">
	<div>
		<h2 class="text-lg font-semibold">Organisation</h2>
		<p class="mt-1 max-w-[70ch] text-ink-2">
			Figures are entered for the units at the bottom of the tree ({plural(leafCount, 'reporting unit')} now). Every unit above
			them shows the combined figures of the units it contains.
		</p>
	</div>
</div>

<div class="panel mt-5 overflow-hidden">
	<table class="w-full text-left">
		<thead class="bg-surface-2 text-sm text-ink-2">
			<tr class="border-b border-line">
				<th scope="col" class="px-5 py-2.5 font-medium">Unit</th>
				<th scope="col" class="hidden px-3 py-2.5 font-medium sm:table-cell">Kind</th>
				<th scope="col" class="hidden px-3 py-2.5 font-medium md:table-cell">Code</th>
				<th scope="col" class="px-3 py-2.5 font-medium">Reports</th>
				<th scope="col" class="px-5 py-2.5"><span class="sr-only">Actions</span></th>
			</tr>
		</thead>
		<tbody>
			{#each rows as { unit, depth } (unit.id)}
				{@const kids = units.children(unit.id)}
				<tr class="group border-b border-line last:border-b-0 hover:bg-surface-2 {unit.is_active ? '' : 'text-muted'}">
					<th scope="row" class="py-2 pr-3 font-normal" style:padding-left="{20 + depth * 22}px">
						<div class="flex items-center gap-1.5">
							{#if kids.length}
								<button class="-ml-1 rounded p-0.5 text-muted hover:text-ink" onclick={() => toggle(unit.id)} aria-expanded={!collapsed.has(unit.id)} aria-label="{collapsed.has(unit.id) ? 'Expand' : 'Collapse'} {unit.name}">
									<Icon name="chevron-down" size={15} class="transition-transform {collapsed.has(unit.id) ? '-rotate-90' : ''}" />
								</button>
							{:else}
								<span class="w-[19px]"></span>
							{/if}
							<span class={depth === 0 ? 'font-semibold' : 'font-medium'}>{unit.name}</span>
							{#if !unit.is_active}<span class="rounded bg-surface-2 px-1.5 text-xs ring-1 ring-line">Inactive</span>{/if}
							{#if collapsed.has(unit.id)}<span class="text-sm text-muted">{plural(kids.length, 'unit')}</span>{/if}
						</div>
					</th>
					<td class="hidden px-3 py-2 text-ink-2 sm:table-cell">{unit.kind ?? ''}</td>
					<td class="hidden px-3 py-2 font-mono text-sm text-ink-2 md:table-cell">{unit.code ?? ''}</td>
					<td class="px-3 py-2 text-sm text-ink-2">{kids.length ? 'Combined' : unit.has_values ? 'Has data' : 'No data yet'}</td>
					<td class="px-5 py-2">
						<div class="flex items-center justify-end gap-1">
							{#if !unit.has_values}
								<button class="btn btn-ghost btn-sm" onclick={() => startAdd(unit)}><Icon name="plus" size={14} /> Add unit</button>
							{/if}
							<button class="btn btn-ghost btn-sm" onclick={() => startEdit(unit)}>Edit</button>
						</div>
					</td>
				</tr>
			{/each}
		</tbody>
	</table>
</div>

<Drawer bind:open title={editing ? `Edit ${editing.name}` : `Add a unit to ${units.get(form.parent_id)?.name ?? ''}`}>
	<form id="unit-form" class="space-y-5" onsubmit={save}>
		<div>
			<label class="field-label" for="u-name">Name</label>
			<input id="u-name" class="input" required bind:value={form.name} aria-invalid={!!error?.field('name')} />
			{#if error?.field('name')}<p class="field-error">Name {error.field('name')}</p>{/if}
		</div>
		<div class="grid gap-4 sm:grid-cols-2">
			<div>
				<label class="field-label" for="u-kind">Kind</label>
				<input id="u-kind" class="input" list="unit-kinds" placeholder="Region, School, Branch…" bind:value={form.kind} />
				<datalist id="unit-kinds">{#each kinds as k (k)}<option value={k}></option>{/each}</datalist>
			</div>
			<div>
				<label class="field-label" for="u-code">Code <span class="font-normal text-muted">(optional)</span></label>
				<input id="u-code" class="input font-mono" bind:value={form.code} aria-invalid={!!error?.field('code')} />
				<p class={error?.field('code') ? 'field-error' : 'field-hint'}>{error?.field('code') ? `Code ${error.field('code')}` : 'Used to match rows when importing.'}</p>
			</div>
		</div>
		{#if editing && editing.parent_id !== null}
			<div>
				<label class="field-label" for="u-parent">Inside</label>
				<select id="u-parent" class="input" bind:value={form.parent_id} aria-invalid={!!error?.field('parent_id')}>
					{#each parentChoices as u (u.id)}<option value={u.id}>{units.pathLabel(u.id) ? `${units.pathLabel(u.id)} › ` : ''}{u.name}</option>{/each}
				</select>
				{#if error?.field('parent_id')}<p class="field-error">{error.field('parent_id')}</p>{/if}
			</div>
			<label class="flex items-start gap-2.5">
				<input type="checkbox" class="mt-0.5 size-4 accent-[var(--accent)]" bind:checked={form.is_active} />
				<span>
					<span class="font-medium">Active</span>
					<span class="block text-sm text-ink-2">Inactive units and everything inside them are left out of scorecards and data entry. Their data is kept.</span>
				</span>
			</label>
		{/if}
		{#if error && !error.field('name') && !error.field('code') && !error.field('parent_id')}
			<p class="text-sm text-bad-ink" role="alert">{Object.values(error.details)[0] ?? error.message}</p>
		{/if}
	</form>
	{#snippet footer()}
		{#if editing && editing.parent_id !== null && editing.is_leaf && !editing.has_values}
			<button class="btn btn-danger mr-auto" type="button" onclick={() => remove(editing!)}><Icon name="trash" size={14} /> Delete</button>
		{/if}
		<button class="btn btn-secondary" type="button" onclick={() => (open = false)}>Cancel</button>
		<button class="btn btn-primary" form="unit-form" disabled={busy}>{busy ? 'Saving…' : editing ? 'Save changes' : 'Add unit'}</button>
	{/snippet}
</Drawer>
