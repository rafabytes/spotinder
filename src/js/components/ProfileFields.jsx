import { useEffect, useRef, useState } from 'react';
import { api, config, t } from '../api';
import { disableLocation, shareLocation } from '../location';
import { Checkbox, ErrorBox, Icon } from './ui';

/**
 * Localização aproximada: ativar (com aceite), atualizar ou desativar.
 */
export function LocationField({ me, onSaved }) {
	const enabled = Boolean(me.location?.enabled);
	const [consent, setConsent] = useState(false);
	const [busy, setBusy] = useState(false);
	const [error, setError] = useState(null);

	const run = async (fn) => {
		setBusy(true);
		setError(null);
		try {
			onSaved(await fn());
		} catch (e) {
			setError(e);
		} finally {
			setBusy(false);
		}
	};

	return (
		<div className="space-y-4">
			<p className="text-sm leading-relaxed text-white/60">{t('location_help')}</p>

			{enabled ? (
				<div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-white/10 bg-white/[0.03] p-4">
					<div>
						<p className="font-medium text-white">📍 Região de {me.location.label}</p>
						<p className="text-xs text-white/45">Atualizada em {me.location.updated} · área aproximada de ~5 km</p>
					</div>
					<div className="flex gap-2">
						<button type="button" className="btn-ghost text-xs" disabled={busy} onClick={() => run(() => shareLocation(true))}>
							Atualizar agora
						</button>
						<button type="button" className="btn-danger text-xs" disabled={busy} onClick={() => run(disableLocation)}>
							Desativar
						</button>
					</div>
				</div>
			) : !window.isSecureContext ? (
				// Navegadores só liberam geolocalização em HTTPS.
				<p className="rounded-2xl border border-white/10 bg-white/[0.03] p-4 text-sm text-white/60">A localização só pode ser ativada quando o site é acessado por conexão segura (HTTPS).</p>
			) : (
				<>
					<Checkbox checked={consent} onChange={setConsent}>
						{t('consent_location_label')}
					</Checkbox>
					<button type="button" className="btn-primary" disabled={!consent || busy} onClick={() => run(() => shareLocation(true))}>
						{busy ? 'Obtendo região…' : '📍 Usar minha região aproximada'}
					</button>
				</>
			)}

			<ErrorBox error={error} />
		</div>
	);
}

const MAX_BYTES = 5 * 1024 * 1024;
const TYPES = ['image/jpeg', 'image/png', 'image/webp'];

/**
 * Foto de perfil: nenhuma (iniciais), foto do Spotify ou foto enviada.
 * Salva direto na API (upload não cabe no formulário JSON do perfil).
 */
export function PhotoField({ me, onSaved }) {
	const saved = me.photoSource || 'none';
	const [choice, setChoice] = useState(saved);
	const [file, setFile] = useState(null);
	const [preview, setPreview] = useState('');
	const [consent, setConsent] = useState(saved !== 'none');
	const [busy, setBusy] = useState(false);
	const [error, setError] = useState(null);
	const [ok, setOk] = useState(false);
	const input = useRef(null);

	useEffect(() => () => preview && URL.revokeObjectURL(preview), [preview]);

	const uploadPreview = preview || me.photoChoice?.upload || '';
	const spotifyPhoto = me.photoChoice?.spotify || '';
	const dirty = choice !== saved || Boolean(file);
	const needsConsent = choice !== 'none';

	const pick = (e) => {
		const f = e.target.files?.[0];
		e.target.value = '';
		if (!f) return;
		if (!TYPES.includes(f.type)) return setError(new Error('Envie uma imagem JPG, PNG ou WebP.'));
		if (f.size > MAX_BYTES) return setError(new Error('A imagem deve ter no máximo 5 MB.'));
		setError(null);
		setFile(f);
		setPreview(URL.createObjectURL(f));
		setChoice('upload');
	};

	const save = async () => {
		setBusy(true);
		setError(null);
		setOk(false);
		try {
			let updated;
			if (choice === 'upload' && file) {
				const body = new FormData();
				body.append('photo', file);
				body.append('consent', consent ? '1' : '0');
				updated = await api('me/photo', { method: 'POST', body });
			} else {
				updated = await api('me/photo-source', { method: 'POST', body: { source: choice, consent } });
			}
			setFile(null);
			setPreview('');
			if (config.me) config.me.photo = updated.photo;
			onSaved(updated);
			setOk(true);
		} catch (e) {
			setError(e);
		} finally {
			setBusy(false);
		}
	};

	const Option = ({ value, label, img, disabled, onClick, hint }) => (
		<button
			type="button"
			disabled={disabled}
			aria-pressed={choice === value}
			onClick={onClick || (() => setChoice(value))}
			className={`flex flex-col items-center gap-2 rounded-2xl border p-3 text-center text-sm transition disabled:cursor-not-allowed disabled:opacity-40 ${choice === value ? 'border-brand bg-brand/15 text-white' : 'border-white/10 bg-white/[0.03] text-white/70 hover:border-white/25'}`}
		>
			<span className="grid size-16 place-items-center overflow-hidden rounded-full text-lg font-semibold text-white ring-2 ring-white/10" style={{ background: me.gradient }}>
				{img ? <img src={img} alt="" className="size-full object-cover" /> : value === 'upload' ? '+' : me.initials}
			</span>
			<span className="font-medium leading-tight">{label}</span>
			{hint && <span className="text-[11px] leading-tight text-white/45">{hint}</span>}
		</button>
	);

	return (
		<div className="space-y-4">
			<p className="text-sm leading-relaxed text-white/60">{t('photo_help')}</p>

			<div className="grid grid-cols-3 gap-2">
				<Option value="none" label="Sem foto" hint="só iniciais" />
				<Option value="spotify" label="Do Spotify" img={spotifyPhoto} disabled={!spotifyPhoto} hint={spotifyPhoto ? 'foto do seu perfil' : 'seu perfil não tem foto'} />
				<Option value="upload" label={uploadPreview ? 'Minha foto' : 'Enviar foto'} img={uploadPreview} hint={uploadPreview ? 'toque para trocar' : 'JPG, PNG ou WebP'} onClick={() => (uploadPreview && choice !== 'upload' ? setChoice('upload') : input.current?.click())} />
			</div>
			<input ref={input} type="file" accept="image/jpeg,image/png,image/webp" className="sr-only" onChange={pick} tabIndex={-1} />

			{needsConsent && (
				<Checkbox checked={consent} onChange={setConsent}>
					{t('consent_photo_label')}
				</Checkbox>
			)}

			<ErrorBox error={error} />

			{(dirty || ok) && (
				<div className="flex items-center justify-end gap-3">
					{ok && !dirty && <span className="text-sm text-emerald-300">Foto salva ✓</span>}
					{dirty && (
						<button type="button" className="btn-primary" disabled={busy || (needsConsent && !consent) || (choice === 'upload' && !file && !me.photoChoice?.upload)} onClick={save}>
							{busy ? 'Salvando…' : choice === 'none' ? 'Remover foto' : 'Salvar foto'}
						</button>
					)}
				</div>
			)}
		</div>
	);
}

/** Campos "sobre você", compartilhados entre cadastro e edição de perfil. */
export function AboutFields({ form, setForm }) {
	const set = (key) => (e) => setForm((f) => ({ ...f, [key]: e.target.value }));
	const toggleIntention = (slug) =>
		setForm((f) => ({
			...f,
			intentions: f.intentions.includes(slug) ? f.intentions.filter((s) => s !== slug) : [...f.intentions, slug],
		}));

	return (
		<div className="space-y-5">
			<div className="grid gap-4 sm:grid-cols-2">
				<label className="block">
					<span className="label">Como quer ser chamado(a)</span>
					<input className="field" required maxLength={40} value={form.name} onChange={set('name')} />
				</label>
				<label className="block">
					<span className="label">Cidade (opcional, se não usar a localização)</span>
					<input className="field" maxLength={60} placeholder="Ex.: Belo Horizonte" value={form.city} onChange={set('city')} />
				</label>
			</div>
			<label className="block">
				<span className="label">Bio curta (opcional)</span>
				<textarea className="field min-h-24" maxLength={240} placeholder="O que você ouve no caminho pro trabalho? Qual show mudou sua vida?" value={form.bio} onChange={set('bio')} />
				<span className="mt-1 block text-right text-xs text-white/40">{form.bio.length}/240</span>
			</label>
			<fieldset>
				<legend className="label">O que você procura? (escolha uma ou mais)</legend>
				<div className="flex flex-wrap gap-2">
					{config.intentions.map((i) => (
						<button key={i.slug} type="button" aria-pressed={form.intentions.includes(i.slug)} className={`chip ${form.intentions.includes(i.slug) ? 'chip-active' : ''}`} onClick={() => toggleIntention(i.slug)}>
							{i.emoji} {i.label}
						</button>
					))}
				</div>
			</fieldset>
		</div>
	);
}

/** Redes sociais + aviso de responsabilidade. */
export function SocialFields({ form, setForm, consent, setConsent }) {
	const setSocial = (key) => (e) => setForm((f) => ({ ...f, socials: { ...f.socials, [key]: e.target.value } }));
	const hasAny = Object.values(form.socials).some((v) => String(v || '').trim());

	return (
		<div className="space-y-5">
			<div className="flex gap-3 rounded-2xl border border-highlight/25 bg-highlight/[0.06] p-4 text-sm text-white/75">
				<Icon.shield className="size-5 shrink-0 text-highlight" />
				<div>
					<p className="font-semibold text-white">{t('social_warning_title')}</p>
					<p className="mt-1 leading-relaxed">{t('social_warning_text')}</p>
				</div>
			</div>

			<div className="grid gap-4 sm:grid-cols-2">
				{Object.entries(config.socials).map(([key, net]) => (
					<label key={key} className="block">
						<span className="label">{net.label}</span>
						<input className="field" maxLength={key === 'website' ? 200 : 60} placeholder={net.placeholder} value={form.socials[key] || ''} onChange={setSocial(key)} inputMode={key === 'website' ? 'url' : 'text'} autoCapitalize="none" autoCorrect="off" />
					</label>
				))}
			</div>

			{hasAny && (
				<Checkbox checked={consent} onChange={setConsent}>
					{t('consent_social_label')}
				</Checkbox>
			)}
		</div>
	);
}

export const profileToForm = (me) => ({
	name: me.name || '',
	city: me.city || '',
	bio: me.bio || '',
	intentions: me.intentions || [],
	socials: { ...(me.socialsRaw || {}) },
});

export const hasSocials = (form) => Object.values(form.socials).some((v) => String(v || '').trim());
