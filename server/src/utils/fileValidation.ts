import path from 'path';

/**
 * มาตรฐานการตรวจสอบความปลอดภัยของไฟล์อัปโหลด (OWASP A08: Software and Data Integrity Failures)
 */

// นามสกุลไฟล์ที่อนุญาตสำหรับหนังสือนำ (Main Document)
export const ALLOWED_MAIN_DOC_EXTENSIONS = ['.pdf'];

// นามสกุลไฟล์ที่อนุญาตสำหรับเอกสารแนบ (Attachments)
export const ALLOWED_ATTACHMENT_EXTENSIONS = [
  '.pdf',
  '.doc',
  '.docx',
  '.xls',
  '.xlsx',
  '.ppt',
  '.pptx',
  '.jpg',
  '.jpeg',
  '.png',
  '.gif',
  '.webp',
  '.zip',
  '.rar',
  '.7z',
  '.txt',
  '.csv',
];

// นามสกุลไฟล์อันตรายที่ต้องบล็อกโดยเด็ดขาด (Executable & Scripting formats)
export const DANGEROUS_EXTENSIONS = [
  '.php',
  '.phtml',
  '.php3',
  '.php4',
  '.php5',
  '.php7',
  '.phps',
  '.phar',
  '.exe',
  '.dll',
  '.so',
  '.bat',
  '.cmd',
  '.sh',
  '.bash',
  '.jsp',
  '.asp',
  '.aspx',
  '.cgi',
  '.pl',
  '.py',
  '.vbs',
  '.js',
  '.html',
  '.htm',
  '.xhtml',
  '.svg', // ป้องกัน SVG Embedded Script XSS
];

/**
 * ตรวจสอบความปลอดภัยของนามสกุลไฟล์
 */
export function validateFileExtension(filename: string, kind: 'MAIN' | 'ATTACHMENT' = 'ATTACHMENT'): { valid: boolean; error?: string } {
  const ext = path.extname(filename).toLowerCase();

  if (!ext) {
    return { valid: false, error: 'ไฟล์ต้องมีนามสกุลระบุประเภทไฟล์' };
  }

  // ตรวจสอบนามสกุลอันตราย
  if (DANGEROUS_EXTENSIONS.includes(ext)) {
    return { valid: false, error: `ระบบไม่อนุญาตให้อัปโหลดไฟล์ประเภท ${ext} เนื่องจากมีความเสี่ยงด้านความปลอดภัย` };
  }

  // กรณีหนังสือนำ
  if (kind === 'MAIN') {
    if (!ALLOWED_MAIN_DOC_EXTENSIONS.includes(ext)) {
      return { valid: false, error: 'หนังสือนำต้องเป็นไฟล์ PDF (.pdf) เท่านั้น' };
    }
    return { valid: true };
  }

  // กรณีไฟล์แนบ
  if (!ALLOWED_ATTACHMENT_EXTENSIONS.includes(ext)) {
    return {
      valid: false,
      error: `ประเภทไฟล์ ${ext} ไม่อยู่ในรายการที่อนุญาต (รองรับเฉพาะ PDF, Word, Excel, PowerPoint, รูปภาพ, ZIP, RAR, TXT, CSV)`,
    };
  }

  return { valid: true };
}
