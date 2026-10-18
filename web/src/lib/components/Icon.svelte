<script lang="ts" module>
	// 24×24 stroke icons. Circles are written as two arcs so every icon is a list of paths.
	const circle = (cx: number, cy: number, r: number) => `M${cx - r} ${cy}a${r} ${r} 0 1 0 ${2 * r} 0a${r} ${r} 0 1 0 ${-2 * r} 0`;

	const ICONS = {
		'chevron-left': ['m15 18-6-6 6-6'],
		'chevron-right': ['m9 18 6-6-6-6'],
		'chevron-down': ['m6 9 6 6 6-6'],
		'arrow-up': ['M12 19V5', 'm5 12 7-7 7 7'],
		'arrow-down': ['M12 5v14', 'm19 12-7 7-7-7'],
		plus: ['M12 5v14', 'M5 12h14'],
		minus: ['M5 12h14'],
		x: ['M18 6 6 18', 'm6 6 12 12'],
		check: ['M20 6 9 17l-5-5'],
		search: [circle(11, 11, 7), 'm20 20-3.5-3.5'],
		upload: ['M12 15V4', 'm7 9 5-5 5 5', 'M5 15v4a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-4'],
		sun: [circle(12, 12, 4), 'M12 2v2', 'M12 20v2', 'm4.9 4.9 1.4 1.4', 'm17.7 17.7 1.4 1.4', 'M2 12h2', 'M20 12h2', 'm4.9 19.1 1.4-1.4', 'm17.7 6.3 1.4-1.4'],
		moon: ['M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5Z'],
		'log-out': ['M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4', 'm16 17 5-5-5-5', 'M21 12H9'],
		pencil: ['M17 3l4 4L8 20H4v-4L17 3Z'],
		trash: ['M3 6h18', 'M8 6V4h8v2', 'm6 6 1 14h10l1-14'],
		alert: [circle(12, 12, 9), 'M12 8v5', 'M12 16.5v.01'],
		file: ['M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8Z', 'M14 3v5h5'],
		table: ['M3 5h18v14H3z', 'M3 10h18', 'M9 10v9'],
		chart: ['M4 19h16', 'M7 16v-5', 'M12 16V7', 'M17 16v-3'],
		drill: ['M5 4v7a3 3 0 0 0 3 3h11', 'm15 10 4 4-4 4'],
		user: [circle(12, 8, 4), 'M4 21a8 8 0 0 1 16 0'],
		layers: ['m12 3 9 5-9 5-9-5 9-5Z', 'm3 13 9 5 9-5'],
		target: [circle(12, 12, 9), circle(12, 12, 5), circle(12, 12, 1)],
		calendar: ['M4 6h16v14H4z', 'M4 10h16', 'M8 3v4', 'M16 3v4'],
		more: ['M5 12h.01', 'M12 12h.01', 'M19 12h.01']
	} satisfies Record<string, string[]>;

	export type IconName = keyof typeof ICONS;
</script>

<script lang="ts">
	let { name, size = 16, stroke = 2, class: className = '' }: { name: IconName; size?: number; stroke?: number; class?: string } = $props();
</script>

<svg
	class={className}
	width={size}
	height={size}
	viewBox="0 0 24 24"
	fill="none"
	stroke="currentColor"
	stroke-width={stroke}
	stroke-linecap="round"
	stroke-linejoin="round"
	aria-hidden="true"
>
	{#each ICONS[name] as d (d)}
		<path {d} />
	{/each}
</svg>
