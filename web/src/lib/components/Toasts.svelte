<script lang="ts">
	import { dismiss, toasts } from '$lib/toast.svelte';
	import Icon from './Icon.svelte';
</script>

<div class="pointer-events-none fixed bottom-5 left-1/2 z-50 flex -translate-x-1/2 flex-col items-center gap-2" aria-live="polite">
	{#each toasts as t (t.id)}
		<div class="pointer-events-auto flex items-center gap-3 rounded-lg bg-navy py-2.5 pr-2.5 pl-4 text-sm text-navy-ink shadow-pop">
			<Icon name={t.tone === 'error' ? 'alert' : 'check'} size={16} class={t.tone === 'error' ? 'text-bad-ink' : 'text-good-ink'} />
			<span>{t.message}</span>
			<button class="rounded p-1 text-navy-ink-2 hover:text-navy-ink" aria-label="Dismiss" onclick={() => dismiss(t.id)}>
				<Icon name="x" size={14} />
			</button>
		</div>
	{/each}
</div>
