/**
 * Configuração injetada pelo PHP (inc/setup.php → spotinder_js_config()).
 */
export const config = window.SPOTINDER || { strings: {}, urls: {}, intentions: [], inviteTypes: [], socials: {}, reasons: {} };

/** Texto editável no painel. */
export const t = (key) => config.strings?.[key] ?? '';

/** Monta a URL da REST API (funciona com e sem permalinks amigáveis). */
function buildUrl(path, query) {
	const base = config.restUrl || '/wp-json/spotinder/v1/';
	let url;
	if (base.includes('rest_route=')) {
		url = new URL(base, window.location.origin);
		url.searchParams.set('rest_route', url.searchParams.get('rest_route').replace(/\/$/, '') + '/' + path);
	} else {
		url = new URL(base.replace(/\/$/, '') + '/' + path, window.location.origin);
	}
	Object.entries(query || {}).forEach(([k, v]) => {
		if (v !== undefined && v !== null && v !== '') url.searchParams.set(k, v);
	});
	return url;
}

/**
 * Chamada à API do tema.
 *
 * @param {string} path   Ex.: 'deck', 'matches/12'.
 * @param {object} opts   { method, body, query }
 */
export async function api(path, { method = 'GET', body, query } = {}) {
	const isForm = body instanceof FormData;
	const headers = { 'X-WP-Nonce': config.nonce };
	// Em uploads o navegador define o Content-Type (multipart com boundary).
	if (!isForm) headers['Content-Type'] = 'application/json';

	const res = await fetch(buildUrl(path, query), {
		method,
		credentials: 'same-origin',
		headers,
		body: isForm ? body : body ? JSON.stringify(body) : undefined,
	});

	const data = await res.json().catch(() => null);
	if (!res.ok) {
		const error = new Error(data?.message || 'Algo deu errado. Tente novamente.');
		error.status = res.status;
		error.code = data?.code;
		throw error;
	}
	return data;
}

/** Navega com transição do Swup quando disponível. */
export function navigate(url) {
	if (window.spotinderNavigate) window.spotinderNavigate(url);
	else window.location.href = url;
}
