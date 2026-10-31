import { api, config } from './api';

/**
 * Botões "Entrar com o Google" ([data-google-login], ver spotinder_google_button()).
 *
 * Abre o popup do Google Identity Services (fluxo de código). O código volta por
 * postMessage — nunca por uma URL do site, que o ModSecurity da hospedagem bloqueia —
 * e é trocado no servidor (POST auth/google). Enquanto a biblioteca do Google não
 * carrega, o botão é um link comum para a página de login.
 */

const GIS_SRC = 'https://accounts.google.com/gsi/client';

let loading = null;
let client = null;
let active = null;

function loadGis() {
	if (window.google?.accounts?.oauth2) return Promise.resolve();
	if (!loading) {
		loading = new Promise((resolve, reject) => {
			const script = document.createElement('script');
			script.src = GIS_SRC;
			script.async = true;
			script.onload = resolve;
			script.onerror = () => {
				loading = null;
				reject(new Error('Não foi possível carregar o login do Google.'));
			};
			document.head.appendChild(script);
		});
	}
	return loading;
}

function setBusy(button, busy) {
	if (!button?.isConnected) return;
	button.classList.toggle('is-busy', busy);
	button.setAttribute('aria-busy', busy ? 'true' : 'false');
}

/** Mensagem de erro logo abaixo do botão clicado. */
function fail(message) {
	setBusy(active, false);
	if (!active?.isConnected) return;
	let box = active.nextElementSibling;
	if (!box?.matches('[data-google-login-error]')) {
		box = document.createElement('p');
		box.setAttribute('data-google-login-error', '');
		box.setAttribute('role', 'alert');
		box.className = 'mt-2 text-sm text-rose-200';
		active.after(box);
	}
	box.textContent = message || 'Não foi possível entrar com o Google. Tente de novo.';
}

async function onCode(response) {
	if (response.error) {
		setBusy(active, false);
		if (response.error !== 'access_denied') fail();
		return;
	}
	try {
		const res = await api('auth/google', {
			method: 'POST',
			headers: { 'X-Requested-With': 'XmlHttpRequest' },
			body: { code: response.code, redirect: active?.dataset.redirect || '' },
		});
		// Recarrega por completo: cabeçalho, nonce da API e ilhas passam a ser de membro.
		window.location.href = res.redirect;
	} catch (e) {
		fail(e.message);
	}
}

function getClient() {
	if (!client) {
		client = window.google.accounts.oauth2.initCodeClient({
			client_id: config.google.clientId,
			scope: config.google.scope,
			ux_mode: 'popup',
			select_account: true,
			callback: onCode,
			// Popup fechado ou bloqueado: só volta o botão ao normal.
			error_callback: () => setBusy(active, false),
		});
	}
	return client;
}

/** Pré-carrega a biblioteca quando a página tem botão (o popup precisa abrir no clique). */
export function initGoogleLogin() {
	if (!config.google?.clientId || !document.querySelector('[data-google-login]')) return;
	loadGis()
		.then(getClient)
		.catch(() => {});
}

document.addEventListener('click', (e) => {
	const button = e.target.closest('[data-google-login]');
	if (!button || !config.google?.clientId || !window.google?.accounts?.oauth2) return; // Sem a biblioteca: segue o link.
	e.preventDefault();
	active = button;
	setBusy(button, true);
	getClient().requestCode();
});
