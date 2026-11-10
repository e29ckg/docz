const assert = require('node:assert/strict');
const express = require('express');
const { pool } = require('../dist/db/connection');
const { documentRouter } = require('../dist/routes/document.routes');

async function main() {
  const originalQuery = pool.query;
  let allowed = true;
  const queries = [];
  pool.query = async (sql, params = []) => {
    queries.push({ sql, params });
    if (sql.startsWith('SELECT id, uid FROM docz')) return [[{ id: 42, uid: 'test' }]];
    if (sql.startsWith('SELECT id FROM doc_user_read')) return [allowed ? [{ id: 1 }] : []];
    if (sql.includes('COUNT(*) as total')) return [[{ total: 1 }]];
    if (sql.includes('SELECT d.id, d.uid')) return [[{ id: 42, st: 4, is_pinned: 1 }]];
    return [{ affectedRows: 1 }];
  };
  const app = express();
  app.use(express.json());
  app.use((req, _res, next) => {
    if (req.headers['x-test-user']) req.user = { id: Number(req.headers['x-test-user']), isAdmin: true };
    next();
  });
  app.use(documentRouter);
  const server = app.listen(0, '127.0.0.1');
  await new Promise(resolve => server.once('listening', resolve));
  const base = `http://127.0.0.1:${server.address().port}`;
  async function pin(user, pinned, extra = {}) {
    return fetch(base + '/documents/42/pin', {
      method: 'POST', headers: { 'Content-Type': 'application/json', ...(user ? { 'x-test-user': String(user) } : {}) },
      body: JSON.stringify({ pinned, ...extra }),
    });
  }
  try {
    assert.equal((await pin(null, true)).status, 401);
    assert.equal((await pin(7, 'true')).status, 400);
    allowed = false;
    assert.equal((await pin(7, true)).status, 403, 'Even admins cannot pin documents outside their inbox');
    allowed = true;
    for (const user of [7, 8]) {
      const response = await pin(user, true, { userId: 99 });
      assert.equal(response.status, 200);
      assert.equal((await response.json()).data.isPinned, true);
      assert.deepEqual(queries.at(-1).params, [user, 42], 'Ignore client-supplied user IDs');
      assert.match(queries.at(-1).sql, /INSERT IGNORE/, 'Repeated pin operations are idempotent');
    }
    const unpin = await pin(7, false);
    assert.equal((await unpin.json()).data.isPinned, false);
    assert.match(queries.at(-1).sql, /DELETE.*user_id = \? AND doc_id = \?/);
    assert.deepEqual(queries.at(-1).params, [7, 42]);
    const inbox = await fetch(base + '/documents?scope=mine&page=2', { headers: { 'x-test-user': '8' } });
    assert.equal(inbox.status, 200);
    assert.equal((await inbox.json()).data[0].isPinned, true);
    const list = queries.at(-1);
    assert.match(list.sql, /ORDER BY is_pinned DESC, d.id DESC\s+LIMIT/);
    assert.deepEqual(list.params, [8, 8, 8, 8, 20, 20]);
    await fetch(base + '/documents?scope=visible', { headers: { 'x-test-user': '8' } });
    assert(!queries.at(-1).sql.includes('FROM doc_user_pin'), 'Registry order is unchanged');
    console.log('PASS: authentication, recipient authorization, user isolation, idempotent pins, unpin, ordering before pagination');
  } finally {
    await new Promise(resolve => server.close(resolve));
    pool.query = originalQuery;
    await pool.end();
  }
}
main().catch(error => { console.error(error); process.exitCode = 1; });
