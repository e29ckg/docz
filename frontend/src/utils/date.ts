/**
 * ฟังก์ชันช่วยแปลงและจัดรูปแบบวันที่-เวลา เป็นภาษาไทยแบบย่อ
 * ตัวอย่าง:
 * - formatThaiDate: "30 ต.ค. 2569" (หรือ "30 ต.ค. 69" เมื่อ shortYear: true)
 * - formatThaiDateTime: "30 ต.ค. 2569 16:55 น." (หรือ "30 ต.ค. 69 16:55 น.")
 * - formatThaiTime: "16:55 น."
 */

const TH_MONTHS_SHORT = [
  'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
  'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.',
]

export function parseDate(val: string | number | Date | null | undefined): Date | null {
  if (!val) return null
  if (val instanceof Date) return isNaN(val.getTime()) ? null : val
  if (typeof val === 'number') {
    const d = new Date(val)
    return isNaN(d.getTime()) ? null : d
  }
  const str = String(val).trim()
  if (
    !str ||
    str === '—' ||
    str === '-' ||
    str.toLowerCase() === 'null' ||
    str.toLowerCase() === 'undefined' ||
    str.startsWith('Invalid')
  ) {
    return null
  }

  // รูปแบบ "YYYY-MM-DDTHH:mm:ss" หรือ "YYYY-MM-DD HH:mm:ss" หรือ "YYYY-MM-DD"
  if (/^\d{4}-\d{2}-\d{2}/.test(str)) {
    const delimiter = str.includes('T') ? 'T' : ' '
    const [datePart, timePart] = str.split(delimiter)
    const [y, m, d] = datePart.split('-').map(Number)
    if (y && m && d) {
      if (timePart) {
        const [hh, mm, ss] = timePart.split(':').map((s) => Number(s.replace(/[^0-9]/g, '')))
        return new Date(y, m - 1, d, hh || 0, mm || 0, ss || 0)
      }
      return new Date(y, m - 1, d)
    }
  }

  // รูปแบบ ISO มี Z หรือ offset
  if (str.includes('T')) {
    const d = new Date(str)
    if (!isNaN(d.getTime())) return d
  }

  const fallback = new Date(str)
  return isNaN(fallback.getTime()) ? null : fallback
}

/**
 * แสดงวันที่แบบย่อภาษาไทย เช่น "30 ต.ค. 2569" หรือ "30 ต.ค. 69"
 */
export function formatThaiDate(
  val: string | number | Date | null | undefined,
  options?: { shortYear?: boolean }
): string {
  const d = parseDate(val)
  if (!d) return '—'

  const date = d.getDate()
  const month = TH_MONTHS_SHORT[d.getMonth()]
  const fullYear = d.getFullYear()
  const beYear = fullYear > 2400 ? fullYear : fullYear + 543
  const yearStr = options?.shortYear ? String(beYear).slice(-2) : String(beYear)

  return `${date} ${month} ${yearStr}`
}

/**
 * แสดงวันที่และเวลาแบบย่อภาษาไทย เช่น "30 ต.ค. 2569 16:55 น." หรือ "30 ต.ค. 69 16:55 น."
 */
export function formatThaiDateTime(
  val: string | number | Date | null | undefined,
  options?: { shortYear?: boolean }
): string {
  const d = parseDate(val)
  if (!d) return '—'

  const date = d.getDate()
  const month = TH_MONTHS_SHORT[d.getMonth()]
  const fullYear = d.getFullYear()
  const beYear = fullYear > 2400 ? fullYear : fullYear + 543
  const yearStr = options?.shortYear ? String(beYear).slice(-2) : String(beYear)

  const hours = String(d.getHours()).padStart(2, '0')
  const minutes = String(d.getMinutes()).padStart(2, '0')

  return `${date} ${month} ${yearStr} ${hours}:${minutes} น.`
}

/**
 * แสดงเวลาแบบย่อภาษาไทย เช่น "16:55 น."
 */
export function formatThaiTime(val: string | number | Date | null | undefined): string {
  const d = parseDate(val)
  if (!d) return '—'
  const hours = String(d.getHours()).padStart(2, '0')
  const minutes = String(d.getMinutes()).padStart(2, '0')
  return `${hours}:${minutes} น.`
}
