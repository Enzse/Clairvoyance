<script lang="ts">
	import { session } from '$lib/session.svelte';
	import { term } from '$lib/terms.svelte';
	import Icon, { type IconName } from '../Icon.svelte';
	import Tour, { type TourStep } from '../Tour.svelte';

	/** The introduction to Manage › People: roles, access, and inviting. */
	let { open = $bindable(false), onClose }: { open?: boolean; onClose?: () => void } = $props();

	const org = $derived(session.user?.organization?.name ?? 'your organisation');

	const steps: TourStep[] = $derived([
		{
			title: 'Everyone who uses the scorecard',
			body: `People here belong to ${org}. Each one only sees what you allow, so a ${term.one} manager can see their ${term.one} without seeing everyone else's.`,
			art: peopleArt
		},
		{
			title: 'Administrators and members',
			body: 'Administrators see and change everything: structure, KPIs and people. Members only see and enter data where you give them access.',
			art: rolesArt
		},
		{
			title: 'Views or enters data',
			body: `Give a member access to a ${term.one} or a group. Access to a group includes everything inside it. "Views" can read the scorecard; "Enters data" can also add figures and upload spreadsheets.`,
			art: accessArt
		},
		{
			title: 'Invite by email',
			body: '"Invite people" sends each person a link that only works for their email address. Until they join, they wait at the bottom of the list, where you can resend or cancel.',
			art: inviteArt
		}
	]);
</script>

{#snippet person(initials: string, tone: string, name: string, sub: string, extra = '')}
	<div class="flex items-center gap-2.5 rounded-lg bg-surface px-2.5 py-2 shadow-sm ring-1 ring-line {extra}">
		<span class="grid size-8 place-items-center rounded-full text-xs font-semibold {tone}">{initials}</span>
		<span class="leading-tight">
			<span class="block text-sm font-medium">{name}</span>
			<span class="block text-[11px] text-muted">{sub}</span>
		</span>
	</div>
{/snippet}

{#snippet chip(icon: IconName, label: string, on = false)}
	<span class="flex items-center gap-1 rounded-md px-2 py-1 text-xs {on ? 'bg-surface font-medium shadow-sm' : 'text-ink-2'}"><Icon name={icon} size={12} class={on ? 'text-accent' : ''} />{label}</span>
{/snippet}

{#snippet peopleArt()}
	<div class="w-64 space-y-1.5">
		{@render person('AR', 'bg-accent-wash text-accent', 'Ana Reyes', 'Can see and change everything')}
		{@render person('BC', 'bg-good-wash text-good-ink', 'Ben Cruz', 'Enters data for Downtown', 'ml-4')}
		{@render person('LS', 'bg-warn-wash text-warn-ink', 'Lia Santos', 'Views North region', 'ml-4')}
	</div>
{/snippet}

{#snippet rolesArt()}
	<div class="grid w-72 grid-cols-2 gap-2">
		{#each [['shield', 'Administrator', 'Everything', true], ['user', 'Member', 'Only where allowed', false]] as [icon, label, hint, on] (label)}
			<div class="rounded-xl bg-surface px-3 py-2.5 shadow-sm {on ? 'ring-2 ring-chart' : 'ring-1 ring-line'}">
				<span class="flex items-center gap-1.5 text-sm font-medium"><Icon name={icon as IconName} size={14} class="text-accent" />{label}</span>
				<span class="mt-0.5 block text-[11px] text-ink-2">{hint}</span>
			</div>
		{/each}
	</div>
{/snippet}

{#snippet accessArt()}
	<div class="w-72 space-y-1.5">
		{#each [['map', 'North region', 'viewer'], ['store', 'Downtown', 'contributor']] as [icon, name, level] (name)}
			<div class="flex items-center gap-2 rounded-xl bg-surface px-2.5 py-2 shadow-sm ring-1 ring-line">
				<span class="grid size-7 place-items-center rounded-md bg-surface-2 text-ink-2 ring-1 ring-line"><Icon name={icon as IconName} size={13} /></span>
				<span class="flex-1 text-sm font-medium">{name}</span>
				<span class="flex rounded-md bg-surface-2 p-0.5 ring-1 ring-line">
					{@render chip('eye', 'Views', level === 'viewer')}
					{@render chip('pencil', 'Enters', level === 'contributor')}
				</span>
			</div>
		{/each}
	</div>
{/snippet}

{#snippet inviteArt()}
	<div class="flex items-center gap-4">
		<span class="flex items-center gap-1.5 rounded-lg bg-navy px-3 py-1.5 text-sm font-medium text-navy-ink shadow-sm"><Icon name="send" size={14} /> Invite people</span>
		<Icon name="arrow-right" size={20} class="text-muted" />
		<div class="w-48 rounded-xl bg-surface p-3 shadow-sm ring-1 ring-line">
			<span class="flex items-center gap-1.5 text-sm font-medium"><Icon name="mail" size={14} class="text-accent" /> ben@example.com</span>
			<span class="mt-1 flex items-center gap-1 text-[11px] text-muted"><Icon name="clock" size={11} /> Waiting to join</span>
		</div>
	</div>
{/snippet}

<Tour bind:open {steps} label="How People works" {onClose} />
