import { useCallback, useEffect, useState } from 'react';
import { api, config, t } from '../api';
import Invites from './Invites';
import ReportDialog from './ReportDialog';
import SpotifyEmbed from './SpotifyEmbed';
import { ArtistTile, Avatar, CompatRing, ErrorBox, Icon, Loading, SpotifyAttribution } from './ui';

const TAGS = {
	both: { label: 'dos dois', className: 'bg-accent text-white' },
	artist: { label: 'artista em comum', className: 'bg-brand/40 text-white' },
	a: { label: 'sua', className: 'bg-white/10 text-white/70' },
	b: { label: 'dela/dele', className: 'bg-white/10 text-white/70' },
};

/**
 * Detalhe de um match: redes sociais (com aviso), trilha sonora, playlist em comum,
 * eventos e convites (substituem o chat) e ações de segurança.
 */
export default function MatchDetail({ id, events, onBack, onRemoved }) {
	const [data, setData] = useState(null);
	const [error, setError] = useState(null);
	const [playing, setPlaying] = useState(null);
	const [inviteEvent, setInviteEvent] = useState(null);
	const [reporting, setReporting] = useState(false);

	const load = useCallback(async () => {
		setError(null);
		try {
			const detail = await api(`matches/${id}`);
			setData(detail);
			setPlaying(detail.soundtrack || detail.playlist[0] || null);
		} catch (e) {
			setError(e);
		}
	}, [id]);

	useEffect(() => {
		load();
	}, [load]);

	const remove = async (kind) => {
		const name = data.partner.name;
		const question = kind === 'block' ? `Bloquear ${name}? Vocês deixam de se ver no app.` : `Desfazer o match com ${name}?`;
		if (!window.confirm(question)) return;
		try {
			if (kind === 'block') await api('block', { method: 'POST', body: { target: data.partner.id } });
			else await api(`matches/${id}`, { method: 'DELETE' });
			onRemoved(id);
		} catch (e) {
			setError(e);
		}
	};

	if (error && !data) return <ErrorBox error={error} onRetry={load} />;
	if (!data) return <Loading />;

	const { partner } = data;
	const intentions = Object.fromEntries(config.intentions.map((i) => [i.slug, i]));
	// Para quem vê, "a" é a própria pessoa (a ordem segue quem consulta).
	const tagLabel = (tag) => (tag === 'b' ? `de ${partner.name}` : TAGS[tag]?.label);

	return (
		<article className="space-y-6">
			<button type="button" onClick={onBack} className="inline-flex items-center gap-1 text-sm text-white/50 hover:text-white lg:hidden">
				<Icon.back className="size-4" /> Matches
			</button>

			<ErrorBox error={error} />

			{/* Cabeçalho */}
			<header className="card overflow-hidden">
				<div className="grid grid-cols-4 gap-0.5 sm:grid-cols-6">
					{partner.artists.slice(0, 6).map((a, i) => (
						<ArtistTile key={a.id || a.name} artist={a} className={`aspect-square ${i > 3 ? 'hidden sm:block' : ''}`} showName={false} />
					))}
				</div>
				<div className="flex flex-wrap items-center gap-4 p-6">
					<Avatar person={partner} size="size-16 text-xl" />
					<div className="min-w-0 flex-1">
						<h1 className="text-2xl font-semibold text-white">
							{partner.name} {partner.isDemo && <span className="ml-1 rounded bg-white/10 px-1.5 align-middle text-xs uppercase text-white/50">perfil de teste</span>}
						</h1>
						<p className="text-sm text-white/55">{[partner.region ? `Região de ${partner.region}` : partner.city, partner.distance, `match em ${data.createdAt}`].filter(Boolean).join(' · ')}</p>
					</div>
					<CompatRing score={data.score} size={72} />
				</div>
				{(partner.bio || partner.intentions?.length > 0) && (
					<div className="space-y-3 border-t border-white/5 px-6 py-5">
						{partner.bio && <p className="text-white/75">{partner.bio}</p>}
						<ul className="flex flex-wrap gap-1.5">
							{partner.intentions.map((slug) => intentions[slug] && <li key={slug} className="chip text-xs">{intentions[slug].emoji} {intentions[slug].label}</li>)}
						</ul>
					</div>
				)}
			</header>

			{/* Redes sociais */}
			<section className="card space-y-4 p-6" aria-labelledby="redes">
				<h2 id="redes" className="text-lg font-semibold text-white">
					Onde encontrar {partner.name}
				</h2>
				<div className="flex gap-3 rounded-2xl border border-highlight/25 bg-highlight/[0.06] p-4 text-sm text-white/75">
					<Icon.shield className="size-5 shrink-0 text-highlight" />
					<div>
						<p className="font-semibold text-white">{t('social_warning_title')}</p>
						<p className="mt-1 leading-relaxed">{t('social_warning_text')}</p>
					</div>
				</div>
				{partner.socials?.length ? (
					<ul className="grid gap-2 sm:grid-cols-2">
						{partner.socials.map((s) => (
							<li key={s.network}>
								{s.url ? (
									<a href={s.url} target="_blank" rel="noopener noreferrer nofollow" className="flex items-center justify-between rounded-2xl bg-white/5 px-4 py-3 transition hover:bg-white/10">
										<span>
											<span className="block text-xs text-white/45">{s.label}</span>
											<span className="font-medium text-white">{s.handle}</span>
										</span>
										<Icon.external className="size-4 text-white/40" />
									</a>
								) : (
									<span className="block rounded-2xl bg-white/5 px-4 py-3">
										<span className="block text-xs text-white/45">{s.label}</span>
										<span className="select-all font-medium text-white">{s.handle}</span>
									</span>
								)}
							</li>
						))}
					</ul>
				) : (
					<p className="text-sm text-white/50">{partner.name} ainda não adicionou redes sociais. Que tal um convite para um evento?</p>
				)}
			</section>

			{/* Trilha sonora + playlist */}
			<section className="card space-y-5 p-6" aria-labelledby="playlist">
				<div>
					<p className="text-xs uppercase tracking-widest text-accent">{t('soundtrack_label')}</p>
					<h2 id="playlist" className="text-lg font-semibold text-white">
						{t('playlist_label')}
					</h2>
				</div>

				{playing && <SpotifyEmbed track={playing} height={152} />}
				{data.soundtrack && playing === data.soundtrack && <p className="text-xs text-white/50">{data.soundtrack.reason}</p>}

				{data.playlist.length === 0 && (
					<p className="text-sm text-white/50">
						Vocês ainda não escolheram músicas favoritas. Adicione as suas em{' '}
						<a className="underline" href={config.urls.profile}>
							Perfil
						</a>{' '}
						e a playlist aparece aqui.
					</p>
				)}

				<ol className="divide-y divide-white/5">
					{data.playlist.map((track, i) => (
						<li key={`${track.id}-${i}`} className="flex items-center gap-3 py-2.5">
							<button type="button" onClick={() => setPlaying(track)} className={`grid size-11 shrink-0 place-items-center rounded-lg bg-white/5 bg-cover bg-center transition hover:opacity-80 ${playing === track ? 'ring-2 ring-accent' : ''}`} style={track.image ? { backgroundImage: `url(${track.image})` } : undefined} aria-label={`Tocar ${track.name}`}>
								{!track.image && <Icon.play className="size-4 text-white/60" />}
							</button>
							<div className="min-w-0 flex-1">
								<p className="truncate text-sm font-medium text-white">{track.name}</p>
								<p className="truncate text-xs text-white/50">{track.artists.map((a) => a.name).join(', ')}</p>
							</div>
							<span className={`hidden shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold sm:inline ${TAGS[track.tag]?.className}`}>{tagLabel(track.tag)}</span>
							{track.url && (
								<a href={track.url} target="_blank" rel="noopener noreferrer" className="shrink-0 text-white/40 hover:text-white" aria-label={`Abrir ${track.name} no Spotify`}>
									<Icon.external className="size-4" />
								</a>
							)}
						</li>
					))}
				</ol>
				<SpotifyAttribution />
			</section>

			{/* Eventos sugeridos */}
			{data.events.length > 0 && (
				<section className="card space-y-4 p-6" aria-labelledby="eventos">
					<h2 id="eventos" className="text-lg font-semibold text-white">
						Eventos para vocês
					</h2>
					<ul className="grid gap-3 sm:grid-cols-2">
						{data.events.map((ev) => (
							<li key={ev.id} className="flex flex-col gap-2 rounded-2xl bg-white/5 p-4">
								<a href={ev.url} className="font-semibold text-white hover:underline">
									{ev.title}
								</a>
								<p className="text-xs text-white/55">
									{ev.dateLabel} · {ev.city}
								</p>
								{ev.theirs && <p className="text-xs text-highlight">{partner.name} {ev.theirs === 'going' ? 'vai' : 'tem interesse'}</p>}
								{ev.because.length > 0 && <p className="text-xs text-white/60">🎧 {ev.because.slice(0, 3).join(', ')}</p>}
								<button type="button" className="btn-ghost mt-auto justify-center text-xs" onClick={() => setInviteEvent(ev.id)}>
									Convidar para este
								</button>
							</li>
						))}
					</ul>
				</section>
			)}

			{/* Convites */}
			<Invites matchId={id} partner={partner} invites={data.invites} events={events} presetEvent={inviteEvent} onChange={(invites) => setData((d) => ({ ...d, invites }))} />

			{/* Segurança */}
			<section className="card space-y-4 p-6" aria-labelledby="seguranca">
				<h2 id="seguranca" className="flex items-center gap-2 text-lg font-semibold text-white">
					<Icon.shield className="size-5 text-highlight" /> Antes de se encontrar
				</h2>
				<ul className="space-y-2 text-sm text-white/65">
					{config.safetyTips.map((tip) => (
						<li key={tip} className="flex gap-2">
							<span className="text-highlight">•</span> {tip}
						</li>
					))}
				</ul>
				<div className="flex flex-wrap gap-2 border-t border-white/5 pt-4">
					<button type="button" className="btn-ghost" onClick={() => remove('unmatch')}>
						Desfazer match
					</button>
					<button type="button" className="btn-danger" onClick={() => remove('block')}>
						Bloquear
					</button>
					<button type="button" className="btn-danger" onClick={() => setReporting(true)}>
						Denunciar
					</button>
				</div>
			</section>

			<ReportDialog open={reporting} partner={partner} onClose={() => setReporting(false)} onDone={() => onRemoved(id)} />
		</article>
	);
}
