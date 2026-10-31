import { useEffect, useRef, useState } from 'react';
import { gradientFor } from './ui';

/**
 * Player oficial do Spotify (iFrame API).
 * - Sem login no Spotify: toca uma prévia de ~30s; com Premium logado: a faixa completa.
 * - Exibe capa, metadados e link de volta para o Spotify (exigências da Developer Policy II.4/II.5).
 * Faixas sem ID (ex.: perfis de teste ainda sem dados do catálogo) viram um cartão com link de busca.
 */
let apiPromise = null;

function loadIframeApi() {
	if (apiPromise) return apiPromise;
	apiPromise = new Promise((resolve) => {
		window.onSpotifyIframeApiReady = (IFrameAPI) => resolve(IFrameAPI);
		const script = document.createElement('script');
		script.src = 'https://open.spotify.com/embed/iframe-api/v1';
		script.async = true;
		document.body.appendChild(script);
	});
	return apiPromise;
}

export default function SpotifyEmbed({ track, autoplay = false, height = 152 }) {
	const host = useRef(null);
	const controller = useRef(null);
	const [blocked, setBlocked] = useState(false);
	const uri = track?.id ? `spotify:track:${track.id}` : null;

	useEffect(() => {
		if (!uri) return undefined;
		let cancelled = false;

		loadIframeApi().then((IFrameAPI) => {
			if (cancelled || !host.current) return;

			if (controller.current) {
				controller.current.loadUri(uri);
				if (autoplay) controller.current.play();
				return;
			}

			const target = document.createElement('div');
			host.current.replaceChildren(target);
			IFrameAPI.createController(target, { uri, width: '100%', height }, (ctrl) => {
				controller.current = ctrl;
				if (autoplay) {
					ctrl.addListener('ready', () => {
						// Navegadores podem bloquear autoplay; nesse caso o usuário aperta o play do próprio player.
						Promise.resolve(ctrl.play()).catch(() => setBlocked(true));
					});
				}
			});
		});

		return () => {
			cancelled = true;
		};
	}, [uri, autoplay, height]);

	useEffect(
		() => () => {
			controller.current?.destroy?.();
			controller.current = null;
		},
		[],
	);

	if (!track) return null;

	if (!uri) {
		const artist = track.artists?.map((a) => a.name).join(', ');
		const search = `https://open.spotify.com/search/${encodeURIComponent(`${track.name} ${artist}`)}`;
		return (
			<a href={track.url || search} target="_blank" rel="noopener noreferrer" className="flex items-center gap-4 rounded-2xl bg-white/5 p-3 transition hover:bg-white/10">
				<span className="grid size-16 shrink-0 place-items-center rounded-xl text-2xl" style={{ background: track.image ? `center/cover url(${track.image})` : gradientFor(track.name) }} aria-hidden="true">
					{!track.image && '♪'}
				</span>
				<span className="min-w-0 flex-1">
					<span className="block truncate font-semibold text-white">{track.name}</span>
					<span className="block truncate text-sm text-white/60">{artist}</span>
				</span>
				<span className="shrink-0 rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-ink">Ouvir no Spotify</span>
			</a>
		);
	}

	return (
		<div>
			<div ref={host} className="overflow-hidden rounded-2xl bg-white/5" style={{ minHeight: height }} />
			{blocked && <p className="mt-2 text-xs text-white/50">Toque no play para ouvir.</p>}
		</div>
	);
}
