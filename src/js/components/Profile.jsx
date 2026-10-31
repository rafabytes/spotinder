import { useEffect, useState } from 'react';
import { api, config } from '../api';
import Onboarding from './Onboarding';
import { AboutFields, LocationField, PhotoField, SocialFields, hasSocials, profileToForm } from './ProfileFields';
import MusicPicker, { musicFromProfile, musicReady, musicToBody } from './MusicPicker';
import { ErrorBox, Loading, Toggle } from './ui';

const TABS = [
	['perfil', 'Perfil'],
	['redes', 'Redes sociais'],
	['privacidade', 'Privacidade'],
];

/**
 * Perfil do membro. Se o cadastro não foi concluído (ou há termos novos), mostra o onboarding.
 */
export default function Profile() {
	const [me, setMe] = useState(null);
	const [error, setError] = useState(null);
	const [tab, setTab] = useState(() => (TABS.some(([k]) => `#${k}` === window.location.hash) ? window.location.hash.slice(1) : 'perfil'));

	useEffect(() => {
		api('me').then(setMe).catch(setError);
	}, []);

	if (error) return <ErrorBox error={error} />;
	if (!me) return <Loading />;
	if (!me.onboarded || !me.consents) return <Onboarding me={me} onDone={setMe} />;

	return (
		<div className="mx-auto max-w-3xl space-y-6">
			<header className="flex flex-wrap items-end justify-between gap-4">
				<div>
					<p className="eyebrow">Meu perfil</p>
					<h1 className="text-3xl font-semibold text-white">{me.name}</h1>
				</div>
				<a href={config.urls.logout} className="btn-ghost" data-no-swup>
					Sair
				</a>
			</header>

			<nav className="flex gap-2 overflow-x-auto" role="tablist">
				{TABS.map(([key, label]) => (
					<button
						key={key}
						type="button"
						role="tab"
						aria-selected={tab === key}
						className={`chip shrink-0 ${tab === key ? 'chip-active' : ''}`}
						onClick={() => {
							setTab(key);
							window.history.replaceState(window.history.state, '', `#${key}`);
						}}
					>
						{label}
					</button>
				))}
			</nav>

			{tab === 'perfil' && <ProfileTab me={me} onSaved={setMe} />}
			{tab === 'redes' && <SocialTab me={me} onSaved={setMe} />}
			{tab === 'privacidade' && <PrivacyTab me={me} onSaved={setMe} />}
		</div>
	);
}

function useSaver(onSaved) {
	const [state, setState] = useState({ busy: false, error: null, ok: false });
	const save = async (body) => {
		setState({ busy: true, error: null, ok: false });
		try {
			onSaved(await api('me', { method: 'POST', body }));
			setState({ busy: false, error: null, ok: true });
		} catch (error) {
			setState({ busy: false, error, ok: false });
		}
	};
	return [state, save];
}

function SaveBar({ state, disabled }) {
	return (
		<div className="flex items-center justify-end gap-3">
			{state.ok && <span className="text-sm text-emerald-300">Salvo ✓</span>}
			<button type="submit" className="btn-primary" disabled={state.busy || disabled}>
				{state.busy ? 'Salvando…' : 'Salvar'}
			</button>
		</div>
	);
}

function ProfileTab({ me, onSaved }) {
	const [form, setForm] = useState(() => profileToForm(me));
	const [state, save] = useSaver(onSaved);

	return (
		<div className="space-y-6">
			<MusicSection me={me} onSaved={onSaved} />

			<section className="card space-y-4 p-6">
				<h2 className="text-lg font-semibold text-white">Foto de perfil</h2>
				<PhotoField me={me} onSaved={onSaved} />
			</section>

			<section className="card space-y-4 p-6">
				<h2 className="text-lg font-semibold text-white">Localização aproximada</h2>
				<LocationField me={me} onSaved={onSaved} />
			</section>

			<form
				className="card space-y-6 p-6"
				onSubmit={(e) => {
					e.preventDefault();
					save({ name: form.name, city: form.city, bio: form.bio, intentions: form.intentions });
				}}
			>
				<AboutFields form={form} setForm={setForm} />
				<ErrorBox error={state.error} />
				<SaveBar state={state} disabled={!form.name.trim() || !form.intentions.length} />
			</form>
		</div>
	);
}

function MusicSection({ me, onSaved }) {
	const [music, setMusic] = useState(() => musicFromProfile(me));
	const [state, save] = useSaver(onSaved);

	return (
		<form
			className="card space-y-6 p-6"
			onSubmit={(e) => {
				e.preventDefault();
				save(musicToBody(music));
			}}
		>
			<div>
				<h2 className="text-lg font-semibold text-white">Seus artistas e músicas</h2>
				<p className="text-sm text-white/50">Atualizado em {me.syncedAt || '—'}. Mudou de fase? É só mexer na lista.</p>
			</div>
			<MusicPicker music={music} setMusic={setMusic} />
			<ErrorBox error={state.error} />
			<SaveBar state={state} disabled={!musicReady(music)} />
		</form>
	);
}

function SocialTab({ me, onSaved }) {
	const [form, setForm] = useState(() => profileToForm(me));
	const [consent, setConsent] = useState(Boolean(me.consents?.social));
	const [state, save] = useSaver(onSaved);
	const needsConsent = hasSocials(form) && !consent;

	return (
		<form
			className="card space-y-6 p-6"
			onSubmit={(e) => {
				e.preventDefault();
				save({ socials: form.socials, socialConsent: consent && hasSocials(form) });
			}}
		>
			<SocialFields form={form} setForm={setForm} consent={consent} setConsent={setConsent} />
			<ErrorBox error={state.error} />
			<SaveBar state={state} disabled={needsConsent} />
		</form>
	);
}

function PrivacyTab({ me, onSaved }) {
	const [state, save] = useSaver(onSaved);
	const [confirm, setConfirm] = useState('');
	const [deleting, setDeleting] = useState(false);
	const [error, setError] = useState(null);

	const download = async () => {
		setError(null);
		try {
			const data = await api('me/export');
			const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
			const link = document.createElement('a');
			link.href = URL.createObjectURL(blob);
			link.download = 'meus-dados.json';
			link.click();
			URL.revokeObjectURL(link.href);
		} catch (e) {
			setError(e);
		}
	};

	const destroy = async () => {
		setDeleting(true);
		setError(null);
		try {
			const res = await api('me', { method: 'DELETE', query: { confirm } });
			window.location.href = res.redirect;
		} catch (e) {
			setError(e);
			setDeleting(false);
		}
	};

	return (
		<div className="space-y-6">
			<section className="card divide-y divide-white/5 px-6">
				<Toggle checked={me.visible} onChange={(visible) => save({ visible })} label="Aparecer no deck" description="Desligado, seu perfil some do deck de outras pessoas. Seus matches continuam." />
				<Toggle checked={me.shareEvents} onChange={(shareEvents) => save({ shareEvents })} label="Mostrar meus eventos para matches" description="Seus matches veem em quais eventos você marcou presença." />
				<ErrorBox error={state.error} />
			</section>

			<section className="card space-y-4 p-6">
				<h2 className="text-lg font-semibold text-white">Seus dados</h2>
				<ul className="space-y-2 text-sm text-white/65">
					<li>• Da sua conta Google guardamos só o identificador, o nome e o endereço da foto pública. Nada de e-mail.</li>
					<li>• Artistas e músicas: só a seleção atual, na ordem que você escolheu (sem histórico).</li>
					<li>• Foto enviada: guardada sem metadados (sem localização) e apagada na hora ao trocar, remover ou excluir a conta.</li>
					<li>• Não guardamos tokens de acesso do Google.</li>
					<li>• Consentimentos aceitos em {me.consents?.terms} (versão {me.consents?.version}).</li>
				</ul>
				<div className="flex flex-wrap gap-2">
					<button type="button" className="btn-ghost" onClick={download}>
						Baixar meus dados (JSON)
					</button>
					<a className="btn-ghost" href={config.urls.privacy}>
						Política de Privacidade
					</a>
					<a className="btn-ghost" href="https://myaccount.google.com/connections" target="_blank" rel="noopener noreferrer">
						Apps conectados no Google
					</a>
				</div>
			</section>

			<section className="card space-y-4 border-rose-400/20 p-6">
				<h2 className="text-lg font-semibold text-white">Apagar minha conta</h2>
				<p className="text-sm text-white/65">Remove na hora seu perfil, artistas e músicas, matches, convites, presenças e redes sociais. Não dá para desfazer.</p>
				<ErrorBox error={error} />
				<label className="block max-w-xs">
					<span className="label">
						Digite <strong>APAGAR</strong> para confirmar
					</span>
					<input className="field" value={confirm} onChange={(e) => setConfirm(e.target.value)} autoComplete="off" />
				</label>
				<button type="button" className="btn-danger" disabled={confirm !== 'APAGAR' || deleting} onClick={destroy}>
					{deleting ? 'Apagando…' : 'Apagar tudo'}
				</button>
			</section>
		</div>
	);
}
