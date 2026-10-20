<script lang="ts">
	import LegalPage, { CONTACT } from '$lib/components/LegalPage.svelte';
</script>

<LegalPage title="Privacy Policy">
	<p>
		This explains what personal information Clairvoyance keeps, why, and what you can ask us to do with it, in line with the
		Philippine Data Privacy Act of 2012 (Republic Act No. 10173).
	</p>

	<h2>1. What we keep</h2>
	<ul>
		<li><strong>Your account:</strong> your name, email address, and password (stored only as a one-way hash we can't read).</li>
		<li><strong>Your organisation's data:</strong> its structure, KPIs, targets, figures and notes, and the people in it with their access.</li>
		<li>
			<strong>A record of changes:</strong> who entered, changed or deleted each figure, and when. For spreadsheet uploads, the file's name
			and how many figures it added; the file itself isn't kept.
		</li>
		<li><strong>Security records:</strong> IP addresses of recent sign-in and sign-up attempts, used to stop password guessing and kept only briefly.</li>
		<li><strong>When you agreed to these terms</strong>, and which version.</li>
	</ul>

	<h2>2. Why</h2>
	<p>
		To run the service: sign you in, show your organisation the figures you're allowed to see, keep the history behind them,
		and send the emails the service needs (confirming your address, resetting your password, invitations). We don't send
		marketing, sell your information, or use it for advertising.
	</p>

	<h2>3. Cookies and your browser</h2>
	<p>
		One cookie keeps you signed in. Your browser also remembers a few display choices, like light or dark mode. There are no
		analytics or advertising trackers.
	</p>

	<h2>4. Who can see it</h2>
	<ul>
		<li>People in your organisation, according to the access its admins give them. Admins can see who belongs and each person's access.</li>
		<li>Us, only when needed to run, support or fix the service.</li>
		<li>Our hosting and email providers, who store and deliver it on our behalf and may not use it for anything else.</li>
		<li>Authorities, only where the law requires it.</li>
	</ul>

	<h2>5. How long</h2>
	<p>
		Your account is kept until you delete it, and an organisation's data until its admin deletes the organisation. Deleting removes
		it from the service straight away; copies in our daily backups are overwritten within 14 days.
	</p>

	<h2>6. Your rights</h2>
	<p>
		You can ask to see the information we hold about you, have it corrected or deleted, object to how it's used, or get a copy of it.
		Much of this you can do yourself: change your name or password in Account, export figures from the scorecard, or delete your account.
		For anything else, write to <a href="mailto:{CONTACT}">{CONTACT}</a>. If you're not satisfied with our answer, you can complain
		to the National Privacy Commission.
	</p>

	<h2>7. Keeping it safe</h2>
	<p>
		Connections are encrypted, passwords are hashed, each person only reaches what their access allows, and repeated wrong
		passwords are blocked. No system is perfectly secure; if a breach ever affects your information, we'll tell you and the
		National Privacy Commission as the law requires.
	</p>

	<h2>8. Changes and contact</h2>
	<p>
		If this policy changes, we'll update the date above and tell you of important changes. Questions go to
		<a href="mailto:{CONTACT}">{CONTACT}</a>.
	</p>
</LegalPage>
