import { useEffect, useState } from 'react';
import { api, config } from '../api';
import { ErrorBox } from './ui';

const STATUS = {
	pending: { label: 'aguardando', className: 'bg-white/10 text-white/70' },
	accepted: { label: 'combinado ✓', className: 'bg-emerald-400/20 text-emerald-200' },
	declined: { label: 'recusado', className: 'bg-rose-400/15 text-rose-200' },
};

/**
 * Convites entre matches — o "chat" do app: uma proposta objetiva por vez.
 */
export default function Invites({ matchId, partner, invites, events, presetEvent, onChange }) {
	const [mode, setMode] = useState('event');
	const [form, setForm] = useState({ event_id: '', kind: config.inviteTypes[0]?.slug || '', proposed_at: '', place: '', note: '' });
	const [busy, setBusy] = useState(false);
	const [error, setError] = useState(null);
	const [open, setOpen] = useState(false);

	useEffect(() => {
		if (presetEvent) {
			setMode('event');
			setForm((f) => ({ ...f, event_id: String(presetEvent) }));
			setOpen(true);
			document.getElementById(`invite-form-${matchId}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
		}
	}, [presetEvent, matchId]);

	const set = (key) => (e) => setForm((f) => ({ ...f, [key]: e.target.value }));

	const submit = async (e) => {
		e.preventDefault();
		setBusy(true);
		setError(null);
		try {
			const body = mode === 'event' ? { event_id: Number(form.event_id), note: form.note } : { kind: form.kind, proposed_at: form.proposed_at, place: form.place, note: form.note };
			onChange(await api(`matches/${matchId}/invites`, { method: 'POST', body }));
			setOpen(false);
			setForm((f) => ({ ...f, note: '', place: '', proposed_at: '' }));
		} catch (err) {
			setError(err);
		} finally {
			setBusy(false);
		}
	};

	const respond = async (invite, status) => {
		setError(null);
		try {
			await api(`invites/${invite.id}`, { method: 'POST', body: { status } });
			onChange(invites.map((i) => (i.id === invite.id ? { ...i, status } : i)).filter((i) => i.status !== 'cancelled'));
			if (status === 'accepted') {
				// Recarrega para trazer os links de calendário.
				const detail = await api(`matches/${matchId}`);
				onChange(detail.invites);
			}
		} catch (err) {
			setError(err);
		}
	};

	const hasPendingFromMe = invites.some((i) => i.mine && i.status === 'pending');

	return (
		<section className="card space-y-4 p-6" aria-labelledby={`convites-${matchId}`}>
			<div className="flex flex-wrap items-center justify-between gap-3">
				<div>
					<h2 id={`convites-${matchId}`} className="text-lg font-semibold text-white">
						Combinar um rolê
					</h2>
					<p className="text-sm text-white/50">Sem chat: mande uma proposta e {partner.name} aceita ou não.</p>
				</div>
				{!open && !hasPendingFromMe && (
					<button type="button" className="btn-primary" onClick={() => setOpen(true)}>
						Fazer convite
					</button>
				)}
			</div>

			<ErrorBox error={error} />

			{open && (
				<form id={`invite-form-${matchId}`} onSubmit={submit} className="space-y-4 rounded-2xl border border-white/10 bg-ink/40 p-4">
					<div className="flex gap-2" role="tablist">
						<button type="button" role="tab" aria-selected={mode === 'event'} className={`chip ${mode === 'event' ? 'chip-active' : ''}`} onClick={() => setMode('event')}>
							Evento da agenda
						</button>
						<button type="button" role="tab" aria-selected={mode === 'free'} className={`chip ${mode === 'free' ? 'chip-active' : ''}`} onClick={() => setMode('free')}>
							Outro rolê
						</button>
					</div>

					{mode === 'event' ? (
						<label className="block">
							<span className="label">Evento</span>
							<select className="field" required value={form.event_id} onChange={set('event_id')}>
								<option value="">Escolha um evento…</option>
								{events.map((ev) => (
									<option key={ev.id} value={ev.id}>
										{ev.title} — {ev.dateLabel}
									</option>
								))}
							</select>
						</label>
					) : (
						<>
							<fieldset>
								<legend className="label">O que vocês vão fazer?</legend>
								<div className="flex flex-wrap gap-2">
									{config.inviteTypes.map((type) => (
										<button key={type.slug} type="button" className={`chip ${form.kind === type.slug ? 'chip-active' : ''}`} onClick={() => setForm((f) => ({ ...f, kind: type.slug }))}>
											{type.emoji} {type.label}
										</button>
									))}
								</div>
							</fieldset>
							<div className="grid gap-3 sm:grid-cols-2">
								<label className="block">
									<span className="label">Quando</span>
									<input type="datetime-local" className="field" required value={form.proposed_at} onChange={set('proposed_at')} />
								</label>
								<label className="block">
									<span className="label">Onde (lugar público)</span>
									<input type="text" className="field" required maxLength={190} placeholder="Ex.: café da praça central" value={form.place} onChange={set('place')} />
								</label>
							</div>
						</>
					)}

					<label className="block">
						<span className="label">Recado curto (opcional)</span>
						<input type="text" className="field" maxLength={140} placeholder="Ex.: te encontro na entrada, vou de camiseta amarela" value={form.note} onChange={set('note')} />
						<span className="mt-1 block text-right text-xs text-white/40">{form.note.length}/140</span>
					</label>

					<div className="flex justify-end gap-2">
						<button type="button" className="btn-ghost" onClick={() => setOpen(false)}>
							Cancelar
						</button>
						<button type="submit" className="btn-primary" disabled={busy}>
							{busy ? 'Enviando…' : 'Enviar convite'}
						</button>
					</div>
				</form>
			)}

			{invites.length > 0 && (
				<ul className="space-y-3">
					{invites.map((invite) => (
						<li key={invite.id} className="space-y-3 rounded-2xl bg-white/5 p-4">
							<div className="flex flex-wrap items-center justify-between gap-2">
								<p className="text-xs text-white/50">{invite.mine ? 'Você convidou' : `${partner.name} convidou você`}</p>
								<span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${STATUS[invite.status]?.className}`}>{STATUS[invite.status]?.label}</span>
							</div>
							<p className="font-semibold text-white">
								{invite.event ? (
									<a href={invite.event.url} className="hover:underline">
										{invite.event.title}
									</a>
								) : (
									invite.kindLabel
								)}
							</p>
							<p className="text-sm text-white/60">{[invite.when, invite.place].filter(Boolean).join(' · ')}</p>
							{invite.note && <p className="rounded-xl bg-ink/50 px-3 py-2 text-sm italic text-white/75">“{invite.note}”</p>}

							<div className="flex flex-wrap gap-2">
								{!invite.mine && invite.status === 'pending' && (
									<>
										<button type="button" className="btn-primary" onClick={() => respond(invite, 'accepted')}>
											Topo!
										</button>
										<button type="button" className="btn-ghost" onClick={() => respond(invite, 'declined')}>
											Dessa vez não
										</button>
									</>
								)}
								{invite.mine && invite.status === 'pending' && (
									<button type="button" className="btn-ghost" onClick={() => respond(invite, 'cancelled')}>
										Cancelar convite
									</button>
								)}
								{invite.calendar && (
									<>
										<a className="btn-ghost text-xs" href={invite.calendar.google} target="_blank" rel="noopener noreferrer">
											Google Agenda
										</a>
										<a className="btn-ghost text-xs" href={invite.calendar.ics} data-no-swup>
											Baixar .ics
										</a>
									</>
								)}
							</div>
						</li>
					))}
				</ul>
			)}
		</section>
	);
}
