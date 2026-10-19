<script lang="ts">
	import InviteList from '$lib/components/InviteList.svelte';
	import Icon from '$lib/components/Icon.svelte';

	/** Inviting people, the last step. Leaving it is fine: invitations can be sent any time. */
	let { onDone }: { onDone: () => void } = $props();
	let created = $state(0);
</script>

<div class="panel -mt-6 p-6 sm:p-8">
	<InviteList onCreated={(n) => (created += n)} />
	<div class="mt-8 flex flex-wrap items-center justify-end gap-3 border-t border-line pt-5">
		<button class="btn btn-primary" onclick={onDone}>
			{created ? 'Go to your scorecard' : 'Skip and go to your scorecard'} <Icon name="arrow-right" size={15} />
		</button>
	</div>
</div>
