import type {
	Breakdown,
	EntryForm,
	ExportData,
	Frequency,
	ImportMapping,
	ImportPreview,
	ImportResult,
	Invitation,
	InvitationLookup,
	Kpi,
	Me,
	Member,
	Membership,
	Role,
	Scorecard,
	Series,
	Setup,
	SetupStep,
	Target,
	Unit,
	User,
	ValueChange
} from './types';

/** Absolute in development (the API runs under XAMPP); "/api" in production, on the same origin. */
const BASE = (import.meta.env.VITE_API_URL ?? 'http://localhost/clairvoyance-v3/api').replace(/\/$/, '');

/** An error response from the API. `details` maps field names to messages. */
export class ApiError extends Error {
	constructor(
		public status: number,
		message: string,
		public details: Record<string, unknown> = {}
	) {
		super(message);
	}

	/** Message for one field, if the server reported one. */
	field(name: string): string | undefined {
		const value = this.details[name];
		return typeof value === 'string' ? value : undefined;
	}
}

let onUnauthorized: () => void = () => {};

/** Called whenever the API says the session is gone (401). */
export function setUnauthorizedHandler(handler: () => void) {
	onUnauthorized = handler;
}

type Query = Record<string, string | number | null | undefined>;

async function request<T>(method: string, path: string, options: { query?: Query; body?: unknown; form?: FormData } = {}): Promise<T> {
	const url = new URL(BASE + path, location.origin);
	for (const [key, value] of Object.entries(options.query ?? {})) {
		if (value !== null && value !== undefined && value !== '') url.searchParams.set(key, String(value));
	}

	let response: Response;
	try {
		response = await fetch(url, {
			method,
			credentials: 'include',
			headers: options.body !== undefined ? { 'Content-Type': 'application/json' } : undefined,
			body: options.form ?? (options.body !== undefined ? JSON.stringify(options.body) : undefined)
		});
	} catch {
		throw new ApiError(0, 'Could not reach the server. Check your connection and try again.');
	}

	const payload = await response.json().catch(() => null);
	if (!response.ok || !payload?.success) {
		if (response.status === 401 && path !== '/auth/login') onUnauthorized();
		throw new ApiError(response.status, payload?.error ?? `Request failed (${response.status})`, payload?.details ?? {});
	}
	return payload.data as T;
}

export const api = {
	// Session and account
	me: () => request<Me>('GET', '/auth/me'),
	login: (email: string, password: string) => request<Me>('POST', '/auth/login', { body: { email, password } }),
	register: (body: { email: string; password: string; name?: string }) => request<Me>('POST', '/auth/register', { body }),
	logout: () => request<[]>('POST', '/auth/logout'),
	updateAccount: (body: { name?: string | null; current_password?: string; new_password?: string }) =>
		request<User>('PUT', '/auth/me', { body }),
	deleteAccount: (password: string) => request<[]>('DELETE', '/auth/me', { body: { password } }),
	verifyEmail: (token: string) => request<Me | []>('POST', '/auth/verify-email', { body: { token } }),
	resendVerification: () => request<[]>('POST', '/auth/verify-email/resend'),
	forgotPassword: (email: string) => request<[]>('POST', '/auth/forgot-password', { body: { email } }),
	resetPassword: (token: string, password: string) => request<Me>('POST', '/auth/reset-password', { body: { token, password } }),

	// Organisations
	createOrganization: (name: string) => request<Me>('POST', '/organizations', { body: { name } }),
	selectOrganization: (organizationId: number) => request<Me>('POST', '/organizations/select', { body: { organization_id: organizationId } }),
	updateOrganization: (body: { name?: string; unit_label?: string }) => request<Me>('PUT', '/organization', { body }),
	leaveOrganization: () => request<Me>('POST', '/organization/leave'),
	deleteOrganization: (name: string, password: string) => request<Me>('DELETE', '/organization', { body: { name, password } }),
	setup: () => request<Setup>('GET', '/organization/setup'),
	dismissSetup: (dismissed = true) => request<Setup>('POST', '/organization/setup/dismiss', { body: { dismissed } }),
	skipSetupStep: (step: SetupStep, skipped = true) => request<Setup>('POST', '/organization/setup/skip', { body: { step, skipped } }),

	// People (admin)
	members: () => request<Member[]>('GET', '/members'),
	setMemberRole: (userId: number, role: Role) => request<Member[]>('PUT', `/members/${userId}`, { body: { role } }),
	removeMember: (userId: number) => request<[]>('DELETE', `/members/${userId}`),
	saveMemberships: (userId: number, memberships: Membership[]) =>
		request<Membership[]>('PUT', `/members/${userId}/memberships`, { body: { memberships } }),
	invitations: () => request<Invitation[]>('GET', '/invitations'),
	invite: (body: { email: string; role: Role; access: Membership[] }) => request<Invitation>('POST', '/invitations', { body }),
	revokeInvitation: (id: number) => request<[]>('DELETE', `/invitations/${id}`),
	lookupInvitation: (token: string) => request<InvitationLookup>('GET', '/invitations/lookup', { query: { token } }),
	acceptInvitation: (token: string) => request<Me>('POST', '/invitations/accept', { body: { token } }),

	// Organisation
	units: () => request<Unit[]>('GET', '/units'),
	createUnit: (body: { parent_id: number; name: string; code?: string | null; kind?: string | null }) =>
		request<Unit>('POST', '/units', { body }),
	updateUnit: (id: number, body: Partial<Pick<Unit, 'name' | 'code' | 'kind' | 'is_active' | 'parent_id'>>) =>
		request<Unit>('PUT', `/units/${id}`, { body }),
	deleteUnit: (id: number) => request<[]>('DELETE', `/units/${id}`),

	// KPIs
	kpis: (includeInactive = false) => request<Kpi[]>('GET', '/kpis', { query: { include_inactive: includeInactive ? 1 : null } }),
	createKpi: (body: Partial<Kpi>) => request<Kpi>('POST', '/kpis', { body }),
	updateKpi: (id: number, body: Partial<Kpi>) => request<Kpi>('PUT', `/kpis/${id}`, { body }),
	targets: (kpiId: number) => request<Target[]>('GET', `/kpis/${kpiId}/targets`),
	saveTargets: (kpiId: number, targets: Target[]) => request<Target[]>('PUT', `/kpis/${kpiId}/targets`, { body: { targets } }),

	// Reports; dates default to the latest period with data
	scorecard: (unit: number, date?: string | null, grain?: Frequency | null) =>
		request<Scorecard>('GET', '/scorecard', { query: { unit, date, grain } }),
	series: (kpiId: number, unit: number, grain: Frequency, to?: string | null, from?: string | null) =>
		request<Series>('GET', `/kpis/${kpiId}/series`, { query: { unit, grain, to, from } }),
	breakdown: (kpiId: number, unit: number, grain: Frequency, date?: string | null) =>
		request<Breakdown>('GET', `/kpis/${kpiId}/breakdown`, { query: { unit, grain, date } }),
	history: (kpiId: number, unit: number, date?: string | null) =>
		request<ValueChange[]>('GET', `/kpis/${kpiId}/history`, { query: { unit, date } }),
	exportValues: (unit: number, from: string, to: string) => request<ExportData>('GET', '/exports/values', { query: { unit, from, to } }),

	// Data entry
	entryForm: (unit: number, date: string) => request<EntryForm>('GET', '/values', { query: { unit, date } }),
	saveValues: (values: { kpi_id: number; unit_id: number; period: string; value: number | null; note: string | null }[]) =>
		request<{ created: number; updated: number; unchanged: number; deleted: number }>('PUT', '/values', { body: { values } }),

	// Import
	previewImport: (file: File, mapping: ImportMapping) => request<ImportPreview>('POST', '/imports/preview', { form: importForm(file, mapping) }),
	commitImport: (file: File, mapping: ImportMapping, skipInvalid: boolean) =>
		request<ImportResult>('POST', '/imports', { form: importForm(file, mapping, skipInvalid) }),

};

/** The link an invited person opens. */
export function invitationLink(token: string): string {
	return `${location.origin}/invite/${token}`;
}

function importForm(file: File, mapping: ImportMapping, skipInvalid = false): FormData {
	const form = new FormData();
	form.append('file', file);
	form.append('mapping', JSON.stringify(mapping));
	if (skipInvalid) form.append('skip_invalid', '1');
	return form;
}
