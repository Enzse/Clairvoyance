<script lang="ts">
	import { slide } from 'svelte/transition';
	import { goto } from '$app/navigation';
	import { api } from '$lib/api';
	import { problem } from '$lib/problem';
	import { session, setMe } from '$lib/session.svelte';
	import { toast } from '$lib/toast.svelte';
	import type { OrganizationSummary } from '$lib/types';
	import Icon from '../Icon.svelte';
	import SectionHeading from '../SectionHeading.svelte';

	/** The organisations you belong to, with leaving confirmed in place. */
	let asking = $state<number | null>(null);
	let busy = $state(false);

	/** Leaving works on the organisation you're in, so switch to it first if needed. */
	async function leave(o: OrganizationSummary) {
		busy = true;
		try {
			if (session.user?.organization?.id !== o.id) setMe(await api.selectOrganization(o.id));
			const me = await api.leaveOrganization();
			setMe(me);
			toast(`You've left ${o.name}`);
			asking = null;
			if (!me.organization) goto('/onboarding');
		} catch (e) {
			toast(problem(e, 'Could not leave'), 'error');
		} finally {
			busy = false;
		}
	}
</script>

<section class="panel p-5 sm:p-6">
	<SectionHeading icon="building" title="Your organisations" lead="Where you can see or report figures." />
	<ul class="mt-4 space-y-2 sm:pl-[3.375rem]">
		{#each session.user?.organizations ?? [] as o (o.id)}
			{@const current = session.user?.organization?.id === o.id}
			<li class="rounded-xl ring-1 ring-line">
				<div class="flex items-center gap-3 px-3.5 py-2.5">
					<span class="grid size-8 shrink-0 place-items-center rounded-lg bg-navy text-navy-ink"><Icon name="building" size={14} /></span>
					<span class="min-w-0 flex-1">
						<span class="block truncate font-medium">{o.name}</span>
						<span class="flex items-center gap-1 text-xs text-ink-2">
							<Icon name={o.role === 'admin' ? 'shield' : 'user'} size={11} />{o.role === 'admin' ? 'Administrator' : 'Member'}{current ? ' · open now' : ''}
						</span>
					</span>
					{#if asking !== o.id}
						<button class="btn btn-ghost btn-sm" onclick={() => (asking = o.id)}>Leave</button>
					{/if}
				</div>
				{#if asking === o.id}
					<div class="border-t border-line bg-bad-wash px-3.5 py-3" transition:slide={{ duration: 160 }}>
						<p class="text-sm">Leave <strong class="font-medium">{o.name}</strong>? You lose access until someone invites you again.</p>
						<div class="mt-2.5 flex gap-2">
							<button class="btn btn-danger btn-sm" disabled={busy} onclick={() => leave(o)}>{busy ? 'Leaving…' : 'Leave'}</button>
							<button class="btn btn-ghost btn-sm" onclick={() => (asking = null)}>Stay</button>
						</div>
					</div>
				{/if}
			</li>
		{/each}
	</ul>
	<a class="btn btn-ghost btn-sm mt-3 sm:ml-[2.75rem]" href="/onboarding?new=1"><Icon name="plus" size={14} /> Create a new organisation</a>
</section>
