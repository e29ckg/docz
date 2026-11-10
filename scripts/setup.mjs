import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { randomBytes } from 'node:crypto';
import { spawnSync } from 'node:child_process';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const [major, minor] = process.versions.node.split('.').map(Number);
if (major < 22 || (major === 22 && minor < 12)) {
  console.error('DOCZ requires Node.js 22.12 or later.');
  process.exit(1);
}

for (const directory of ['server', 'frontend']) {
  if (!fs.existsSync(path.join(root, directory, 'package-lock.json'))) {
    throw new Error(`Missing ${directory}/package-lock.json`);
  }
}
if (process.argv.includes('--check')) {
  console.log('Setup checks passed: Node.js version and dependency lockfiles.');
  process.exit(0);
}

const envPath = path.join(root, 'server/.env');
if (!fs.existsSync(envPath)) {
  const template = fs.readFileSync(path.join(root, 'server/.env.example'), 'utf8');
  fs.writeFileSync(envPath, template.replace(/^JWT_SECRET=.*$/m, `JWT_SECRET=${randomBytes(48).toString('hex')}`), { flag: 'wx', mode: 0o600 });
  console.log('Created server/.env with a random JWT secret. Configure DB_* before initializing the database.');
} else {
  console.log('Kept existing server/.env.');
}

for (const directory of ['web/uploads/docz', 'web/uploads/profile', 'logs', 'runtime']) {
  fs.mkdirSync(path.join(root, directory), { recursive: true });
}

function npm(args) {
  const result = process.platform === 'win32'
    ? spawnSync('cmd.exe', ['/d', '/s', '/c', `npm ${args.join(' ')}`], { cwd: root, stdio: 'inherit' })
    : spawnSync('npm', args, { cwd: root, stdio: 'inherit' });
  if (result.error) throw result.error;
  if (result.status !== 0) process.exit(result.status || 1);
}
npm(['--prefix', 'server', 'ci', '--include=dev']);
npm(['--prefix', 'frontend', 'ci', '--include=dev']);
npm(['run', 'build']);
console.log('\nSetup complete. Next: configure server/.env, npm run db:init, npm run admin:create, npm start.');
