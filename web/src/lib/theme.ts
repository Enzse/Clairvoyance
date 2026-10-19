export type Theme = 'light' | 'dark';

/** The theme currently showing: the saved choice, else light. */
export function currentTheme(): Theme {
	return document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
}

export function setTheme(theme: Theme): void {
	document.documentElement.dataset.theme = theme;
	try {
		localStorage.setItem('theme', theme);
	} catch {
		// Private mode or blocked storage: the choice lasts for this page only
	}
}
