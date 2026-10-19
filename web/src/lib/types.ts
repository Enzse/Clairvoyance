// Shapes returned by the PHP API (see docs/data-model.md)

export type Frequency = 'daily' | 'weekly' | 'monthly' | 'quarterly' | 'yearly';
export type Status = 'green' | 'amber' | 'red';
export type Access = 'admin' | 'contributor' | 'viewer';

export interface Membership {
	unit_id: number;
	role: 'viewer' | 'contributor';
}

export type Role = 'admin' | 'member';

export interface OrganizationSummary {
	id: number;
	name: string;
	role: Role;
}

export interface User {
	id: number;
	email: string;
	name: string | null;
	created_at?: string;
}

/** The signed-in person; `organization` null means they still need to create or join one. */
export interface Me extends User {
	organizations: OrganizationSummary[];
	organization: OrganizationSummary | null;
	memberships: Membership[];
}

/** Someone in the current organisation, as admins see them. */
export interface Member extends User {
	role: Role;
	joined_at: string;
	memberships: Membership[];
}

export interface Invitation {
	id: number;
	email: string;
	role: Role;
	access: Membership[];
	invited_by: string | null;
	expires_at: string;
	created_at: string;
	/** Only present in the response that created it */
	token?: string;
}

export interface InvitationLookup {
	organization_name: string;
	email: string;
	role: Role;
	invited_by: string | null;
	has_account: boolean;
}

export interface Setup {
	dismissed: boolean;
	steps: { structure: boolean; kpis: boolean; targets: boolean; data: boolean; people: boolean };
}

export interface Unit {
	id: number;
	parent_id: number | null;
	name: string;
	code: string | null;
	kind: string | null;
	is_active: boolean;
	has_values: boolean;
	is_leaf?: boolean;
	access?: Access | null;
}

export interface Kpi {
	id: number;
	code: string;
	name: string;
	description: string | null;
	frequency: Frequency;
	time_aggregation: 'sum' | 'average' | 'last';
	unit_aggregation: 'sum' | 'average' | 'weighted_average';
	weight_kpi_id: number | null;
	direction: 'higher_is_better' | 'lower_is_better' | 'none';
	tolerance_pct: number;
	applies_to_kind: string | null;
	/** Units whose reporting units report this KPI; empty means everyone */
	scope_unit_ids: number[];
	decimals: number;
	prefix: string;
	suffix: string;
	is_active: boolean;
}

export interface Result {
	value: number | null;
	target: number | null;
	status: Status | null;
	reported: number;
	expected: number;
}

export interface PeriodResult extends Result {
	period_start: string;
	period_label: string;
}

export interface ScorecardRow extends PeriodResult {
	kpi: Kpi;
	grain: Frequency;
	previous: { period_start: string; period_label: string; value: number | null } | null;
	change: { value: number; percent: number | null; direction: 'better' | 'worse' | 'flat' | null } | null;
	sparkline: { period_start: string; value: number | null; status: Status | null }[];
}

export interface Scorecard {
	unit: Unit;
	date: string;
	kpis: ScorecardRow[];
}

export interface Series {
	kpi: Kpi;
	unit: Unit;
	grain: Frequency;
	periods: PeriodResult[];
}

export interface Breakdown {
	kpi: Kpi;
	unit: Unit;
	grain: Frequency;
	period_start: string;
	period_label: string;
	units: (Result & { unit: Unit & { is_leaf: boolean } })[];
}

export interface Target {
	unit_id: number | null;
	effective_from: string;
	value: number;
}

export interface EntryRow {
	kpi: Kpi;
	period_start: string;
	period_label: string;
	value: number | null;
	note: string | null;
	updated_at: string | null;
	updated_by: string | null;
}

export interface EntryForm {
	unit: Unit;
	can_edit: boolean;
	rows: EntryRow[];
}

export interface ImportMapping {
	unit_columns: string[];
	unit_match: 'path' | 'name' | 'code';
	unit_kinds?: string[];
	parent_unit_id?: number;
	create_missing_units: boolean;
	date_column: string;
	date_format: DateFormat;
	columns: Record<string, string>;
}

export type DateFormat = 'YYYY-MM-DD' | 'DD/MM/YYYY' | 'MM/DD/YYYY' | 'YYYY-MM';

export interface ImportIssue {
	row: number;
	column: string | null;
	message: string;
}

export interface ImportPreview {
	rows_total: number;
	values_total: number;
	empty_cells: number;
	will_create: number;
	will_update: number;
	unchanged: number;
	new_units: string[];
	errors: ImportIssue[];
	error_count: number;
	sample: { row: number; unit: string; kpi: string; period_label: string; value: number }[];
}

export interface ImportResult {
	import_id: number;
	values_created: number;
	values_updated: number;
	unchanged: number;
	units_created: number;
	skipped_errors: number;
}
