import { useEffect, useState } from 'react';
import { api, config } from '../api';

/** Bolinha com novos matches + convites pendentes, no cabeçalho. */
export default function MatchBadge() {
	const [count, setCount] = useState(0);

	useEffect(() => {
		if (!config.isMember || !config.onboarded) return;
		api('summary')
			.then((s) => setCount((s.newMatches || 0) + (s.invites || 0)))
			.catch(() => {});
	}, []);

	if (!count) return null;
	return (
		<span className="absolute -right-1 -top-1 grid min-w-5 place-items-center rounded-full bg-accent px-1 text-[10px] font-bold text-white" aria-label={`${count} novidades`}>
			{count}
		</span>
	);
}
