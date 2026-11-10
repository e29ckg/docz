<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import { ApiError, apiUrl, resolveMediaUrl, request, type Document, type Page, type User } from '../api'
import AdminUsers from './AdminUsers.vue'
import AdminRoles from './AdminRoles.vue'
import AdminDocProfiles from './AdminDocProfiles.vue'
import AdminDocCategories from './AdminDocCategories.vue'
import AdminDocDestruction from './AdminDocDestruction.vue'
import IncomingModal from './IncomingModal.vue'
import SigningWorkspace from './SigningWorkspace.vue'
import EditDocumentModal from './EditDocumentModal.vue'
import DistributeModal from './DistributeModal.vue'
import SendPreviewModal from './SendPreviewModal.vue'
import { formatThaiDate, formatThaiDateTime } from '../utils/date'
import { showSuccess, showError, showConfirm } from '../utils/alert'

const props = defineProps<{ user: User }>()
const emit = defineEmits<{ logout: []; expired: [] }>()
const documents = ref<Document[]>([])
const pinBusy = ref<number | null>(null)
const pinError = ref('')

async function togglePin(document: Document) {
  if (pinBusy.value !== null) return
  pinBusy.value = document.id
  pinError.value = ''
  try {
    const result = await request<{ data: { isPinned: boolean } }>(`documents/${document.uid || document.id}/pin`, {}, { pinned: !document.isPinned })
    document.isPinned = result.data.isPinned
    if (scope.value === 'mine') await load(1)
  } catch (e) {
    if (e instanceof ApiError && e.status === 401) emit('expired')
    else pinError.value = e instanceof Error ? e.message : 'ไม่สามารถบันทึกการปักหมุดได้'
  } finally {
    pinBusy.value = null
  }
}
const selected = ref<Document | null>(null)
const page = ref<Page>({ page: 1, pageSize: 20, pages: 0, total: 0 })
const isOfficerOrAdmin = computed(() => Boolean(props.user.registryOfficer || props.user.isAdmin))
const isSignerUser = computed(() => Boolean(props.user.isSigner || pendingSignCount.value > 0))

const userRoleDescription = computed(() => {
  if (isOfficerOrAdmin.value) return 'ผู้ดูแลระบบ / สารบรรณ'
  if (props.user.roles && props.user.roles.length > 0) {
    return props.user.roles.map((r) => r.name).join(', ')
  }
  if (props.user.isSigner) return 'ผู้ลงนาม'
  return 'ผู้ใช้งานทั่วไป'
})

const scope = ref<'mine' | 'visible' | 'signing-pending' | 'signing-history' | 'admin-users' | 'admin-permissions' | 'admin-doc-profiles' | 'admin-doc-cats' | 'admin-destruction' | 'admin-destroyed-docs'>('mine')
const q = ref('')
const from = ref('')
const to = ref('')
const selectedStatus = ref('')
const selectedPriority = ref('')
const selectedDocTo = ref('')
const editDocId = ref<number | null>(null)
const distributeDocId = ref<number | null>(null)
const previewSendDocId = ref<number | null>(null)
const sendingDocId = ref<number | null>(null)
const selectedDetailAttachmentId = ref<number | null>(null)
const detailActiveTab = ref<'info' | 'workflow' | 'readers'>('info')
const readerFilter = ref<'all' | 'read' | 'unread'>('all')
const readerSearch = ref('')

const filteredReaders = computed(() => {
  if (!selected.value?.readers) return []
  let list = selected.value.readers
  if (readerFilter.value === 'read') {
    list = list.filter((r) => r.isRead)
  } else if (readerFilter.value === 'unread') {
    list = list.filter((r) => !r.isRead)
  }
  const query = readerSearch.value.trim().toLowerCase()
  if (query) {
    list = list.filter(
      (r) =>
        r.readerName.toLowerCase().includes(query) ||
        r.depName.toLowerCase().includes(query) ||
        r.groupWork.toLowerCase().includes(query)
    )
  }
  return list
})
const busy = ref(false)
const detailBusy = ref(false)
const error = ref('')
const showIncomingModal = ref(false)
const isDailyIncoming = ref(false)
const incomingSuccessMsg = ref('')

function openNormalIncoming() {
  isDailyIncoming.value = false
  showIncomingModal.value = true
}

function openDailyIncoming() {
  isDailyIncoming.value = true
  showIncomingModal.value = true
}
const pendingSignCount = ref(0)
const returnedSignCount = ref(0)
const unreadCount = ref(0)
const panel = ref<HTMLElement | null>(null)
let previousFocus: HTMLElement | null = null
let listRequest = 0
let detailRequest = 0

async function loadSigningCount() {
  try {
    const res = await request<{ data: { pendingCount: number; returnedCount?: number } }>('signing/count')
    pendingSignCount.value = res.data.pendingCount || 0
    returnedSignCount.value = res.data.returnedCount || 0
  } catch {
    // เงียบไว้หากโหลดไม่ได้
  }
}

async function loadUnreadCount() {
  try {
    const res = await request<{ data: { unreadCount: number } }>('documents/unread-count')
    unreadCount.value = res.data.unreadCount || 0
  } catch {
    // เงียบไว้หากโหลดไม่ได้
  }
}

function onDocumentCreated(doc: { id: number; receiveNumber: string; subject: string }) {
  showIncomingModal.value = false
  incomingSuccessMsg.value = `ลงรับหนังสือเรียบร้อยแล้ว: เลขรับ ${doc.receiveNumber} — ${doc.subject}`
  load()
  loadSigningCount()
  setTimeout(() => {
    incomingSuccessMsg.value = ''
  }, 8000)
}

const scopeLabel = computed(() => {
  switch (scope.value) {
    case 'mine':
      return 'หนังสือของฉัน'
    case 'visible':
      return isOfficerOrAdmin.value ? 'ทะเบียนหนังสือ' : 'ค้นหาหนังสือ'
    case 'signing-pending':
      return 'หนังสือรอลงนาม'
    case 'signing-history':
      return 'ประวัติการลงนาม'
    case 'admin-users':
      return 'จัดการสมาชิก'
    case 'admin-permissions':
      return 'กำหนดสิทธิ์'
    case 'admin-doc-profiles':
      return 'โปรไฟล์การลงนาม'
    case 'admin-doc-cats':
      return 'จัดการที่เก็บเอกสาร'
    case 'admin-destruction':
      return 'จัดการทำลายเอกสาร'
    case 'admin-destroyed-docs':
      return 'ทะเบียนหนังสือที่ถูกทำลาย'
    default:
      return 'พื้นที่ทำงาน'
  }
})

const topbarCategory = computed(() => {
  if (scope.value.startsWith('admin-')) return 'ผู้ดูแลระบบ'
  if (scope.value.startsWith('signing-')) return 'การลงนาม'
  return 'ระบบหนังสือ'
})

const statusLabels: Record<number, string> = { 1: 'รอเสนอ', 2: 'อยู่ระหว่างดำเนินการ', 3: 'เกษียนครบ', 4: 'แจกจ่ายแล้ว', 5: 'ถูกตีกลับ', 6: 'ทำลายแล้ว' }
function status(code: number | null) { return code === null ? 'ไม่ระบุสถานะ' : statusLabels[code] || `สถานะเดิม ${code}` }
const date = formatThaiDate
const datetime = formatThaiDateTime
function fail(e: unknown) {
  if (e instanceof ApiError && e.status === 401) emit('expired')
  else error.value = e instanceof Error ? e.message : 'เกิดข้อผิดพลาด'
}
async function load(targetPage = 1) {
  if (scope.value.startsWith('admin-') || scope.value.startsWith('signing-')) return
  const ticket = ++listRequest
  busy.value = true
  error.value = ''
  try {
    const params: Record<string, string | number> = {
      scope: scope.value,
      q: q.value,
      from: from.value,
      to: to.value,
      page: targetPage,
      pageSize: 20,
    }
    if (selectedStatus.value) params.status = selectedStatus.value
    if (selectedPriority.value) params.priority = selectedPriority.value
    if (selectedDocTo.value) params.docTo = selectedDocTo.value

    const result = await request<{ data: Document[]; meta: Page }>('documents/index', params)
    if (ticket !== listRequest) return
    documents.value = result.data
    page.value = result.meta
  } catch (e) { if (ticket === listRequest) fail(e) }
  finally { if (ticket === listRequest) busy.value = false }
}

function resetFilters() {
  q.value = ''
  from.value = ''
  to.value = ''
  selectedStatus.value = ''
  selectedPriority.value = ''
  selectedDocTo.value = ''
  load(1)
}

function openSendPreview(doc: Document) {
  previewSendDocId.value = doc.id
}

function onDocSent(_doc: { id: number; receiveNumber: string; subject: string }) {
  previewSendDocId.value = null
  load(page.value.page)
  loadSigningCount()
}

function openEditDoc(id: number) {
  editDocId.value = id
}

function onDocUpdated(_updated: { id: number; receiveNumber: string; subject: string }) {
  editDocId.value = null
  load(page.value.page)
}

function openDistributeDoc(id: number) {
  distributeDocId.value = id
}

function onDocDistributed(_res: { docId: number; legacyStatus: number; recipientCount: number }) {
  distributeDocId.value = null
  load(page.value.page)
}
async function changeScope(next: 'mine' | 'visible' | 'signing-pending' | 'signing-history' | 'admin-users' | 'admin-permissions' | 'admin-doc-profiles' | 'admin-doc-cats' | 'admin-destruction' | 'admin-destroyed-docs') {
  if (next.startsWith('admin-') && !isOfficerOrAdmin.value) {
    scope.value = 'mine'
    await load()
    return
  }
  if (next.startsWith('signing-') && !isSignerUser.value) {
    scope.value = 'mine'
    await load()
    return
  }
  scope.value = next
  selected.value = null
  selectedStatus.value = ''
  if (next === 'mine' || next === 'visible') {
    await load()
    if (next === 'mine') {
      loadUnreadCount()
    }
  } else if (next.startsWith('signing-')) {
    loadSigningCount()
  }
}
async function open(id: number, uid?: string) {
  previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null
  const ticket = ++detailRequest
  detailBusy.value = true
  selected.value = null
  selectedDetailAttachmentId.value = null
  detailActiveTab.value = 'info'
  readerFilter.value = 'all'
  readerSearch.value = ''
  error.value = ''
  await nextTick()
  panel.value?.focus()
  try {
    const result = await request<{ data: Document }>('documents/view', { id, uid })
    if (ticket === detailRequest) {
      selected.value = result.data
      const found = documents.value.find((d) => d.id === id || (uid && d.uid === uid))
      if (found && !found.isRead) {
        found.isRead = true
      }
      loadUnreadCount()
    }
  } catch (e) { if (ticket === detailRequest) fail(e) }
  finally { if (ticket === detailRequest) detailBusy.value = false }
}
function close() {
  ++detailRequest
  selected.value = null
  selectedDetailAttachmentId.value = null
  detailBusy.value = false
  loadUnreadCount()
  previousFocus?.focus()
}
function dialogKey(event: KeyboardEvent) {
  if (event.key === 'Escape') { close(); return }
  if (event.key !== 'Tab' || !panel.value) return
  const focusable = [...panel.value.querySelectorAll<HTMLElement>('button, a[href], [tabindex="0"]')]
  const first = focusable[0]
  const last = focusable.at(-1)
  if (event.shiftKey && (document.activeElement === first || document.activeElement === panel.value)) {
    event.preventDefault(); last?.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault(); first?.focus()
  }
}

onMounted(() => {
  load()
  loadSigningCount()
  loadUnreadCount()
})
</script>

<template>
  <div class="workspace">
    <aside class="sidebar">
      <a class="brand" href="./"><span class="brand-icon">D</span>DOCZ<span class="brand-period">.</span></a>
      
      <span class="nav-heading">ระบบเอกสาร</span>
      <nav aria-label="เมนูเอกสาร">
        <button :class="{ active: scope === 'mine' }" @click="changeScope('mine')">
          <span aria-hidden="true">▤</span>หนังสือของฉัน
          <span v-if="unreadCount > 0" class="sidebar-badge-count sidebar-badge-unread">{{ unreadCount }}</span>
        </button>
        <button :class="{ active: scope === 'visible' }" @click="changeScope('visible')">
          <span aria-hidden="true">⌕</span>{{ isOfficerOrAdmin ? 'ทะเบียนหนังสือ' : 'ค้นหาหนังสือ' }}
        </button>
      </nav>

      <!-- เมนูการลงนามในเอกสาร (Signing Menu) - แสดงเฉพาะผู้มีสิทธิ์ลงนาม หรือ สารบรรณ/แอดมิน หรือมีหนังสือรอลงนาม -->
      <template v-if="isSignerUser">
        <span class="nav-heading" style="margin-top: 1.25rem">การลงนาม</span>
        <nav aria-label="เมนูการลงนาม">
          <button :class="{ active: scope === 'signing-pending' }" @click="changeScope('signing-pending')">
            <span aria-hidden="true">✍️</span>รอลงนาม
            <span v-if="pendingSignCount > 0" class="sidebar-badge-count">{{ pendingSignCount }}</span>
            <span v-if="returnedSignCount > 0" class="sidebar-badge-count sidebar-badge-returned" title="มีเอกสารถูกตีกลับ">{{ returnedSignCount }} ตีกลับ</span>
          </button>
          <button :class="{ active: scope === 'signing-history' }" @click="changeScope('signing-history')">
            <span aria-hidden="true">📋</span>ประวัติการลงนาม
          </button>
        </nav>
      </template>

      <!-- เมนูผู้ดูแลระบบ (Admin) - เจ้าหน้าที่สารบรรณ จะได้สิทธิ์แอดมินด้วย -->
      <template v-if="isOfficerOrAdmin">
        <span class="nav-heading" style="margin-top: 1.25rem">ผู้ดูแลระบบ</span>
        <nav aria-label="เมนูผู้ดูแลระบบ">
          <button :class="{ active: scope === 'admin-users' }" @click="changeScope('admin-users')">
            <span aria-hidden="true">👤</span>จัดการสมาชิก
          </button>
          <button :class="{ active: scope === 'admin-permissions' }" @click="changeScope('admin-permissions')">
            <span aria-hidden="true">🛡️</span>กำหนดสิทธิ์
          </button>
          <button :class="{ active: scope === 'admin-doc-profiles' }" @click="changeScope('admin-doc-profiles')">
            <span aria-hidden="true">📝</span>โปรไฟล์การลงนาม
          </button>
          <button :class="{ active: scope === 'admin-doc-cats' }" @click="changeScope('admin-doc-cats')">
            <span aria-hidden="true">📁</span>จัดการที่เก็บเอกสาร
          </button>
          <button :class="{ active: scope === 'admin-destruction' }" @click="changeScope('admin-destruction')">
            <span aria-hidden="true">🗑️</span>จัดการทำลายเอกสาร
          </button>
          <button :class="{ active: scope === 'admin-destroyed-docs' }" @click="changeScope('admin-destroyed-docs')">
            <span aria-hidden="true">📋</span>ทะเบียนหนังสือที่ทำลาย
          </button>
        </nav>
      </template>

      <div class="sidebar-bottom">
        <div class="avatar">{{ user.name.slice(0, 1) }}</div>
        <div>
          <strong>{{ user.name }}</strong>
          <small>{{ userRoleDescription }}</small>
        </div>
      </div>
    </aside>

    <main class="main-content">
      <header class="topbar">
        <span>{{ topbarCategory }} <span class="separator">/</span> {{ scopeLabel }}</span>
        <button class="text-button" @click="emit('logout')">ออกจากระบบ ↗</button>
      </header>

      <section class="content">
        <!-- กรณีเป็นหน้าจัดการสมาชิก -->
        <AdminUsers v-if="scope === 'admin-users'" />

        <!-- กรณีเป็นหน้ากำหนดสิทธิ์ -->
        <AdminRoles v-else-if="scope === 'admin-permissions'" />

        <!-- กรณีเป็นหน้าโปรไฟล์การลงนาม -->
        <AdminDocProfiles v-else-if="scope === 'admin-doc-profiles'" />

        <!-- กรณีเป็นหน้าจัดการที่เก็บเอกสาร -->
        <AdminDocCategories v-else-if="scope === 'admin-doc-cats'" />

        <!-- กรณีเป็นหน้าจัดการทำลายเอกสาร / ทะเบียนหนังสือที่ทำลาย -->
        <AdminDocDestruction
          v-else-if="scope === 'admin-destruction' || scope === 'admin-destroyed-docs'"
          :initial-tab="scope === 'admin-destroyed-docs' ? 'register' : 'annual'"
          @view-document="open($event)"
        />

        <!-- กรณีเป็นหน้าการลงนามในเอกสาร (รอลงนาม / ประวัติการลงนาม) -->
        <SigningWorkspace
          v-else-if="scope === 'signing-pending' || scope === 'signing-history'"
          :initial-tab="scope === 'signing-history' ? 'history' : 'pending'"
          @count-changed="loadSigningCount"
        />

        <!-- กรณีเป็นหน้าหนังสือของฉัน / ทะเบียนค้นหาหนังสือ -->
        <template v-else>
          <div class="page-heading">
            <div>
              <span class="eyebrow">DOCUMENT WORKSPACE</span>
              <h1>{{ scopeLabel }}</h1>
              <p class="muted">{{ scope === 'mine' ? 'หนังสือที่ได้รับแจกจ่ายถึงคุณ' : 'ค้นหาและเปิดหนังสือภายในขอบเขตสิทธิ์ของคุณ' }}</p>
            </div>
            <div class="heading-actions">
              <template v-if="scope !== 'mine' && isOfficerOrAdmin">
                <button
                  class="btn-create-doc"
                  type="button"
                  @click="openNormalIncoming"
                >
                  <span>➕</span> เพิ่มเอกสารเข้าระบบ
                </button>
                <button
                  class="btn-create-daily-doc"
                  type="button"
                  title="เพิ่มเอกสารวันทำการ (ออกเลขตามวันที่ YYYYMMDD-XXX และดึงเรื่องจากชื่อไฟล์หลัก)"
                  @click="openDailyIncoming"
                >
                  <span>📅</span> เพิ่มเอกสารวันทำการ
                </button>
              </template>
              <div class="count-card">
                <strong>{{ page.total.toLocaleString('th-TH') }}</strong>
                <span>รายการที่ค้นพบ</span>
              </div>
            </div>
          </div>

          <!-- แจ้งเตือนเมื่อลงรับหนังสือเรียบร้อย -->
          <div v-if="incomingSuccessMsg" class="alert-success-banner" role="status">
            <span class="banner-icon">✅</span>
            <div class="banner-body">
              <strong>บันทึกนำเข้าหนังสือสำเร็จ!</strong>
              <p>{{ incomingSuccessMsg }}</p>
            </div>
            <button type="button" class="banner-close" @click="incomingSuccessMsg = ''">✕</button>
          </div>

          <section class="document-card" aria-label="รายการหนังสือ">
            <form class="filters filters-extended" @submit.prevent="load(1)">
              <div class="search-field">
                <label class="sr-only" for="search">ค้นหาหนังสือ</label>
                <span aria-hidden="true">⌕</span>
                <input id="search" v-model="q" placeholder="ค้นหาเรื่อง เลขรับ หรือเลขที่หนังสือ…" maxlength="200" />
              </div>

              <div class="filter-field">
                <label for="filter-status">สถานะ</label>
                <!-- หน้าหนังสือของฉัน: กรองสถานะ ทั้งหมด / อ่านแล้ว / ยังไม่ได้อ่าน -->
                <select v-if="scope === 'mine'" id="filter-status" v-model="selectedStatus" @change="load(1)">
                  <option value="">ทั้งหมด</option>
                  <option value="read">อ่านแล้ว</option>
                  <option value="unread">ยังไม่ได้อ่าน</option>
                </select>

                <!-- หน้าทะเบียนหนังสือ / ค้นหาหนังสือ: กรองสถานะขั้นตอนของหนังสือ -->
                <select v-else id="filter-status" v-model="selectedStatus" @change="load(1)">
                  <option value="">ทั้งหมด</option>
                  <option value="1">1: ⏳ รอเสนอ</option>
                  <option value="2">2: 🔄 อยู่ระหว่างดำเนินการ</option>
                  <option value="3">3: ✓ เกษียนครบ</option>
                  <option value="4">4: 📨 แจกจ่ายแล้ว</option>
                  <option value="5">5: ⛔ ถูกตีกลับ</option>
                  <option value="6">6: 🗑️ ทำลายแล้ว</option>
                </select>
              </div>

              <div class="filter-field">
                <label for="filter-priority">ชั้นความเร็ว</label>
                <select id="filter-priority" v-model="selectedPriority" @change="load(1)">
                  <option value="">ทั้งหมด</option>
                  <option value="ปกติ">ปกติ</option>
                  <option value="ด่วน">ด่วน</option>
                  <option value="ด่วนที่สุด">ด่วนที่สุด</option>
                </select>
              </div>

              <div class="filter-field">
                <label for="filter-docto">เรียน</label>
                <input id="filter-docto" v-model="selectedDocTo" placeholder="เช่น ผู้อำนวยการฯ" list="docToList" />
                <datalist id="docToList">
                  <option value="ผู้อำนวยการสำนักอำนวยการประจำศาลจังหวัดประจวบคีรีขันธ์" />
                  <option value="ผู้พิพากษาหัวหน้าศาลจังหวัดประจวบคีรีขันธ์" />
                </datalist>
              </div>

              <div class="date-filter"><label for="from">ตั้งแต่</label><input id="from" v-model="from" type="date" /></div>
              <div class="date-filter"><label for="to">ถึง</label><input id="to" v-model="to" type="date" /></div>

              <div class="filter-actions">
                <button class="primary" type="submit" :disabled="busy">ค้นหา</button>
                <button class="secondary btn-reset-filter" type="button" @click="resetFilters" :disabled="busy">ล้าง</button>
              </div>
            </form>

            <p v-if="error" class="error inset" role="alert">{{ error }}</p>
            <p v-if="scope === 'mine' && pinError" class="error inset" role="alert">{{ pinError }}</p>
            <div v-if="busy" class="empty-state" role="status">กำลังค้นหาหนังสือ…</div>
            <div v-else-if="!documents.length" class="empty-state">
              <span class="empty-icon" aria-hidden="true">▤</span>
              <h2>ยังไม่พบหนังสือ</h2>
              <p>ลองปรับคำค้นหาหรือช่วงวันที่</p>
            </div>
            <div v-else class="table-scroll">
              <table>
                <thead>
                  <tr>
                    <th>เลขรับ / วันที่รับ</th>
                    <th>เรื่อง / เลขที่หนังสือ</th>
                    <th>สถานะ</th>
                    <th style="text-align: right; min-width: 170px;">การจัดการ</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="document in documents" :key="document.id" :class="{ 'pinned-document': scope === 'mine' && document.isPinned }">
                    <td class="receive-cell"><strong>{{ document.receiveNumber }}</strong><small>{{ date(document.receivedAt) }}</small></td>
                    <td class="subject-cell">
                      <button
                        v-if="scope === 'mine'"
                        type="button"
                        class="pin-document-button"
                        :class="{ 'is-pinned': document.isPinned }"
                        :aria-pressed="Boolean(document.isPinned)"
                        :aria-label="`${document.isPinned ? 'ถอนหมุด' : 'ปักหมุด'} ${document.subject}`"
                        :title="document.isPinned ? 'ถอนหมุดหนังสือส่วนตัว' : 'ปักหมุดไว้ด้านบนสำหรับฉัน'"
                        :disabled="pinBusy !== null"
                        @click="togglePin(document)"
                      ><span v-if="pinBusy === document.id">กำลังบันทึก…</span><template v-else><span class="pin-star" aria-hidden="true">{{ document.isPinned ? '★' : '☆' }}</span> {{ document.isPinned ? 'ปักหมุดแล้ว' : 'ปักหมุด' }}</template></button>
                      <button class="document-title" @click="open(document.id, document.uid)">{{ document.subject }}</button>
                      <small>{{ document.letterNumber || 'ไม่ระบุเลขที่หนังสือ' }}<span v-if="document.priority" class="priority">{{ document.priority }}</span></small>
                    </td>
                    <td>
                      <!-- ในหน้าหนังสือของฉัน: แสดงสถานะ อ่านแล้ว / ยังไม่ได้อ่าน -->
                      <template v-if="scope === 'mine'">
                        <span class="mine-status-tag" :class="document.isRead ? 'status-read' : 'status-unread'">
                          {{ document.isRead ? '✅ อ่านแล้ว' : '🔵 ยังไม่ได้อ่าน' }}
                        </span>
                      </template>
                      <!-- ในหน้าทะเบียนหนังสือ / ค้นหาหนังสือ: แสดงสถานะเอกสาร -->
                      <template v-else>
                        <span class="status" :class="`status-${document.legacyStatus}`">{{ status(document.legacyStatus) }}</span>
                        <div v-if="document.legacyStatus === 5 && document.returnReason" class="returned-doc-snippet" :title="`เหตุผลตีกลับ: ${document.returnReason}`">
                          <small>💬 {{ document.returnReason }}</small>
                        </div>
                      </template>
                    </td>
                    <td class="action-cell">
                      <div class="action-btn-group">
                        <!-- ปุ่มเสนอหนังสือ (แสดงเฉพาะกรณี รอดำเนินการ st = 1 หรือ ถูกตีกลับ st = 5 สำหรับสารบรรณ หรือ Admin) -->
                        <button
                          v-if="scope !== 'mine' && (document.legacyStatus === 1 || document.legacyStatus === 5) && isOfficerOrAdmin"
                          type="button"
                          class="btn-row-action btn-send-doc"
                          :title="document.legacyStatus === 5 ? 'เสนอหนังสือเข้าสู่สายการลงนามใหม่' : 'ตรวจสอบความเรียบร้อยและเสนอหนังสือเข้าสู่สายการลงนาม'"
                          @click="openSendPreview(document)"
                        >
                          {{ document.legacyStatus === 5 ? '📤 เสนอใหม่' : '📤 เสนอ' }}
                        </button>

                        <!-- ปุ่มจ่ายหนังสือ (แสดงเฉพาะกรณี เกษียนครบ st = 3 สำหรับสารบรรณ หรือ Admin) -->
                        <button
                          v-if="scope !== 'mine' && document.legacyStatus === 3 && isOfficerOrAdmin"
                          type="button"
                          class="btn-row-action btn-distribute-doc"
                          title="จ่ายหนังสือถึงบุคลากร/กลุ่มงาน และจัดเก็บเข้าแฟ้ม"
                          @click="openDistributeDoc(document.id)"
                        >
                          📨 จ่ายหนังสือ
                        </button>

                        <!-- ปุ่มแก้ไขการจ่าย (แสดงเฉพาะกรณี จ่ายแล้ว st = 4 สำหรับสารบรรณ หรือ Admin) -->
                        <button
                          v-if="scope !== 'mine' && document.legacyStatus === 4 && isOfficerOrAdmin"
                          type="button"
                          class="btn-row-action btn-edit-distribute-doc"
                          title="แก้ไขผู้รับหนังสือหรือแฟ้มจัดเก็บ"
                          @click="openDistributeDoc(document.id)"
                        >
                          📨 แก้ไขการจ่าย
                        </button>

                        <!-- ปุ่มแก้ไขหนังสือ (สารบรรณ หรือ Admin) -->
                        <button
                          v-if="scope !== 'mine' && isOfficerOrAdmin"
                          type="button"
                          class="btn-row-action btn-edit-doc"
                          title="แก้ไขข้อมูลหนังสือ / เปลี่ยนไฟล์หลัก"
                          @click="openEditDoc(document.id)"
                        >
                          ✏️ แก้ไข
                        </button>

                        <!-- ปุ่มเปิดดูรายละเอียด -->
                        <button
                          type="button"
                          class="open-button"
                          :aria-label="`เปิดหนังสือ ${document.receiveNumber}`"
                          title="เปิดดูรายละเอียด"
                          @click="open(document.id, document.uid)"
                        >
                          ↗
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <footer class="pagination">
              <span>{{ page.total ? `หน้า ${page.page} จาก ${page.pages}` : '0 รายการ' }}</span>
              <div>
                <button :disabled="busy || page.page <= 1" @click="load(page.page - 1)">← ก่อนหน้า</button>
                <button :disabled="busy || page.page >= page.pages" @click="load(page.page + 1)">ถัดไป →</button>
              </div>
            </footer>
          </section>
          <p class="workspace-note">แสดงข้อมูลจากทะเบียนเดิมตามสิทธิ์การเข้าถึงของคุณ</p>
        </template>
      </section>
    </main>

    <div v-if="selected || detailBusy" class="detail-overlay" @click.self="close" @keydown="dialogKey">
      <section ref="panel" class="detail-panel detail-panel-expanded" role="dialog" aria-modal="true" :aria-label="selected ? selected.subject : 'กำลังเปิดหนังสือ'" tabindex="-1">
        <button class="close-button" aria-label="ปิดรายละเอียด" @click="close">×</button>
        <p v-if="detailBusy" class="detail-loading-text" role="status">กำลังเปิดหนังสือ…</p>
        <template v-if="selected">
          <div class="detail-modal-layout">
            <!-- ฝั่งซ้าย: กล่องพรีวิวไฟล์แบบ Interactive (PDF / Image / Preview Viewer) -->
            <div class="detail-preview-pane">
              <div class="detail-preview-tabs">
                <button
                  type="button"
                  class="preview-tab-pill"
                  :class="{ active: selectedDetailAttachmentId === null }"
                  @click="selectedDetailAttachmentId = null"
                >
                  📄 หนังสือนำ (ไฟล์หลัก)
                </button>
                <button
                  v-for="att in selected.attachments"
                  :key="att.id"
                  type="button"
                  class="preview-tab-pill"
                  :class="{ active: selectedDetailAttachmentId === att.id }"
                  @click="selectedDetailAttachmentId = att.id"
                >
                  📎 {{ att.name }}
                </button>
              </div>

              <div class="detail-iframe-box">
                <iframe
                  v-if="selected.hasMainFile || selectedDetailAttachmentId"
                  :src="apiUrl('documents/view-file', {
                    id: selected.id,
                    uid: selected.uid,
                    ...(selectedDetailAttachmentId ? { attachmentId: selectedDetailAttachmentId } : {})
                  })"
                  class="detail-pdf-frame"
                  title="ตัวอย่างเอกสาร"
                ></iframe>
                <div v-else class="detail-empty-file">
                  <span class="empty-file-icon">📄</span>
                  <p>ไม่มีไฟล์หนังสือนำในระบบ</p>
                </div>
              </div>
            </div>

            <!-- ฝั่งขวา: ข้อมูลรายละเอียดและรายการดาวน์โหลด -->
            <div class="detail-info-pane">
              <div class="detail-header-meta">
                <span class="eyebrow">เลขรับ {{ selected.receiveNumber }}</span>
                <span class="status" :class="`status-${selected.legacyStatus}`">{{ status(selected.legacyStatus) }}</span>
              </div>
              <div v-if="scope !== 'mine' && isOfficerOrAdmin" class="detail-quick-actions">
                <button
                  v-if="selected.legacyStatus === 1 || selected.legacyStatus === 5"
                  type="button"
                  class="btn-row-action btn-send-doc"
                  :title="selected.legacyStatus === 5 ? 'เสนอหนังสือเข้าสู่สายการลงนามใหม่' : 'ตรวจสอบความเรียบร้อยและเสนอหนังสือ'"
                  @click="openSendPreview(selected)"
                >
                  {{ selected.legacyStatus === 5 ? '📤 เสนอใหม่' : '📤 เสนอหนังสือ' }}
                </button>
                <button
                  v-if="selected.legacyStatus === 3"
                  type="button"
                  class="btn-row-action btn-distribute-doc"
                  title="จ่ายหนังสือถึงบุคลากร/กลุ่มงาน และจัดเก็บเข้าแฟ้ม"
                  @click="openDistributeDoc(selected.id)"
                >
                  📨 จ่ายหนังสือ
                </button>
                <button
                  v-if="selected.legacyStatus === 4"
                  type="button"
                  class="btn-row-action btn-edit-distribute-doc"
                  title="แก้ไขผู้รับหนังสือหรือแฟ้มจัดเก็บ"
                  @click="openDistributeDoc(selected.id)"
                >
                  📨 แก้ไขการจ่าย
                </button>
                <button
                  type="button"
                  class="btn-row-action btn-edit-doc"
                  title="แก้ไขข้อมูลหนังสือ / เปลี่ยนไฟล์หลัก"
                  @click="openEditDoc(selected.id)"
                >
                  ✏️ แก้ไขข้อมูล
                </button>
              </div>
              <h2 id="detail-title">{{ selected.subject }}</h2>

              <!-- กล่องแจ้งเตือนเอกสารถูกทำลายแล้ว (Destruction Callout) -->
              <div v-if="selected.legacyStatus === 6 || selected.destructionInfo" class="detail-destroyed-callout">
                <div class="callout-destroyed-icon">🗑️</div>
                <div class="callout-destroyed-content">
                  <strong>เอกสารฉบับนี้ถูกทำลายตามระเบียบงานสารบรรณแล้ว</strong>
                  <p v-if="selected.destructionInfo?.reason">
                    <strong>เหตุผล:</strong> {{ selected.destructionInfo.reason }}
                    <span v-if="selected.destructionInfo?.orderNumber"> (คำสั่ง/อนุมัติ: {{ selected.destructionInfo.orderNumber }})</span>
                  </p>
                  <p v-if="selected.destructionInfo?.destroyedAt">
                    <strong>วันเวลาที่ทำลาย:</strong> {{ datetime(selected.destructionInfo.destroyedAt) }}
                    <span v-if="selected.destructionInfo?.destroyedByName"> โดย {{ selected.destructionInfo.destroyedByName }}</span>
                  </p>
                  <p v-if="selected.destructionInfo?.batchNumber">
                    <strong>รอบการทำลาย:</strong> {{ selected.destructionInfo.batchNumber }}
                  </p>
                  <div v-if="selected.destructionInfo?.fileHash" class="hash-box-sm">
                    <small>SHA-256 Checksum: </small><code>{{ selected.destructionInfo.fileHash }}</code>
                  </div>
                </div>
              </div>

              <!-- กล่องแจ้งเตือนเอกสารถูกตีกลับในหน้ารายละเอียด (In-App Callout) -->
              <div v-if="selected.legacyStatus === 5 || selected.returnReason" class="detail-returned-callout">
                <div class="callout-returned-icon">⛔</div>
                <div class="callout-returned-content">
                  <strong>หนังสือนี้ถูกตีกลับจากสายการลงนาม</strong>
                  <p v-if="selected.returnReason">
                    <strong>เหตุผลการตีกลับ:</strong> {{ selected.returnReason }}
                  </p>
                  <small v-if="selected.returnerName" class="muted">
                    ตีกลับโดย: {{ selected.returnerName }} <span v-if="selected.returnedAt">({{ datetime(selected.returnedAt) }})</span>
                  </small>
                </div>
              </div>

              <!-- แถบสลับแท็บรายละเอียด: ข้อมูลทั่วไป, ข้อมูลการลงนาม, รายชื่อผู้เปิดอ่าน -->
              <div class="detail-tab-navs">
                <button
                  type="button"
                  class="detail-tab-btn"
                  :class="{ active: detailActiveTab === 'info' }"
                  @click="detailActiveTab = 'info'"
                >
                  📋 รายละเอียด & ไฟล์
                </button>
                <button
                  type="button"
                  class="detail-tab-btn"
                  :class="{ active: detailActiveTab === 'workflow' }"
                  @click="detailActiveTab = 'workflow'"
                >
                  ✍️ ข้อมูลการลงนาม
                  <span v-if="selected.signingSteps?.length" class="tab-count-pill">
                    {{ selected.signingSteps.filter(s => s.status === 3).length }}/{{ selected.signingSteps.length }}
                  </span>
                </button>
                <button
                  type="button"
                  class="detail-tab-btn"
                  :class="{ active: detailActiveTab === 'readers' }"
                  @click="detailActiveTab = 'readers'"
                >
                  👁️ ผู้เปิดอ่านเอกสาร
                  <span v-if="selected.totalAssignedCount" class="tab-count-pill" :class="{ 'pill-success': selected.readCount === selected.totalAssignedCount }">
                    {{ selected.readCount || 0 }}/{{ selected.totalAssignedCount }}
                  </span>
                </button>
              </div>

              <!-- แท็บ 1: ข้อมูลทั่วไป & ไฟล์แนบ -->
              <div v-show="detailActiveTab === 'info'" class="tab-pane-content">
                <dl>
                  <div><dt>เลขที่หนังสือ</dt><dd>{{ selected.letterNumber || '—' }}</dd></div>
                  <div><dt>วันที่รับ</dt><dd>{{ datetime(selected.receivedAt) }}</dd></div>
                  <div><dt>วันที่หนังสือ</dt><dd>{{ date(selected.letterDate) }}</dd></div>
                  <div><dt>เรียน</dt><dd>{{ selected.to || '—' }}</dd></div>
                  <div><dt>ความเร่งด่วน</dt><dd>{{ selected.priority || 'ปกติ' }}</dd></div>
                </dl>

                <hr class="detail-divider" />

                <div class="file-sections">
                  <div class="file-section">
                    <div class="file-section-head">
                      <h3>หนังสือนำ</h3>
                      <button
                        v-if="selected.hasMainFile"
                        type="button"
                        class="btn-switch-preview"
                        :class="{ 'btn-active-pill': selectedDetailAttachmentId === null }"
                        @click="selectedDetailAttachmentId = null"
                      >
                        👁️ ดูไฟล์หลัก
                      </button>
                    </div>
                    <a
                      v-if="selected.hasMainFile"
                      class="file-link"
                      :href="apiUrl('documents/download', { id: selected.id, uid: selected.uid })"
                      target="_blank"
                    >
                      <span>▤ ดาวน์โหลดหนังสือนำฉบับเต็ม</span>
                      <span class="download-arrow">↓</span>
                    </a>
                    <p v-else class="muted">ไม่มีไฟล์หนังสือนำในทะเบียนเดิม</p>
                  </div>

                  <div class="file-section">
                    <div class="file-section-head">
                      <h3>เอกสารแนบ <span class="muted-badge">{{ selected.attachments?.length || 0 }}</span></h3>
                    </div>
                    <div v-if="selected.attachments?.length" class="attachments-compact-list">
                      <div
                        v-for="file in selected.attachments"
                        :key="file.id"
                        class="att-item-row"
                        :class="{ 'is-viewing': selectedDetailAttachmentId === file.id }"
                      >
                        <button
                          type="button"
                          class="btn-view-att"
                          title="คลิกเพื่อพรีวิวไฟล์นี้"
                          @click="selectedDetailAttachmentId = file.id"
                        >
                          <span class="att-view-icon">👁️</span>
                          <span class="att-view-name">{{ file.name }}</span>
                        </button>
                        <a
                          class="att-dl-btn"
                          :href="apiUrl('documents/download', { id: selected.id, uid: selected.uid, attachmentId: file.uid || file.id })"
                          target="_blank"
                          title="ดาวน์โหลดไฟล์แนบนี้"
                        >
                          ↓ โหลด
                        </a>
                      </div>
                    </div>
                    <p v-else class="muted">ไม่มีเอกสารแนบ</p>
                  </div>
                </div>
              </div>

              <!-- แท็บ 2: ข้อมูลการลงนาม (Signing Workflow Timeline) -->
              <div v-show="detailActiveTab === 'workflow'" class="tab-pane-content">
                <div v-if="!selected.signingSteps?.length" class="detail-empty-box">
                  <span class="empty-icon">✍️</span>
                  <p>ยังไม่มีข้อมูลขั้นตอนการลงนามสำหรับหนังสือนี้</p>
                </div>
                <div v-else class="signing-timeline">
                  <div
                    v-for="step in selected.signingSteps"
                    :key="step.id || step.sort"
                    class="timeline-item"
                    :class="{
                      'step-signed': step.status === 3,
                      'step-active': step.status === 2,
                      'step-pending': step.status === 1
                    }"
                  >
                    <div class="timeline-badge-col">
                      <span class="step-num">{{ step.sort }}</span>
                      <div class="timeline-line"></div>
                    </div>

                    <div class="timeline-card" :class="{ 'card-returned': step.isReturned || (step.detail && step.detail.startsWith('[ตีกลับ]')) }">
                      <div class="step-card-header">
                        <div class="step-role-title">
                          <strong>{{ step.roleName }}</strong>
                          <span v-if="step.isReturned || (step.detail && step.detail.startsWith('[ตีกลับ]'))" class="status-badge badge-red">⛔ ถูกตีกลับ</span>
                          <span v-else-if="step.status === 3" class="status-badge badge-green">✓ ลงนามแล้ว</span>
                          <span v-else-if="step.status === 2" class="status-badge badge-amber">⏳ อยู่ระหว่างลงนาม</span>
                          <span v-else class="status-badge badge-gray">รอคิว</span>
                        </div>
                        <span v-if="(step.status === 3 || step.isReturned) && step.signedAt" class="step-time">
                          🕒 {{ datetime(step.signedAt) }}
                        </span>
                      </div>

                      <!-- แสดงรายละเอียดกรณีขั้นตอนที่ถูกตีกลับ -->
                      <div v-if="step.isReturned || (step.detail && step.detail.startsWith('[ตีกลับ]'))" class="step-returned-box">
                        <span class="returned-label">เหตุผลการตีกลับ:</span>
                        <p class="returned-text">{{ step.returnReason || step.detail?.replace(/^\[ตีกลับ\]\s*/, '') }}</p>
                        <div v-if="step.signerName" class="step-returner-info">
                          <small class="muted">โดย: <strong>{{ step.signerName }}</strong></small>
                        </div>
                      </div>

                      <!-- แสดงรายละเอียดเฉพาะขั้นตอนที่ลงนามแล้ว (status === 3) -->
                      <template v-if="step.status === 3">
                        <!-- ผู้ลงนาม / ตำแหน่ง -->
                        <div v-if="step.signerName" class="step-signer-row">
                          <div class="signer-avatar">
                            <img v-if="step.signerPhoto" :src="resolveMediaUrl(step.signerPhoto)" alt="" />
                            <span v-else>{{ step.signerName.slice(0, 1) }}</span>
                          </div>
                          <div class="signer-meta">
                            <div class="signer-fullname">{{ step.signerName }}</div>
                            <div class="signer-positions">
                              <span v-if="step.position1">{{ step.position1 }}</span>
                              <span v-if="step.position2">{{ step.position2 }}</span>
                              <span v-if="step.position3">{{ step.position3 }}</span>
                              <span v-if="!step.position1 && step.signerDepName">{{ step.signerDepName }}</span>
                            </div>
                          </div>
                        </div>

                        <!-- ข้อความเกษียน / คำสั่งการ -->
                        <div v-if="step.stamp" class="step-stamp-box">
                          <span class="stamp-label">คำสั่งการ / เกษียน:</span>
                          <div class="stamp-content" v-html="step.stamp"></div>
                        </div>

                        <!-- รายละเอียดเพิ่มเติม -->
                        <div v-if="step.detail" class="step-detail-box">
                          <span class="detail-label">บันทึกเพิ่มเติม:</span>
                          <p class="detail-text">{{ step.detail }}</p>
                        </div>

                        <!-- ลายมือชื่อดิจิทัล -->
                        <div v-if="step.signerSignPhoto" class="step-signature-preview">
                          <span class="sig-label">ลายมือชื่อดิจิทัล:</span>
                          <div class="sig-img-container">
                            <img :src="resolveMediaUrl(step.signerSignPhoto)" alt="ลายมือชื่อ" />
                          </div>
                        </div>
                      </template>
                    </div>
                  </div>
                </div>
              </div>

              <!-- แท็บ 3: รายชื่อผู้ที่อ่านเอกสารแล้ว และเวลาที่อ่าน (Readers & Timestamps) -->
              <div v-show="detailActiveTab === 'readers'" class="tab-pane-content">
                <div v-if="!selected.readers?.length" class="detail-empty-box">
                  <span class="empty-icon">👁️</span>
                  <p>หนังสือฉบับนี้ยังไม่มีรายการแจกจ่ายถึงบุคลากรในระบบ</p>
                  <p class="empty-sub">เมื่อเจ้าหน้าที่สารบรรณจ่ายหนังสือ รายชื่อผู้รับและเวลาที่เปิดอ่านจะปรากฏที่นี่</p>
                </div>
                <div v-else class="readers-section">
                  <!-- แถบสรุปสถิติการเปิดอ่าน -->
                  <div class="readers-stats-card">
                    <div class="stats-row">
                      <div class="stat-col">
                        <span class="stat-val text-green">{{ selected.readCount || 0 }}</span>
                        <span class="stat-lbl">อ่านแล้ว</span>
                      </div>
                      <div class="stat-col">
                        <span class="stat-val text-gray">{{ (selected.totalAssignedCount || 0) - (selected.readCount || 0) }}</span>
                        <span class="stat-lbl">ยังไม่อ่าน</span>
                      </div>
                      <div class="stat-col">
                        <span class="stat-val">{{ selected.totalAssignedCount || 0 }}</span>
                        <span class="stat-lbl">ผู้รับทั้งหมด</span>
                      </div>
                    </div>
                    <div class="progress-track">
                      <div
                        class="progress-fill"
                        :style="{ width: `${selected.totalAssignedCount ? ((selected.readCount || 0) / selected.totalAssignedCount) * 100 : 0}%` }"
                      ></div>
                    </div>
                  </div>

                  <!-- ตัวกรองค้นหาผู้รับ/ผู้เปิดอ่าน -->
                  <div class="readers-filter-bar">
                    <div class="filter-pills">
                      <button
                        type="button"
                        class="pill-btn"
                        :class="{ active: readerFilter === 'all' }"
                        @click="readerFilter = 'all'"
                      >
                        ทั้งหมด ({{ selected.totalAssignedCount || 0 }})
                      </button>
                      <button
                        type="button"
                        class="pill-btn pill-green"
                        :class="{ active: readerFilter === 'read' }"
                        @click="readerFilter = 'read'"
                      >
                        ✅ อ่านแล้ว ({{ selected.readCount || 0 }})
                      </button>
                      <button
                        type="button"
                        class="pill-btn pill-gray"
                        :class="{ active: readerFilter === 'unread' }"
                        @click="readerFilter = 'unread'"
                      >
                        ⏳ ยังไม่อ่าน ({{ (selected.totalAssignedCount || 0) - (selected.readCount || 0) }})
                      </button>
                    </div>
                    <input
                      v-model="readerSearch"
                      type="search"
                      class="reader-search-input"
                      placeholder="🔍 ค้นชื่อ / ตำแหน่ง / กลุ่มงาน…"
                    />
                  </div>

                  <!-- รายการผู้รับ/ผู้อ่าน -->
                  <div class="readers-grid">
                    <div
                      v-for="reader in filteredReaders"
                      :key="reader.id"
                      class="reader-card"
                      :class="{ 'is-read': reader.isRead, 'is-unread': !reader.isRead }"
                    >
                      <div class="reader-avatar">
                        <img v-if="reader.photo" :src="resolveMediaUrl(reader.photo)" alt="" />
                        <span v-else>{{ reader.readerName.slice(0, 1) }}</span>
                      </div>
                      <div class="reader-details">
                        <div class="reader-header">
                          <span class="reader-name">{{ reader.readerName }}</span>
                          <span v-if="reader.isRead" class="reader-badge badge-read">
                            ✅ อ่านแล้ว
                          </span>
                          <span v-else class="reader-badge badge-unread">
                            ⏳ ยังไม่อ่าน
                          </span>
                        </div>
                        <div class="reader-meta-text">
                          <span v-if="reader.depName">{{ reader.depName }}</span>
                          <span v-if="reader.groupWork" class="reader-group-pill">{{ reader.groupWork }}</span>
                        </div>
                        <div class="reader-time-row">
                          <template v-if="reader.isRead">
                            <span class="read-timestamp">
                              🕒 <strong>เปิดอ่านเมื่อ:</strong> {{ datetime(reader.readAt) }}
                            </span>
                          </template>
                          <template v-else>
                            <span class="unread-timestamp">
                              📨 จ่ายหนังสือเมื่อ: {{ datetime(reader.assignedAt) }}
                            </span>
                          </template>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </template>
      </section>
    </div>

    <!-- Modal เพิ่มเอกสารเข้าระบบ (สำหรับเจ้าหน้าที่สารบรรณ) -->
    <IncomingModal
      v-if="showIncomingModal"
      :initial-daily="isDailyIncoming"
      @close="showIncomingModal = false"
      @created="onDocumentCreated"
    />

    <!-- Modal แก้ไขข้อมูลหนังสือ (สำหรับเจ้าหน้าที่สารบรรณ / Admin) -->
    <EditDocumentModal
      v-if="editDocId !== null"
      :doc-id="editDocId"
      @close="editDocId = null"
      @updated="onDocUpdated"
    />

    <!-- Modal จ่ายหนังสือและจัดเก็บ (สำหรับเอกสารที่เกษียนครบ st = 3) -->
    <DistributeModal
      v-if="distributeDocId !== null"
      :doc-id="distributeDocId"
      @close="distributeDocId = null"
      @distributed="onDocDistributed"
    />

    <!-- Modal ตรวจสอบความเรียบร้อยก่อนเสนอหนังสือ (Send Preview Verification) -->
    <SendPreviewModal
      v-if="previewSendDocId !== null"
      :doc-id="previewSendDocId"
      @close="previewSendDocId = null"
      @sent="onDocSent"
      @updated="() => load()"
    />
  </div>
</template>

<style scoped>
.pin-document-button {
  display: block;
  margin-bottom: 6px;
  padding: 3px 8px;
  font-size: 0.8rem;
  line-height: 1.5;
  color: #475569;
  background: #f8fafc;
  border: 1px solid #cbd5e1;
}
.pin-star {
  font-size: 1.15em;
  line-height: 1;
  vertical-align: -0.05em;
}
.pin-document-button.is-pinned {
  color: #92400e;
  background: #fef3c7;
  border-color: #f59e0b;
  font-weight: 600;
}
.pinned-document .receive-cell {
  box-shadow: inset 3px 0 #f59e0b;
}
.heading-actions {
  display: flex;
  align-items: center;
  gap: 1.25rem;
}

.filters-extended {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 10px;
  margin-bottom: 20px;
  background: #f8fafc;
  padding: 14px 16px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.filter-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.filter-field label {
  font-size: 0.7857rem;
  font-weight: 600;
  color: #475569;
}

.filter-field select,
.filter-field input {
  height: 36px;
  padding: 0 10px;
  font-size: 0.9286rem;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  background: #fff;
  color: #1e293b;
  min-width: 140px;
}

.filter-actions {
  display: flex;
  gap: 6px;
  align-items: center;
}

.btn-reset-filter {
  height: 36px;
  padding: 0 12px;
  background: #f1f5f9;
  border: 1px solid #cbd5e1;
  color: #475569;
  border-radius: 6px;
  font-size: 0.9286rem;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-reset-filter:hover {
  background: #e2e8f0;
  color: #1e293b;
}

.mine-status-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.78rem;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 9999px;
  line-height: 1.4;
  white-space: nowrap;
}

.mine-status-tag.status-read {
  color: #047857;
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
}

.mine-status-tag.status-unread {
  color: #1d4ed8;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
}

.action-cell {
  text-align: right;
  white-space: nowrap;
}

.action-btn-group {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 6px;
}

.btn-row-action {
  border: none;
  border-radius: 5px;
  padding: 5px 10px;
  font-size: 0.8571rem;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: all 0.15s ease;
}

.btn-send-doc {
  background: #0284c7;
  color: #ffffff;
}

.btn-send-doc:hover:not(:disabled) {
  background: #0369a1;
  transform: translateY(-1px);
}

.btn-distribute-doc {
  background: #10b981;
  color: #ffffff;
}

.btn-distribute-doc:hover {
  background: #059669;
  transform: translateY(-1px);
}

.btn-edit-distribute-doc {
  background: #0d9488;
  color: #ffffff;
}

.btn-edit-distribute-doc:hover {
  background: #0f766e;
  transform: translateY(-1px);
}

.detail-quick-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
  margin-bottom: 14px;
}

.btn-edit-doc {
  background: #f59e0b;
  color: #ffffff;
}

.btn-edit-doc:hover {
  background: #d97706;
  transform: translateY(-1px);
}

.btn-create-doc {
  background: #00a65a;
  color: #fff;
  border: 1px solid #008d4c;
  border-radius: 6px;
  padding: 10px 20px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  box-shadow: 0 2px 4px rgba(0, 166, 90, 0.2);
  transition: all 0.18s ease;
}

.btn-create-doc:hover {
  background: #008d4c;
  box-shadow: 0 4px 10px rgba(0, 166, 90, 0.35);
  transform: translateY(-1px);
}

.btn-create-daily-doc {
  background: #0284c7;
  color: #fff;
  border: 1px solid #0369a1;
  border-radius: 6px;
  padding: 10px 18px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  box-shadow: 0 2px 4px rgba(2, 132, 199, 0.2);
  transition: all 0.18s ease;
}

.btn-create-daily-doc:hover {
  background: #0369a1;
  box-shadow: 0 4px 10px rgba(2, 132, 199, 0.35);
  transform: translateY(-1px);
}

.btn-nav-incoming {
  color: #a8dfc1 !important;
  font-weight: 600;
}

.btn-nav-daily {
  color: #7dd3fc !important;
}

.btn-nav-daily:hover {
  background: #0c4a6e !important;
  color: #fff !important;
}

.btn-nav-incoming:hover {
  background: #1b4b45 !important;
  color: #fff !important;
}

.alert-success-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  background: #e8f8f0;
  border: 1px solid #a3e0c0;
  border-radius: 8px;
  padding: 12px 18px;
  margin-bottom: 20px;
  color: #175e3c;
  box-shadow: 0 2px 6px rgba(0, 166, 90, 0.08);
}

.banner-icon {
  font-size: 1.4286rem;
}

.banner-body {
  flex: 1;
}

.banner-body strong {
  display: block;
  font-size: 0.9286rem;
  margin-bottom: 2px;
}

.banner-body p {
  margin: 0;
  font-size: 0.8571rem;
  color: #276f4c;
}

.banner-close {
  background: transparent;
  border: none;
  font-size: 1.1429rem;
  color: #175e3c;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 4px;
}

.sidebar-badge-count {
  background: #ef4444;
  color: #ffffff;
  font-size: 0.78rem;
  font-weight: 700;
  padding: 1px 7px;
  border-radius: 10px;
  margin-left: auto;
  line-height: 1.4;
}

.sidebar-badge-unread {
  background: #2563eb;
}

.banner-close:hover {
  background: rgba(0, 0, 0, 0.05);
}

/* Expanded Detail Panel with File Previews */
.detail-panel-expanded {
  width: min(1360px, 96vw) !important;
  max-width: 1400px;
  padding: 0 !important;
  display: flex;
  flex-direction: column;
  overflow: hidden !important;
  background: #ffffff;
  border-radius: 12px 0 0 12px;
}

.detail-panel-expanded .close-button {
  top: 14px;
  right: 18px;
  z-index: 30;
  background: #f1f5f9;
  border-radius: 50%;
  width: 34px;
  height: 34px;
  display: grid;
  place-items: center;
  font-size: 1.4286rem;
  border: 1px solid #cbd5e1;
  transition: all 0.15s;
}

.detail-panel-expanded .close-button:hover {
  background: #e2e8f0;
  color: #0f172a;
}

.detail-loading-text {
  padding: 3rem;
  text-align: center;
  color: #64748b;
  font-size: 1rem;
}

.detail-modal-layout {
  display: grid;
  grid-template-columns: 1.55fr 1fr;
  height: 100%;
  overflow: hidden;
}

/* Preview Pane (Left) */
.detail-preview-pane {
  background: #1e293b;
  border-right: 1px solid #334155;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.detail-preview-tabs {
  display: flex;
  gap: 6px;
  padding: 10px 14px;
  background: #0f172a;
  border-bottom: 1px solid #334155;
  overflow-x: auto;
  flex-shrink: 0;
}

.preview-tab-pill {
  background: #1e293b;
  border: 1px solid #334155;
  color: #94a3b8;
  padding: 6px 14px;
  border-radius: 6px;
  font-size: 0.82rem;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.12s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.preview-tab-pill:hover {
  background: #334155;
  color: #ffffff;
}

.preview-tab-pill.active {
  background: #0284c7;
  border-color: #0284c7;
  color: #ffffff;
  font-weight: 600;
}

.detail-iframe-box {
  flex: 1;
  background: #334155;
  position: relative;
  overflow: hidden;
}

.detail-pdf-frame {
  width: 100%;
  height: 100%;
  border: none;
}

.detail-empty-file {
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
  gap: 10px;
}

.empty-file-icon {
  font-size: 44px;
}

/* Info Pane (Right) */
.detail-info-pane {
  padding: 45px 32px 30px 32px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  background: #ffffff;
}

.detail-header-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: -4px;
}

.detail-info-pane h2 {
  font-size: 1.3rem;
  line-height: 1.45;
  color: #0f172a;
  margin: 0;
}

.detail-divider {
  border: 0;
  height: 1px;
  background: #e2e8f0;
  margin: 0.5rem 0;
}

.file-sections {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.file-section {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 14px 16px;
}

.file-section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}

.file-section-head h3 {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 700;
  color: #1e293b;
}

.muted-badge {
  font-size: 0.78rem;
  background: #e2e8f0;
  color: #475569;
  padding: 1px 7px;
  border-radius: 10px;
  font-weight: 600;
}

.btn-switch-preview {
  background: #f1f5f9;
  border: 1px solid #cbd5e1;
  color: #334155;
  font-size: 0.78rem;
  padding: 3px 10px;
  border-radius: 5px;
  cursor: pointer;
  transition: all 0.12s;
}

.btn-switch-preview:hover,
.btn-switch-preview.btn-active-pill {
  background: #0284c7;
  color: #ffffff;
  border-color: #0284c7;
}

.attachments-compact-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.att-item-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 6px 10px;
  transition: all 0.12s;
}

.att-item-row:hover {
  border-color: #94a3b8;
}

.att-item-row.is-viewing {
  border-color: #38bdf8;
  background: #f0f9ff;
}

.btn-view-att {
  background: transparent;
  border: none;
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  padding: 0;
  text-align: left;
  flex: 1;
  min-width: 0;
}

.att-view-icon {
  font-size: 0.9286rem;
  flex-shrink: 0;
}

.att-view-name {
  font-size: 0.85rem;
  color: #1e293b;
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.att-item-row.is-viewing .att-view-name {
  color: #0369a1;
  font-weight: 600;
}

.att-dl-btn {
  background: #f1f5f9;
  border: 1px solid #cbd5e1;
  color: #334155;
  font-size: 0.78rem;
  padding: 3px 8px;
  border-radius: 4px;
  text-decoration: none;
  white-space: nowrap;
  flex-shrink: 0;
  margin-left: 8px;
}

.att-dl-btn:hover {
  background: #e2e8f0;
  color: #0f172a;
}

/* Detail Tabs Navigation */
.detail-tab-navs {
  display: flex;
  gap: 6px;
  border-bottom: 2px solid #e2e8f0;
  padding-bottom: 0px;
  margin-top: 6px;
  margin-bottom: 4px;
}

.detail-tab-btn {
  background: transparent;
  border: none;
  border-bottom: 3px solid transparent;
  padding: 8px 12px;
  font-size: 0.88rem;
  font-weight: 600;
  color: #64748b;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all 0.15s ease;
  margin-bottom: -2px;
}

.detail-tab-btn:hover {
  color: #0284c7;
}

.detail-tab-btn.active {
  color: #0284c7;
  border-bottom-color: #0284c7;
}

.tab-count-pill {
  font-size: 0.78rem;
  padding: 1px 7px;
  border-radius: 10px;
  background: #e2e8f0;
  color: #475569;
  font-weight: 700;
}

.tab-count-pill.pill-success {
  background: #dcfce7;
  color: #15803d;
}

.tab-pane-content {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.detail-empty-box {
  padding: 3rem 1.5rem;
  text-align: center;
  background: #f8fafc;
  border-radius: 8px;
  border: 1px dashed #cbd5e1;
  color: #64748b;
}

.detail-empty-box .empty-icon {
  font-size: 2.5714rem;
  display: block;
  margin-bottom: 8px;
}

.detail-empty-box p {
  margin: 0;
  font-size: 0.95rem;
}

.detail-empty-box .empty-sub {
  margin-top: 6px;
  font-size: 0.82rem;
  color: #94a3b8;
}

/* Signing Timeline */
.signing-timeline {
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin-top: 4px;
}

.timeline-item {
  display: flex;
  gap: 12px;
  position: relative;
}

.timeline-badge-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex-shrink: 0;
  width: 28px;
}

.step-num {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  font-size: 0.8rem;
  font-weight: 700;
  background: #e2e8f0;
  color: #475569;
  border: 2px solid #cbd5e1;
  z-index: 2;
}

.step-signed .step-num {
  background: #10b981;
  color: #ffffff;
  border-color: #059669;
}

.step-active .step-num {
  background: #f59e0b;
  color: #ffffff;
  border-color: #d97706;
}

.timeline-line {
  flex: 1;
  width: 2px;
  background: #e2e8f0;
  margin-top: 4px;
}

.timeline-item:last-child .timeline-line {
  display: none;
}

.timeline-card {
  flex: 1;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 12px 16px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.step-signed .timeline-card {
  border-left: 4px solid #10b981;
}

.step-active .timeline-card {
  border-left: 4px solid #f59e0b;
  background: #fffbeb;
}

.step-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
}

.step-role-title {
  display: flex;
  align-items: center;
  gap: 8px;
}

.step-role-title strong {
  font-size: 0.95rem;
  color: #1e293b;
}

.status-badge {
  font-size: 0.78rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 12px;
}

.badge-green {
  background: #dcfce7;
  color: #15803d;
}

.badge-amber {
  background: #fef3c7;
  color: #b45309;
}

.badge-gray {
  background: #f1f5f9;
  color: #64748b;
}

.badge-red {
  background: #fee2e2;
  color: #b91c1c;
}

/* สไตล์สำหรับเอกสารถูกตีกลับ (In-App Notifications) */
.status.status-5 {
  color: #b91c1c;
  background: #fef2f2;
  border: 1px solid #fecaca;
  font-weight: 700;
}

/* สไตล์สำหรับเอกสารถูกทำลาย (Destruction Status) */
.status.status-6 {
  color: #64748b;
  background: #f1f5f9;
  border: 1px solid #cbd5e1;
  font-weight: 700;
}

.btn-destroy-doc {
  background: #fef2f2;
  color: #b91c1c;
  border-color: #fecaca;
}

.btn-destroy-doc:hover {
  background: #dc2626;
  color: #ffffff;
}

.detail-destroyed-callout {
  display: flex;
  gap: 12px;
  background: #f8fafc;
  border: 1px solid #cbd5e1;
  border-left: 4px solid #64748b;
  padding: 12px 16px;
  border-radius: 8px;
  margin-bottom: 16px;
}

.callout-destroyed-icon {
  font-size: 1.5rem;
  line-height: 1;
}

.callout-destroyed-content h4 {
  margin: 0 0 4px 0;
  color: #334155;
  font-size: 0.95rem;
}

.callout-destroyed-content p {
  margin: 2px 0;
  font-size: 0.85rem;
  color: #475569;
}

.hash-box-sm {
  margin-top: 6px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.8rem;
}

.hash-box-sm code {
  color: #0284c7;
  font-family: inherit;
}

/* Single Destruction Modal */
.single-destruction-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.65);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1050;
  padding: 20px;
}

.single-destruction-dialog {
  background: #ffffff;
  border-radius: 12px;
  width: 100%;
  max-width: 520px;
  overflow: hidden;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);
}

.single-destruction-header {
  padding: 16px 20px;
  background: #fef2f2;
  border-bottom: 1px solid #fee2e2;
  border-left: 5px solid #dc2626;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.single-destruction-header h3 {
  margin: 0;
  color: #991b1b;
  font-size: 1.15rem;
}

.single-destruction-body {
  padding: 18px 20px;
}

.target-doc-box {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 10px 14px;
  font-size: 0.88rem;
  margin-bottom: 16px;
  color: #1e293b;
}

.destruction-form-group {
  margin-bottom: 14px;
}

.destruction-form-group label {
  display: block;
  font-size: 0.85rem;
  font-weight: 600;
  color: #334155;
  margin-bottom: 6px;
}

.switch-box-label {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  font-size: 0.88rem;
  color: #b91c1c;
}

.single-destruction-footer {
  padding: 14px 20px;
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.btn-danger-confirm {
  background: #dc2626;
  color: #ffffff;
  border: none;
  padding: 8px 16px;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
}

.btn-danger-confirm:hover {
  background: #b91c1c;
}

.sidebar-badge-returned {
  background: #ef4444 !important;
  color: #ffffff !important;
  font-size: 0.78rem;
  padding: 1px 6px;
  border-radius: 10px;
  margin-left: 4px;
}

.returned-doc-snippet {
  margin-top: 3px;
  color: #dc2626;
  font-size: 0.7857rem;
  max-width: 140px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.detail-returned-callout {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-left: 4px solid #ef4444;
  border-radius: 6px;
  padding: 10px 14px;
  margin: 10px 0 16px 0;
  color: #991b1b;
}

.callout-returned-icon {
  font-size: 1.4286rem;
  line-height: 1;
}

.callout-returned-content strong {
  color: #b91c1c;
  font-size: 0.92rem;
}

.callout-returned-content p {
  margin: 3px 0;
  font-size: 0.85rem;
  color: #7f1d1d;
}

.card-returned {
  border-left: 4px solid #ef4444 !important;
  background: #fff8f8 !important;
}

.step-returned-box {
  background: #ffffff;
  border: 1px dashed #fca5a5;
  border-radius: 6px;
  padding: 8px 12px;
  margin-top: 8px;
}

.returned-label {
  font-size: 0.8rem;
  font-weight: 700;
  color: #dc2626;
  display: block;
  margin-bottom: 2px;
}

.returned-text {
  margin: 0;
  font-size: 0.88rem;
  color: #991b1b;
  line-height: 1.4;
}

.step-returner-info {
  margin-top: 4px;
}

.step-time {
  font-size: 0.8rem;
  color: #059669;
  font-weight: 600;
}

.step-signer-row {
  display: flex;
  align-items: center;
  gap: 10px;
  background: #ffffff;
  padding: 8px 10px;
  border-radius: 6px;
  border: 1px solid #e2e8f0;
}

.signer-avatar {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  overflow: hidden;
  background: #e2e8f0;
  color: #475569;
  display: grid;
  place-items: center;
  font-weight: 700;
  font-size: 1rem;
  flex-shrink: 0;
}

.signer-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.signer-fullname {
  font-weight: 600;
  color: #0f172a;
  font-size: 0.88rem;
}

.signer-positions {
  display: flex;
  flex-direction: column;
  font-size: 0.78rem;
  color: #64748b;
  line-height: 1.35;
}

.step-stamp-box {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 6px;
  padding: 8px 12px;
}

.stamp-label {
  display: block;
  font-size: 0.78rem;
  font-weight: 700;
  color: #166534;
  margin-bottom: 3px;
}

.stamp-content {
  font-size: 0.88rem;
  color: #14532d;
  line-height: 1.4;
}

.step-detail-box {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 8px 12px;
}

.detail-label {
  display: block;
  font-size: 0.78rem;
  font-weight: 700;
  color: #475569;
  margin-bottom: 2px;
}

.detail-text {
  margin: 0;
  font-size: 0.85rem;
  color: #1e293b;
  white-space: pre-line;
}

.step-signature-preview {
  display: flex;
  align-items: center;
  gap: 12px;
  background: #ffffff;
  border: 1px dashed #cbd5e1;
  border-radius: 6px;
  padding: 6px 12px;
}

.sig-label {
  font-size: 0.78rem;
  color: #64748b;
}

.sig-img-container {
  height: 38px;
  display: flex;
  align-items: center;
}

.sig-img-container img {
  max-height: 36px;
  max-width: 140px;
  object-fit: contain;
}

/* Readers Section */
.readers-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.readers-stats-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 12px 16px;
}

.stats-row {
  display: flex;
  justify-content: space-around;
  margin-bottom: 10px;
}

.stat-col {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.stat-val {
  font-size: 1.25rem;
  font-weight: 800;
  color: #0f172a;
}

.stat-val.text-green {
  color: #10b981;
}

.stat-val.text-gray {
  color: #94a3b8;
}

.stat-lbl {
  font-size: 0.78rem;
  color: #64748b;
}

.progress-track {
  width: 100%;
  height: 7px;
  background: #e2e8f0;
  border-radius: 10px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: #10b981;
  border-radius: 10px;
  transition: width 0.3s ease;
}

.readers-filter-bar {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.filter-pills {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.pill-btn {
  background: #f1f5f9;
  border: 1px solid #cbd5e1;
  color: #475569;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 0.78rem;
  cursor: pointer;
  transition: all 0.12s ease;
}

.pill-btn:hover {
  background: #e2e8f0;
}

.pill-btn.active {
  background: #0f172a;
  color: #ffffff;
  border-color: #0f172a;
}

.pill-btn.pill-green.active {
  background: #10b981;
  border-color: #059669;
}

.pill-btn.pill-gray.active {
  background: #64748b;
  border-color: #475569;
}

.reader-search-input {
  width: 100%;
  padding: 7px 12px;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  font-size: 0.85rem;
  outline: none;
  box-sizing: border-box;
}

.reader-search-input:focus {
  border-color: #0284c7;
  box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.15);
}

.readers-grid {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 480px;
  overflow-y: auto;
  padding-right: 2px;
}

.reader-card {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 10px 14px;
  border-radius: 8px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  transition: all 0.12s ease;
}

.reader-card.is-read {
  border-left: 4px solid #10b981;
}

.reader-card.is-unread {
  border-left: 4px solid #cbd5e1;
  background: #fafafa;
}

.reader-card:hover {
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
}

.reader-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  overflow: hidden;
  background: #e2e8f0;
  color: #475569;
  display: grid;
  place-items: center;
  font-weight: 700;
  font-size: 0.9286rem;
  flex-shrink: 0;
  margin-top: 2px;
}

.reader-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.reader-details {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.reader-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 6px;
}

.reader-name {
  font-size: 0.88rem;
  font-weight: 700;
  color: #0f172a;
}

.reader-badge {
  font-size: 0.78rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 10px;
}

.badge-read {
  background: #dcfce7;
  color: #15803d;
}

.badge-unread {
  background: #f1f5f9;
  color: #94a3b8;
}

.reader-meta-text {
  font-size: 0.78rem;
  color: #64748b;
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.reader-group-pill {
  background: #f1f5f9;
  padding: 1px 6px;
  border-radius: 4px;
  color: #475569;
  font-size: 0.78rem;
}

.reader-time-row {
  margin-top: 3px;
  font-size: 0.78rem;
}

.read-timestamp {
  color: #059669;
  background: #ecfdf5;
  padding: 2px 8px;
  border-radius: 4px;
  display: inline-block;
  font-weight: 500;
}

.unread-timestamp {
  color: #94a3b8;
  font-size: 0.78rem;
}

@media (max-width: 900px) {
  .detail-panel-expanded {
    width: 100vw !important;
    border-radius: 0;
  }
  .detail-modal-layout {
    grid-template-columns: 1fr;
    grid-template-rows: 1fr 1fr;
  }
}
</style>
