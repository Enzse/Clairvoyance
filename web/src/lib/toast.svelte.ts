export interface Toast {
	id: number;
	message: string;
	tone: 'success' | 'error';
}

let nextId = 1;

/** Short confirmations ("Report saved") and errors that have no better place on the page. */
export const toasts = $state<Toast[]>([]);

export function toast(message: string, tone: Toast['tone'] = 'success'): void {
	const id = nextId++;
	toasts.push({ id, message, tone });
	setTimeout(() => dismiss(id), tone === 'error' ? 7000 : 3500);
}

export function dismiss(id: number): void {
	const index = toasts.findIndex((t) => t.id === id);
	if (index !== -1) toasts.splice(index, 1);
}
