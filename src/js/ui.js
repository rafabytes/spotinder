let heroTimer = null;

/**
 * Pilha de cartões do destaque: o da frente "curte" ou "passa" e vai para o fundo, em loop.
 * As posições ficam em data-slot (0 = frente); o CSS faz as transições.
 */
function initHeroDeck() {
	clearInterval(heroTimer);
	heroTimer = null;

	const deck = document.querySelector('[data-hero-deck]');
	if (!deck) return;
	const cards = [...deck.querySelectorAll('.hero-swipe')];
	if (cards.length < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

	const match = deck.querySelector('[data-hero-match]');
	let order = cards.slice();
	let turn = 0;

	const step = () => {
		if (document.hidden || !deck.isConnected) return;

		const front = order[0];
		const action = turn++ % 3 === 2 ? 'pass' : 'like'; // Duas curtidas, uma passada.
		front.dataset.swipe = action; // Inclina e mostra o carimbo.

		setTimeout(() => {
			delete front.dataset.swipe;
			front.dataset.slot = `out-${action}`;
			order = [...order.slice(1), front];
			order.slice(0, -1).forEach((card, i) => (card.dataset.slot = String(i)));

			if (action === 'like' && match) {
				match.classList.add('is-visible');
				setTimeout(() => match.classList.remove('is-visible'), 1300);
			}

			// Depois de sair, volta escondido para o fundo da pilha.
			setTimeout(() => {
				front.classList.add('no-transition');
				front.dataset.slot = String(order.length - 1);
				void front.offsetWidth;
				front.classList.remove('no-transition');
			}, 800);
		}, 650);
	};

	heroTimer = setInterval(step, 3200);
}

/**
 * Pequenos comportamentos fora do React, reaplicados a cada troca de página.
 */
export function initUi() {
	initHeroDeck();

	// Menu mobile.
	const toggle = document.querySelector('[data-menu-toggle]');
	const menu = document.getElementById('mobile-menu');
	if (toggle && menu) {
		toggle.addEventListener('click', () => {
			const open = menu.classList.toggle('hidden') === false;
			toggle.setAttribute('aria-expanded', String(open));
		});
	}

	// Avisos pós-redirecionamento: fecham sozinhos e saem da URL.
	document.querySelectorAll('[data-notice]').forEach((notice) => {
		const close = () => notice.remove();
		notice.querySelector('[data-notice-close]')?.addEventListener('click', close);
		setTimeout(close, 8000);
	});
	const url = new URL(window.location.href);
	if (url.searchParams.has('sp_notice')) {
		url.searchParams.delete('sp_notice');
		window.history.replaceState(window.history.state, '', url.toString());
	}

	// Aba ativa na barra inferior (fica fora do container do Swup).
	const here = window.location.pathname.replace(/\/$/, '');
	document.querySelectorAll('[data-tabbar] a').forEach((a) => {
		const path = new URL(a.href).pathname.replace(/\/$/, '');
		const active = path && (here === path || here.startsWith(path + '/'));
		if (active) a.setAttribute('aria-current', 'page');
		else a.removeAttribute('aria-current');
	});
}
