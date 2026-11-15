const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const express = require('express');
const { PDFDocument } = require('pdf-lib');
const { pool } = require('../dist/db/connection');
const { createActionFilesRouter } = require('../dist/routes/actionFiles.routes');

async function main() {
  const parent = path.resolve(__dirname, '../../scratch');
  fs.mkdirSync(parent, { recursive: true });
  const root = fs.mkdtempSync(path.join(parent, 'action-files-test-'));
  const originals = { query: pool.query, getConnection: pool.getConnection };
  const records = [];
  let allowed = true;
  let destroyed = false;
  let failInsert = false;
  let pending = [];
  const query = async (sql, params = []) => {
    if (sql.startsWith('SELECT id, uid FROM docz')) return [[{ id: Number(params[0]) === 43 ? 43 : 42, uid: 'doc' }]];
    if (sql.startsWith('SELECT d.st')) return [allowed ? [{ st: destroyed ? 6 : 4 }] : []];
    if (sql.startsWith('SELECT deleted_at FROM doc_user_action_file')) return [[]];
    if (sql.startsWith('UPDATE doc_user_action_file SET deleted_at')) {
      const row = records.find(row => row.uid === params[0] && row.doc_id === params[1] && row.user_id === params[2] && !row.deleted_at);
      if (row) row.deleted_at = new Date();
      return [{ affectedRows: row ? 1 : 0 }];
    }
    if (sql.startsWith('INSERT INTO doc_user_action_file')) {
      if (failInsert) throw new Error('Simulated database failure');
      pending.push({ uid: params[0], doc_id: params[1], user_id: params[2], original_name: params[3], size_bytes: params[4], note: params[5], created_at: new Date() });
      return [{ affectedRows: 1 }];
    }
    if (sql.includes('WHERE uid = ? AND doc_id = ? AND user_id = ?')) return [records.filter(row => row.uid === params[0] && row.doc_id === params[1] && row.user_id === params[2] && !row.deleted_at)];
    if (sql.includes('FROM doc_user_action_file WHERE doc_id = ? AND user_id = ?')) return [records.filter(row => row.doc_id === params[0] && row.user_id === params[1] && !row.deleted_at)];
    throw new Error('Unexpected query: ' + sql);
  };
  pool.query = query;
  pool.getConnection = async () => ({
    query, beginTransaction: async () => { pending = []; },
    commit: async () => { records.push(...pending); pending = []; },
    rollback: async () => { pending = []; }, release() {},
  });
  const app = express();
  app.use((req, _res, next) => { if (req.headers['x-test-user']) req.user = { id: Number(req.headers['x-test-user']), isAdmin: true }; next(); });
  app.use(createActionFilesRouter(root));
  const server = app.listen(0, '127.0.0.1');
  await new Promise(resolve => server.once('listening', resolve));
  const base = `http://127.0.0.1:${server.address().port}/documents/42/action-files`;
  const pdf = await PDFDocument.create(); pdf.addPage();
  const bytes = await pdf.save();
  const headers = user => user ? { 'x-test-user': String(user) } : {};
  async function upload(user, uploads = [{ name: 'หลักฐาน.pdf', data: bytes }], note = 'ดำเนินการเรียบร้อยแล้ว') {
    const form = new FormData();
    uploads.forEach(file => form.append('files', new Blob([file.data], { type: 'application/pdf' }), file.name));
    form.append('note', note);
    return fetch(base, { method: 'POST', headers: headers(user), body: form });
  }
  try {
    assert.equal((await upload(null)).status, 401);
    allowed = false;
    assert.equal((await upload(7)).status, 403);
    allowed = true;
    assert.equal((await upload(7, [{ name: 'bad.pdf', data: Buffer.from('%PDF-invalid') }])).status, 400);
    assert.equal((await upload(7, [{ name: 'bad.txt', data: bytes }])).status, 400);
    assert.equal((await upload(7, [], 'test')).status, 400);
    assert.equal(fs.readdirSync(root).length, 0);
    const successful = await upload(7, [{ name: 'หลักฐาน.pdf', data: bytes }, { name: 'report.PDF', data: bytes }]);
    assert.equal(successful.status, 201, await successful.text());
    assert.equal(records.length, 2);
    assert.equal(records[0].original_name, 'หลักฐาน.pdf');
    assert.equal(records[0].note, 'ดำเนินการเรียบร้อยแล้ว');
    const own = await fetch(base, { headers: headers(7) });
    assert.equal((await own.json()).data.length, 2);
    const other = await fetch(base, { headers: headers(8) });
    assert.equal((await other.json()).data.length, 0);
    assert.equal((await fetch(`${base}/${records[0].uid}/file`, { headers: headers(8) })).status, 404);
    const viewed = await fetch(`${base}/${records[0].uid}/file`, { headers: headers(7) });
    assert.equal(viewed.status, 200);
    assert.equal(viewed.headers.get('content-type'), 'application/pdf');
    assert.deepEqual(Buffer.from(await viewed.arrayBuffer()), Buffer.from(bytes));
    const downloaded = await fetch(`${base}/${records[0].uid}/file?download=1`, { headers: headers(7) });
    assert.match(downloaded.headers.get('content-disposition'), /^attachment;/);
    assert.equal((await upload(7, [{ name: 'large.pdf', data: Buffer.alloc(10 * 1024 * 1024 + 1) }])).status, 400);
    assert.equal((await upload(7, Array.from({ length: 6 }, () => ({ name: 'test.pdf', data: bytes })))).status, 400);
    destroyed = true;
    assert.equal((await upload(7)).status, 409);
    destroyed = false;
    failInsert = true;
    assert.equal((await upload(7)).status, 500);
    assert.equal(fs.readdirSync(root).length, 2, 'Failed database insert must remove the uploaded file');
    assert.equal(records.length, 2);
    failInsert = false;
    const removeUrl = `${base}/${records[0].uid}`;
    assert.equal((await fetch(removeUrl, { method: 'DELETE' })).status, 401);
    assert.equal((await fetch(removeUrl, { method: 'DELETE', headers: headers(8) })).status, 404, 'Other users, including administrators, cannot remove the owner\'s evidence');
    assert.equal((await fetch(removeUrl.replace('/documents/42/', '/documents/43/'), { method: 'DELETE', headers: headers(7) })).status, 404);
    assert(!records[0].deleted_at);
    assert.equal((await fetch(removeUrl, { method: 'DELETE', headers: headers(7) })).status, 200);
    assert(records[0].deleted_at);
    assert(!records[1].deleted_at, 'Other evidence must remain available');
    assert.equal((await (await fetch(base, { headers: headers(7) })).json()).data.length, 1);
    assert.equal((await fetch(`${removeUrl}/file`, { headers: headers(7) })).status, 404);
    assert.equal((await fetch(`${removeUrl}/file?download=1`, { headers: headers(7) })).status, 404);
    assert.equal((await fetch(removeUrl, { method: 'DELETE', headers: headers(7) })).status, 404);
    assert(fs.existsSync(path.join(root, `${records[0].uid}.pdf`)), 'Removed evidence is retained privately for recovery');
    console.log('PASS: upload/view/download, limits, rollback cleanup, owner-only removal, cross-document protection, removed files inaccessible');
  } finally {
    await new Promise(resolve => server.close(resolve));
    pool.query = originals.query; pool.getConnection = originals.getConnection;
    for (const file of fs.readdirSync(root)) {
      assert(/^[a-f0-9-]+\.pdf$/.test(file));
      fs.unlinkSync(path.join(root, file));
    }
    fs.rmdirSync(root);
    await pool.end();
  }
}
main().catch(error => { console.error(error); process.exitCode = 1; });
