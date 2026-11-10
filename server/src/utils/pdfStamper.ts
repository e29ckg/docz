import path from 'path';
import fs from 'fs';
import { PDFDocument, rgb } from 'pdf-lib';
// @ts-ignore - fontkit does not have official complete type defs
import fontkit from '@pdf-lib/fontkit';
import { pool } from '../db/connection';
import { RowDataPacket } from 'mysql2';
import { wrapPdfText } from './pdfTextLayout';

export interface StampResult {
  success: boolean;
  pages?: number;
  message?: string;
  error?: string;
}

/**
 * แปลงวันที่หรือ Timestamp เป็นข้อความวันที่แบบไทยสำหรับงานสารบรรณ
 * เช่น "วันที่ 3 พ.ย. 2569 เวลา 10:30 น."
 */
export function formatThaiTimestamp(dateInput?: string | Date | null): string {
  if (!dateInput) return '';
  const str = String(dateInput).trim();
  if (str.includes('วันที่')) return str;
  const d = new Date(str);
  if (isNaN(d.getTime())) return str;

  const thYear = d.getFullYear() + 543;
  const months = [
    '',
    'ม.ค.',
    'ก.พ.',
    'มี.ค.',
    'เม.ย.',
    'พ.ค.',
    'มิ.ย.',
    'ก.ค.',
    'ส.ค.',
    'ก.ย.',
    'ต.ค.',
    'พ.ย.',
    'ธ.ค.',
  ];
  const monthName = months[d.getMonth() + 1] || '';
  const day = d.getDate();
  const pad = (n: number) => String(n).padStart(2, '0');
  const hh = pad(d.getHours());
  const mm = pad(d.getMinutes());
  return `วันที่ ${day} ${monthName} ${thYear} เวลา ${hh}:${mm} น.`;
}

/**
 * ฝังภาพลายเซ็นลงใน PDF อย่างปลอดภัย รองรับทั้ง PNG และ JPG
 * แก้ไขปัญหา pdf-lib JpegEmbedder ใช้อ้างอิง imageData.buffer เริ่มต้นที่ offset 0
 * ซึ่งไม่ตรงกับ Node.js Buffer pool จึงต้องโคลนเป็น Uint8Array ใหม่ที่มี byteOffset = 0 เสมอ
 */
async function embedImageSafely(pdfDoc: PDFDocument, imgBytes: Buffer | Uint8Array) {
  if (!imgBytes || imgBytes.length < 4) return null;

  // โคลนเป็น Uint8Array ที่มี ArrayBuffer ประจำตัวเริ่มต้นที่ index 0 อย่างแม่นยำ
  const cleanBytes = new Uint8Array(imgBytes);

  const isPng =
    cleanBytes[0] === 0x89 && cleanBytes[1] === 0x50 && cleanBytes[2] === 0x4e && cleanBytes[3] === 0x47;
  if (isPng) {
    try {
      return await pdfDoc.embedPng(cleanBytes);
    } catch {
      try {
        return await pdfDoc.embedJpg(cleanBytes);
      } catch {
        return null;
      }
    }
  }

  const isJpg = cleanBytes[0] === 0xff && cleanBytes[1] === 0xd8;
  if (isJpg) {
    try {
      return await pdfDoc.embedJpg(cleanBytes);
    } catch {
      try {
        return await pdfDoc.embedPng(cleanBytes);
      } catch {
        return null;
      }
    }
  }

  try {
    return await pdfDoc.embedJpg(cleanBytes);
  } catch {
    try {
      return await pdfDoc.embedPng(cleanBytes);
    } catch {
      return null;
    }
  }
}

/**
 * วาดตรายางรับหนังสือ (Watermark Receive Stamp) ที่มุมบนขวา
 * ตามรูปแบบตัวอย่างราชการ:
 * - กรอบสี่เหลี่ยมเดี่ยวสีน้ำเงิน เส้นขอบ 0.8 pt (ไม่มีเส้นคั่นแถวด้านใน)
 * - ข้อความสีน้ำเงิน ชิดซ้าย (Left-aligned) พร้อมระยะขอบซ้าย 6 pt
 * แถว 1: ชื่อศาล (ฟอนต์ธรรมดา ขนาด 8.8 pt)
 * แถว 2: รับที่ {เลขรับ} (ฟอนต์ธรรมดา/กึ่งหนา ขนาด 10.5 pt)
 * แถว 3: วันที่ {วันที่รับแบบไทย} เวลา {เวลา} น. (ฟอนต์ธรรมดา ขนาด 9.5 pt)
 */
function drawWatermarkReceiveStamp(
  page: any,
  fontRegular: any,
  fontBold: any,
  courtName: string,
  receiveNumber: string,
  receiveDateStr: string
) {
  const { width, height } = page.getSize();
  const mm = 72 / 25.4;
  const boxW = 60 * mm;
  const boxH = 18 * mm; // ความสูงรวม 18 มม. (3 แถว แถวละ 6 มม.)

  // ตำแหน่งมุมบนขวา (140 mm จากซ้าย หรือชิดขอบขวาห่าง 5 mm)
  const x = Math.min(140 * mm, width - boxW - 5 * mm);
  const yTop = height - 5 * mm;
  const yBottom = yTop - boxH;
  const blue = rgb(0, 0, 1); // สีน้ำเงินบริสุทธิ์ (Pure Blue #0000FF)

  // กรอบสี่เหลี่ยมด้านนอก (ไม่มีเส้นคั่นแถวด้านใน ตามตัวอย่างราชการ)
  page.drawRectangle({
    x,
    y: yBottom,
    width: boxW,
    height: boxH,
    borderColor: blue,
    borderWidth: 0.8,
  });

  const padLeft = 6;
  const courtText = courtName || 'ศาลเยาวชนและครอบครัวจังหวัดประประจวบคีรีขันธ์';

  // แถวที่ 1: ชื่อศาล (ชิดซ้าย)
  page.drawText(courtText, {
    x: x + padLeft,
    y: yBottom + 35,
    size: 8.8,
    font: fontRegular,
    color: blue,
  });

  // แถวที่ 2: เลขรับ (ชิดซ้าย)
  const rNum = 'รับที่  ' + (receiveNumber || '');
  page.drawText(rNum, {
    x: x + padLeft,
    y: yBottom + 20,
    size: 10.5,
    font: fontRegular,
    color: blue,
  });

  // แถวที่ 3: วันที่รับแบบไทยและเวลา (ชิดซ้าย)
  const dStr = formatThaiTimestamp(receiveDateStr);
  page.drawText(dStr, {
    x: x + padLeft,
    y: yBottom + 5,
    size: 9.5,
    font: fontRegular,
    color: blue,
  });
}

/**
 * 1. ประทับตราเลขลงรับ (Watermark Stamp) บนหัวหนังสือด้านขวาของหนังสือนำ (PDF)
 * ทำงานด้วย Pure Node.js (pdf-lib) เร็วกว่าเดิมเกือบ 10 เท่า และไม่พึ่งพา PHP CLI
 */
export async function stampReceivePdf(
  relativePdfPath: string,
  courtName: string,
  receiveNumber: string,
  receiveDate: string
): Promise<StampResult> {
  const absolutePdfPath = path.resolve(__dirname, '../../../web', relativePdfPath);
  if (!fs.existsSync(absolutePdfPath)) {
    return { success: false, error: 'ไม่พบไฟล์ PDF บนดิสก์: ' + relativePdfPath };
  }

  try {
    const existingBytes = fs.readFileSync(absolutePdfPath);
    const pdfDoc = await PDFDocument.load(existingBytes);
    pdfDoc.registerFontkit(fontkit);

    const fontRegularPath = path.resolve(__dirname, '../../../web/fonts/THSarabunNew.ttf');
    const fontBoldPath = path.resolve(__dirname, '../../../web/fonts/THSarabunNew Bold.ttf');

    if (!fs.existsSync(fontRegularPath) || !fs.existsSync(fontBoldPath)) {
      return { success: false, error: 'ไม่พบไฟล์ฟอนต์ THSarabunNew ใน web/fonts/' };
    }

    const fontRegular = await pdfDoc.embedFont(fs.readFileSync(fontRegularPath));
    const fontBold = await pdfDoc.embedFont(fs.readFileSync(fontBoldPath));

    const totalPages = pdfDoc.getPageCount();
    if (totalPages === 0) {
      return { success: false, error: 'เอกสาร PDF ไม่มีหน้ากระดาษ' };
    }

    // ประทับตราที่หน้าแรก (หน้าที่ 1)
    const page0 = pdfDoc.getPage(0);
    drawWatermarkReceiveStamp(
      page0,
      fontRegular,
      fontBold,
      courtName || 'ศาลเยาวชนและครอบครัวจังหวัดประประจวบคีรีขันธ์',
      receiveNumber,
      receiveDate
    );

    pdfDoc.setTitle('หนังสือเลขรับ ' + receiveNumber);
    pdfDoc.setAuthor(courtName || 'ศาลเยาวชนและครอบครัวจังหวัดประประจวบคีรีขันธ์');

    const modifiedBytes = await pdfDoc.save();
    fs.writeFileSync(absolutePdfPath, modifiedBytes);

    return {
      success: true,
      pages: totalPages,
      message: `ประทับตราเลขลงรับ ${receiveNumber} เรียบร้อยแล้ว (Pure Node.js)`,
    };
  } catch (err: any) {
    console.error('[Pure Node.js Stamp Receive Error]', err);
    return {
      success: false,
      error: err.message || 'เกิดข้อผิดพลาดในการประทับตราเลขลงรับ',
    };
  }
}

/**
 * 2. เพิ่มหน้าบันทึกเกษียนหนังสือ (Signing Slip Page) เข้าสู่ไฟล์ PDF หลัก
 * เมื่อหนังสือดำเนินการลงนามเสร็จสมบูรณ์ (เกษียนครบ st = 3)
 * ทำงานด้วย Pure Node.js (pdf-lib) 100% ไม่ต้องเรียกใช้ PHP scripts/stamp-end.php
 */
export async function stampEndPdf(docId: number): Promise<StampResult> {
  try {
    // 1. ดึงข้อมูลหนังสือ docz
    const [docRows] = await pool.query<RowDataPacket[]>(
      'SELECT id, r_number, r_date, name, file, st FROM docz WHERE id = ? LIMIT 1',
      [docId]
    );

    if (!docRows.length) {
      return { success: false, error: `ไม่พบข้อมูลหนังสือ ID: ${docId}` };
    }

    const doc = docRows[0];
    if (!doc.file) {
      return { success: false, error: `หนังสือ ID ${docId} ไม่มีไฟล์หนังสือนำ (file is empty)` };
    }

    const webRoot = path.resolve(__dirname, '../../../web');
    const completePath = path.resolve(webRoot, doc.file);

    if (!fs.existsSync(completePath)) {
      return { success: false, error: `ไม่พบไฟล์ PDF บนดิสก์: ${completePath}` };
    }

    // 2. ดึงข้อมูลการลงนามทั้งหมดของหนังสือที่ลงนามแล้ว (st = 3)
    const sqlSteps = `
      SELECT 
        dm.id,
        dm.doc_id,
        dm.sort,
        dm.ty,
        dm.detail,
        dm.updated,
        dm.user_id,
        dm.role_name_id,
        rn.name as role_title,
        up.pfname,
        up.name,
        up.sname,
        up.dep_name as user_dep_name,
        up.sign_photo,
        r.name_dep1,
        r.name_dep2,
        r.name_dep3
      FROM doc_manage dm
      LEFT JOIN user_profile up ON up.user_id = dm.user_id
      LEFT JOIN role_name rn ON rn.id = dm.role_name_id
      LEFT JOIN role r ON (r.user_id = dm.user_id AND r.role_name_id = dm.role_name_id)
      WHERE dm.doc_id = ? AND dm.st = 3
      ORDER BY dm.sort ASC, dm.id ASC
    `;
    const [signedSteps] = await pool.query<RowDataPacket[]>(sqlSteps, [docId]);

    if (!signedSteps.length) {
      return { success: false, error: `หนังสือ ID ${docId} ยังไม่มีขั้นตอนที่ลงนามแล้ว (st = 3)` };
    }

    // 3. จัดการไฟล์ต้นฉบับสำรอง (เพื่อให้ประทับซ้ำได้เสมอโดยไม่ซ้อนทับหน้าเพิ่มซ้ำๆ)
    const origPath = completePath + '.orig.pdf';
    if (!fs.existsSync(origPath)) {
      fs.copyFileSync(completePath, origPath);
    }

    const sourcePath = fs.existsSync(origPath) ? origPath : completePath;
    const existingBytes = fs.readFileSync(sourcePath);
    const pdfDoc = await PDFDocument.load(existingBytes);
    pdfDoc.registerFontkit(fontkit);

    const fontRegularPath = path.resolve(webRoot, 'fonts/THSarabunNew.ttf');
    const fontBoldPath = path.resolve(webRoot, 'fonts/THSarabunNew Bold.ttf');

    if (!fs.existsSync(fontRegularPath) || !fs.existsSync(fontBoldPath)) {
      return { success: false, error: 'ไม่พบไฟล์ฟอนต์ THSarabunNew ใน web/fonts/' };
    }

    const fontRegular = await pdfDoc.embedFont(fs.readFileSync(fontRegularPath));
    const fontBold = await pdfDoc.embedFont(fs.readFileSync(fontBoldPath));

    const origPageCount = pdfDoc.getPageCount();

    // 4. เพิ่มหน้าใหม่สำหรับรายการเกษียนหนังสือ (A4: 595.28 x 841.89 pt)
    let currentSlipPage = pdfDoc.addPage([595.28, 841.89]);
    const { width } = currentSlipPage.getSize();

    // วาดตรายางรับหนังสือที่มุมบนขวาของหน้าใบเกษียน
    const courtName = 'ศาลเยาวชนและครอบครัวจังหวัดประประจวบคีรีขันธ์';
    drawWatermarkReceiveStamp(
      currentSlipPage,
      fontRegular,
      fontBold,
      courtName,
      doc.r_number || '',
      doc.r_date
    );

    // 5. วาดรายการเกษียนของผู้ลงนามแต่ละท่าน
    let currentY = 760;
    const blue = rgb(0, 0, 1);
    const orange = rgb(0.85, 0.4, 0);
    const margin = 50;
    const contentWidth = width - margin * 2;
    const newSlipPage = () => {
      currentSlipPage = pdfDoc.addPage([595.28, 841.89]);
      currentY = 780;
    };

    for (const step of signedSteps) {
      const tyLines = step.ty && String(step.ty).trim()
        ? wrapPdfText(String(step.ty).replace(/<[^>]+>/g, '').trim(), fontBold, 13, contentWidth)
        : [];
      const detailLines = step.detail && String(step.detail).trim()
        ? wrapPdfText(String(step.detail).trim(), fontRegular, 12.5, contentWidth)
        : [];
      const fullName = `(${((step.pfname || '') + (step.name || '') + ' ' + (step.sname || '')).trim() || 'ผู้ลงนาม'})`;
      const nameLines = wrapPdfText(fullName, fontRegular, 12, contentWidth);
      const depLines = [
        step.name_dep1 || step.role_title || step.user_dep_name,
        step.name_dep2,
        step.name_dep3,
      ].filter(Boolean).flatMap(dep => wrapPdfText(String(dep).trim(), fontRegular, 11, contentWidth));
      // Reserve the whole signature block, including the tallest possible signature image.
      const signatureHeight = (step.sign_photo ? 40 : 0)
        + (nameLines.length + depLines.length) * 14 + (step.updated ? 22 : 0);
      const textHeight = tyLines.length * 18 + detailLines.length * 16;
      const blockHeight = textHeight + signatureHeight;
      if (currentY - blockHeight < margin && blockHeight <= 780 - margin) newSlipPage();

      // 5.1 ข้อความเกษียน (ty) สีส้มเข้ม ชิดซ้าย
      for (const line of tyLines) {
        if (currentY - 18 < margin) newSlipPage();
        currentSlipPage.drawText(line, {
          x: margin,
          y: currentY,
          size: 13,
          font: fontBold,
          color: orange,
        });
        currentY -= 18;
      }

      // 5.2 ข้อสั่งการ (detail) สีน้ำเงิน ชิดซ้าย
      for (const line of detailLines) {
        if (currentY - 16 < margin) newSlipPage();
        currentSlipPage.drawText(line, {
          x: margin,
          y: currentY,
          size: 12.5,
          font: fontRegular,
          color: blue,
        });
        currentY -= 16;
      }

      if (currentY - signatureHeight < margin) newSlipPage();

      // 5.3 ภาพลายเซ็น (signature image) กึ่งกลาง
      if (step.sign_photo) {
        let cleanSign = String(step.sign_photo).replace(/^[\\\/]+/, '');
        if (cleanSign.startsWith('web/') || cleanSign.startsWith('web\\')) {
          cleanSign = cleanSign.substring(4);
        }
        const signFullPath = path.resolve(webRoot, cleanSign);
        if (fs.existsSync(signFullPath)) {
          try {
            const imgBytes = fs.readFileSync(signFullPath);
            const embeddedImg = await embedImageSafely(pdfDoc, imgBytes);
            if (embeddedImg) {
              const scale = Math.min(34 / embeddedImg.height, contentWidth / embeddedImg.width);
              const targetH = embeddedImg.height * scale;
              const targetW = embeddedImg.width * scale;
              const imgX = (width - targetW) / 2;

              currentSlipPage.drawImage(embeddedImg, {
                x: imgX,
                y: currentY - targetH,
                width: targetW,
                height: targetH,
              });
              currentY -= targetH + 6;
            }
          } catch (imgErr) {
            console.warn('[Embed Signature Warning]', imgErr);
          }
        }
      }

      // 5.4 ชื่อ-นามสกุลผู้ลงนาม (กึ่งกลาง)
      for (const nameLine of nameLines) {
        const fnW = fontRegular.widthOfTextAtSize(nameLine, 12);
        currentSlipPage.drawText(nameLine, {
          x: (width - fnW) / 2,
          y: currentY,
          size: 12,
          font: fontRegular,
          color: blue,
        });
        currentY -= 14;
      }

      // 5.5 ตำแหน่ง / กลุ่มงาน (กึ่งกลาง)
      for (const depStr of depLines) {
        const depW = fontRegular.widthOfTextAtSize(depStr, 11);
        currentSlipPage.drawText(depStr, {
          x: (width - depW) / 2,
          y: currentY,
          size: 11,
          font: fontRegular,
          color: blue,
        });
        currentY -= 14;
      }

      // 5.6 วันที่และเวลาที่ลงนาม (กึ่งกลาง)
      if (step.updated) {
        const stepDateStr = formatThaiTimestamp(step.updated);
        const sDateW = fontRegular.widthOfTextAtSize(stepDateStr, 10.5);
        currentSlipPage.drawText(stepDateStr, {
          x: (width - sDateW) / 2,
          y: currentY,
          size: 10.5,
          font: fontRegular,
          color: blue,
        });
        currentY -= 22; // ระยะห่างก่อนถึงผู้ลงนามท่านถัดไป
      }
    }

    pdfDoc.setTitle(doc.name || 'หนังสือ');
    pdfDoc.setAuthor(courtName);

    // 6. เขียนทับไฟล์ PDF ปลายทาง
    const finalBytes = await pdfDoc.save();
    fs.writeFileSync(completePath, finalBytes);

    const totalPages = pdfDoc.getPageCount();

    return {
      success: true,
      pages: totalPages,
      message: `สร้างใบต่อท้ายเกษียนครบสำเร็จ (เดิม ${origPageCount} หน้า รวมเป็น ${totalPages} หน้า)`,
    };
  } catch (err: any) {
    console.error('[Pure Node.js Stamp End Error]', err);
    return {
      success: false,
      error: err.message || 'เกิดข้อผิดพลาดในการสร้างใบต่อท้ายเกษียนครบ',
    };
  }
}
