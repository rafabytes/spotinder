import { useCallback, useEffect, useRef, useState } from 'react';
import { api, config, t } from '../api';
import MatchModal from './MatchModal';
import SwipeCard from './SwipeCard';
import { ErrorBox, Icon, Loading } from './ui';

/**
 * Deck de descoberta estilo swipe.
 */
export default function Deck() {
	const [cards, setCards] = useState([]);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [intention, setIntention] = useState('');
	const [fling, setFling] = useState(null);
	const [match, setMatch] = useState(null);
	const [maxDistance, setMaxDistance] = useState(config.location?.max || 0);
	const seen = useRef(new Set());
	const fetching = useRef(false);

	const load = useCallback(
		async (append = false) => {
			if (fetching.current) return;
			fetching.current = true;
			if (!append) setLoading(true);
			setError(null);
			try {
				const deck = await api('deck', { query: { intention } });
				const fresh = deck.filter((p) => !seen.current.has(p.id));
				setCards((current) => {
					const ids = new Set(current.map((c) => c.id));
					return append ? [...current, ...fresh.filter((p) => !ids.has(p.id))] : fresh;
				});
			} catch (e) {
				setError(e);
			} finally {
				fetching.current = false;
				setLoading(false);
			}
		},
		[intention],
	);

	useEffect(() => {
		load(false);
		const reload = () => load(false);
		window.addEventListener('spotinder:region-changed', reload);
		return () => window.removeEventListener('spotinder:region-changed', reload);
	}, [load]);

	// A distância máxima fica salva no perfil (vale em qualquer aparelho).
	const changeDistance = async (value) => {
		setMaxDistance(value);
		try {
			await api('me', { method: 'POST', body: { maxDistance: value } });
			config.location.max = value;
			load(false);
		} catch (e) {
			setError(e);
		}
	};

	const top = cards[0];

	const onDone = useCallback(
		async (action) => {
			const card = cards[0];
			if (!card) return;
			seen.current.add(card.id);
			setFling(null);
			setCards((current) => current.slice(1));
			if (cards.length <= 3) load(true);

			try {
				const result = await api('swipe', { method: 'POST', body: { target: card.id, action } });
				if (result?.match) setMatch(result.match);
			} catch (e) {
				setError(e);
			}
		},
		[cards, load],
	);

	// Setas do teclado.
	useEffect(() => {
		const onKey = (e) => {
			if (match || !top || ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) return;
			if (e.key === 'ArrowRight') setFling('like');
			if (e.key === 'ArrowLeft') setFling('pass');
		};
		window.addEventListener('keydown', onKey);
		return () => window.removeEventListener('keydown', onKey);
	}, [top, match]);

	return (
		<div className="mx-auto max-w-md">
			<header className="mb-4 flex items-center justify-between">
				<h1 className="text-2xl font-semibold text-white">{t('deck_title')}</h1>
				<span className="text-xs text-white/40">← passo · curti →</span>
			</header>

			<nav className="-mx-5 mb-5 flex gap-2 overflow-x-auto px-5 pb-1 [scrollbar-width:none]" aria-label="Filtrar por intenção">
				<button type="button" className={`chip shrink-0 ${!intention ? 'chip-active' : ''}`} onClick={() => setIntention('')}>
					Todos
				</button>
				{config.intentions.map((i) => (
					<button key={i.slug} type="button" className={`chip shrink-0 ${intention === i.slug ? 'chip-active' : ''}`} onClick={() => setIntention(i.slug)}>
						{i.emoji} {i.label}
					</button>
				))}
			</nav>

			<div className="-mt-2 mb-4 flex items-center justify-between gap-3 text-sm">
				{config.location?.enabled ? (
					<label className="flex items-center gap-2 text-white/60">
						📍 Distância
						<select className="rounded-full border border-white/10 bg-surface px-3 py-1.5 text-white focus:border-brand focus:outline-none" value={maxDistance} onChange={(e) => changeDistance(Number(e.target.value))}>
							{(config.location.options || [0, 10, 25, 50, 100, 250]).map((km) => (
								<option key={km} value={km}>
									{km ? `até ${km} km` : 'qualquer'}
								</option>
							))}
						</select>
					</label>
				) : (
					<a href={config.urls.profile} className="text-white/50 underline-offset-4 hover:text-white hover:underline">
						📍 Ative sua região aproximada para ver quem está perto
					</a>
				)}
			</div>

			<ErrorBox error={error} onRetry={() => load(false)} />

			{loading ? (
				<Loading />
			) : (
				<>
					{/* Mobile: cabe cabeçalho + filtros + cartão + botões + barra de abas numa tela só. */}
					<div className="relative h-[calc(100dvh-26rem)] min-h-[320px] sm:h-[min(620px,calc(100dvh-17rem))] sm:min-h-[460px]">
						{cards.length === 0 ? (
							<div className="card absolute inset-0 grid place-items-center p-8 text-center">
								<div className="space-y-4">
									<span className="vinyl vinyl-lg mx-auto block" aria-hidden="true" />
									<h2 className="text-xl font-semibold text-white">{t('deck_empty_title')}</h2>
									<p className="text-sm text-white/60">{t('deck_empty_text')}</p>
									<a href={config.urls.events} className="btn-ghost">
										Ver agenda
									</a>
								</div>
							</div>
						) : (
							cards
								.slice(0, 3)
								.map((profile, i) => <SwipeCard key={profile.id} profile={profile} active={i === 0} depth={i} fling={i === 0 ? fling : null} onDone={onDone} />)
								.reverse()
						)}
					</div>

					{top && (
						<div className="mt-4 flex items-center justify-center gap-6 sm:mt-6">
							<button type="button" onClick={() => setFling('pass')} className="grid size-16 place-items-center rounded-full border border-white/15 bg-surface text-rose-300 shadow-xl transition hover:scale-105 hover:border-rose-300/50" aria-label={`Passar ${top.name}`}>
								<Icon.x className="size-7" />
							</button>
							<button type="button" onClick={() => setFling('like')} className="grid size-20 place-items-center rounded-full bg-accent text-white shadow-xl shadow-accent/40 transition hover:scale-105" aria-label={`Curtir ${top.name}`}>
								<Icon.heart className="size-9" />
							</button>
						</div>
					)}
				</>
			)}

			<MatchModal match={match} onClose={() => setMatch(null)} />
		</div>
	);
}
