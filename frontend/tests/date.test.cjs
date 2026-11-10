const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { spawnSync } = require('node:child_process');
const ts = require('typescript');

if (!process.argv.includes('--case')) {
  for (const timeZone of ['Asia/Bangkok', 'UTC', 'America/New_York']) {
    const run = spawnSync(process.execPath, [__filename, '--case'], { env: { ...process.env, TZ: timeZone }, encoding: 'utf8' });
    assert.equal(run.status, 0, `${timeZone}: ${run.stderr || run.stdout}`);
    console.log(`PASS: Bangkok dates and times with host timezone ${timeZone}`);
  }
} else {
  const compiled = ts.transpileModule(fs.readFileSync(path.join(__dirname, '../src/utils/date.ts'), 'utf8'), {
    compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.CommonJS },
  }).outputText;
  const context = { exports: {}, Date, Intl };
  vm.runInNewContext(compiled, context);
  const { formatThaiDateTime, formatThaiDate, formatThaiTime, parseDate } = context.exports;
  for (const input of [
    '2026-11-10T04:59:00.000Z', '2026-11-10T11:59:00+07:00',
    '2026-11-10T11:59:00', '2026-11-10 11:59:00', '2026-11-10T11:59',
    new Date('2026-11-10T04:59:00Z'), Date.parse('2026-11-10T04:59:00Z'),
  ]) {
    assert.equal(formatThaiDateTime(input), '10 พ.ย. 2569 11:59 น.');
    assert.equal(formatThaiTime(input), '11:59 น.');
  }
  assert.equal(formatThaiDateTime('2026-11-09T18:30:00Z'), '10 พ.ย. 2569 01:30 น.');
  assert.equal(formatThaiDateTime('2026-12-31T17:00:00Z'), '1 ม.ค. 2570 00:00 น.');
  assert.equal(formatThaiDate('2026-11-10'), '10 พ.ย. 2569');
  assert.equal(formatThaiDateTime('2026-11-10T04:59:00Z', { shortYear: true }), '10 พ.ย. 69 11:59 น.');
  assert.equal(parseDate('2026-11-10T04:59:00Z').toISOString(), '2026-11-10T04:59:00.000Z');
  for (const input of [null, undefined, '', '-', '—', 'Invalid Date', 'not-a-date']) {
    assert.equal(formatThaiDateTime(input), '—');
  }
}
