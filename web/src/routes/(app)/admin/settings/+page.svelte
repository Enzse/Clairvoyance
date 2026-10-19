<script lang="ts">
	import { cubicOut } from 'svelte/easing';
	import { prefersReducedMotion } from 'svelte/motion';
	import { fly } from 'svelte/transition';
	import { goto } from '$app/navigation';
	import { api } from '$lib/api';
	import Icon from '$lib/components/Icon.svelte';
	import InlineEdit from '$lib/components/InlineEdit.svelte';
	import SectionHeading from '$lib/components/SectionHeading.svelte';
	import DeleteOrganization from '$lib/components/settings/DeleteOrganization.svelte';
	import WordPicker from '$lib/components/settings/WordPicker.svelte';
	import { problem } from '$lib/problem';
	import { session, setMe } from '$lib/session.svelte';
	import { toast } from '$lib/toast.svelte';
	import { units } from '$lib/units.svelte';

	/** The organisation's name and wording, the getting-started guide, and deleting it. */
	const current = $derived(session.user?.organization?.name ?? '');
	let deleting = $state(false);
	const move = $derived(prefersReducedMotion.current ? 0 : 1);

	async function rename(name: string) {
		setMe(await api.updateOrganization({ name }));
		await units.load(true); // the top of the structure carries the organisation's name
		toast('Organisation renamed');
	}

	async function showGuide() {
		try {
			await api.dismissSetup(false);
			goto('/');
		} catch (e) {
			toast(problem(e, 'Could not show the guide'), 'error');
		}
	}
</script>

<svelte:head><title>Settings | Clairvoyance</title></svelte:head>



<div class="mx-auto max-w-3xl space-y-4">
	<section class="panel p-5 sm:p-6" in:fly={{ y: 10 * move, duration: 260, easing: cubicOut }}>
		<SectionHeading icon="building" title="Organisation name" lead="Click the name to change it. It shows in the top bar, on the scorecard and in invitation emails." />
		<p class="mt-4 pl-[3.375rem] text-xl font-semibold tracking-[-0.015em]">
			<InlineEdit value={current} label="Organisation name" tip="Rename" onSave={rename} inputClass="w-full" />
		</p>
	</section>

	<section class="panel p-5 sm:p-6" in:fly={{ y: 10 * move, duration: 260, delay: 60 * move, easing: cubicOut }}>
		<SectionHeading icon="pin" title="Your word for units" lead={`Units are the parts of ${current} that report figures, like branches, schools or departments. Choose the word your people use, and the scorecard, data entry and the rest of the app will use it.`} />
		<div class="mt-4 sm:pl-[3.375rem]"><WordPicker /></div>
	</section>

	<section class="panel flex flex-wrap items-center gap-4 p-5 sm:p-6" in:fly={{ y: 10 * move, duration: 260, delay: 120 * move, easing: cubicOut }}>
		<div class="min-w-0 flex-1"><SectionHeading icon="flag" title="Getting started guide" lead="The step-by-step guide on the scorecard. Bring it back if you hid it, or to finish skipped steps." /></div>
		<button class="btn btn-secondary" onclick={showGuide}>Show it again <Icon name="arrow-right" size={15} /></button>
	</section>

	<section class="flex flex-wrap items-center gap-4 rounded-xl p-5 ring-1 ring-bad/30 sm:p-6" in:fly={{ y: 10 * move, duration: 260, delay: 180 * move, easing: cubicOut }}>
		<div class="min-w-0 flex-1"><SectionHeading icon="trash" title={`Delete ${current}`} lead="Removes it and all its figures for everyone. People keep their accounts." tone="bg-bad-wash text-bad-ink" /></div>
		<button class="btn btn-danger" onclick={() => (deleting = true)}>Delete…</button>
	</section>
</div>

<DeleteOrganization bind:open={deleting} />
