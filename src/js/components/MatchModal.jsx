import { config, navigate, t } from '../api';
import SpotifyEmbed from './SpotifyEmbed';
import { Avatar, Modal } from './ui';

/**
 * "Fez barulho!" — aparece quando a curtida é recíproca e toca a trilha sonora do match.
 */
export default function MatchModal({ match, onClose }) {
	if (!match) return null;
	const partner = match.partner;
	const goTo = () => navigate(`${config.urls.matches}#match-${match.id}`);

	return (
		<Modal open onClose={onClose} labelledBy="match-title" className="overflow-hidden bg-gradient-to-b from-brand/30 via-surface to-surface text-center">
			<div className="mx-auto mb-6 flex items-center justify-center">
				<Avatar person={config.me} size="size-20 text-2xl" className="-rotate-6 ring-4 ring-ink" />
				<span className="z-10 -mx-3 grid size-12 place-items-center rounded-full bg-accent text-xl shadow-lg shadow-accent/50 animate-pop">♪</span>
				<Avatar person={partner} size="size-20 text-2xl" className="rotate-6 ring-4 ring-ink" />
			</div>

			<h2 id="match-title" className="text-4xl font-semibold tracking-tight text-white">
				{t('match_title')}
			</h2>
			<p className="mt-2 text-white/70">
				Você e <strong className="text-white">{partner.name}</strong> · {match.score}% de compatibilidade
			</p>
			<p className="mt-4 text-sm text-white/60">{t('match_text')}</p>

			{match.soundtrack && (
				<div className="mt-4 space-y-2 text-left">
					<SpotifyEmbed track={match.soundtrack} autoplay={config.autoplay} height={152} />
					<p className="text-center text-xs text-white/50">{match.soundtrack.reason}</p>
				</div>
			)}

			<div className="mt-6 grid gap-2 sm:grid-cols-2">
				<button type="button" className="btn-ghost justify-center" onClick={onClose}>
					Continuar descobrindo
				</button>
				<button type="button" className="btn-accent justify-center" onClick={goTo}>
					Ver redes e playlist
				</button>
			</div>
		</Modal>
	);
}
