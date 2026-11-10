import fs from 'fs';
import path from 'path';
import mysql, { Connection, RowDataPacket } from 'mysql2/promise';
import dotenv from 'dotenv';

dotenv.config({ path: path.resolve(__dirname, '../../.env') });

export async function initializeDatabase(connection: Connection, database: string): Promise<void> {
  if (!/^[A-Za-z0-9_]+$/.test(database)) throw new Error('DB_NAME must contain only letters, numbers, and underscores.');
  await connection.query(`CREATE DATABASE IF NOT EXISTS \`${database}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`);
  await connection.query(`USE \`${database}\``);
  const [tables] = await connection.query<RowDataPacket[]>('SHOW TABLES');
  if (tables.length) throw new Error('Database is not empty. Refusing to change existing data; use the upgrade instructions.');
  const schema = fs.readFileSync(path.resolve(__dirname, '../../src/db/schema.sql'), 'utf8');
  const statements = schema.replace(/^--.*$/gm, '').split(/;\s*(?:\n|$)/).map(sql => sql.trim()).filter(Boolean);
  for (const statement of statements) await connection.query(statement);
}

async function main() {
  const connection = await mysql.createConnection({
    host: process.env.DB_HOST || '127.0.0.1',
    port: Number(process.env.DB_PORT) || 3306,
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    charset: 'utf8mb4',
  });
  try {
    await initializeDatabase(connection, process.env.DB_NAME || 'docz');
    console.log('Initialized empty DOCZ database. Run npm run admin:create next.');
  } finally {
    await connection.end();
  }
}
if (require.main === module) main().catch(error => { console.error(error.message); process.exitCode = 1; });
