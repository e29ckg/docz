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
  if (val === null || val === undefined || val === '') return null
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

  // Preserve Z/offset from API timestamps before handling SQL wall-clock values.
  if (/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}.*(?:Z|[+-]\d{2}:?\d{2})$/i.test(str)) {
    const d = new Date(str.replace(' ', 'T'))
    return isNaN(d.getTime()) ? null : d
  }

  // SQL/datetime-local strings without an offset represent Bangkok time.
  if (/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?)?$/.test(str)) {
    const local = str.length === 10 ? `${str}T00:00:00` : str.replace(' ', 'T')
    const d = new Date(`${local}+07:00`)
    return isNaN(d.getTime()) ? null : d
  }

  const fallback = new Date(str)
  return isNaN(fallback.getTime()) ? null : fallback
}

const bangkokFormatter = new Intl.DateTimeFormat('en-GB', {
  timeZone: 'Asia/Bangkok',
  calendar: 'gregory',
  numberingSystem: 'latn',
  year: 'numeric', month: 'numeric', day: 'numeric',
  hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
})

function bangkokParts(val: string | number | Date | null | undefined) {
  const date = parseDate(val)
  if (!date) return null
  return Object.fromEntries(bangkokFormatter.formatToParts(date).map(part => [part.type, part.value]))
}

/**
 * แสดงวันที่แบบย่อภาษาไทย เช่น "30 ต.ค. 2569" หรือ "30 ต.ค. 69"
 */
export function formatThaiDate(
  val: string | number | Date | null | undefined,
  options?: { shortYear?: boolean }
): string {
  const parts = bangkokParts(val)
  if (!parts) return '—'

  const date = Number(parts.day)
  const month = TH_MONTHS_SHORT[Number(parts.month) - 1]
  const fullYear = Number(parts.year)
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
  const parts = bangkokParts(val)
  if (!parts) return '—'
  return `${formatThaiDate(val, options)} ${parts.hour}:${parts.minute} น.`
}

/**
 * แสดงเวลาแบบย่อภาษาไทย เช่น "16:55 น."
 */
export function formatThaiTime(val: string | number | Date | null | undefined): string {
  const parts = bangkokParts(val)
  if (!parts) return '—'
  return `${parts.hour}:${parts.minute} น.`
}
