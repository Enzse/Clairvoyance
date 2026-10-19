import { ApiError } from './api';

/** The most useful message from a failed save: the first field error, or the error itself. */
export function problem(e: unknown, fallback = 'Could not save'): string {
	if (!(e instanceof ApiError)) return fallback;
	const [field, message] = Object.entries(e.details)[0] ?? [];
	if (typeof message !== 'string') return e.message;
	return `${field === 'name' ? 'That name' : field === 'code' ? 'That code' : 'It'} ${message}`;
}
