/**
 * Roda o Parcel com a pasta temporária dentro do projeto.
 *
 * O Parcel grava arquivos em TEMP e depois os move (rename) para dist/ e .parcel-cache/.
 * No Windows, se o projeto está em outro drive (ex.: B:) e o TEMP em C:, o rename falha
 * com EXDEV — às vezes em silêncio, deixando dist/ desatualizado.
 */
import { spawn } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const tmp = resolve(root, '.parcel-tmp');
mkdirSync(tmp, { recursive: true });

const env = { ...process.env, TEMP: tmp, TMP: tmp, TMPDIR: tmp };
const child = spawn('npx', ['parcel', ...process.argv.slice(2)], { cwd: root, env, stdio: 'inherit', shell: true });
child.on('exit', (code) => process.exit(code ?? 1));
