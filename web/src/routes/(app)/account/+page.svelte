<script lang="ts">
	import { cubicOut } from 'svelte/easing';
	import { prefersReducedMotion } from 'svelte/motion';
	import { fly } from 'svelte/transition';
	import { goto } from '$app/navigation';
	import { api, ApiError } from '$lib/api';
	import AccountOrganizations from '$lib/components/account/AccountOrganizations.svelte';
	import PasswordInput, { mismatched } from '$lib/components/auth/PasswordInput.svelte';
	import ConfirmDialog from '$lib/components/ConfirmDialog.svelte';
	import InlineEdit from '$lib/components/InlineEdit.svelte';
	import PageBand, { WIDTH } from '$lib/components/PageBand.svelte';
	import SectionHeading from '$lib/components/SectionHeading.svelte';
	import { session } from '$lib/session.svelte';
	import { toast } from '$lib/toast.svelte';

	/** Your name, password and organisations, and deleting the account. */
	let current = $state('');
	let next = $state('');
	let repeat = $state('');
	let passwordBusy = $state(false);
	let passwordError = $state<ApiError | null>(null);
	let deleting = $state(false);
	let deletePassword = $state('');
	let deleteBusy = $state(false);
	let deleteError = $state('');
	const move = $derived(prefersReducedMotion.current ? 0 : 1);
	const soloAdmin = $derived((session.user?.organizations ?? []).some((o) => o.role === 'admin'));
	const enter = (i: number) => ({ y: 10 * move, duration: 260, delay: i * 60 * move, easing: cubicOut });

	async function rename(name: string) {
		const user = await api.updateAccount({ name: name || null });
		if (session.user) session.user.name = user.name;
		toast('Name saved');
	}

	async function savePassword(event: SubmitEvent) {
		event.preventDefault();
		if (next !== repeat) return;
		passwordBusy = true;
		passwordError = null;
		try {
			await api.updateAccount({ current_password: current, new_password: next });
			current = next = repeat = '';
			toast('Password changed. Other devices have been signed out');
		} catch (e) {
			passwordError = e instanceof ApiError ? e : new ApiError(0, 'Could not change the password');
		} finally {
			passwordBusy = false;
		}
	}

	async function deleteAccount() {
		deleteBusy = true;
		deleteError = '';
		try {
			await api.deleteAccount(deletePassword);
			deleting = false;
			session.user = null;
			toast('Your account has been deleted');
			goto('/login');
		} catch (e) {
			deleteError = e instanceof ApiError ? (e.field('password') ? `That password ${e.field('password')}` : e.message) : 'Could not delete the account';
		} finally {
			deleteBusy = false;
		}
	}
</script>

<svelte:head><title>Account | Clairvoyance</title></svelte:head>

<PageBand title="Your account" lead={session.user?.email} width="narrow" />

<main class="mx-auto max-w-[1240px] px-4 pb-16 sm:px-6">
	<div class="mx-auto -mt-5 space-y-4 {WIDTH.narrow}">
		<section class="panel p-5 sm:p-6" in:fly={enter(0)}>
			<SectionHeading icon="user" title="Your name" lead="Click it to change it. People in your organisations see it." />
			<p class="mt-4 text-xl font-semibold tracking-[-0.015em] sm:pl-[3.375rem]">
				<InlineEdit value={session.user?.name ?? ''} label="Your name" tip="Rename" placeholder="Add your name" optional onSave={rename} inputClass="w-full" />
			</p>
		</section>

		<form class="panel p-5 sm:p-6" onsubmit={savePassword} in:fly={enter(1)}>
			<SectionHeading icon="lock" title="Password" lead="Changing it signs you out on your other devices." />
			<div class="mt-4 grid gap-4 sm:grid-cols-2 sm:pl-[3.375rem]">
				<div>
					<label class="field-label" for="current-password">Current password</label>
					<PasswordInput id="current-password" bind:value={current} error={passwordError?.field('current_password') ? `That ${passwordError.field('current_password')}` : ''} />
				</div>
				<div>
					<label class="field-label" for="new-password">New password</label>
					<PasswordInput id="new-password" bind:value={next} autocomplete="new-password" rule error={passwordError?.field('new_password') ? `Password ${passwordError.field('new_password')}` : ''} />
				</div>
				<div class="sm:col-start-2">
					<label class="field-label" for="repeat-password">Confirm new password</label>
					<PasswordInput id="repeat-password" bind:value={repeat} autocomplete="new-password" error={mismatched(next, repeat) ? "The passwords don't match" : ''} />
				</div>
			</div>
			<div class="mt-5 flex justify-end">
				<button class="btn btn-secondary" disabled={passwordBusy || !current || next.length < 8 || next !== repeat}>{passwordBusy ? 'Changing…' : 'Change password'}</button>
			</div>
		</form>

		<div in:fly={enter(2)}><AccountOrganizations /></div>

		<section class="flex flex-wrap items-center gap-4 rounded-xl p-5 ring-1 ring-bad/30 sm:p-6" in:fly={enter(3)}>
			<div class="min-w-0 flex-1">
				<SectionHeading icon="trash" tone="bg-bad-wash text-bad-ink" title="Delete your account" lead="Removes your sign-in and your access everywhere. Figures you entered stay, without your name." />
			</div>
			<button class="btn btn-danger" onclick={() => { deletePassword = ''; deleteError = ''; deleting = true; }}>Delete…</button>
		</section>
	</div>
</main>

<ConfirmDialog bind:open={deleting} title="Delete your account?" icon="trash" confirmLabel="Delete my account" cancelLabel="Keep it" busy={deleteBusy} disabled={!deletePassword} onConfirm={deleteAccount}>
	<p>
		This can't be undone. Figures you entered stay, without your name.
		{#if soloAdmin}Organisations where you're the only person are deleted with it. If others are in one you run, make one of them an administrator first.{/if}
	</p>
	<div class="mt-5 text-ink">
		<label class="field-label" for="delete-password">Your password</label>
		<PasswordInput id="delete-password" bind:value={deletePassword} error={deleteError} />
	</div>
</ConfirmDialog>
