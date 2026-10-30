import { useCallback, useEffect, useState } from 'react';
import { api, config, t } from '../api';
import MatchDetail from './MatchDetail';
import { Avatar, ErrorBox, Loading } from './ui';

const idFromHash = () => {
	const m = window.location.hash.match(/^#match-(\d+)$/);
	return m ? Number(m[1]) : null;
};

/**
 * Lista de matches + detalhe (#match-ID na URL).
 */
export default function Matches({ events = [] }) {
	const [list, setList] = useState(null);
	const [error, setError] = useState(null);
	const [selected, setSelected] = useState(idFromHash);

	const load = useCallback(async () => {
		setError(null);
		try {
			setList(await api('matches'));
		} catch (e) {
			setError(e);
		}
	}, []);

	useEffect(() => {
		load();
		const onHash = () => setSelected(idFromHash());
		window.addEventListener('hashchange', onHash);
		return () => window.removeEventListener('hashchange', onHash);
	}, [load]);

	const open = (id) => {
		window.history.replaceState(window.history.state, '', id ? `#match-${id}` : window.location.pathname);
		setSelected(id);
		setList((current) => current?.map((m) => (m.id === id ? { ...m, isNew: false } : m)));
		if (window.innerWidth < 1024) window.scrollTo({ top: 0, behavior: 'smooth' });
	};

	const onRemoved = (id) => {
		setList((current) => current.filter((m) => m.id !== id));
		open(null);
	};

	if (!list && !error) return <Loading />;

	return (
		<div className="grid gap-6 lg:grid-cols-[22rem_1fr]">
			<aside className={selected ? 'hidden lg:block' : ''}>
				<h1 className="mb-4 text-2xl font-semibold text-white">{t('matches_title')}</h1>
				<ErrorBox error={error} onRetry={load} />
				{list?.length === 0 && (
					<div className="card space-y-4 p-6 text-center">
						<p className="text-white/60">{t('matches_empty_text')}</p>
						<a href={config.urls.discover} className="btn-primary">
							Descobrir pessoas
						</a>
					</div>
				)}
				<ul className="space-y-2">
					{list?.map((m) => (
						<li key={m.id}>
							<button
								type="button"
								onClick={() => open(m.id)}
								className={`flex w-full items-center gap-3 rounded-2xl border p-3 text-left transition ${selected === m.id ? 'border-brand/60 bg-brand/10' : 'border-white/5 bg-surface hover:border-white/15'}`}
							>
								<span className="relative">
									<Avatar person={m.partner} />
									{m.isNew && <span className="absolute -right-0.5 -top-0.5 size-3.5 rounded-full border-2 border-surface bg-accent" aria-label="novo" />}
								</span>
								<span className="min-w-0 flex-1">
									<span className="flex items-center gap-2">
										<span className="truncate font-semibold text-white">{m.partner.name}</span>
										{m.partner.isDemo && <span className="rounded bg-white/10 px-1.5 text-[10px] uppercase text-white/50">teste</span>}
									</span>
									<span className="block truncate text-xs text-white/50">{m.partner.artists.map((a) => a.name).join(' · ')}</span>
								</span>
								<span className="flex shrink-0 flex-col items-end gap-1">
									<span className="text-sm font-semibold text-accent">{m.score}%</span>
									{m.invites > 0 && <span className="rounded-full bg-highlight px-2 py-0.5 text-[10px] font-bold text-ink">convite</span>}
								</span>
							</button>
						</li>
					))}
				</ul>
			</aside>

			<section className={selected ? '' : 'hidden lg:block'}>
				{selected ? (
					<MatchDetail key={selected} id={selected} events={events} onBack={() => open(null)} onRemoved={onRemoved} />
				) : (
					<div className="card hidden min-h-[60vh] place-items-center p-10 text-center lg:grid">
						<div className="space-y-3">
							<span className="vinyl vinyl-lg mx-auto block" aria-hidden="true" />
							<p className="text-white/60">Escolha um match para ver as redes, a trilha sonora e a playlist de vocês.</p>
						</div>
					</div>
				)}
			</section>
		</div>
	);
}
