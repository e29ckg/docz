export interface ClientPageDimensionInfo {
  page: number;
  widthPt: number;
  heightPt: number;
  widthMm: number;
  heightMm: number;
  rotation: number;
  orientation: 'แนวตั้ง' | 'แนวนอน';
  isA4: boolean;
  isLetter: boolean;
  paperFormat: 'A4' | 'Letter' | 'Other';
  isValidPortrait: boolean;
}

export interface ClientPdfValidationOptions {
  allowLetter?: boolean;
}

export interface ClientPdfValidationResult {
  valid: boolean;
  pageCount: number;
  detectedFormat: string;
  message: string;
  pages: ClientPageDimensionInfo[];
}

/**
 * ตรวจสอบขนาดและแนวของไฟล์ PDF บนฝั่ง Client ก่อนทำการอัปโหลด
 * รองรับทั้งขนาด:
 *  - A4: 210 x 297 มม. (ISO 216)
 *  - Letter: 215.9 x 279.4 มม. (ANSI Y14.1)
 *  - แนวตั้ง (Portrait): ความสูงหน้ากระดาษต้องมากกว่าหรือเท่ากับความกว้าง
 * ยอมรับความคลาดเคลื่อน +/- 6 มม. จากอุปกรณ์สแกนเนอร์
 */
export async function validateClientPdfA4Portrait(
  input: File | ArrayBuffer,
  options?: ClientPdfValidationOptions
): Promise<ClientPdfValidationResult> {
  const allowLetter = options?.allowLetter !== false; // ค่าเริ่มต้น true
  let str: string;

  if (input instanceof File) {
    const arrayBuffer = await input.arrayBuffer();
    const bytes = new Uint8Array(arrayBuffer);
    let binary = '';
    const len = bytes.byteLength;
    const chunkSize = 32768;
    for (let i = 0; i < len; i += chunkSize) {
      const chunk = bytes.subarray(i, Math.min(i + chunkSize, len));
      binary += String.fromCharCode.apply(null, chunk as unknown as number[]);
    }
    str = binary;
  } else {
    const bytes = new Uint8Array(input);
    let binary = '';
    const len = bytes.byteLength;
    const chunkSize = 32768;
    for (let i = 0; i < len; i += chunkSize) {
      const chunk = bytes.subarray(i, Math.min(i + chunkSize, len));
      binary += String.fromCharCode.apply(null, chunk as unknown as number[]);
    }
    str = binary;
  }

  const pageObjects: { box: [number, number, number, number]; rotate: number }[] = [];
  const pagesInherited: { mediaBox: [number, number, number, number] | null; rotate: number } = {
    mediaBox: null,
    rotate: 0,
  };

  // ดึงค่าสืบทอดจาก /Pages หรือค่าเริ่มต้นในเอกสาร
  const pagesRootMatch = str.match(
    /\/Type\s*\/Pages\b[\s\S]*?\/MediaBox\s*\[\s*([0-9\.\-]+)\s+([0-9\.\-]+)\s+([0-9\.\-]+)\s+([0-9\.\-]+)\s*\]/
  );
  if (pagesRootMatch) {
    pagesInherited.mediaBox = [
      parseFloat(pagesRootMatch[1]),
      parseFloat(pagesRootMatch[2]),
      parseFloat(pagesRootMatch[3]),
      parseFloat(pagesRootMatch[4]),
    ];
  }

  const pagesRotateMatch = str.match(/\/Type\s*\/Pages\b[\s\S]*?\/Rotate\s+(\d+)/);
  if (pagesRotateMatch) {
    pagesInherited.rotate = parseInt(pagesRotateMatch[1], 10);
  }

  if (!pagesInherited.mediaBox) {
    const globalM = str.match(
      /\/MediaBox\s*\[\s*([0-9\.\-]+)\s+([0-9\.\-]+)\s+([0-9\.\-]+)\s+([0-9\.\-]+)\s*\]/
    );
    if (globalM) {
      pagesInherited.mediaBox = [
        parseFloat(globalM[1]),
        parseFloat(globalM[2]),
        parseFloat(globalM[3]),
        parseFloat(globalM[4]),
      ];
    }
  }

  // ค้นหา Object ที่เป็น /Page
  const objRegex = /(\d+\s+\d+\s+obj[\s\S]*?endobj)/g;
  let match: RegExpExecArray | null;

  while ((match = objRegex.exec(str)) !== null) {
    const objText = match[1];
    if (/\/Type\s*\/Page\b/.test(objText) && !/\/Type\s*\/Pages\b/.test(objText)) {
      let box: [number, number, number, number] | null = null;
      const cropM = objText.match(
        /\/CropBox\s*\[\s*([0-9\.\-]+)\s+([0-9\.\-]+)\s+([0-9\.\-]+)\s+([0-9\.\-]+)\s*\]/
      );
      const mediaM = objText.match(
        /\/MediaBox\s*\[\s*([0-9\.\-]+)\s+([0-9\.\-]+)\s+([0-9\.\-]+)\s+([0-9\.\-]+)\s*\]/
      );

      if (cropM) {
        box = [parseFloat(cropM[1]), parseFloat(cropM[2]), parseFloat(cropM[3]), parseFloat(cropM[4])];
      } else if (mediaM) {
        box = [parseFloat(mediaM[1]), parseFloat(mediaM[2]), parseFloat(mediaM[3]), parseFloat(mediaM[4])];
      } else if (pagesInherited.mediaBox) {
        box = pagesInherited.mediaBox;
      }

      let rotate = pagesInherited.rotate;
      const rotM = objText.match(/\/Rotate\s+(\d+)/);
      if (rotM) {
        rotate = parseInt(rotM[1], 10);
      }

      if (box) {
        pageObjects.push({ box, rotate });
      }
    }
  }

  if (pageObjects.length === 0 && pagesInherited.mediaBox) {
    pageObjects.push({ box: pagesInherited.mediaBox, rotate: pagesInherited.rotate });
  }

  if (pageObjects.length === 0) {
    return {
      valid: false,
      pageCount: 0,
      detectedFormat: 'Unknown',
      message: 'ไม่สามารถระบุขนาดหน้าของไฟล์ PDF ได้ กรุณาตรวจสอบว่าเป็นไฟล์ PDF ที่สมบูรณ์',
      pages: [],
    };
  }

  const pages: ClientPageDimensionInfo[] = pageObjects.map((p, idx) => {
    const rawW = Math.abs(p.box[2] - p.box[0]);
    const rawH = Math.abs(p.box[3] - p.box[1]);

    const normalizedRot = ((p.rotate % 360) + 360) % 360;
    const isSwapped = normalizedRot === 90 || normalizedRot === 270;
    const effW = isSwapped ? rawH : rawW;
    const effH = isSwapped ? rawW : rawH;

    const widthMm = Math.round(((effW * 25.4) / 72) * 10) / 10;
    const heightMm = Math.round(((effH * 25.4) / 72) * 10) / 10;

    const isPortrait = effH >= effW;
    const isA4Width = Math.abs(widthMm - 210.0) <= 6.0;
    const isA4Height = Math.abs(heightMm - 297.0) <= 6.0;
    const isA4 = isA4Width && isA4Height;

    const isLetterWidth = Math.abs(widthMm - 215.9) <= 6.0;
    const isLetterHeight = Math.abs(heightMm - 279.4) <= 6.0;
    const isLetter = isLetterWidth && isLetterHeight;

    const paperFormat: 'A4' | 'Letter' | 'Other' = isA4 ? 'A4' : isLetter ? 'Letter' : 'Other';
    const isValidPortrait = isPortrait && (isA4 || (allowLetter && isLetter));

    return {
      page: idx + 1,
      widthPt: Math.round(effW * 10) / 10,
      heightPt: Math.round(effH * 10) / 10,
      widthMm,
      heightMm,
      rotation: normalizedRot,
      orientation: isPortrait ? 'แนวตั้ง' : 'แนวนอน',
      isA4,
      isLetter,
      paperFormat,
      isValidPortrait,
    };
  });

  const invalidPages = pages.filter((p) => !p.isValidPortrait);
  const isValid = invalidPages.length === 0;

  const formats = Array.from(new Set(pages.map((p) => p.paperFormat)));
  let detectedFormat = formats.join(' / ');
  if (formats.length === 1 && formats[0] !== 'Other') {
    detectedFormat = formats[0];
  } else if (formats.every((f) => f === 'A4' || f === 'Letter')) {
    detectedFormat = 'A4 / Letter';
  }

  let message = `เอกสารขนาด ${detectedFormat} แนวตั้งถูกต้อง`;
  if (!isValid) {
    const firstInvalid = invalidPages[0];
    if (firstInvalid.orientation === 'แนวนอน') {
      message = `ไฟล์หลักหนังสือนำต้องเป็นแนวตั้ง (พบหน้า ${firstInvalid.page} เป็นแนวนอน ขนาด ${firstInvalid.widthMm} × ${firstInvalid.heightMm} มม.)`;
    } else {
      const allowedStr = allowLetter ? 'A4 (210 × 297 มม.) หรือ Letter (216 × 279 มม.)' : 'A4 (210 × 297 มม.)';
      message = `ไฟล์หลักหนังสือนำต้องเป็นขนาด ${allowedStr} แนวตั้ง (พบหน้า ${firstInvalid.page} มีขนาด ${firstInvalid.widthMm} × ${firstInvalid.heightMm} มม.)`;
    }
  }

  return {
    valid: isValid,
    pageCount: pages.length,
    detectedFormat,
    message,
    pages,
  };
}
