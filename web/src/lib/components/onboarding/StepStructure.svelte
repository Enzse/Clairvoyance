<script lang="ts">
	import { cubicOut } from 'svelte/easing';
	import { prefersReducedMotion } from 'svelte/motion';
	import { fly, slide } from 'svelte/transition';
	import { api, ApiError } from '$lib/api';
	import { plural, pluralize } from '$lib/format';
	import { setMe } from '$lib/session.svelte';
	import { units } from '$lib/units.svelte';
	import FormAlert from '../auth/FormAlert.svelte';
	import Icon, { type IconName } from '../Icon.svelte';
	import OptionCards from '../OptionCards.svelte';

	/**
	 * One location, several locations, or departments. Names go in one per line; a live preview
	 * shows the tree that will be created. Deeper trees (regions with branches) are built later
	 * under Manage › Structure. The kind becomes the organisation's word for them everywhere
	 * ("2 of 3 branches reported").
	 */
	let { onDone }: { onDone: () => void } = $props();

	type Shape = 'single' | 'locations' | 'departments';
	const SHAPES: { value: Shape; label: string; hint: string; icon: IconName; kind: string; example: string }[] = [
		{ value: 'single', label: 'One team or location', hint: 'Everyone reports together. Add structure any time.', icon: 'home', kind: '', example: '' },
		{ value: 'locations', label: 'Several locations', hint: 'Branches, stores, sites or clinics that report the same KPIs.', icon: 'store', kind: 'Branch', example: 'Downtown\nAirport\nMall of Asia' },
		{ value: 'departments', label: 'Departments or teams', hint: 'Each reports the KPIs it owns, like Sales or Support.', icon: 'briefcase', kind: 'Department', example: 'Sales\nOperations\nCustomer support' }
	];

	const root = $derived(units.home());
	const existing = $derived(root ? units.children(root.id) : []);
	const move = $derived(prefersReducedMotion.current ? 0 : 1);

	let shape = $state<Shape>('locations');
	let text = $state('');
	let kind = $state('Branch');
	let error = $state('');
	let busy = $state(false);

	const chosen = $derived(SHAPES.find((s) => s.value === shape)!);
	// A new choice brings its usual word, e.g. Branch or Department
	let lastShape: Shape = 'locations';
	$effect(() => {
		if (shape !== lastShape) {
			lastShape = shape;
			kind = chosen.kind;
		}
	});

	const names = $derived([...new Set(text.split('\n').map((n) => n.trim()).filter(Boolean))]);
	const fresh = $derived(names.filter((n) => !existing.some((u) => u.name.toLowerCase() === n.toLowerCase())));
	const word = $derived(kind.trim().toLowerCase() || 'team');

	async function submit(event: SubmitEvent) {
		event.preventDefault();
		if (shape === 'single' || !names.length || !root) return onDone();
		busy = true;
		error = '';
		try {
			if (kind.trim()) setMe(await api.updateOrganization({ unit_label: kind.trim().toLowerCase() }));
			for (const name of fresh) await api.createUnit({ parent_id: root.id, name, kind: kind.trim() || null });
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
		<OptionCards
			name="shape"
			legend="Which is closest?"
			bind:value={shape}
			options={SHAPES.map((s) => ({ value: s.value, label: s.label, hint: s.hint, icon: s.icon }))}
		/>

		{#if shape !== 'single'}
			<div class="mt-7 grid gap-5 sm:grid-cols-[minmax(0,1fr)_180px]" transition:slide={{ duration: 200 }}>
				<div>
					<label class="field-label" for="unit-names">Their names, one per line</label>
					<textarea id="unit-names" class="input font-sans" rows="6" placeholder={chosen.example} bind:value={text}></textarea>
					<p class="field-hint">You can paste a list from a spreadsheet.</p>
				</div>
				<div>
					<label class="field-label" for="unit-kind">Each one is a</label>
					<input id="unit-kind" class="input" bind:value={kind} placeholder={chosen.kind} />
					<p class="field-hint">Store, Clinic, School… whatever you call them.</p>
				</div>
			</div>
		{/if}

		<div class="mt-5"><FormAlert message={error} /></div>

		<div class="mt-8 flex flex-wrap items-center justify-end gap-3 border-t border-line pt-5">
			{#if shape !== 'single'}<button type="button" class="btn btn-ghost" onclick={onDone}>Skip for now</button>{/if}
			<button class="btn btn-primary" disabled={busy || (shape !== 'single' && !names.length)}>
				{busy ? 'Saving…' : shape === 'single' || !fresh.length ? 'Continue' : `Add ${plural(fresh.length, word)}`}
				<Icon name="arrow-right" size={15} />
			</button>
		</div>
	</div>

	<aside class="panel h-fit p-5" aria-label="Preview">
		<p class="text-xs font-semibold tracking-wide text-muted uppercase">Preview</p>
		<div class="mt-3 flex items-center gap-2.5">
			<span class="grid size-8 place-items-center rounded-lg bg-navy text-navy-ink"><Icon name="building" size={15} /></span>
			<span class="leading-tight">
				<span class="block font-semibold">{root?.name}</span>
				<span class="block text-xs text-muted">{shape === 'single' ? 'reports its own figures' : existing.length + fresh.length ? `adds up ${plural(existing.length + fresh.length, word)}` : `adds up its ${pluralize(word)}`}</span>
			</span>
		</div>
		{#if shape !== 'single'}
			<ul class="mt-2 ml-4 space-y-1.5 border-l border-line pl-3">
				{#each existing as u (u.id)}
					<li class="flex items-center gap-2.5">
						<span class="grid size-7 place-items-center rounded-md bg-surface-2 text-ink-2 ring-1 ring-line"><Icon name={chosen.icon} size={13} /></span>
						<span class="text-sm">{u.name}<span class="ml-1.5 text-xs text-muted">{u.kind}</span></span>
					</li>
				{/each}
				{#each fresh as n (n)}
					<li class="flex items-center gap-2.5" in:fly={{ x: -8 * move, duration: 220, easing: cubicOut }}>
						<span class="grid size-7 place-items-center rounded-md bg-accent-wash text-accent ring-1 ring-chart/30"><Icon name={chosen.icon} size={13} /></span>
						<span class="text-sm">{n}<span class="ml-1.5 text-xs text-muted">{kind}</span></span>
					</li>
				{:else}
					{#if !existing.length}<li class="py-1 text-sm text-muted">Names you type appear here.</li>{/if}
				{/each}
			</ul>
		{/if}
		<p class="mt-5 rounded-lg bg-surface-2 px-3 py-2.5 text-sm text-ink-2">
			{shape === 'single'
				? `Figures are entered for ${root?.name} as a whole.`
				: `Each ${word} enters its own figures, and ${root?.name} adds them up.`}
		</p>
	</aside>
</form>
