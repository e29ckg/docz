const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { PDFDocument, PDFPage } = require('pdf-lib');
const fontkit = require('@pdf-lib/fontkit');
const { wrapPdfText } = require('../dist/utils/pdfTextLayout');
const { stampEndPdf } = require('../dist/utils/pdfStamper');
const { pool } = require('../dist/db/connection');

async function main() {
  const source = await PDFDocument.create();
  source.addPage([595.28, 841.89]);
  const sourceBytes = await source.save();
  source.registerFontkit(fontkit);
  const font = await source.embedFont(fs.readFileSync(path.join(__dirname, '../../web/fonts/THSarabunNew.ttf')));
  const detail = '- ขอความอนุเคราะห์ตรวจสอบข้อมูลสำหรับการทดสอบการตัดคำภาษาไทยในหน้าบันทึกท้ายเอกสาร โดยข้อความนี้เป็นข้อมูลสมมติทั้งหมดและใช้ตรวจว่าข้อความที่ยาวสามารถขึ้นบรรทัดใหม่ได้โดยไม่ล้นขอบกระดาษและไม่ทับลายเซ็น\n- เห็นควรให้กลุ่มงานดำเนินการในส่วนที่เกี่ยวข้องต่อไป';
  for (const text of [detail, 'ภาษาไทยไม่มีช่องว่าง'.repeat(40), 'LONG_IDENTIFIER_'.repeat(80), 'บรรทัดแรก\r\n\r\nบรรทัดสุดท้าย']) {
    const lines = wrapPdfText(text, font, 12.5, 495.28);
    assert(lines.every(line => font.widthOfTextAtSize(line, 12.5) <= 495.28));
    assert.equal(lines.join('').replace(/\s/g, ''), text.replace(/\s/g, ''));
    assert(lines.every(line => !/^[\u0e31\u0e34-\u0e3a\u0e47-\u0e4e]/u.test(line)));
  }
  assert.deepEqual(wrapPdfText('ก\r\n\r\nข', font, 12.5, 495.28), ['ก', '', 'ข']);

  const originals = { exists: fs.existsSync, read: fs.readFileSync, write: fs.writeFileSync, query: pool.query, draw: PDFPage.prototype.drawText };
  let output;
  let steps;
  const draws = [];
  try {
    // No database access or modifications to existing documents during this regression check.
    pool.query = async sql => [sql.includes('FROM docz')
      ? [{ file: 'layout-regression.pdf', r_number: '2022/2569', r_date: '2026-11-06T15:51:00', name: 'Layout regression' }]
      : steps];
    fs.existsSync = file => String(file).includes('layout-regression.pdf') || originals.exists(file);
    fs.readFileSync = (file, ...args) => String(file).includes('layout-regression.pdf') ? sourceBytes : originals.read(file, ...args);
    fs.writeFileSync = (file, bytes) => { assert(String(file).endsWith('layout-regression.pdf')); output = bytes; };
    PDFPage.prototype.drawText = function(text, options) {
      draws.push({ page: this, text, ...options });
      return originals.draw.call(this, text, options);
    };
    const step = { ty: 'เรียน ผู้พิพากษาหัวหน้าศาลฯ - เพื่อโปรดพิจารณา', detail, pfname: 'นาย', name: 'ทดสอบ', sname: 'ระบบ', name_dep1: 'หัวหน้ากลุ่มงาน', updated: '2026-11-06T16:12:00' };
    steps = [step, { ...step, detail: '' }, { ...step, ty: '- ทราบ / ดำเนินการตามเสนอ', detail: '' }];
    let result = await stampEndPdf(1);
    assert.equal(result.success, true, result.error);
    assert.equal(result.pages, 2);
    const firstDetail = draws.filter(draw => draw.size === 12.5);
    assert(firstDetail.length > 2, 'Long Thai text must wrap beyond the two explicit lines');
    assert(firstDetail.every(draw => draw.x + draw.font.widthOfTextAtSize(draw.text, draw.size) <= 545.28));
    const firstName = draws.find(draw => draw.size === 12);
    assert(firstName.y < firstDetail.at(-1).y - 12, 'Signature block must follow all detail lines');
    if (process.env.PDF_LAYOUT_PREVIEW) originals.write(process.env.PDF_LAYOUT_PREVIEW, output);

    draws.length = 0;
    steps = [{ ...step, detail: (detail + '\n').repeat(35) }, step];
    result = await stampEndPdf(1);
    assert.equal(result.success, true, result.error);
    assert(result.pages > 3, 'Oversized notes must continue on additional pages');
    assert(draws.every(draw => draw.y >= 50), 'All text must stay above bottom margin');
    assert(draws.filter(draw => draw.size === 12.5).every(draw => draw.y <= 780));
    console.log('PASS: Thai wrapping, grapheme boundaries, explicit blank lines, signature spacing, and multipage notes');
  } finally {
    fs.existsSync = originals.exists;
    fs.readFileSync = originals.read;
    fs.writeFileSync = originals.write;
    pool.query = originals.query;
    PDFPage.prototype.drawText = originals.draw;
    await pool.end();
  }
}
main().catch(error => { console.error(error); process.exitCode = 1; });
