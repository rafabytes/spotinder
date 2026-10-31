import { useEffect, useState } from 'react';
import { api, config } from '../api';
import { Avatar, ErrorBox, GoogleButton, Spinner } from './ui';

/**
 * Presença no evento + quais matches também vão.
 */
export default function EventRsvp({ eventId }) {
	const [data, setData] = useState(null);
	const [error, setError] = useState(null);
	const [busy, setBusy] = useState(false);

	useEffect(() => {
		api(`events/${eventId}/rsvp`).then(setData).catch(setError);
	}, [eventId]);

	const set = async (status) => {
		setBusy(true);
		setError(null);
		try {
			const next = data.mine === status ? '' : status;
			const res = await api(`events/${eventId}/rsvp`, { method: 'POST', body: { status: next } });
			setData((d) => ({ ...d, ...res }));
		} catch (e) {
			setError(e);
		} finally {
			setBusy(false);
		}
	};

	if (error && !data) return <ErrorBox error={error} />;
	if (!data) return <Spinner className="mx-auto block" />;

	return (
		<div className="space-y-4">
			<p className="text-sm text-white/60">
				<strong className="text-white">{data.counts.going}</strong> vão · <strong className="text-white">{data.counts.interested}</strong> com interesse
			</p>

			{data.because?.length > 0 && <p className="rounded-xl bg-brand/15 px-3 py-2 text-xs text-white/80">🎧 Porque você ouve {data.because.slice(0, 3).join(', ')}</p>}

			{config.isMember && config.onboarded ? (
				<div className="grid grid-cols-2 gap-2">
					<button type="button" disabled={busy} className={`${data.mine === 'going' ? 'btn-accent' : 'btn-ghost'} justify-center`} onClick={() => set('going')} aria-pressed={data.mine === 'going'}>
						{data.mine === 'going' ? 'Eu vou ✓' : 'Eu vou'}
					</button>
					<button type="button" disabled={busy} className={`${data.mine === 'interested' ? 'btn-primary' : 'btn-ghost'} justify-center`} onClick={() => set('interested')} aria-pressed={data.mine === 'interested'}>
						{data.mine === 'interested' ? 'Interesse ✓' : 'Tenho interesse'}
					</button>
				</div>
			) : (
				<GoogleButton label="Entre com o Google para marcar presença" className="w-full" />
			)}

			<ErrorBox error={error} />

			{data.matches?.length > 0 && (
				<div className="space-y-2 border-t border-white/5 pt-4">
					<p className="text-xs uppercase tracking-widest text-white/40">Seus matches que vão</p>
					<ul className="space-y-2">
						{data.matches.map((m) => (
							<li key={m.matchId}>
								<a href={`${config.urls.matches}#match-${m.matchId}`} className="flex items-center gap-3 rounded-xl bg-white/5 p-2 pr-3 transition hover:bg-white/10">
									<Avatar person={m} size="size-9 text-xs" />
									<span className="flex-1 text-sm text-white">{m.name}</span>
									<span className="text-xs text-white/50">{m.status === 'going' ? 'vai' : 'interesse'}</span>
								</a>
							</li>
						))}
					</ul>
					<p className="text-xs text-white/40">Convide pelo match para combinar o ponto de encontro.</p>
				</div>
			)}
		</div>
	);
}
