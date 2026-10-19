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
		download: ['M12 4v11', 'm7 10 5 5 5-5', 'M5 15v4a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-4'],
		mail: ['M3 6h18v12H3z', 'm3 7 9 6 9-6'],
		history: ['M3 12a9 9 0 1 0 3-6.7L3 8', 'M3 3v5h5', 'M12 7v5l3 2'],
		move: ['M5 12h14', 'm13 6 6 6-6 6'],
		coins: [circle(9, 9, 6), 'M15.4 9.6a6 6 0 1 1-5.8 5.8'],
		percent: ['M19 5 5 19', circle(6.5, 6.5, 2.5), circle(17.5, 17.5, 2.5)],
		hash: ['M5 9h14', 'M5 15h14', 'M10 4 8 20', 'M16 4l-2 16'],
		pause: ['M9 5v14', 'M15 5v14'],
		help: [circle(12, 12, 9), 'M9.5 9.2a2.5 2.5 0 0 1 4.9.6c0 1.7-2.4 2.2-2.4 3.7', 'M12 17v.01'],
		'arrow-right': ['M5 12h14', 'm12 5 7 7-7 7'],
		sliders: ['M4 6h10', 'M18 6h2', circle(16, 6, 2), 'M4 18h4', 'M12 18h8', circle(10, 18, 2), 'M4 12h2', 'M10 12h10', circle(8, 12, 2)],
		// Choosable unit icons (see unit-icons.ts)
		building: ['M4 21V4a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v17', 'M15 9h4a1 1 0 0 1 1 1v11', 'M3 21h18', 'M8 7h3', 'M8 11h3', 'M8 15h3'],
		folder: ['M3 6a1 1 0 0 1 1-1h5l2 2h8a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1Z'],
		pin: ['M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11Z', circle(12, 10, 2.5)],
		store: ['M4 10v10h16V10', 'M3 4h18l-1.5 6h-15Z', 'M10 20v-5h4v5'],
		coffee: ['M5 8h11v6a5 5 0 0 1-5 5h-1a5 5 0 0 1-5-5Z', 'M16 10h2a2 2 0 0 1 0 4h-2', 'M8 3v2', 'M12 3v2'],
		home: ['M3 11 12 4l9 7', 'M5 10v10h14V10', 'M10 20v-6h4v6'],
		factory: ['M3 21V10l6 4v-4l6 4V6h6v15Z', 'M3 21h18'],
		box: ['m3 7 9-4 9 4v10l-9 4-9-4Z', 'm3 7 9 4 9-4', 'M12 11v10'],
		truck: ['M2 6h12v10H2z', 'M14 10h4l3 3v3h-7', circle(6, 18, 2), circle(17, 18, 2)],
		briefcase: ['M3 7h18v13H3z', 'M8 7V5a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2', 'M3 13h18'],
		users: [circle(9, 8, 3.5), 'M2 20a7 7 0 0 1 14 0', 'M16 4.5a3.5 3.5 0 0 1 0 7', 'M18 14a6 6 0 0 1 4 6'],
		heart: ['M12 20s-7-4.5-9-9a4.5 4.5 0 0 1 9-2.5A4.5 4.5 0 0 1 21 11c-2 4.5-9 9-9 9Z'],
		school: ['m2 9 10-5 10 5-10 5Z', 'M6 11v5c0 1.5 3 3 6 3s6-1.5 6-3v-5', 'M22 9v6'],
		globe: [circle(12, 12, 9), 'M3 12h18', 'M12 3a14 14 0 0 1 0 18', 'M12 3a14 14 0 0 0 0 18'],
		map: ['M9 4 3 6v14l6-2 6 2 6-2V4l-6 2Z', 'M9 4v14', 'M15 6v14'],
		flag: ['M5 21V4', 'M5 4h12l-2 4 2 4H5'],
		star: ['m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9Z'],
		cart: [circle(9, 20, 1.5), circle(17, 20, 1.5), 'M3 4h2l2.5 11h11L21 8H6.5'],
		phone: ['M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a1 1 0 0 1-1 1A16 16 0 0 1 4 5a1 1 0 0 1 1-1Z'],
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
