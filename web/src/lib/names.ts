/**
 * Names typed or pasted as a list: one per line, or separated by commas, semicolons or tabs.
 * List bullets ("- ", "• ", "1. ") and stray punctuation at either end are dropped, and a name
 * that appears twice (in any case) is kept once.
 */
export function splitNames(text: string): string[] {
	const seen = new Set<string>();
	const names: string[] = [];
	for (const part of text.split(/[\n\r,;\t]+/)) {
		const name = part
			.trim()
			.replace(/^(?:[-*•]|\d+[.)])\s+/, '')
			.replace(/^[\s.,;:]+|[\s.,;:]+$/g, '');
		if (name && !seen.has(name.toLowerCase())) {
			seen.add(name.toLowerCase());
			names.push(name);
		}
	}
	return names;
}
