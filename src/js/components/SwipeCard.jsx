import { useEffect, useRef, useState } from 'react';
import { config } from '../api';
import { ArtistTile, Avatar, CompatRing, SpotifyAttribution, gradientFor } from './ui';

const THRESHOLD = 110;

/**
 * Cartão arrastável: artistas favoritos em destaque e foto opcional da pessoa.
 *
 * @param {object}   props.profile  Perfil (spotinder_member_payload, contexto "card").
 * @param {boolean}  props.active   É o cartão do topo?
 * @param {number}   props.depth    Posição na pilha (0 = topo).
 * @param {string}   props.fling    'like' | 'pass' quando acionado pelos botões/teclado.
 * @param {Function} props.onDone   Chamado após a animação de saída.
 */
export default function SwipeCard({ profile, active, depth, fling, onDone }) {
	const [drag, setDrag] = useState({ x: 0, y: 0, dragging: false });
	const [leaving, setLeaving] = useState(null);
	const start = useRef(null);

	const intentions = Object.fromEntries((config.intentions || []).map((i) => [i.slug, i]));

	useEffect(() => {
		if (active && fling && !leaving) setLeaving(fling);
	}, [fling, active, leaving]);

	useEffect(() => {
		if (!leaving) return undefined;
		const id = setTimeout(() => onDone(leaving), 280);
		return () => clearTimeout(id);
	}, [leaving, onDone]);

	// Só vira "arrasto" depois de alguns pixels: assim um toque num artista ainda abre o Spotify.
	const moved = useRef(false);

	const onPointerDown = (e) => {
		if (!active || leaving || e.button > 0) return;
		start.current = { x: e.clientX, y: e.clientY, id: e.pointerId };
		moved.current = false;
	};

	const onPointerMove = (e) => {
		if (!start.current) return;
		const x = e.clientX - start.current.x;
		const y = e.clientY - start.current.y;
		if (!moved.current) {
			if (Math.abs(x) < 8 || Math.abs(x) < Math.abs(y)) return;
			moved.current = true;
			e.currentTarget.setPointerCapture(start.current.id);
		}
		setDrag({ x, y: y * 0.3, dragging: true });
	};

	const onPointerUp = () => {
		if (!start.current) return;
		start.current = null;
		if (!moved.current) return;
		if (drag.x > THRESHOLD) setLeaving('like');
		else if (drag.x < -THRESHOLD) setLeaving('pass');
		else setDrag({ x: 0, y: 0, dragging: false });
	};

	const onClickCapture = (e) => {
		if (moved.current) {
			e.preventDefault();
			e.stopPropagation();
			moved.current = false;
		}
	};

	let transform = `translateY(${depth * 12}px) scale(${1 - depth * 0.04})`;
	if (active) {
		if (leaving) {
			const dir = leaving === 'like' ? 1 : -1;
			transform = `translate(${dir * 140}%, ${drag.y}px) rotate(${dir * 24}deg)`;
		} else {
			transform = `translate(${drag.x}px, ${drag.y}px) rotate(${drag.x / 18}deg)`;
		}
	}

	const likeOpacity = leaving === 'like' ? 1 : Math.max(0, Math.min(1, drag.x / THRESHOLD));
	const passOpacity = leaving === 'pass' ? 1 : Math.max(0, Math.min(1, -drag.x / THRESHOLD));
	const top = profile.artists.slice(0, 3);
	const rest = profile.artists.slice(3);

	return (
		<article
			className="swipe-card absolute inset-0 flex flex-col overflow-hidden rounded-[2rem] border border-white/10 bg-surface shadow-2xl shadow-black/50"
			style={{
				transform,
				zIndex: 10 - depth,
				opacity: depth > 2 ? 0 : 1,
				transition: drag.dragging && !leaving ? 'none' : 'transform .3s cubic-bezier(.2,.8,.2,1), opacity .3s',
				pointerEvents: active ? 'auto' : 'none',
			}}
			aria-hidden={!active}
			onPointerDown={onPointerDown}
			onPointerMove={onPointerMove}
			onPointerUp={onPointerUp}
			onPointerCancel={onPointerUp}
			onClickCapture={onClickCapture}
			onDragStart={(e) => e.preventDefault()}
		>
			{/* Mosaico: o primeiro artista da lista em destaque + os dois seguintes. */}
			<div className="grid h-[44%] shrink-0 grid-cols-3 grid-rows-2 gap-0.5 bg-ink">
				{top.length === 0 && <div className="col-span-3 row-span-2 grid place-items-center text-sm text-white/40">Ainda sem artistas favoritos</div>}
				{top.map((artist, i) => (
					<ArtistTile
						key={artist.id || artist.name}
						artist={artist}
						rank={i + 1}
						large={i === 0}
						className={i === 0 ? (top.length === 1 ? 'col-span-3 row-span-2' : 'col-span-2 row-span-2') : top.length === 2 ? 'row-span-2' : ''}
					/>
				))}
			</div>

			{/* Identidade: foto (ou iniciais) sobreposta ao mosaico. */}
			<header className="relative z-10 flex items-center gap-3 py-3 pl-[7.25rem] pr-5">
				<Avatar person={profile} size="size-20 text-2xl" className="absolute -top-10 left-5 shadow-xl shadow-black/50 ring-4! ring-surface!" />
				<div className="min-w-0 flex-1">
					<h2 className="truncate text-2xl font-semibold leading-tight text-white">{profile.name}</h2>
					<p className="truncate text-sm text-white/55">{[profile.region || profile.city, profile.isDemo ? 'perfil de teste' : null].filter(Boolean).join(' · ')}</p>
					{profile.distance && <p className="truncate text-xs text-highlight/80">📍 {profile.distance}</p>}
				</div>
				{profile.compat && <CompatRing score={profile.compat.score} />}
			</header>

			<span className="swipe-stamp left-6 -rotate-12 border-emerald-400 text-emerald-300" style={{ opacity: likeOpacity }}>
				Curti
			</span>
			<span className="swipe-stamp right-6 rotate-12 border-rose-400 text-rose-300" style={{ opacity: passOpacity }}>
				Passo
			</span>

			<div className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-5 pt-4">
				{profile.compat && (profile.compat.sharedArtists > 0 || profile.compat.sharedTracks > 0) && (
					<p className="rounded-2xl bg-accent/15 px-3 py-2 text-sm text-white/85">
						🎧 {profile.compat.sharedArtists} artista{profile.compat.sharedArtists === 1 ? '' : 's'} em comum
						{profile.compat.sharedTracks > 0 && ` · ${profile.compat.sharedTracks} música${profile.compat.sharedTracks === 1 ? '' : 's'} em comum`}
					</p>
				)}

				{profile.bio && <p className="text-sm leading-relaxed text-white/75">{profile.bio}</p>}

				{profile.intentions?.length > 0 && (
					<ul className="flex flex-wrap gap-1.5">
						{profile.intentions.map((slug) =>
							intentions[slug] ? (
								<li key={slug} className="rounded-full bg-white/5 px-3 py-1 text-xs text-white/75">
									{intentions[slug].emoji} {intentions[slug].label}
								</li>
							) : null,
						)}
					</ul>
				)}

				{rest.length > 0 && (
					<div>
						<p className="mb-2 text-xs uppercase tracking-widest text-white/40">Também ouve</p>
						<ul className="flex flex-wrap gap-1.5">
							{rest.map((a) => (
								<li key={a.id || a.name}>
									<a
										href={a.url}
										target="_blank"
										rel="noopener noreferrer"
										draggable="false"
										title={`${a.name} no Spotify`}
										className={`inline-flex items-center gap-2 rounded-full py-1 pl-1 pr-3 text-xs transition hover:bg-white/15 ${a.shared ? 'bg-accent/25 text-white ring-1 ring-accent/60' : 'bg-white/5 text-white/75'}`}
									>
										<span className="size-7 shrink-0 overflow-hidden rounded-full" style={{ background: gradientFor(a.name) }}>
											{a.image && <img src={a.image} alt="" loading="lazy" draggable="false" className="size-full object-cover" />}
										</span>
										{a.name}
									</a>
								</li>
							))}
						</ul>
					</div>
				)}

				{profile.genres?.length > 0 && <p className="text-xs text-white/40">{profile.genres.join(' · ')}</p>}
				<SpotifyAttribution className="mt-auto" />
			</div>
		</article>
	);
}
