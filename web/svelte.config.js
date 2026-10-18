import adapter from '@sveltejs/adapter-static';
import { vitePreprocess } from '@sveltejs/vite-plugin-svelte';

/** Single-page app: the PHP API lives on another origin and the session is a cookie. */
const config = {
	preprocess: vitePreprocess(),
	kit: { adapter: adapter({ fallback: 'index.html' }) }
};

export default config;
