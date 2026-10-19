<script lang="ts">
	import { api, ApiError } from '$lib/api';
	import { session } from '$lib/session.svelte';
	import Icon from './Icon.svelte';

	/** Asks the person to confirm their address; until they do, invitations aren't emailed. */
	let status = $state<'idle' | 'sending' | 'sent'>('idle');
	let error = $state('');
	let hidden = $state(false);

	const show = $derived(!hidden && !!session.user && session.user.email_delivery && !session.user.email_verified);

	async function resend() {
		status = 'sending';
		error = '';
		try {
			await api.resendVerification();
			status = 'sent';
		} catch (e) {
			error = e instanceof ApiError ? e.message : 'Could not send the email';
			status = 'idle';
		}
	}
</script>

{#if show}
	<div class="border-b border-line bg-warn-wash">
		<div class="mx-auto flex max-w-[1240px] flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2.5 text-sm sm:px-6" role="status">
			<Icon name="mail" size={16} />
			<p class="min-w-0 flex-1">
				Confirm your email address: we sent a link to <strong>{session.user?.email}</strong>. Until then, invitations are links you send yourself.
				{#if error}<span class="text-bad-ink">{error}.</span>{/if}
			</p>
			{#if status === 'sent'}
				<span class="font-medium">Sent. Check your inbox.</span>
			{:else}
				<button class="font-medium text-accent hover:underline" disabled={status === 'sending'} onclick={resend}>
					{status === 'sending' ? 'Sending…' : 'Resend the email'}
				</button>
			{/if}
			<button class="rounded p-1 text-ink-2 hover:bg-surface" aria-label="Hide this message" onclick={() => (hidden = true)}>
				<Icon name="x" size={14} />
			</button>
		</div>
	</div>
{/if}
