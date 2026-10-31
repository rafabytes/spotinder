import Swup from 'swup';
import SwupHeadPlugin from '@swup/head-plugin';
import SwupBodyClassPlugin from '@swup/body-class-plugin';
import SwupPreloadPlugin from '@swup/preload-plugin';
import SwupA11yPlugin from '@swup/a11y-plugin';

import { mountIslands, unmountIslands } from './islands';
import { initGoogleLogin } from './google-login';
import { refreshLocationQuietly } from './location';
import { initUi } from './ui';

/**
 * Links que precisam de navegação completa (login, admin, downloads).
 */
function shouldIgnore(url, el) {
	if (el?.closest('[data-no-swup]')) return true;
	const u = new URL(url, window.location.origin);
	return (
		/\/wp-(admin|login)/.test(u.pathname) ||
		u.searchParams.has('spotinder_auth') ||
		u.searchParams.has('spotinder_ics') ||
		/\.(pdf|ics|zip|xml|json)$/i.test(u.pathname)
	);
}

const swup = new Swup({
	containers: ['#swup', '#site-header'],
	animationSelector: '[class*="transition-"]',
	ignoreVisit: (url, { el } = {}) => shouldIgnore(url, el),
	plugins: [
		new SwupHeadPlugin({ awaitAssets: true }),
		new SwupBodyClassPlugin(),
		new SwupPreloadPlugin(),
		new SwupA11yPlugin(),
	],
});

// Componentes React podem navegar com transição.
window.spotinderNavigate = (url) => swup.navigate(url);

swup.hooks.before('content:replace', unmountIslands);
swup.hooks.on('page:view', () => {
	mountIslands();
	initUi();
	initGoogleLogin();
	refreshLocationQuietly();
});

mountIslands();
initUi();
initGoogleLogin();
refreshLocationQuietly();
