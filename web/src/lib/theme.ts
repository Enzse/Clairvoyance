export type Theme = 'light' | 'dark';

/** The theme currently showing: the saved choice, else the operating system's. */
export function currentTheme(): Theme {
	const saved = document.documentElement.dataset.theme;
	if (saved === 'light' || saved === 'dark') return saved;
	return matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export function setTheme(theme: Theme): void {
	document.documentElement.dataset.theme = theme;
	try {
		localStorage.setItem('theme', theme);
	} catch {
		// Private mode or blocked storage: the choice lasts for this page only
	}
}
