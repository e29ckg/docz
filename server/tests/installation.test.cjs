const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const mysql = require('mysql2/promise');
const bcrypt = require('bcryptjs');
const { initializeDatabase } = require('../dist/scripts/initDatabase');
const { createInitialAdmin } = require('../dist/scripts/createAdmin');
const { pool: applicationPool } = require('../dist/db/connection');

async function main() {
  const database = 'docz_install_test_' + crypto.randomBytes(8).toString('hex');
  const config = {
    host: process.env.DB_HOST || '127.0.0.1', port: Number(process.env.DB_PORT) || 3306,
    user: process.env.DB_USER || 'root', password: process.env.DB_PASSWORD || '',
  };
  const connection = await mysql.createConnection(config);
  let testPool;
  let created = false;
  try {
    await assert.rejects(initializeDatabase(connection, 'bad-name;DROP'), /DB_NAME/);
    await connection.query(`CREATE DATABASE \`${database}\``);
    created = true;
    await initializeDatabase(connection, database);
    const [tables] = await connection.query('SHOW TABLES');
    assert.equal(tables.length, 20);
    await assert.rejects(initializeDatabase(connection, database), /not empty/);
    testPool = mysql.createPool({ ...config, database });
    await assert.rejects(createInitialAdmin(testPool, { username: 'test-admin', name: 'Test Admin', password: 'short' }), /12 characters/);
    const password = crypto.randomBytes(20).toString('hex');
    await createInitialAdmin(testPool, { username: 'test-admin', name: 'Test Admin', password });
    const [users] = await testPool.query('SELECT u.username, u.uid, u.password_hash, u.status, p.name FROM user u JOIN user_profile p ON p.user_id = u.id');
    assert.equal(users.length, 1);
    assert.equal(users[0].status, 10);
    assert.equal(users[0].name, 'Test Admin');
    assert(await bcrypt.compare(password, users[0].password_hash));
    assert.notEqual(users[0].password_hash, password);
    const [permissions] = await testPool.query('SELECT role_name_id FROM role_power');
    assert.deepEqual(permissions.map(row => row.role_name_id), [1]);
    await assert.rejects(createInitialAdmin(testPool, { username: 'another-admin', name: 'Second', password }), /already exists/);
    const [documents] = await testPool.query('SELECT COUNT(*) AS total FROM docz');
    assert.equal(documents[0].total, 0);
    console.log('PASS: clean schema, no document records, existing database guard, password hashing, administrator role, duplicate admin guard');
  } finally {
    if (testPool) await testPool.end();
    if (created && /^docz_install_test_[a-f0-9]{16}$/.test(database)) await connection.query(`DROP DATABASE \`${database}\``);
    await connection.end();
    await applicationPool.end();
  }
}
main().catch(error => { console.error(error.message); process.exitCode = 1; });
