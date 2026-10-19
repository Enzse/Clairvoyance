<script lang="ts">
	import { api, ApiError } from '$lib/api';
	import Icon from '$lib/components/Icon.svelte';
	import { plural } from '$lib/format';
	import { units } from '$lib/units.svelte';

	/**
	 * One location, several locations, or departments. Names go in one per line; a live preview
	 * shows the tree that will be created. Deeper trees (regions with branches) are built later
	 * under Admin › Organisation.
	 */
	let { onDone }: { onDone: () => void } = $props();

	type Shape = 'single' | 'locations' | 'departments';
	const SHAPES: { value: Shape; label: string; hint: string; kind: string; example: string }[] = [
		{ value: 'single', label: 'One team or location', hint: 'Everyone reports together. You can add structure any time.', kind: '', example: '' },
		{ value: 'locations', label: 'Several locations', hint: 'Branches, stores, sites or clinics that report the same KPIs.', kind: 'Branch', example: 'Downtown\nAirport\nMall of Asia' },
		{ value: 'departments', label: 'Departments or teams', hint: 'Each reports the KPIs it owns, like Sales or Support.', kind: 'Department', example: 'Sales\nOperations\nCustomer support' }
	];

	const root = $derived(units.home());
	const existing = $derived(root ? units.children(root.id) : []);

	let shape = $state<Shape>('locations');
	let text = $state('');
	let kind = $state('Branch');
	let error = $state('');
	let busy = $state(false);

	const names = $derived([...new Set(text.split('\n').map((n) => n.trim()).filter(Boolean))]);
	const chosen = $derived(SHAPES.find((s) => s.value === shape)!);

	function pick(value: Shape) {
		shape = value;
		kind = SHAPES.find((s) => s.value === value)!.kind;
	}

	async function submit(event: SubmitEvent) {
		event.preventDefault();
		if (shape === 'single' || !names.length || !root) return onDone();
		busy = true;
		error = '';
		try {
			for (const name of names.filter((n) => !existing.some((u) => u.name.toLowerCase() === n.toLowerCase()))) {
				await api.createUnit({ parent_id: root.id, name, kind: kind.trim() || null });
			}
			await units.load(true);
			onDone();
		} catch (e) {
			error = e instanceof ApiError ? Object.values(e.details)[0]?.toString() ?? e.message : 'Could not save';
			await units.load(true);
		} finally {
			busy = false;
		}
	}
</script>

<form class="-mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]" onsubmit={submit}>
	<div class="panel p-6 sm:p-8">
		<fieldset>
			<legend class="sr-only">Structure</legend>
			<div class="grid gap-3 sm:grid-cols-3">
				{#each SHAPES as s (s.value)}
					<label class="cursor-pointer rounded-xl p-4 ring-1 {shape === s.value ? 'bg-accent-wash ring-2 ring-chart' : 'ring-line-strong hover:bg-surface-2'}">
						<input type="radio" name="shape" class="sr-only" value={s.value} checked={shape === s.value} onchange={() => pick(s.value)} />
						<span class="block font-semibold">{s.label}</span>
						<span class="mt-1 block text-sm text-ink-2">{s.hint}</span>
					</label>
				{/each}
			</div>
		</fieldset>

		{#if shape !== 'single'}
			<div class="mt-7 grid gap-5 sm:grid-cols-[minmax(0,1fr)_180px]">
				<div>
					<label class="field-label" for="unit-names">Their names, one per line</label>
					<textarea id="unit-names" class="input font-sans" rows="6" placeholder={chosen.example} bind:value={text}></textarea>
				</div>
				<div>
					<label class="field-label" for="unit-kind">Each one is a</label>
					<input id="unit-kind" class="input" bind:value={kind} placeholder={chosen.kind} />
					<p class="field-hint">Store, Clinic, Team… whatever you call them.</p>
				</div>
			</div>
		{/if}

		{#if error}<p class="mt-4 text-sm text-bad-ink" role="alert">{error}</p>{/if}

		<div class="mt-8 flex flex-wrap items-center justify-end gap-3 border-t border-line pt-5">
			{#if shape !== 'single'}<button type="button" class="btn btn-ghost" onclick={onDone}>Skip for now</button>{/if}
			<button class="btn btn-primary" disabled={busy || (shape !== 'single' && !names.length)}>
				{busy ? 'Saving…' : shape === 'single' ? 'Continue' : `Add ${plural(names.length, kind.trim().toLowerCase() || 'unit')}`}
			</button>
		</div>
	</div>

	<aside class="panel h-fit p-6" aria-label="Preview">
		<p class="text-sm text-ink-2">Your organisation</p>
		<ul class="mt-3 text-base">
			<li class="flex items-center gap-2 font-semibold"><Icon name="layers" size={16} class="text-muted" />{root?.name}</li>
			{#each existing as u (u.id)}
				<li class="mt-2 ml-2.5 flex items-center gap-2 border-l border-line pl-4">{u.name}<span class="text-sm text-muted">{u.kind}</span></li>
			{/each}
			{#if shape !== 'single'}
				{#each names.filter((n) => !existing.some((u) => u.name.toLowerCase() === n.toLowerCase())) as n (n)}
					<li class="mt-2 ml-2.5 flex items-center gap-2 border-l border-chart pl-4">{n}<span class="text-sm text-muted">{kind}</span></li>
				{/each}
			{/if}
		</ul>
		<p class="mt-5 text-sm text-ink-2">
			{shape === 'single'
				? `Figures are entered for ${root?.name} as a whole.`
				: `Figures are entered by each ${kind.trim().toLowerCase() || 'unit'} and added up for ${root?.name}.`}
		</p>
	</aside>
</form>
