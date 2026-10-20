/** Small, dependency-free helpers for the hand-drawn SVG charts. */

/** A "nice" tick step (1, 2 or 5 × 10^n) splitting `range` into about `count` parts. */
export function niceStep(range: number, count: number): number {
	if (range <= 0 || !Number.isFinite(range)) return 1;
	const raw = range / count;
	const magnitude = 10 ** Math.floor(Math.log10(raw));
	const normalized = raw / magnitude;
	const step = normalized < 1.5 ? 1 : normalized < 3 ? 2 : normalized < 7 ? 5 : 10;
	return step * magnitude;
}

export interface Scale {
	min: number;
	max: number;
	ticks: number[];
}

/**
 * Domain rounded out to nice ticks around the values. `includeZero` is for bars, which must
 * grow from zero; lines may start wherever the data does.
 */
export function niceScale(values: number[], { count = 4, includeZero = false } = {}): Scale {
	let lo = Math.min(...values, ...(includeZero ? [0] : []));
	let hi = Math.max(...values, ...(includeZero ? [0] : []));
	if (!values.length) {
		lo = 0;
		hi = 1;
	}
	if (lo === hi) {
		const pad = Math.abs(lo) * 0.1 || 1;
		lo = includeZero && lo >= 0 ? 0 : lo - pad;
		hi += pad;
	}
	const step = niceStep(hi - lo, count);
	const min = Math.floor(lo / step) * step;
	const max = Math.ceil(hi / step) * step;
	const ticks: number[] = [];
	for (let t = min; t <= max + step / 2; t += step) ticks.push(Number(t.toPrecision(12)));
	return { min, max, ticks };
}

/**
 * Tick label formatter precise enough that neighbouring ticks never read the same:
 * compact (8.4K, 4.5M) with as many decimals as the step needs in the compact unit.
 */
export function tickFormatter(scale: Scale): (v: number) => string {
	const step = scale.ticks.length > 1 ? scale.ticks[1] - scale.ticks[0] : 1;
	if (Math.abs(scale.max) >= 1000 && step >= 1000) {
		// The unit compact notation will use: thousands, millions, billions...
		const unit = 10 ** (3 * Math.floor(Math.log10(Math.abs(scale.max)) / 3));
		// Decimals the step has in that unit: 0.5M needs one, 2.5K one, 2K none
		let digits = 0;
		while (digits < 2 && Math.abs(Math.round((step / unit) * 10 ** digits) - (step / unit) * 10 ** digits) > 1e-9) digits++;
		return (v) => v.toLocaleString('en-US', { notation: 'compact', maximumFractionDigits: digits });
	}
	const decimals = Math.max(0, -Math.floor(Math.log10(step) + 1e-9));
	return (v) => v.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

/** Drop leading periods with no value, so a chart starts where the data does. */
export function trimLeading<T>(items: T[], hasValue: (item: T) => boolean): T[] {
	const first = items.findIndex(hasValue);
	return first === -1 ? items : items.slice(first);
}

/** SVG path through the points, broken wherever a value is missing. */
export function linePath(points: ({ x: number; y: number } | null)[]): string {
	let d = '';
	let pen = false;
	for (const p of points) {
		if (!p) {
			pen = false;
			continue;
		}
		d += `${pen ? 'L' : 'M'}${p.x.toFixed(1)} ${p.y.toFixed(1)}`;
		pen = true;
	}
	return d;
}

/** Horizontal bar with a 4px rounded data end and a square baseline end. */
export function barPath(x0: number, x1: number, y: number, height: number, radius = 4): string {
	const left = Math.min(x0, x1);
	const right = Math.max(x0, x1);
	const r = Math.min(radius, (right - left) / 2, height / 2);
	if (right - left < 0.5) return '';
	if (x1 >= x0) {
		return `M${left} ${y}H${right - r}Q${right} ${y} ${right} ${y + r}V${y + height - r}Q${right} ${y + height} ${right - r} ${y + height}H${left}Z`;
	}
	return `M${right} ${y}H${left + r}Q${left} ${y} ${left} ${y + r}V${y + height - r}Q${left} ${y + height} ${left + r} ${y + height}H${right}Z`;
}
