import { useEffect, useState } from 'react';
import { api, config } from '../api';
import { ErrorBox, Icon, Spinner, SpotifyAttribution, gradientFor, initials } from './ui';

const limits = () => config.music || { catalog: false, minArtists: 3, maxArtists: 15, maxTracks: 10 };

/** Seleção inicial a partir do perfil (contas antigas do Spotify podem ter mais itens que o limite). */
export const musicFromProfile = (me) => ({
	artists: (me.artists || []).slice(0, limits().maxArtists),
	tracks: (me.tracks || []).slice(0, limits().maxTracks),
});

/** Corpo enviado à API: só IDs (ou o nome, para artistas fora do catálogo). O servidor confere o resto. */
export const musicToBody = (music) => ({
	artists: music.artists.map((a) => (a.id ? { id: a.id } : { name: a.name })),
	tracks: music.tracks.map((tr) => ({ id: tr.id })),
});

export const musicReady = (music) => music.artists.length >= limits().minArtists;

const artistKey = (a) => a.id || a.name.trim().toLowerCase();

/** Busca no catálogo com espera curta entre teclas; respostas atrasadas são ignoradas. */
function useCatalogSearch(type, query) {
	const [state, setState] = useState({ items: [], busy: false, error: null });

	useEffect(() => {
		const q = query.trim();
		if (!limits().catalog || q.length < 2) {
			setState({ items: [], busy: false, error: null });
			return undefined;
		}
		let alive = true;
		setState((s) => ({ ...s, busy: true, error: null }));
		const timer = setTimeout(() => {
			api('catalog', { query: { type, q } })
				.then((items) => alive && setState({ items, busy: false, error: null }))
				.catch((error) => alive && setState({ items: [], busy: false, error }));
		}, 300);
		return () => {
			alive = false;
			clearTimeout(timer);
		};
	}, [type, query]);

	return state;
}

function move(list, from, to) {
	const next = [...list];
	const [item] = next.splice(from, 1);
	next.splice(to, 0, item);
	return next;
}

function Thumb({ image, name, round = false }) {
	return (
		<span className={`grid size-10 shrink-0 place-items-center overflow-hidden text-xs font-bold text-white/70 ${round ? 'rounded-full' : 'rounded-lg'}`} style={image ? undefined : { background: gradientFor(name) }}>
			{image ? <img src={image} alt="" loading="lazy" className="size-full object-cover" /> : initials(name)}
		</span>
	);
}

function Row({ image, name, detail, round }) {
	return (
		<>
			<Thumb image={image} name={name} round={round} />
			<span className="min-w-0 flex-1">
				<span className="block truncate text-sm font-medium text-white">{name}</span>
				{detail && <span className="block truncate text-xs text-white/45">{detail}</span>}
			</span>
		</>
	);
}

/** Lista escolhida, na ordem: dá para subir um item ou removê-lo. */
function Selected({ items, itemKey, onChange, render, empty }) {
	if (!items.length) return <p className="rounded-2xl border border-dashed border-white/10 p-4 text-center text-sm text-white/45">{empty}</p>;
	return (
		<ol className="space-y-1.5">
			{items.map((item, i) => (
				<li key={itemKey(item)} className="flex items-center gap-3 rounded-2xl bg-white/5 p-2 pr-2.5">
					<span className="w-5 shrink-0 text-center text-xs font-semibold text-white/40">{i + 1}</span>
					{render(item)}
					<button type="button" className="btn-icon size-8 shrink-0 text-sm disabled:opacity-30" disabled={i === 0} onClick={() => onChange(move(items, i, i - 1))} aria-label={`Subir ${item.name}`}>
						↑
					</button>
					<button type="button" className="btn-icon size-8 shrink-0" onClick={() => onChange(items.filter((_, j) => j !== i))} aria-label={`Remover ${item.name}`}>
						<Icon.x className="size-3.5" />
					</button>
				</li>
			))}
		</ol>
	);
}

function Results({ search, children }) {
	return (
		<ul className="divide-y divide-white/5 overflow-hidden rounded-2xl border border-white/10">
			{search.busy && !search.items.length && (
				<li className="grid place-items-center p-4">
					<Spinner className="size-6" />
				</li>
			)}
			{children}
		</ul>
	);
}

function ResultButton({ added, disabled, onClick, children }) {
	return (
		<button type="button" className="flex w-full items-center gap-3 p-2.5 text-left transition hover:bg-white/5 disabled:cursor-default disabled:opacity-50 disabled:hover:bg-transparent" disabled={added || disabled} onClick={onClick}>
			{children}
			<span className="shrink-0 text-xs font-semibold text-accent">{added ? 'Adicionado' : '+ Adicionar'}</span>
		</button>
	);
}

function Counter({ count, max, min }) {
	const short = min && count < min;
	return (
		<span className={`text-xs ${short ? 'text-rose-200' : 'text-white/45'}`}>
			{count}/{max}
			{min ? ` · mínimo ${min}` : ' · opcional'}
		</span>
	);
}

export function ArtistPicker({ value, onChange }) {
	const { catalog, minArtists, maxArtists } = limits();
	const [query, setQuery] = useState('');
	const search = useCatalogSearch('artist', query);
	const keys = new Set(value.map(artistKey));
	const full = value.length >= maxArtists;
	const name = query.trim().slice(0, 80);
	const exact = search.items.some((a) => a.name.toLowerCase() === name.toLowerCase());

	const add = (artist) => {
		if (full || keys.has(artistKey(artist))) return;
		onChange([...value, artist]);
		setQuery('');
	};
	const addByName = () => name && add({ id: '', name, image: '', url: '' });

	return (
		<div className="space-y-3">
			<div className="flex items-baseline justify-between gap-3">
				<h3 className="font-semibold text-white">Artistas favoritos</h3>
				<Counter count={value.length} max={maxArtists} min={minArtists} />
			</div>

			<input
				className="field"
				type="search"
				placeholder={full ? 'Lista completa' : catalog ? 'Busque um artista ou banda' : 'Digite o nome de um artista ou banda'}
				value={query}
				disabled={full}
				onChange={(e) => setQuery(e.target.value)}
				onKeyDown={(e) => {
					if (e.key !== 'Enter') return;
					e.preventDefault();
					const first = search.items.find((a) => !keys.has(artistKey(a)));
					if (catalog && first) add(first);
					else if (!catalog) addByName();
				}}
				autoComplete="off"
			/>

			{name.length >= 2 && (
				<Results search={search}>
					{search.items.map((a) => (
						<li key={a.id}>
							<ResultButton added={keys.has(artistKey(a))} disabled={full} onClick={() => add(a)}>
								<Row image={a.image} name={a.name} detail={a.genres?.slice(0, 2).join(' · ')} round />
							</ResultButton>
						</li>
					))}
					{!search.busy && !exact && (
						<li>
							<button type="button" className="w-full p-3 text-left text-sm text-white/70 transition hover:bg-white/5" disabled={full} onClick={addByName}>
								+ Adicionar “{name}” só pelo nome {catalog && <span className="text-white/40">(sem foto — bom para bandas locais)</span>}
							</button>
						</li>
					)}
				</Results>
			)}
			<ErrorBox error={search.error} />

			<Selected items={value} itemKey={artistKey} onChange={onChange} empty="Nenhum artista ainda. Comece pelos que você mais curte." render={(a) => <Row image={a.image} name={a.name} round />} />
			<p className="text-xs text-white/40">Os primeiros da lista aparecem em destaque no seu cartão e pesam mais na compatibilidade.</p>
		</div>
	);
}

export function TrackPicker({ value, onChange }) {
	const { catalog, maxTracks } = limits();
	const [query, setQuery] = useState('');
	const search = useCatalogSearch('track', query);
	const ids = new Set(value.map((tr) => tr.id));
	const full = value.length >= maxTracks;
	const byline = (tr) => tr.artists.map((a) => a.name).join(', ');

	const add = (track) => {
		if (full || ids.has(track.id)) return;
		onChange([...value, track]);
		setQuery('');
	};

	return (
		<div className="space-y-3">
			<div className="flex items-baseline justify-between gap-3">
				<h3 className="font-semibold text-white">Músicas favoritas</h3>
				<Counter count={value.length} max={maxTracks} />
			</div>
			<p className="text-sm text-white/55">Viram a trilha sonora dos seus matches e entram na playlist em comum.</p>

			{catalog ? (
				<>
					<input
						className="field"
						type="search"
						placeholder={full ? 'Lista completa' : 'Busque uma música'}
						value={query}
						disabled={full}
						onChange={(e) => setQuery(e.target.value)}
						onKeyDown={(e) => {
							if (e.key !== 'Enter') return;
							e.preventDefault();
							const first = search.items.find((tr) => !ids.has(tr.id));
							if (first) add(first);
						}}
						autoComplete="off"
					/>
					{query.trim().length >= 2 && (
						<Results search={search}>
							{search.items.map((tr) => (
								<li key={tr.id}>
									<ResultButton added={ids.has(tr.id)} disabled={full} onClick={() => add(tr)}>
										<Row image={tr.image} name={tr.name} detail={byline(tr)} />
									</ResultButton>
								</li>
							))}
							{!search.busy && !search.error && !search.items.length && <li className="p-3 text-sm text-white/45">Nada encontrado.</li>}
						</Results>
					)}
					<ErrorBox error={search.error} />
				</>
			) : (
				<p className="rounded-2xl bg-white/5 p-4 text-sm text-white/55">A busca de músicas está indisponível no momento. Você pode adicionar depois, em Perfil.</p>
			)}

			{(catalog || value.length > 0) && <Selected items={value} itemKey={(tr) => tr.id} onChange={onChange} empty="Nenhuma música ainda." render={(tr) => <Row image={tr.image} name={tr.name} detail={byline(tr)} />} />}
		</div>
	);
}

/**
 * Seletor de artistas e músicas favoritos. A busca usa o catálogo público do
 * Spotify pelo servidor (credencial do app): ninguém conecta a própria conta.
 */
export default function MusicPicker({ music, setMusic }) {
	return (
		<div className="space-y-8">
			<ArtistPicker value={music.artists} onChange={(artists) => setMusic((m) => ({ ...m, artists }))} />
			<div className="border-t border-white/5 pt-6">
				<TrackPicker value={music.tracks} onChange={(tracks) => setMusic((m) => ({ ...m, tracks }))} />
			</div>
			{limits().catalog && <SpotifyAttribution />}
		</div>
	);
}
