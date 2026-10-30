/**
 * Gera ícones e a imagem de compartilhamento a partir de SVG/HTML, renderizados no Chrome.
 *
 *   npm run icons
 *   npm run icons -- --brand="Outro Nome" --primary=#7C3AED --accent=#FF4D6D
 *
 * Opções: --brand, --primary, --accent, --highlight, --title, --subtitle, --tag
 * Chrome: usa CHROME_PATH ou os caminhos padrão do Windows/macOS/Linux.
 *
 * Saída em assets/icons e assets/img. Depois de regerar, rode "Criar conteúdo que estiver
 * faltando" em Spotinder > Ferramentas, ou troque o Ícone do site em Aparência > Personalizar.
 */
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import puppeteer from 'puppeteer-core';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const args = Object.fromEntries(
	process.argv
		.slice(2)
		.map((a) => a.match(/^--([^=]+)=(.*)$/))
		.filter(Boolean)
		.map((m) => [m[1], m[2]]),
);

const opt = {
	brand: args.brand || 'Spotinder',
	primary: args.primary || '#7C3AED',
	accent: args.accent || '#FF4D6D',
	highlight: args.highlight || '#C6FF3D',
	title: args.title || 'Ninguém deveria ouvir sozinho.',
	subtitle: args.subtitle || 'Conheça pessoas pelo que elas realmente escutam. Artistas em primeiro lugar. Sem chat.',
	tag: args.tag || '#NinguémOuveSozinho',
};

const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);

/**
 * Marca: um disco de vinil com um coração no selo.
 * variant "rounded" = cantos arredondados (favicon, ícone "any")
 * variant "full"    = fundo inteiro, disco dentro da zona segura (maskable, apple-touch)
 */
function mark(variant = 'rounded') {
	const full = variant === 'full';
	const r = full ? 150 : 172; // Raio do disco.
	const scale = r / 172;
	const grooves = [150, 132, 114].map((g) => `<circle cx="256" cy="256" r="${g * scale}" fill="none" stroke="#fff" stroke-opacity=".13" stroke-width="${3 * scale}"/>`).join('');
	const label = 82 * scale;
	// Coração centrado em (256, 262), largura ~100 no tamanho padrão.
	const s = scale;
	const heart = `<path transform="translate(256 258) scale(${s}) translate(-256 -258)" d="M256 304C234 290 204 268 204 237a26 26 0 0 1 52-8a26 26 0 0 1 52 8c0 31-30 53-52 67z" fill="#fff"/>`;

	return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
	<defs>
		<linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="${opt.primary}"/><stop offset="1" stop-color="${opt.accent}"/></linearGradient>
		<linearGradient id="lb" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="${opt.accent}"/><stop offset="1" stop-color="${opt.primary}"/></linearGradient>
	</defs>
	<rect width="512" height="512" ${full ? '' : 'rx="116"'} fill="url(#bg)"/>
	<circle cx="256" cy="256" r="${r}" fill="#0B0A10"/>
	${grooves}
	<path d="M${256 - r * 0.72} ${256 - r * 0.42}A${r * 0.84} ${r * 0.84} 0 0 1 ${256 - r * 0.18} ${256 - r * 0.8}" fill="none" stroke="#fff" stroke-opacity=".28" stroke-width="${10 * scale}" stroke-linecap="round"/>
	<circle cx="256" cy="256" r="${label}" fill="url(#lb)"/>
	${heart}
</svg>`;
}

function ogHtml() {
	const font = readFileSync(resolve(root, 'node_modules/@fontsource-variable/outfit/files/outfit-latin-wght-normal.woff2')).toString('base64');
	const tiles = [
		['Milton Nascimento', 300],
		['Tame Impala', 20],
		['Gal Costa', 330],
		['Lô Borges', 260],
	]
		.map(([n, h]) => `<div class="tile" style="background:linear-gradient(135deg,hsl(${h} 75% 55%),hsl(${(h + 60) % 360} 75% 35%))"><span class="badge">em comum</span><b>${esc(n)}</b></div>`)
		.join('');

	return `<!doctype html><html><head><meta charset="utf-8"><style>
	@font-face{font-family:Outfit;src:url(data:font/woff2;base64,${font}) format('woff2');font-weight:100 900}
	*{box-sizing:border-box;margin:0}
	body{width:1200px;height:630px;overflow:hidden;font-family:Outfit,sans-serif;color:#fff;background:#0B0A10;position:relative}
	.glow{position:absolute;inset:0;background:radial-gradient(520px 420px at 88% 30%,${opt.primary}88,transparent 70%),radial-gradient(420px 360px at 98% 95%,${opt.accent}66,transparent 70%),radial-gradient(500px 300px at 0% 100%,${opt.primary}33,transparent 70%)}
	.lines{position:absolute;inset:0;background:repeating-linear-gradient(90deg,rgba(255,255,255,.035) 0 1px,transparent 1px 80px)}
	.left{position:absolute;left:80px;top:70px;width:620px}
	.brand{display:flex;align-items:center;gap:16px;font-size:34px;font-weight:600;letter-spacing:-.5px}
	.brand svg{width:64px;height:64px}
	h1{margin-top:58px;font-size:74px;line-height:1.02;font-weight:600;letter-spacing:-2.5px}
	p{margin-top:26px;font-size:27px;line-height:1.4;color:rgba(255,255,255,.72);max-width:560px}
	.tag{position:absolute;left:80px;bottom:64px;display:inline-flex;padding:10px 22px;border-radius:99px;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.06);font-size:22px;color:rgba(255,255,255,.85)}
	.tag b{color:${opt.highlight};font-weight:600}
	.card{position:absolute;right:86px;top:78px;width:330px;height:470px;border-radius:34px;background:#15131D;border:1px solid rgba(255,255,255,.1);overflow:hidden;box-shadow:0 40px 80px rgba(0,0,0,.6);transform:rotate(4deg)}
	.card.back{transform:rotate(-7deg) translateX(-34px);opacity:.45}
	.grid{display:grid;grid-template-columns:1fr 1fr;gap:2px;height:270px}
	.tile{position:relative;display:flex;align-items:flex-end;padding:10px 12px;font-size:16px}
	.tile b{position:relative;font-weight:500;text-shadow:0 1px 6px rgba(0,0,0,.5)}
	.badge{position:absolute;left:10px;top:10px;background:${opt.accent};border-radius:99px;padding:3px 9px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
	.info{padding:22px 24px}
	.row{display:flex;justify-content:space-between;align-items:center}
	.name{font-size:32px;font-weight:600}
	.city{font-size:16px;color:rgba(255,255,255,.5);margin-top:4px}
	.ring{width:66px;height:66px;border-radius:50%;display:grid;place-items:center;font-weight:700;font-size:18px;background:conic-gradient(${opt.accent} 0 79%,rgba(255,255,255,.12) 79% 100%);position:relative}
	.ring::before{content:"";position:absolute;inset:6px;border-radius:50%;background:#15131D}
	.ring span{position:relative}
	.btns{display:flex;gap:14px;margin-top:22px}
	.btn{width:52px;height:52px;border-radius:50%;display:grid;place-items:center;font-size:22px;border:1px solid rgba(255,255,255,.15)}
	.btn.like{background:${opt.accent};border:0;box-shadow:0 10px 30px ${opt.accent}88}
	.sticker{position:absolute;right:358px;top:500px;transform:rotate(-8deg);background:${opt.highlight};color:#0B0A10;font-weight:700;font-size:26px;padding:12px 22px;border-radius:18px;box-shadow:0 20px 40px rgba(0,0,0,.4)}
	</style></head><body>
	<div class="glow"></div><div class="lines"></div>
	<div class="left">
		<div class="brand">${mark('rounded')}<span>${esc(opt.brand)}</span></div>
		<h1>${esc(opt.title)}</h1>
		<p>${esc(opt.subtitle)}</p>
	</div>
	<div class="tag"><b>${esc(opt.tag)}</b></div>
	<div class="card back"></div>
	<div class="card">
		<div class="grid">${tiles}</div>
		<div class="info">
			<div class="row"><div><div class="name">Theo</div><div class="city">Belo Horizonte</div></div><div class="ring"><span>79%</span></div></div>
			<div class="btns"><div class="btn">✕</div><div class="btn like">♥</div></div>
		</div>
	</div>
	<div class="sticker">Deu som! ♪</div>
	</body></html>`;
}

function findChrome() {
	const candidates = [
		process.env.CHROME_PATH,
		'C:/Program Files/Google/Chrome/Application/chrome.exe',
		'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
		'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
		'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
		'/usr/bin/google-chrome',
		'/usr/bin/chromium',
	].filter(Boolean);
	const found = candidates.find((p) => existsSync(p));
	if (!found) throw new Error('Chrome não encontrado. Defina CHROME_PATH.');
	return found;
}

const icons = resolve(root, 'assets/icons');
const img = resolve(root, 'assets/img');
mkdirSync(icons, { recursive: true });
mkdirSync(img, { recursive: true });

writeFileSync(resolve(icons, 'icon.svg'), mark('rounded'));
writeFileSync(resolve(icons, 'icon-maskable.svg'), mark('full'));

const browser = await puppeteer.launch({ executablePath: findChrome(), headless: true });
const page = await browser.newPage();

async function png(html, file, width, height) {
	await page.setViewport({ width, height, deviceScaleFactor: 1 });
	await page.setContent(html, { waitUntil: 'load' });
	await page.evaluate(() => document.fonts.ready);
	await page.screenshot({ path: file, omitBackground: true, clip: { x: 0, y: 0, width, height } });
	console.log('✓', file.replace(root, '.'));
}

const svgPage = (svg, size) => `<!doctype html><html><body style="margin:0;background:transparent">${svg.replace('<svg ', `<svg width="${size}" height="${size}" `)}</body></html>`;

await png(svgPage(mark('rounded'), 512), resolve(icons, 'site-icon-512.png'), 512, 512);
await png(svgPage(mark('rounded'), 192), resolve(icons, 'icon-192.png'), 192, 192);
await png(svgPage(mark('rounded'), 512), resolve(icons, 'icon-512.png'), 512, 512);
await png(svgPage(mark('full'), 512), resolve(icons, 'icon-maskable-512.png'), 512, 512);
await png(svgPage(mark('full'), 192), resolve(icons, 'icon-maskable-192.png'), 192, 192);
await png(svgPage(mark('full'), 180), resolve(icons, 'apple-touch-icon.png'), 180, 180);
await png(svgPage(mark('rounded'), 32), resolve(icons, 'favicon-32.png'), 32, 32);
await png(ogHtml(), resolve(img, 'og-default.png'), 1200, 630);

await browser.close();
