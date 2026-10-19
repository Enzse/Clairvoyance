<script lang="ts">
	import { goto } from '$app/navigation';
	import { page } from '$app/state';
	import { api, ApiError } from '$lib/api';
	import { logout, session, setMe } from '$lib/session.svelte';
	import { currentTheme, setTheme, type Theme } from '$lib/theme';
	import { toast } from '$lib/toast.svelte';
	import { units } from '$lib/units.svelte';
	import Icon from './Icon.svelte';
	import Logo from './Logo.svelte';
	import Popover from './Popover.svelte';

	let theme = $state<Theme>(currentTheme());

	const org = $derived(session.user?.organization);
	const isAdmin = $derived(org?.role === 'admin');

	async function switchTo(id: number, close: () => void) {
		close();
		try {
			setMe(await api.selectOrganization(id));
			goto('/');
		} catch (e) {
			toast(e instanceof ApiError ? e.message : 'Could not switch organisation', 'error');
		}
	}
	const canEnter = $derived(units.enterable().length > 0);

	const links = $derived(
		[
			{ href: '/', label: 'Scorecard', show: true, active: (p: string) => p === '/' || p.startsWith('/kpis') },
			// Typing figures in and uploading a spreadsheet are two tabs of one page
			{ href: '/enter', label: 'Add data', show: canEnter, active: (p: string) => p.startsWith('/enter') || p.startsWith('/import') },
			{ href: '/admin/units', label: 'Admin', show: isAdmin, active: (p: string) => p.startsWith('/admin') }
		].filter((l) => l.show)
	);

	function toggleTheme() {
		theme = theme === 'dark' ? 'light' : 'dark';
		setTheme(theme);
	}

	async function signOut() {
		await logout();
		goto('/login');
	}
</script>

<header class="bg-navy text-navy-ink">
	<div class="mx-auto flex max-w-[1240px] flex-wrap items-center gap-x-6 px-4 sm:h-14 sm:flex-nowrap sm:px-6">
		<div class="flex h-14 min-w-0 shrink items-center gap-3">
			<a href="/" class="shrink-0 rounded-md" aria-label="Clairvoyance home"><Logo onNavy size={26} wordmark={false} /></a>
			<!-- Which organisation you're in, and switching between yours -->
			<Popover label="Organisations" width={280} triggerClass="flex min-w-0 items-center gap-1.5 rounded-md px-2 py-1.5 font-semibold text-navy-ink hover:bg-navy-2">
				{#snippet trigger(open)}
					<span class="truncate">{org?.name}</span>
					<Icon name="chevron-down" size={14} class="shrink-0 text-navy-ink-2 transition-transform {open ? 'rotate-180' : ''}" />
				{/snippet}
				{#snippet children(close)}
					<p class="px-4 pt-3 pb-1 text-sm text-muted">Your organisations</p>
					<ul class="max-h-72 overflow-y-auto px-1.5">
						{#each session.user?.organizations ?? [] as o (o.id)}
							<li>
								<button class="flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-left hover:bg-accent-wash" onclick={() => (o.id === org?.id ? close() : switchTo(o.id, close))}>
									<span class="min-w-0 flex-1">
										<span class="block truncate {o.id === org?.id ? 'font-semibold' : ''}">{o.name}</span>
										<span class="block text-xs text-muted">{o.role === 'admin' ? 'Administrator' : 'Member'}</span>
									</span>
									{#if o.id === org?.id}<Icon name="check" size={16} stroke={2.5} class="text-chart" />{/if}
								</button>
							</li>
						{/each}
					</ul>
					<div class="mt-1 border-t border-line p-1.5">
						<a class="flex items-center gap-2.5 rounded-md px-2.5 py-2 hover:bg-accent-wash" href="/onboarding?new=1" onclick={close}>
							<Icon name="plus" class="text-muted" /> Create a new organisation
						</a>
					</div>
				{/snippet}
			</Popover>
		</div>

		<!-- On phones the links get their own scrollable row under the logo -->
		<nav class="order-last -mx-1 flex w-full min-w-0 items-center gap-1 overflow-x-auto pb-2 sm:order-none sm:w-auto sm:pb-0" aria-label="Main">
			{#each links as link (link.href)}
				{@const active = link.active(page.url.pathname)}
				<a
					href={link.href}
					aria-current={active ? 'page' : undefined}
					class="rounded-md px-3 py-1.5 text-base whitespace-nowrap transition-colors {active
						? 'bg-navy-2 font-semibold text-navy-ink'
						: 'text-navy-ink-2 hover:text-navy-ink'}"
				>
					{link.label}
				</a>
			{/each}
		</nav>

		<div class="ml-auto flex items-center gap-1">
			<button
				class="rounded-md p-2 text-navy-ink-2 hover:bg-navy-2 hover:text-navy-ink"
				onclick={toggleTheme}
				aria-label={theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme'}
			>
				<Icon name={theme === 'dark' ? 'sun' : 'moon'} size={18} />
			</button>
			<Popover label="Account" align="end" width={260} triggerClass="flex items-center gap-2 rounded-md py-1.5 pr-2 pl-1.5 text-navy-ink-2 hover:bg-navy-2 hover:text-navy-ink">
				{#snippet trigger()}
					<span class="grid size-7 place-items-center rounded-full bg-navy-2 text-sm font-semibold text-navy-ink">
						{(session.user?.name || session.user?.email || '?').slice(0, 1).toUpperCase()}
					</span>
					<Icon name="chevron-down" size={14} />
				{/snippet}
				{#snippet children()}
					<div class="border-b border-line px-4 py-3">
						<p class="truncate font-semibold">{session.user?.name || session.user?.email}</p>
						{#if session.user?.name}<p class="truncate text-sm text-ink-2">{session.user.email}</p>{/if}
						<p class="mt-1 text-sm text-muted">{isAdmin ? 'Administrator' : 'Member'} of {org?.name}</p>
					</div>
					<div class="p-1.5">
						<a class="flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 hover:bg-accent-wash" href="/account">
							<Icon name="user" class="text-muted" /> Account settings
						</a>
						<button class="flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-left hover:bg-accent-wash" onclick={signOut}>
							<Icon name="log-out" class="text-muted" /> Sign out
						</button>
					</div>
				{/snippet}
			</Popover>
		</div>
	</div>
</header>
