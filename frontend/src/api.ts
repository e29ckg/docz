export interface NavigationItem {
  id: string
  label: string
  view?: string
  href?: string
  count?: number
  roleId?: number
}

export interface NavigationGroup {
  id: string
  label: string
  items: NavigationItem[]
}

export interface User {
  id: number
  username: string
  name: string
  registryOfficer: boolean
  isAdmin?: boolean
  isSigner?: boolean
  roles?: { id: number; name: string }[]
  navigation?: NavigationGroup[]
}

export interface AdminUser {
  id: number
  uid?: string
  username: string
  email: string
  status: number
  isActive: boolean
  pfname: string
  name: string
  sname: string
  id_card?: string
  fullName: string
  dep_name: string
  group_work: string
  phone: string
  line_id: string
  photo: string
  sign_photo?: string
  sort: number
  roles: { id: number; name: string }[]
}

export interface RoleNameItem {
  id: number
  name: string
  status: number
  sort: number
}

export interface RolePowerItem {
  id: number
  roleId: number
  roleName: string
  userId: number
  username: string
  fullName: string
  dep_name: string
  group_work: string
  name_dep1?: string | null
  name_dep2?: string | null
  name_dep3?: string | null
}

export interface DocProfileStep {
  id: number
  roleId: number
  roleName: string
  stepOrder: number
}

export interface DocProfileItem {
  id: number
  code: string
  name: string
  steps: DocProfileStep[]
}

export interface DocCatItem {
  id: number
  uid?: string
  name: string
  note?: string | null
  docCount: number
}

export interface DocCatDocumentItem {
  id: number
  uid?: string
  receiveNumber: string
  receivedAt: string
  letterNumber?: string | null
  letterDate?: string | null
  subject: string
  legacyStatus: number
  priority?: string | null
  mainFile?: string | null
}

export interface AdminMeta {
  pfnames: string[]
  departments: string[]
  groupWorks: string[]
  roleNames: { id: number; name: string; sort?: number }[]
}

export interface ReturnInfo {
  stepId?: number
  returnerName?: string | null
  returnerRole?: string | null
  reason?: string | null
  returnedAt?: string | null
}

export interface PendingSigningItem {
  docManageId: number
  docId: number
  docUid?: string
  stepOrder: number
  roleId: number
  roleName: string
  assignedAt: string
  receiveNumber: string
  receivedAt: string
  priority: string
  letterNumber: string
  letterDate: string | null
  docTo: string
  docForm: string
  subject: string
  hasMainFile: boolean
  isReturned?: boolean
  returnReason?: string | null
  returnerName?: string | null
  returnerRole?: string | null
  returnedAt?: string | null
}

export interface SignedHistoryItem {
  docManageId: number
  docId: number
  docUid?: string
  stepOrder: number
  roleId: number
  roleName: string
  signedAt: string
  stamp: string
  signDetail: string
  receiveNumber: string
  receivedAt: string
  priority: string
  letterNumber: string
  letterDate: string | null
  docTo: string
  docForm: string
  subject: string
  docStatus: number
  hasMainFile: boolean
}

export interface SigningStepItem {
  id: number
  docId: number
  roleId: number
  roleName: string
  sort: number
  status: number
  isReturned?: boolean
  stamp: string
  detail: string
  signerName: string
  signPhoto?: string | null
  signedAt: string | null
  createdAt: string
}

export interface SigningDetailData {
  document: {
    id: number
    uid?: string
    receiveNumber: string
    receivedAt: string
    priority: string
    letterNumber: string
    letterDate: string | null
    docTo: string
    docForm: string
    subject: string
    status: number
    mainFile: string | null
    creatorName: string
    createdAt: string
    attachments: { id: number; uid?: string; name: string; file: string; extension: string | null }[]
  }
  steps: SigningStepItem[]
  activeStepId: number | null
  currentUserSignPhoto?: string | null
  currentUserName?: string
  suggestedStamps: string[]
  availableProfiles?: DocProfileItem[]
  returnInfo?: ReturnInfo | null
}

export interface SigningWorkflowItem {
  id: number
  sort: number
  roleId: number
  roleName: string
  status: number // 1 = รอคิว, 2 = กำลังลงนาม, 3 = ลงนามแล้ว
  isReturned?: boolean
  returnReason?: string | null
  signerId: number | null
  signerName: string | null
  signerUsername: string | null
  signerDepName: string | null
  signerPhoto: string | null
  signerSignPhoto: string | null
  stamp: string | null
  detail: string | null
  position1: string | null
  position2: string | null
  position3: string | null
  signedAt: string | null
  createdAt: string | null
}

export interface DocumentReaderItem {
  id: number
  userId: number
  username: string
  readerName: string
  depName: string
  groupWork: string
  photo: string | null
  isRead: boolean
  readAt: string | null
  assignedAt: string | null
  ip?: string | null
}

export interface DestructionInfo {
  reason: string
  orderNumber: string
  purgeFiles: boolean
  fileHash?: string | null
  batchNumber?: string | null
  batchTitle?: string | null
  destroyedAt: string | null
  destroyedByName: string
}

export interface DestructionYearStat {
  year: number
  totalCount: number
  signedCount: number
  distributedCount: number
  oldestDate: string | null
  newestDate: string | null
}

export interface DestructionPreviewItem {
  id: number
  uid: string
  receiveNumber: string
  receivedAt: string | null
  letterNumber: string | null
  letterDate: string | null
  subject: string
  legacyStatus: number
  hasMainFile: boolean
  attachmentCount: number
}

export interface DestructionBatchItem {
  id: number
  uid: string
  batchNumber: string
  title: string
  targetYear: number | null
  orderNumber: string | null
  approvalNumber: string | null
  approvedDate: string | null
  totalDocs: number
  note: string | null
  createdAt: string | null
  creatorName: string
}

export interface DestroyedDocItem {
  id: number
  docId: number
  docUid: string
  receiveNumber: string
  letterNumber: string
  subject: string
  purgeFiles: boolean
  fileHash: string | null
  reason: string
  orderNumber: string
  batchId: number | null
  batchNumber: string
  batchTitle: string | null
  destroyedAt: string | null
  destroyedByName: string
}

export interface Document {
  id: number
  uid?: string
  receiveNumber: string
  receivedAt: string | null
  letterNumber: string | null
  letterDate: string | null
  to: string
  priority: string | null
  subject: string
  legacyStatus: number | null
  hasMainFile: boolean
  isRead?: boolean
  isPinned?: boolean
  readAt?: string | null
  isReturned?: boolean
  returnReason?: string | null
  returnerName?: string | null
  returnerRole?: string | null
  returnedAt?: string | null
  returnInfo?: ReturnInfo | null
  destructionInfo?: DestructionInfo | null
  destroyedAt?: string | null
  attachments?: { id: number; uid?: string; name: string; extension: string | null }[]
  signingSteps?: SigningWorkflowItem[]
  readers?: DocumentReaderItem[]
  readCount?: number
  totalAssignedCount?: number
}

export interface Session {
  user: User | null
  csrfToken: string
  token?: string
}

export interface Page {
  page: number
  pageSize: number
  total: number
  pages: number
}

let csrf = ''
let authToken = localStorage.getItem('docz_auth_token') || ''

// ตรวจสอบ Base URL สำหรับ API ให้สอดคล้องกับ Host และ Subpath อัตโนมัติ (เช่น /docz/api หรือ /api)
function getApiBase(): string {
  if (import.meta.env.VITE_API_URL) return import.meta.env.VITE_API_URL
  if (import.meta.env.DEV) return '/api'
  if (typeof window === 'undefined') return '/api'
  const pathname = window.location.pathname || ''
  if (pathname.startsWith('/docz')) {
    return '/docz/api'
  }
  return '/api'
}
const base = getApiBase()

export class ApiError extends Error {
  constructor(message: string, public status: number) {
    super(message)
  }
}

/**
 * สร้าง API URL ที่รองรับทั้ง RESTful (Node.js Express) และ Query format เดิม (PHP ?r=...)
 */
export function apiUrl(route: string, params: Record<string, string | number | undefined | null> = {}): string {
  // หากเป็นพาธรูปภาพหรือไฟล์ uploads ให้ส่งต่อไปยัง resolveMediaUrl ทันที ไม่ต้องผ่าน /api
  if (route.startsWith('uploads/') || route.startsWith('/uploads/') || route.startsWith('/uploads')) {
    return resolveMediaUrl(route)
  }

  const isNodeRest = !base.includes('.php')
  const cleanBase = base.replace(/\/+$/, '')

  if (isNodeRest) {
    // RESTful Mapping สำหรับ Node.js Express
    let path = ''
    const remainingParams: Record<string, any> = { ...params }

    if (route === 'session/index') path = '/session'
    else if (route === 'session/login') path = '/session/login'
    else if (route === 'session/logout') path = '/session/logout'
    else if (route === 'documents/index') path = '/documents'
    else if (route === 'documents/view') {
      const target = remainingParams.uid || remainingParams.id
      path = `/documents/${target}`
      delete remainingParams.uid
      delete remainingParams.id
    } else if (route === 'documents/download') {
      const target = remainingParams.uid || remainingParams.id
      path = `/documents/${target}/download`
      delete remainingParams.uid
      delete remainingParams.id
    } else if (route === 'documents/view-file') {
      const target = remainingParams.uid || remainingParams.id
      path = `/documents/${target}/view-file`
      delete remainingParams.uid
      delete remainingParams.id
    } else if (route.startsWith('admin/')) {
      path = `/${route}`
    } else if (route.startsWith('incoming/')) {
      path = `/${route}`
    } else {
      path = `/${route}`
    }

    const url = new URL(`${cleanBase}${path}`, window.location.href)
    Object.entries(remainingParams).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        url.searchParams.set(key, String(value))
      }
    })
    const currentToken = localStorage.getItem('docz_auth_token')
    if (currentToken && !url.searchParams.has('token')) {
      url.searchParams.set('token', currentToken)
    }
    return url.href
  }

  // รูปแบบเดิมของ PHP (?r=route)
  const url = new URL(base, window.location.href)
  url.searchParams.set('r', route)
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      url.searchParams.set(key, String(value))
    }
  })
  return url.href
}

export async function request<T>(route: string, params: Record<string, string | number | undefined | null> = {}, body?: unknown): Promise<T> {
  const headers: Record<string, string> = {
    Accept: 'application/json',
  }

  if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
  }
  if (csrf) {
    headers['X-CSRF-Token'] = csrf
  }
  if (authToken) {
    headers['Authorization'] = `Bearer ${authToken}`
  }

  const response = await fetch(apiUrl(route, params), {
    credentials: 'include', // ส่ง Cookie ข้ามพอร์ตได้
    method: body === undefined ? 'GET' : 'POST',
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  })

  const payload = await response.json().catch(() => null)
  if (!response.ok) {
    throw new ApiError(payload?.message || 'ไม่สามารถติดต่อระบบได้ กรุณาลองใหม่', response.status)
  }
  if (!payload) throw new ApiError('ระบบส่งข้อมูลกลับไม่ถูกต้อง', response.status)
  return payload as T
}

export async function uploadMedia<T = any>(route: string, formData: FormData): Promise<T> {
  const headers: Record<string, string> = {
    Accept: 'application/json',
  }
  if (csrf) {
    headers['X-CSRF-Token'] = csrf
  }
  if (authToken) {
    headers['Authorization'] = `Bearer ${authToken}`
  }

  const response = await fetch(apiUrl(route), {
    credentials: 'include',
    method: 'POST',
    headers,
    body: formData,
  })

  const payload = await response.json().catch(() => null)
  if (!response.ok) {
    throw new ApiError(payload?.message || 'ไม่สามารถอัปโหลดไฟล์ได้', response.status)
  }
  if (!payload) throw new ApiError('ระบบส่งข้อมูลกลับไม่ถูกต้อง', response.status)
  return payload as T
}

export function resolveMediaUrl(mediaPath?: string | null): string {
  if (!mediaPath) return ''
  if (mediaPath.startsWith('http://') || mediaPath.startsWith('https://') || mediaPath.startsWith('data:')) {
    return mediaPath
  }
  const cleanPath = mediaPath.replace(/^\/+/, '')
  const envBase = (import.meta.env.VITE_DOCZ_API_BASE as string) || (window as any).DOCZ_API_BASE || ''
  if (envBase && envBase.startsWith('http') && !envBase.includes('/docz/')) {
    const origin = new URL(envBase).origin
    return `${origin}/${cleanPath}`
  }
  if (typeof window !== 'undefined') {
    const pathname = window.location.pathname || ''
    if (pathname.startsWith('/docz')) {
      return `/docz/${cleanPath}`
    }
  }
  return `/${cleanPath}`
}

export async function sessionRequest(route = 'session/index', body?: unknown): Promise<Session> {
  const result = await request<{ data: Session }>(route, {}, body)
  csrf = result.data.csrfToken || ''
  if (result.data.token) {
    authToken = result.data.token
    localStorage.setItem('docz_auth_token', authToken)
  }
  if (route === 'session/logout') {
    authToken = ''
    localStorage.removeItem('docz_auth_token')
  }
  return result.data
}

// ==========================================
// API บริการจัดการทำลายเอกสาร (Document Destruction API)
// ==========================================

export async function fetchDestructionYears(): Promise<DestructionYearStat[]> {
  const res = await request<{ data: DestructionYearStat[] }>('destruction/years')
  return res.data
}

export async function fetchDestructionPreview(
  year: number,
  q?: string,
  page = 1,
  pageSize = 50
): Promise<{ data: DestructionPreviewItem[]; meta: Page }> {
  return request<{ data: DestructionPreviewItem[]; meta: Page }>('destruction/preview', {
    year: String(year),
    q: q || '',
    page: String(page),
    pageSize: String(pageSize),
  })
}

export async function submitBatchDestruction(payload: {
  targetYear?: number
  title: string
  orderNumber?: string
  approvalNumber?: string
  approvedDate?: string
  note?: string
  purgeFiles?: boolean
  docIds: number[]
}): Promise<any> {
  return request<any>('destruction/batch', {}, payload)
}

export async function submitSingleDestruction(payload: {
  docIds: (number | string)[]
  reason: string
  orderNumber?: string
  purgeFiles?: boolean
}): Promise<any> {
  return request<any>('destruction/single', {}, payload)
}

export async function fetchDestructionHistory(): Promise<DestructionBatchItem[]> {
  const res = await request<{ data: DestructionBatchItem[] }>('destruction/history')
  return res.data
}

export async function fetchDestructionBatchDetail(idOrUid: number | string): Promise<{ batch: DestructionBatchItem; items: any[] }> {
  const res = await request<{ data: { batch: DestructionBatchItem; items: any[] } }>(`destruction/history/${idOrUid}`)
  return res.data
}

export async function fetchDestroyedDocuments(params?: {
  q?: string
  year?: number
  page?: number
  pageSize?: number
}): Promise<{ data: DestroyedDocItem[]; meta: Page }> {
  const queryParams: Record<string, string> = {}
  if (params?.q) queryParams.q = params.q
  if (params?.year) queryParams.year = String(params.year)
  if (params?.page) queryParams.page = String(params.page)
  if (params?.pageSize) queryParams.pageSize = String(params.pageSize)
  return request<{ data: DestroyedDocItem[]; meta: Page }>('destruction/documents', queryParams)
}
