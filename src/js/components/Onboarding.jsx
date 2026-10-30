import { useState } from 'react';
import { api, config, navigate, t } from '../api';
import { AboutFields, LocationField, PhotoField, SocialFields, hasSocials, profileToForm } from './ProfileFields';
import { ArtistTile, Checkbox, ErrorBox, SpotifyAttribution } from './ui';

const STEPS = ['Seu som', 'Foto', 'Sobre você', 'Redes', 'Combinados'];

/**
 * Cadastro em 5 passos. Os consentimentos ficam no fim, depois de a pessoa
 * ver exatamente o que será exibido (consentimento informado — LGPD art. 8º).
 */
export default function Onboarding({ me, onDone }) {
	const [step, setStep] = useState(0);
	const [form, setForm] = useState(() => profileToForm(me));
	const [current, setCurrent] = useState(me);
	const [consents, setConsents] = useState({ age: false, terms: false, share: false, social: false });
	const [busy, setBusy] = useState(false);
	const [error, setError] = useState(null);
	const isUpdate = me.onboarded; // Já tinha conta: só precisa aceitar a nova versão dos termos.

	const setConsent = (key) => (value) => setConsents((c) => ({ ...c, [key]: value }));
	const socialsOk = !hasSocials(form) || consents.social;
	const canNext = [true, true, form.name.trim() && form.intentions.length > 0, socialsOk, consents.age && consents.terms && consents.share][step];

	const submit = async () => {
		setBusy(true);
		setError(null);
		try {
			const updated = await api('me', { method: 'POST', body: { ...form, consents } });
			config.onboarded = true;
			onDone(updated);
			navigate(config.urls.discover);
		} catch (e) {
			setError(e);
			setBusy(false);
		}
	};

	return (
		<div className="mx-auto max-w-2xl space-y-6">
			<header className="space-y-3">
				<p className="eyebrow">{isUpdate ? 'Atualizamos nossos termos' : `Passo ${step + 1} de ${STEPS.length}`}</p>
				<h1 className="text-3xl font-semibold tracking-tight text-white md:text-4xl">{t('onboarding_title')}</h1>
				<p className="text-white/60">{t('onboarding_text')}</p>
				<ol className="grid grid-cols-5 gap-2 pt-2" aria-label="Progresso">
					{STEPS.map((label, i) => (
						<li key={label} className="space-y-1.5">
							<span className={`block h-1.5 rounded-full ${i <= step ? 'bg-brand' : 'bg-white/10'}`} />
							<span className={`block text-xs ${i === step ? 'text-white' : 'text-white/40'}`}>{label}</span>
						</li>
					))}
				</ol>
			</header>

			<ErrorBox error={error} />

			<div className="card p-6 md:p-8">
				{step === 0 && (
					<div className="space-y-5">
						<h2 className="text-xl font-semibold text-white">É isso que as pessoas vão ver no seu cartão</h2>
						{me.artists.length ? (
							<>
								<div className="grid grid-cols-3 gap-1 overflow-hidden rounded-2xl sm:grid-cols-4">
									{me.artists.slice(0, 8).map((a) => (
										<ArtistTile key={a.id || a.name} artist={a} className="aspect-square" />
									))}
								</div>
								<SpotifyAttribution />
							</>
						) : (
							<p className="rounded-2xl bg-white/5 p-4 text-sm text-white/70">Seu Spotify ainda não tem artistas mais ouvidos suficientes. Você pode continuar, mas seu cartão vai aparecer vazio até você ouvir mais músicas e atualizar o perfil.</p>
						)}
						<div className="rounded-2xl bg-white/5 p-4 text-sm leading-relaxed text-white/65">
							<p className="font-semibold text-white">O que lemos do Spotify</p>
							<p className="mt-1">Só seus artistas e músicas mais ouvidos dos últimos meses e seu nome e foto públicos (a foto só aparece se você escolher usá-la no próximo passo). Não acessamos e-mail, playlists ou o que você está ouvindo agora — e não guardamos seus tokens de acesso.</p>
						</div>
					</div>
				)}

				{step === 1 && (
					<div className="space-y-4">
						<h2 className="text-xl font-semibold text-white">Quer aparecer com foto?</h2>
						<PhotoField me={current} onSaved={setCurrent} />
					</div>
				)}

				{step === 2 && (
					<div className="space-y-8">
						<AboutFields form={form} setForm={setForm} />
						<div className="space-y-3 border-t border-white/5 pt-6">
							<h3 className="font-semibold text-white">Mostrar quem está perto? (opcional)</h3>
							<LocationField me={current} onSaved={setCurrent} />
						</div>
					</div>
				)}

				{step === 3 && (
					<div className="space-y-4">
						<p className="text-sm text-white/60">Opcional. Sem chat no app: é por aqui que seus matches vão falar com você. Se preferir, deixe em branco e use só os convites para eventos.</p>
						<SocialFields form={form} setForm={setForm} consent={consents.social} setConsent={setConsent('social')} />
					</div>
				)}

				{step === 4 && (
					<div className="space-y-4">
						<div className="grid gap-3 rounded-2xl bg-white/5 p-4 text-sm text-white/70 sm:grid-cols-2">
							<p>
								<strong className="block text-white">Todos no app veem</strong>
								nome, foto (se você escolheu uma), cidade, bio, intenções e seus artistas mais ouvidos.
							</p>
							<p>
								<strong className="block text-white">Só seus matches veem</strong>
								suas redes sociais e a playlist em comum.
							</p>
						</div>
						<Checkbox checked={consents.age} onChange={setConsent('age')}>
							{t('consent_age_label')}
						</Checkbox>
						<Checkbox checked={consents.terms} onChange={setConsent('terms')}>
							{t('consent_terms_label')}{' '}
							<a className="underline" href={config.urls.terms} target="_blank" rel="noopener noreferrer">
								Termos
							</a>{' '}
							·{' '}
							<a className="underline" href={config.urls.privacy} target="_blank" rel="noopener noreferrer">
								Privacidade
							</a>
						</Checkbox>
						<Checkbox checked={consents.share} onChange={setConsent('share')}>
							{t('consent_share_label')}
						</Checkbox>
						{hasSocials(form) && !consents.social && <p className="text-sm text-rose-200">Volte ao passo "Redes" e confirme o aviso de responsabilidade, ou remova as redes.</p>}
					</div>
				)}
			</div>

			<div className="flex items-center justify-between gap-3">
				<button type="button" className="btn-ghost" disabled={step === 0} onClick={() => setStep((s) => s - 1)}>
					Voltar
				</button>
				{step < STEPS.length - 1 ? (
					<button type="button" className="btn-primary" disabled={!canNext} onClick={() => setStep((s) => s + 1)}>
						Continuar
					</button>
				) : (
					<button type="button" className="btn-accent btn-lg" disabled={!canNext || !socialsOk || busy} onClick={submit}>
						{busy ? 'Salvando…' : 'Começar a descobrir'}
					</button>
				)}
			</div>
		</div>
	);
}
