<script lang="ts">
	import LegalPage, { CONTACT } from '$lib/components/LegalPage.svelte';
</script>

<LegalPage title="Terms of Service">
	<p>
		These terms cover your use of Clairvoyance at clairvoyance.solutions: a service for keeping an organisation's KPIs, figures and
		targets in one place. By creating an account you agree to them. Please read them with our <a href="/privacy">Privacy Policy</a>.
	</p>

	<h2>1. Your account</h2>
	<ul>
		<li>Give your real name and an email address you can receive mail at.</li>
		<li>An account is for one person. Keep your password to yourself; you're responsible for what's done with your account.</li>
		<li>Tell us at <a href="mailto:{CONTACT}">{CONTACT}</a> if you think someone else has used it.</li>
	</ul>

	<h2>2. Organisations and their admins</h2>
	<p>
		Each organisation's admins decide who joins, what each person can see and enter, and how the organisation is set up. The figures,
		targets and notes in an organisation belong to that organisation. If you leave or delete your account, the figures you entered stay
		with the organisation, and its change history keeps showing who made each change.
	</p>

	<h2>3. Your data</h2>
	<p>
		You keep ownership of what you put into Clairvoyance. You allow us to store and process it only to run the service for you: to
		show, add up and compare your figures, and to send the emails the service needs. We don't sell it or use it for advertising.
	</p>

	<h2>4. Using Clairvoyance fairly</h2>
	<p>Please don't:</p>
	<ul>
		<li>try to see or change another organisation's data, or get around the access an admin has given you;</li>
		<li>probe, overload or attack the service, or use automated tools against it beyond normal use;</li>
		<li>send invitations to people who haven't asked for them;</li>
		<li>store anything unlawful, or sensitive personal information about other people (for example in notes) that the work doesn't need.</li>
	</ul>

	<h2>5. Availability and backups</h2>
	<p>
		Clairvoyance is currently offered free of charge. We work to keep it running and back it up daily, but we can't promise it will
		always be available or free of errors. Export your figures from time to time (Export, on the scorecard) if you rely on them.
	</p>

	<h2>6. Ending</h2>
	<p>
		You can delete your account at any time from Account, and an organisation's admin can delete the organisation. We may suspend
		accounts that break these terms, or that put the service or other people's data at risk; where we can, we'll tell you first.
	</p>

	<h2>7. Responsibility</h2>
	<p>
		Clairvoyance shows figures as they were entered. Decisions you make with them are your own. To the extent the law allows,
		the service is provided as it is, and we aren't liable for indirect losses or for losses caused by figures entered incorrectly.
	</p>

	<h2>8. Changes to these terms</h2>
	<p>
		If we change these terms, we'll update the date above. For a change that matters, we'll tell you by email or in the app
		before it applies. Continuing to use Clairvoyance after that means you accept the new terms.
	</p>

	<h2>9. Law and contact</h2>
	<p>
		These terms are governed by the laws of the Republic of the Philippines. Questions about them go to
		<a href="mailto:{CONTACT}">{CONTACT}</a>.
	</p>
</LegalPage>
