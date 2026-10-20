import { describe, expect, it } from 'vitest';
import { splitNames } from './names';

describe('splitNames', () => {
	it('takes one name per line, dropping trailing commas', () => {
		expect(splitNames('Cubao,\nBGC,\nMakati,\nAlabang')).toEqual(['Cubao', 'BGC', 'Makati', 'Alabang']);
	});

	it('takes names separated by commas on one line', () => {
		expect(splitNames('Cubao, BGC , Makati')).toEqual(['Cubao', 'BGC', 'Makati']);
	});

	it('drops bullets, numbering, blank lines and repeats', () => {
		expect(splitNames('- Cubao\r\n\r\n• BGC\n1. Makati.\ncubao')).toEqual(['Cubao', 'BGC', 'Makati']);
	});

	it('keeps punctuation inside a name', () => {
		expect(splitNames("St. Luke's\nMall of Asia")).toEqual(["St. Luke's", 'Mall of Asia']);
	});
});
