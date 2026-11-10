import Swal, { type SweetAlertOptions } from 'sweetalert2'
import 'sweetalert2/dist/sweetalert2.min.css'

/**
 * ตั้งค่าเริ่มต้นของ SweetAlert2 ให้เข้ากับธีมระบบ DOCZ
 */
const defaultSwalOptions: SweetAlertOptions = {
  customClass: {
    popup: 'docz-swal-popup',
    title: 'docz-swal-title',
    htmlContainer: 'docz-swal-text',
    confirmButton: 'docz-swal-btn-confirm',
    cancelButton: 'docz-swal-btn-cancel',
  },
  buttonsStyling: true,
  confirmButtonColor: '#0284c7',
  cancelButtonColor: '#64748b',
}

/**
 * แจ้งเตือนข้อความทั่วไป (Alert)
 */
export async function showAlert(
  title: string,
  text?: string,
  icon: 'info' | 'success' | 'warning' | 'error' = 'info'
): Promise<void> {
  await Swal.fire({
    ...defaultSwalOptions,
    title,
    text,
    icon,
    confirmButtonText: 'ตกลง',
  })
}

/**
 * แจ้งเตือนสำเร็จ (Success)
 */
export async function showSuccess(title: string, text?: string): Promise<void> {
  await Swal.fire({
    ...defaultSwalOptions,
    title,
    text,
    icon: 'success',
    confirmButtonText: 'ตกลง',
    confirmButtonColor: '#16a34a',
  })
}

/**
 * แจ้งเตือนข้อผิดพลาด (Error)
 */
export async function showError(title: string, text?: string): Promise<void> {
  await Swal.fire({
    ...defaultSwalOptions,
    title,
    text,
    icon: 'error',
    confirmButtonText: 'ตกลง',
    confirmButtonColor: '#dc2626',
  })
}

/**
 * แจ้งเตือนข้อควรระวัง (Warning)
 */
export async function showWarning(title: string, text?: string): Promise<void> {
  await Swal.fire({
    ...defaultSwalOptions,
    title,
    text,
    icon: 'warning',
    confirmButtonText: 'ตกลง',
    confirmButtonColor: '#f59e0b',
  })
}

/**
 * กล่องยืนยันการทำรายการ (Confirm Dialog)
 * คืนค่า true หากผู้ใช้กดยืนยัน, false หากกดยกเลิก
 */
export async function showConfirm(
  title: string,
  text?: string,
  options?: {
    confirmText?: string
    cancelText?: string
    isDanger?: boolean
    icon?: 'warning' | 'question' | 'info'
  }
): Promise<boolean> {
  const result = await Swal.fire({
    ...defaultSwalOptions,
    title,
    text,
    icon: options?.icon || (options?.isDanger ? 'warning' : 'question'),
    showCancelButton: true,
    confirmButtonText: options?.confirmText || 'ยืนยัน',
    cancelButtonText: options?.cancelText || 'ยกเลิก',
    confirmButtonColor: options?.isDanger ? '#dc2626' : '#0284c7',
    cancelButtonColor: '#94a3b8',
    reverseButtons: true,
  })

  return result.isConfirmed
}

/**
 * แสดง Toast แจ้งเตือนมุมขวาบน
 */
export function showToast(
  title: string,
  icon: 'success' | 'error' | 'warning' | 'info' = 'success'
): void {
  const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2500,
    timerProgressBar: true,
    didOpen: (toast) => {
      toast.addEventListener('mouseenter', Swal.stopTimer)
      toast.addEventListener('mouseleave', Swal.resumeTimer)
    },
  })

  Toast.fire({
    icon,
    title,
  })
}

export default Swal
