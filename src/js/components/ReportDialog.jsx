import { useState } from 'react';
import { api, config } from '../api';
import { ErrorBox, Modal } from './ui';

export default function ReportDialog({ open, partner, onClose, onDone }) {
	const [reason, setReason] = useState('');
	const [details, setDetails] = useState('');
	const [busy, setBusy] = useState(false);
	const [error, setError] = useState(null);
	const [sent, setSent] = useState(false);

	const submit = async (e) => {
		e.preventDefault();
		setBusy(true);
		setError(null);
		try {
			await api('report', { method: 'POST', body: { target: partner.id, reason, details } });
			setSent(true);
		} catch (err) {
			setError(err);
		} finally {
			setBusy(false);
		}
	};

	const close = () => {
		if (sent) onDone();
		else onClose();
	};

	return (
		<Modal open={open} onClose={close} labelledBy="report-title">
			{sent ? (
				<div className="space-y-4 text-center">
					<h2 id="report-title" className="text-xl font-semibold text-white">
						Denúncia enviada
					</h2>
					<p className="text-sm text-white/60">Obrigado por avisar. {partner.name} foi bloqueado(a) para você e nossa equipe vai analisar o caso.</p>
					<button type="button" className="btn-primary" onClick={close}>
						Fechar
					</button>
				</div>
			) : (
				<form onSubmit={submit} className="space-y-4">
					<h2 id="report-title" className="text-xl font-semibold text-white">
						Denunciar {partner.name}
					</h2>
					<p className="text-sm text-white/60">A pessoa não fica sabendo quem denunciou. Ela será bloqueada para você automaticamente.</p>
					<ErrorBox error={error} />
					<fieldset className="space-y-2">
						<legend className="label">Motivo</legend>
						{Object.entries(config.reasons).map(([key, label]) => (
							<label key={key} className="flex cursor-pointer items-center gap-3 rounded-xl bg-white/5 px-4 py-3 text-sm text-white/80 has-[:checked]:bg-brand/20">
								<input type="radio" name="reason" value={key} required checked={reason === key} onChange={() => setReason(key)} className="accent-[var(--color-brand)]" />
								{label}
							</label>
						))}
					</fieldset>
					<label className="block">
						<span className="label">Detalhes (opcional)</span>
						<textarea className="field min-h-24" maxLength={1000} value={details} onChange={(e) => setDetails(e.target.value)} />
					</label>
					<p className="text-xs text-white/40">Em caso de perigo imediato, ligue 190.</p>
					<div className="flex justify-end gap-2">
						<button type="button" className="btn-ghost" onClick={onClose}>
							Cancelar
						</button>
						<button type="submit" className="btn-danger" disabled={busy || !reason}>
							{busy ? 'Enviando…' : 'Enviar denúncia'}
						</button>
					</div>
				</form>
			)}
		</Modal>
	);
}
