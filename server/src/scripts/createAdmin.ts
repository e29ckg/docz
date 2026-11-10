import { createInterface } from 'readline/promises';
import { emitKeypressEvents } from 'readline';
import crypto from 'crypto';
import bcrypt from 'bcryptjs';
import { Pool, RowDataPacket, ResultSetHeader } from 'mysql2/promise';
import { pool } from '../db/connection';
import { generateUid } from '../utils/uid';

export async function createInitialAdmin(database: Pool, input: { username: string; name: string; password: string }): Promise<void> {
  if (!input.username.trim() || !input.name.trim()) throw new Error('Username and display name are required.');
  if (input.password.length < 12) throw new Error('Password must contain at least 12 characters.');
  const connection = await database.getConnection();
  try {
    await connection.beginTransaction();
    // Lock the role so concurrent setup processes cannot create multiple initial admins.
    const [roles] = await connection.query<RowDataPacket[]>('SELECT id FROM role_name WHERE id = 1 FOR UPDATE');
    if (!roles.length) throw new Error('Initialize the database before creating the administrator.');
    const [admins] = await connection.query<RowDataPacket[]>('SELECT id FROM role_power WHERE role_name_id = 1 LIMIT 1');
    if (admins.length) throw new Error('An administrator already exists. Manage additional users through the application.');
    const [user] = await connection.query<ResultSetHeader>(
      'INSERT INTO user (uid, username, auth_key, password_hash, status, created_at, updated_at) VALUES (?, ?, ?, ?, 10, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())',
      [generateUid(), input.username.trim(), crypto.randomBytes(16).toString('hex'), await bcrypt.hash(input.password, 12)]
    );
    await connection.query('INSERT INTO user_profile (user_id, name, sname, sort) VALUES (?, ?, ?, 0)', [user.insertId, input.name.trim(), '']);
    await connection.query('INSERT INTO role_power (user_id, role_name_id) VALUES (?, 1)', [user.insertId]);
    await connection.commit();
  } catch (error) {
    await connection.rollback();
    throw error;
  } finally {
    connection.release();
  }
}

function passwordPrompt(label: string): Promise<string> {
  process.stdout.write(label);
  emitKeypressEvents(process.stdin);
  process.stdin.setRawMode(true);
  process.stdin.resume();
  return new Promise((resolve, reject) => {
    let password = '';
    const finish = () => {
      process.stdin.setRawMode(false);
      process.stdin.removeListener('keypress', onKey);
      process.stdin.pause();
      process.stdout.write('\n');
    };
    const onKey = (text: string, key: { name?: string; ctrl?: boolean }) => {
      if (key?.ctrl && key.name === 'c') { finish(); reject(new Error('Cancelled.')); }
      else if (key?.name === 'return' || key?.name === 'enter') { finish(); resolve(password); }
      else if (key?.name === 'backspace') password = Array.from(password).slice(0, -1).join('');
      else if (text && !key?.ctrl && !/[\x00-\x1f\x7f]/.test(text)) password += text;
    };
    process.stdin.on('keypress', onKey);
  });
}

async function main() {
  if (!process.stdin.isTTY) throw new Error('Run admin:create in an interactive terminal. Passwords are not accepted as command-line arguments.');
  const readline = createInterface({ input: process.stdin, output: process.stdout });
  let username: string;
  let name: string;
  try {
    username = await readline.question('Administrator username: ');
    name = await readline.question('Display name: ');
  } finally { readline.close(); }
  const password = await passwordPrompt('Password (12+ characters, hidden): ');
  const confirmation = await passwordPrompt('Confirm password (hidden): ');
  if (password !== confirmation) throw new Error('Passwords do not match.');
  await createInitialAdmin(pool, { username, name, password });
  console.log('Administrator created. Sign in at http://localhost:3000/docz/');
}
if (require.main === module) main().catch(error => { console.error(error.message); process.exitCode = 1; }).finally(() => pool.end());
