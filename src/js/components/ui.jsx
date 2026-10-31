import { useEffect, useRef } from 'react';

export function Spinner({ className = '' }) {
	return <span className={`loader ${className}`} aria-label="Carregando" />;
}

export function Loading() {
	return (
		<div className="grid min-h-[50vh] place-items-center">
			<Spinner />
		</div>
	);
}

export function ErrorBox({ error, onRetry }) {
	if (!error) return null;
	return (
		<div role="alert" className="flex items-center justify-between gap-4 rounded-2xl border border-rose-400/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
			<span>{error.message || String(error)}</span>
			{onRetry && (
				<button type="button" className="font-semibold underline" onClick={onRetry}>
					Tentar de novo
				</button>
			)}
		</div>
	);
}

/** Avatar: foto escolhida pela pessoa ou, sem foto, iniciais sobre gradiente. */
export function Avatar({ person, size = 'size-12', className = '' }) {
	return (
		<span
			className={`grid shrink-0 place-items-center overflow-hidden rounded-full font-semibold text-white ring-2 ring-white/10 ${size} ${className}`}
			style={{ background: person?.gradient }}
			aria-hidden="true"
		>
			{person?.photo ? <img src={person.photo} alt="" draggable="false" loading="lazy" className="size-full object-cover" /> : person?.initials}
		</span>
	);
}

export function CompatRing({ score = 0, size = 64 }) {
	const r = 26;
	const c = 2 * Math.PI * r;
	return (
		<span className="relative inline-grid place-items-center" style={{ width: size, height: size }} title={`${score}% de compatibilidade`}>
			<svg viewBox="0 0 64 64" className="absolute inset-0 -rotate-90">
				<circle cx="32" cy="32" r={r} fill="rgb(11 10 16 / .7)" stroke="rgb(255 255 255 / .12)" strokeWidth="5" />
				{score > 0 && <circle cx="32" cy="32" r={r} fill="none" stroke="var(--color-accent)" strokeWidth="5" strokeLinecap="round" strokeDasharray={`${(score / 100) * c} ${c}`} />}
			</svg>
			<span className="relative text-sm font-bold text-white">
				{score}
				<span className="text-[10px] font-medium">%</span>
			</span>
		</span>
	);
}

/** Tile de artista com link de volta ao Spotify (exigência da Developer Policy II.4.2). */
export function ArtistTile({ artist, className = '', showName = true, rank = null, large = false }) {
	return (
		<a
			href={artist.url}
			target="_blank"
			rel="noopener noreferrer"
			draggable="false"
			className={`group relative block overflow-hidden bg-surface-2 ${className}`}
			style={!artist.image ? { background: gradientFor(artist.name) } : undefined}
			title={`${artist.name} no Spotify`}
		>
			{artist.image ? (
				<img src={artist.image} alt="" loading="lazy" draggable="false" className="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-105" />
			) : (
				<span className="absolute inset-0 grid place-items-center text-3xl font-bold text-white/30">{initials(artist.name)}</span>
			)}
			{artist.shared && <span className="absolute left-2 top-2 rounded-full bg-accent px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-white shadow-lg">em comum</span>}
			{showName && (
				// No cartão do deck, o avatar da pessoa sobrepõe o canto inferior esquerdo do tile grande: o rótulo sobe.
				<span className={`absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent leading-tight text-white ${large ? 'p-4 pb-12 pt-14' : 'p-2.5 pt-8'}`}>
					{rank && <span className={`block font-semibold uppercase tracking-widest text-white/60 ${large ? 'mb-0.5 text-[11px]' : 'text-[9px]'}`}>#{rank} favorito</span>}
					<span className={`block font-semibold ${large ? 'text-xl' : 'text-sm'}`}>{artist.name}</span>
				</span>
			)}
		</a>
	);
}

export function SpotifyAttribution({ className = '' }) {
	return <p className={`text-[11px] text-white/35 ${className}`}>Artistas e músicas via Spotify</p>;
}

export function Modal({ open, onClose, children, labelledBy, className = '' }) {
	const ref = useRef(null);
	useEffect(() => {
		if (!open) return undefined;
		const onKey = (e) => e.key === 'Escape' && onClose?.();
		document.addEventListener('keydown', onKey);
		document.documentElement.style.overflow = 'hidden';
		ref.current?.focus();
		return () => {
			document.removeEventListener('keydown', onKey);
			document.documentElement.style.overflow = '';
		};
	}, [open, onClose]);

	if (!open) return null;
	return (
		<div className="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-ink/85 p-4 backdrop-blur-md" onClick={(e) => e.target === e.currentTarget && onClose?.()}>
			<div ref={ref} tabIndex={-1} role="dialog" aria-modal="true" aria-labelledby={labelledBy} className={`card w-full max-w-lg p-6 outline-none animate-pop md:p-8 ${className}`}>
				{children}
			</div>
		</div>
	);
}

export function Toggle({ checked, onChange, label, description }) {
	return (
		<label className="flex cursor-pointer items-start justify-between gap-6 py-4">
			<span>
				<span className="block font-medium text-white">{label}</span>
				{description && <span className="mt-1 block text-sm text-white/55">{description}</span>}
			</span>
			<span className="relative mt-1 inline-flex shrink-0">
				<input type="checkbox" className="peer sr-only" checked={checked} onChange={(e) => onChange(e.target.checked)} />
				<span className="h-7 w-12 rounded-full bg-white/15 transition peer-checked:bg-brand peer-focus-visible:ring-2 peer-focus-visible:ring-highlight" />
				<span className="absolute left-1 top-1 size-5 rounded-full bg-white transition peer-checked:translate-x-5" />
			</span>
		</label>
	);
}

export function Checkbox({ checked, onChange, children }) {
	return (
		<label className="flex cursor-pointer items-start gap-3 rounded-2xl border border-white/10 bg-white/[0.03] p-4 text-sm leading-relaxed text-white/80 has-[:checked]:border-brand/60 has-[:checked]:bg-brand/10">
			<input type="checkbox" className="mt-0.5 size-5 shrink-0 accent-[var(--color-brand)]" checked={checked} onChange={(e) => onChange(e.target.checked)} />
			<span>{children}</span>
		</label>
	);
}

export function initials(name = '') {
	return name
		.trim()
		.split(/\s+/)
		.slice(0, 2)
		.map((p) => p[0]?.toUpperCase())
		.join('');
}

export function gradientFor(seed = '') {
	let h = 0;
	for (let i = 0; i < seed.length; i++) h = (h * 31 + seed.charCodeAt(i)) % 360;
	return `linear-gradient(135deg, hsl(${h} 70% 45%), hsl(${(h + 60) % 360} 75% 30%))`;
}

export const Icon = {
	heart: (p) => (
		<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" {...p}>
			<path d="M12 21s-7.5-4.6-7.5-10.6A4.3 4.3 0 0 1 12 7.6a4.3 4.3 0 0 1 7.5 2.8C19.5 16.4 12 21 12 21Z" />
		</svg>
	),
	x: (p) => (
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.6" strokeLinecap="round" aria-hidden="true" {...p}>
			<path d="M6 6l12 12M18 6 6 18" />
		</svg>
	),
	play: (p) => (
		<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" {...p}>
			<path d="M8 5.5v13l11-6.5-11-6.5Z" />
		</svg>
	),
	external: (p) => (
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" {...p}>
			<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5" />
		</svg>
	),
	shield: (p) => (
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" {...p}>
			<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6l-8-3Z" />
			<path d="m9 12 2 2 4-4" />
		</svg>
	),
	back: (p) => (
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" {...p}>
			<path d="M15 6l-6 6 6 6" />
		</svg>
	),
};
