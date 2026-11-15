const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { PDFDocument, degrees, rgb, pushGraphicsState, popGraphicsState, concatTransformationMatrix, StandardFonts } = require('pdf-lib');
const { stampReceivePdf } = require('../dist/utils/pdfStamper');
const { pool } = require('../dist/db/connection');

async function main() {
  const originals = { exists: fs.existsSync, read: fs.readFileSync, write: fs.writeFileSync };
  const preview = await PDFDocument.create();
  try {
    for (const cropped of [false, true]) {
      for (const angle of [0, 90, 180, 270]) {
        const swapped = angle === 90 || angle === 270;
        const width = swapped ? 841.89 : 595.28;
        const height = swapped ? 595.28 : 841.89;
        const source = await PDFDocument.create();
        const page = source.addPage([width, height]);
        const x = cropped ? 15 : 0;
        const y = cropped ? 50 : 0;
        if (cropped) {
          page.setMediaBox(-20, 30, width + 80, height + 70);
          page.setCropBox(x, y, width, height);
        }
        page.setRotation(degrees(angle));
        // Independent visible-coordinate fixture, not using the production helper.
        const matrices = {
          0: [1, 0, 0, 1, x, y], 90: [0, 1, -1, 0, x + width, y],
          180: [-1, 0, 0, -1, x + width, y + height], 270: [0, -1, 1, 0, x, y + height],
        };
        page.pushOperators(pushGraphicsState(), concatTransformationMatrix(...matrices[angle]));
        const font = await source.embedFont(StandardFonts.Helvetica);
        page.drawText(`TOP - rotation ${angle}, cropped ${cropped}`, { x: 50, y: 750, font, size: 14, color: rgb(0, 0, 0) });
        page.drawRectangle({ x: 50, y: 50, width: 495, height: 660, borderWidth: 1, borderColor: rgb(0.8, 0, 0) });
        page.pushOperators(popGraphicsState());
        const bytes = await source.save();
        let stampedBytes;
        fs.existsSync = file => String(file).includes('rotation-regression.pdf') || originals.exists(file);
        fs.readFileSync = (file, ...args) => String(file).includes('rotation-regression.pdf') ? bytes : originals.read(file, ...args);
        fs.writeFileSync = (file, value) => { assert(String(file).endsWith('rotation-regression.pdf')); stampedBytes = value; };
        const result = await stampReceivePdf('rotation-regression.pdf', 'หน่วยงานทดสอบ', 'TEST/2569', '2026-11-12T13:37:00');
        assert.equal(result.success, true, result.error);
        const output = await PDFDocument.load(stampedBytes);
        assert.equal(output.getPage(0).getRotation().angle, angle);
        assert.deepEqual(output.getPage(0).getMediaBox(), page.getMediaBox());
        assert.deepEqual(output.getPage(0).getCropBox(), page.getCropBox());
        const [copy] = await preview.copyPages(output, [0]);
        preview.addPage(copy);
      }
    }
    if (process.env.PDF_ROTATION_PREVIEW) originals.write(path.resolve(process.env.PDF_ROTATION_PREVIEW), await preview.save());
    console.log('PASS: receive stamps preserve page rotation and crop/media boxes for all 8 fixtures');
  } finally {
    fs.existsSync = originals.exists;
    fs.readFileSync = originals.read;
    fs.writeFileSync = originals.write;
    await pool.end();
  }
}
main().catch(error => { console.error(error); process.exitCode = 1; });
