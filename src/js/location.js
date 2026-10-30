import { api, config } from './api';

/**
 * Localização aproximada.
 * A coordenada exata do navegador é arredondada AQUI, no aparelho, para o centro de uma
 * célula de ~5 km (mesma grade de inc/location.php) — só a célula é enviada ao servidor.
 */
const GRID = config.location?.grid || 0.05;
const REFRESH_KEY = 'spotinder:locationCheckedAt';
const REFRESH_EVERY = 30 * 60 * 1000; // No máximo uma checagem a cada 30 min.

export function snap(value) {
	return Math.round((Math.floor(value / GRID) * GRID + GRID / 2) * 10000) / 10000;
}

/** Pede a posição ao navegador (baixa precisão: não precisamos de GPS fino). */
function currentCell() {
	return new Promise((resolve, reject) => {
		if (!('geolocation' in navigator)) {
			reject(new Error('Seu navegador não oferece localização.'));
			return;
		}
		navigator.geolocation.getCurrentPosition(
			(pos) => resolve({ lat: snap(pos.coords.latitude), lng: snap(pos.coords.longitude) }),
			(err) =>
				reject(
					new Error(
						err.code === err.PERMISSION_DENIED
							? 'Você bloqueou a localização no navegador. Libere nas configurações do site para ativar.'
							: 'Não conseguimos obter sua localização agora. Tente de novo.',
					),
				),
			{ enableHighAccuracy: false, timeout: 15000, maximumAge: 15 * 60 * 1000 },
		);
	});
}

/** Primeira ativação (com aceite) ou atualização manual. */
export async function shareLocation(consent) {
	const cell = await currentCell();
	const me = await api('me/location', { method: 'POST', body: { ...cell, consent } });
	config.location = { ...config.location, enabled: true, ...cell };
	localStorage.setItem(REFRESH_KEY, String(Date.now()));
	return me;
}

export async function disableLocation() {
	const me = await api('me/location', { method: 'DELETE' });
	config.location = { ...config.location, enabled: false, lat: null, lng: null };
	return me;
}

/**
 * Atualização automática: só se a pessoa ativou a localização, se o navegador JÁ concedeu
 * permissão (nunca abre o pedido de permissão sozinha) e se a célula mudou.
 */
export async function refreshLocationQuietly() {
	try {
		if (!config.isMember || !config.location?.enabled || !navigator.permissions) return;
		const last = Number(localStorage.getItem(REFRESH_KEY) || 0);
		if (Date.now() - last < REFRESH_EVERY) return;

		const status = await navigator.permissions.query({ name: 'geolocation' });
		if (status.state !== 'granted') return;

		localStorage.setItem(REFRESH_KEY, String(Date.now()));
		const cell = await currentCell();
		if (cell.lat === config.location.lat && cell.lng === config.location.lng) return;

		await api('me/location', { method: 'POST', body: cell });
		config.location = { ...config.location, ...cell };
		// O deck aberto recarrega com as distâncias novas.
		window.dispatchEvent(new CustomEvent('spotinder:region-changed'));
	} catch (e) {
		// Silencioso: a região anterior continua valendo.
	}
}
