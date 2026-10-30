import { createElement } from 'react';
import { createRoot } from 'react-dom/client';

import Deck from './components/Deck';
import Matches from './components/Matches';
import Profile from './components/Profile';
import EventRsvp from './components/EventRsvp';
import MatchBadge from './components/MatchBadge';

/**
 * Componentes React montados em <div data-island="Nome" data-props='{...}'>.
 * O Swup troca o HTML da página; as ilhas são desmontadas antes e remontadas depois.
 */
const registry = { Deck, Matches, Profile, EventRsvp, MatchBadge };
const roots = new Map();

export function mountIslands(scope = document) {
	scope.querySelectorAll('[data-island]').forEach((el) => {
		if (roots.has(el)) return;
		const Component = registry[el.dataset.island];
		if (!Component) return;

		let props = {};
		try {
			props = JSON.parse(el.dataset.props || '{}');
		} catch (e) {
			console.warn('[spotinder] props inválidas em', el, e);
		}

		const root = createRoot(el);
		root.render(createElement(Component, props));
		roots.set(el, root);
	});
}

export function unmountIslands() {
	roots.forEach((root) => root.unmount());
	roots.clear();
}
