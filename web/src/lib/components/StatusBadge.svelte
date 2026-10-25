<script lang="ts">
	import { STATUS } from '$lib/status';
	import type { Status } from '$lib/types';
	import StatusMark from './StatusMark.svelte';

	/** `soFar`: some figures are still to come, so the status may change */
	let { status, empty = 'No target', soFar = false }: { status: Status | null; empty?: string; soFar?: boolean } = $props();
</script>

<span class="inline-flex items-center gap-1.5 text-sm whitespace-nowrap {status ? 'text-ink' : 'text-muted'}">
	<StatusMark {status} />
	<span>{status ? STATUS[status].label : empty}{#if status && soFar}<span class="text-muted"> so far</span>{/if}</span>
</span>
