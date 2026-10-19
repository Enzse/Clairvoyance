<script lang="ts">
	import { page } from '$app/state';
	import Icon, { type IconName } from './Icon.svelte';

	/** The two ways to add figures, shown at the top of both pages. */
	const tabs: { href: string; label: string; icon: IconName }[] = [
		{ href: '/enter', label: 'Type in figures', icon: 'pencil' },
		{ href: '/import', label: 'Upload a spreadsheet', icon: 'upload' }
	];
</script>

<nav class="inline-flex flex-wrap gap-1 rounded-lg bg-navy-2/70 p-1" aria-label="Ways to add data">
	{#each tabs as tab (tab.href)}
		{@const active = page.url.pathname.startsWith(tab.href)}
		<a
			href={tab.href}
			aria-current={active ? 'page' : undefined}
			class="flex items-center gap-2 rounded-md px-3 py-1.5 text-sm {active ? 'bg-surface font-semibold text-ink' : 'text-navy-ink-2 hover:text-navy-ink'}"
		>
			<Icon name={tab.icon} size={14} />
			{tab.label}
		</a>
	{/each}
</nav>
