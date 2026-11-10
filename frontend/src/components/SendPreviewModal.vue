<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { apiUrl, request } from '../api'
import { formatThaiDate, formatThaiDateTime } from '../utils/date'
import { showSuccess, showError, showWarning, showConfirm } from '../utils/alert'

interface StepItem {
  sort: number
  roleName: string
}

interface DocProfileStep {
  sort: number
  roleName: string
}

interface DocProfile {
  id: number
  code: string
  name: string
  steps?: DocProfileStep[]
}

interface AttachmentItem {
  id: number
  name: string
  file?: string
  extension: string | null
}

interface DocumentDetail {
  id: number
  receiveNumber: string
  receivedAt: string | null
  letterNumber: string | null
  letterDate: string | null
  to: string
  docForm: string
  profileName?: string
  signingSteps?: StepItem[]
  priority: string | null
  subject: string
  legacyStatus: number | null
  hasMainFile: boolean
  attachments: AttachmentItem[]
}

const props = defineProps<{
  docId: number
}>()

const emit = defineEmits<{
  close: []
  sent: [doc: { id: number; receiveNumber: string; subject: string }]
  updated: [doc: { id: number; receiveNumber: string; subject: string }]
}>()

const loading = ref(false)
const sending = ref(false)
const error = ref('')
const doc = ref<DocumentDetail | null>(null)
const selectedAttachmentId = ref<number | null>(null)

// โหมดแก้ไขรายละเอียดหนังสือในหน้านี้
const isEditing = ref(false)
const savingEdit = ref(false)

const toOptions = ref<string[]>(['ผู้อำนวยการฯ', 'ผู้พิพากษาหัวหน้าศาลฯ'])
const priorityOptions = ref<string[]>(['ปกติ', 'ด่วน', 'ด่วนที่สุด'])
const profileOptions = ref<DocProfile[]>([])

const editForm = ref({
  subject: '',
  letterNumber: '',
  letterDate: '',
  receivedAt: '',
  priority: 'ปกติ',
  to: 'ผู้อำนวยการฯ',
  profileCode: '',
})

const currentPreviewUrl = computed(() => {
  if (!props.docId) return ''
  if (selectedAttachmentId.value) {
    return apiUrl('documents/view-file', {
      id: props.docId,
      attachmentId: selectedAttachmentId.value,
    })
  }
  return apiUrl('documents/view-file', { id: props.docId })
})

// ขั้นตอนการลงนามที่จะแสดงผล (อัปเดตสดตามโปรไฟล์ที่เลือกขณะแก้ไข)
const displaySteps = computed(() => {
  if (isEditing.value && editForm.value.profileCode) {
    const prof = profileOptions.value.find((p) => p.code === editForm.value.profileCode)
    if (prof && prof.steps?.length) {
      return prof.steps.map((s) => ({ sort: s.sort, roleName: s.roleName }))
    }
  }
  return doc.value?.signingSteps || []
})

const displayProfileName = computed(() => {
  if (isEditing.value && editForm.value.profileCode) {
    const prof = profileOptions.value.find((p) => p.code === editForm.value.profileCode)
    if (prof) return `${prof.name} (${prof.code})`
  }
  return doc.value?.profileName || doc.value?.docForm || ''
})

async function loadDocument() {
  loading.value = true
  error.value = ''
  try {
    const res = await request<{ data: DocumentDetail }>(`documents/${props.docId}`)
    doc.value = res.data
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'ไม่สามารถโหลดข้อมูลหนังสือได้'
  } finally {
    loading.value = false
  }
}

async function loadOptions() {
  try {
    const res = await request<{
      data: {
        to: string[]
        priorities: string[]
        profiles: DocProfile[]
      }
    }>('incoming/options')
    if (res.data.to?.length) toOptions.value = res.data.to
    if (res.data.priorities?.length) priorityOptions.value = res.data.priorities
    if (res.data.profiles?.length) profileOptions.value = res.data.profiles
  } catch (e) {
    console.warn('Cannot load options', e)
  }
}

function startEdit() {
  if (!doc.value) return
  editForm.value = {
    subject: doc.value.subject || '',
    letterNumber: doc.value.letterNumber || '',
    letterDate: doc.value.letterDate ? doc.value.letterDate.slice(0, 10) : '',
    receivedAt: doc.value.receivedAt ? doc.value.receivedAt.slice(0, 16) : '',
    priority: doc.value.priority || 'ปกติ',
    to: doc.value.to || 'ผู้อำนวยการฯ',
    profileCode: doc.value.docForm || '',
  }
  isEditing.value = true
}

function cancelEdit() {
  isEditing.value = false
}

function onEditToChange() {
  if (!profileOptions.value.length) return
  if (editForm.value.to === 'ผู้อำนวยการฯ') {
    const poProf = profileOptions.value.find(
      (p) =>
        p.code.toLowerCase() === 'doczpo' ||
        p.name.includes('ผอ.') ||
        p.name.includes('ผู้อำนวยการ')
    )
    if (poProf) editForm.value.profileCode = poProf.code
  } else if (editForm.value.to === 'ผู้พิพากษาหัวหน้าศาลฯ') {
    const headProf = profileOptions.value.find(
      (p) =>
        p.code.toLowerCase() === 'docz' ||
        p.name.includes('หัวหน้าศาล') ||
        p.name.includes('ผู้พิพากษา')
    )
    if (headProf) editForm.value.profileCode = headProf.code
  }
}

async function saveEdit() {
  if (!doc.value) return
  if (!editForm.value.subject.trim()) {
    await showWarning('ข้อมูลไม่ครบถ้วน', 'กรุณาระบุเรื่องของหนังสือ')
    return
  }

  savingEdit.value = true
  try {
    const formData = new FormData()
    formData.append('subject', editForm.value.subject.trim())
    formData.append('letterNumber', editForm.value.letterNumber.trim())
    formData.append('letterDate', editForm.value.letterDate || '')
    formData.append('receivedAt', editForm.value.receivedAt || '')
    formData.append('priority', editForm.value.priority)
    formData.append('docTo', editForm.value.to)
    formData.append('profileCode', editForm.value.profileCode)

    const updateUrl = apiUrl(`documents/${props.docId}/update`)
    const authToken = localStorage.getItem('docz_auth_token') || ''
    const response = await fetch(updateUrl, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${authToken}`,
      },
      body: formData,
    })

    if (!response.ok) {
      const errJson = await response.json().catch(() => ({}))
      throw new Error(errJson.message || 'บันทึกข้อมูลไม่สำเร็จ')
    }

    await showSuccess('สำเร็จ', 'บันทึกการแก้ไขข้อมูลหนังสือเรียบร้อยแล้ว')
    isEditing.value = false
    await loadDocument()
    if (doc.value) {
      emit('updated', {
        id: doc.value.id,
        receiveNumber: doc.value.receiveNumber,
        subject: doc.value.subject,
      })
    }
  } catch (err) {
    await showError('เกิดข้อผิดพลาด', err instanceof Error ? err.message : 'ไม่สามารถบันทึกข้อมูลได้')
  } finally {
    savingEdit.value = false
  }
}

async function confirmAndSend() {
  if (!doc.value) return
  const confirmed = await showConfirm(
    'ยืนยันเสนอหนังสือ',
    `คุณต้องการเสนอหนังสือเลขรับ ${doc.value.receiveNumber} เข้าสู่สายการลงนามหรือไม่?\n(ระบบจะประทับตราเลขรับบนหัวหนังสือด้านขวาโดยอัตโนมัติ)`
  )
  if (!confirmed) {
    return
  }

  sending.value = true
  try {
    await request(`documents/${props.docId}/send`, {}, {})
    await showSuccess('สำเร็จ', `เสนอหนังสือเลขรับ ${doc.value.receiveNumber} เข้าสู่สายการลงนามเรียบร้อยแล้ว`)
    emit('sent', {
      id: props.docId,
      receiveNumber: doc.value.receiveNumber,
      subject: doc.value.subject,
    })
  } catch (err) {
    await showError('เกิดข้อผิดพลาด', err instanceof Error ? err.message : 'เกิดข้อผิดพลาดในการเสนอหนังสือ')
  } finally {
    sending.value = false
  }
}

onMounted(() => {
  loadDocument()
  loadOptions()
})
</script>

<template>
  <div class="send-modal-backdrop" @click.self="emit('close')">
    <div class="send-modal-card">
      <header class="send-modal-header">
        <div class="header-titles">
          <span class="eyebrow">SUBMIT WORKFLOW VERIFICATION</span>
          <h3>ตรวจสอบความเรียบร้อยก่อนเสนอหนังสือ</h3>
        </div>
        <button class="btn-close" aria-label="ปิด" @click="emit('close')">✕</button>
      </header>

      <div v-if="loading" class="send-loading">
        <span class="spinner">⏳</span> กำลังโหลดข้อมูลและตัวอย่างเอกสาร…
      </div>

      <div v-else-if="error" class="send-error">
        <p>{{ error }}</p>
        <button type="button" class="btn-secondary" @click="loadDocument">ลองใหม่</button>
      </div>

      <div v-else-if="doc" class="send-modal-body">
        <!-- ฝั่งซ้าย: PDF Viewer แสดงตัวอย่างเอกสาร (62%) -->
        <div class="preview-column">
          <div class="preview-tabs-bar">
            <button
              type="button"
              class="preview-tab-btn"
              :class="{ active: selectedAttachmentId === null }"
              @click="selectedAttachmentId = null"
            >
              📄 หนังสือนำ (ไฟล์หลัก)
            </button>
            <button
              v-for="att in doc.attachments"
              :key="att.id"
              type="button"
              class="preview-tab-btn"
              :class="{ active: selectedAttachmentId === att.id }"
              @click="selectedAttachmentId = att.id"
            >
              📎 {{ att.name }}
            </button>
          </div>

          <div class="iframe-container">
            <iframe
              v-if="doc.hasMainFile || selectedAttachmentId"
              :src="currentPreviewUrl"
              class="pdf-frame"
              title="ตัวอย่างเอกสาร"
            ></iframe>
            <div v-else class="empty-file-notice">
              <span class="notice-icon">📄</span>
              <p>ไม่มีไฟล์หนังสือนำในระบบ</p>
            </div>
          </div>
        </div>

        <!-- ฝั่งขวา: สรุปข้อมูลหนังสือและสายการลงนาม (38%) -->
        <div class="details-column">
          <!-- โหมดแสดงสรุปข้อมูลปกติ -->
          <div v-if="!isEditing" class="doc-summary-card">
            <div class="summary-top">
              <span class="badge-rnumber">เลขรับ {{ doc.receiveNumber }}</span>
              <div class="top-actions">
                <span v-if="doc.priority" class="badge-priority" :class="`prio-${doc.priority}`">
                  {{ doc.priority }}
                </span>
                <button
                  type="button"
                  class="btn-edit-inline"
                  title="แก้ไขรายละเอียดหนังสือและสายการลงนาม"
                  @click="startEdit"
                >
                  ✏️ แก้ไขข้อมูล
                </button>
              </div>
            </div>

            <h4 class="doc-subject">{{ doc.subject }}</h4>

            <div class="info-grid">
              <div class="info-item">
                <span class="info-label">เลขที่หนังสือ</span>
                <span class="info-val">{{ doc.letterNumber || '—' }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">วันที่รับ</span>
                <span class="info-val">{{ formatThaiDateTime(doc.receivedAt) }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">วันที่หนังสือ</span>
                <span class="info-val">{{ formatThaiDate(doc.letterDate) }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">เรียน</span>
                <span class="info-val">{{ doc.to || '—' }}</span>
              </div>
            </div>
          </div>

          <!-- โหมดฟอร์มแก้ไขข้อมูลหนังสือ -->
          <div v-else class="doc-summary-card edit-mode-card">
            <div class="summary-top">
              <span class="badge-rnumber">เลขรับ {{ doc.receiveNumber }}</span>
              <span class="badge-editing-mode">🛠️ กำลังแก้ไขข้อมูล</span>
            </div>

            <div class="edit-form-grid">
              <div class="edit-field full-width">
                <label>เรื่อง *</label>
                <input
                  v-model="editForm.subject"
                  type="text"
                  required
                  placeholder="เรื่องของหนังสือ"
                  class="edit-input font-bold"
                />
              </div>

              <div class="edit-field">
                <label>เลขที่หนังสือ</label>
                <input
                  v-model="editForm.letterNumber"
                  type="text"
                  placeholder="เช่น ศย"
                  class="edit-input"
                />
              </div>

              <div class="edit-field">
                <label>หนังสือลงวันที่</label>
                <input
                  v-model="editForm.letterDate"
                  type="date"
                  class="edit-input"
                />
              </div>

              <div class="edit-field">
                <label>ลงรับวันที่</label>
                <input
                  v-model="editForm.receivedAt"
                  type="datetime-local"
                  class="edit-input"
                />
              </div>

              <div class="edit-field">
                <label>ชั้นความเร็ว</label>
                <select v-model="editForm.priority" class="edit-select">
                  <option v-for="p in priorityOptions" :key="p" :value="p">{{ p }}</option>
                </select>
              </div>

              <div class="edit-field">
                <label>เรียน *</label>
                <select v-model="editForm.to" class="edit-select" @change="onEditToChange">
                  <option v-for="opt in toOptions" :key="opt" :value="opt">{{ opt }}</option>
                </select>
              </div>

              <div class="edit-field full-width">
                <label>ขั้นตอนการลงนาม (สายการลงนาม) *</label>
                <select v-model="editForm.profileCode" class="edit-select select-profile">
                  <option v-for="p in profileOptions" :key="p.code" :value="p.code">
                    {{ p.name }} ({{ p.code }}) {{ p.steps?.length ? '— [' + p.steps.map(s => s.roleName).join(' ➔ ') + ']' : '' }}
                  </option>
                </select>
              </div>
            </div>

            <div class="edit-actions-bar">
              <button
                type="button"
                class="btn-cancel-edit"
                :disabled="savingEdit"
                @click="cancelEdit"
              >
                ยกเลิก
              </button>
              <button
                type="button"
                class="btn-save-edit"
                :disabled="savingEdit"
                @click="saveEdit"
              >
                {{ savingEdit ? 'กำลังบันทึก…' : '💾 บันทึกการแก้ไข' }}
              </button>
            </div>
          </div>

          <!-- กล่องสายการลงนามที่จะถูกเสนอเข้าไป -->
          <div class="signing-route-card">
            <div class="route-header">
              <span class="route-icon">📝</span>
              <strong>สายการลงนามที่จะส่งต่อ:</strong>
              <span class="profile-tag">{{ displayProfileName }}</span>
              <span v-if="isEditing" class="live-preview-badge">⚡ กำลังพรีวิวสด</span>
            </div>

            <div v-if="displaySteps && displaySteps.length" class="route-timeline">
              <div
                v-for="(step, idx) in displaySteps"
                :key="idx"
                class="timeline-item"
              >
                <div class="step-circle" :class="{ 'first-step': idx === 0 }">
                  {{ idx + 1 }}
                </div>
                <div class="step-detail">
                  <span class="step-role">{{ step.roleName }}</span>
                  <small v-if="idx === 0" class="first-hint">👉 รอลงนามลำดับแรกทันทีเมื่อกดเสนอ</small>
                </div>
              </div>
            </div>
            <p v-else class="no-steps-text">ไม่พบขั้นตอนลำดับการลงนามที่กำหนดไว้</p>
          </div>

          <!-- แจ้งเตือนเรื่องการประทับตราเลขรับอัตโนมัติ -->
          <div class="watermark-notice-box">
            <span class="notice-pin">💡</span>
            <div class="notice-text">
              <strong>ระบบจะประทับตราเลขรับอัตโนมัติ:</strong>
              <p>เมื่อกดปุ่ม "ยืนยันเสนอหนังสือ" ระบบจะวาดกรอบตรายางเลขรับ วันที่ และเวลาลงรับ สีน้ำเงิน บนมุมขวาบนของหนังสือนำ (PDF) ให้อัตโนมัติ</p>
            </div>
          </div>

          <!-- ปุ่มแอ็กชันด้านล่าง -->
          <div class="details-footer">
            <button
              type="button"
              class="btn-cancel"
              :disabled="sending"
              @click="emit('close')"
            >
              ปิดหน้าต่าง
            </button>
            <button
              type="button"
              class="btn-confirm-send"
              :disabled="sending"
              @click="confirmAndSend"
            >
              {{ sending ? 'กำลังเสนอหนังสือ…' : '📤 ยืนยันเสนอหนังสือเข้าสู่สายการลงนาม' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.send-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 240;
  padding: 1.25rem;
}

.send-modal-card {
  background: #ffffff;
  border-radius: 12px;
  width: 100%;
  max-width: 1300px;
  height: 92vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 25px 60px rgba(0, 0, 0, 0.35);
  border-top: 5px solid #0284c7;
  overflow: hidden;
}

.send-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.9rem 1.5rem;
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
}

.header-titles .eyebrow {
  display: block;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 1.5px;
  color: #0284c7;
  margin-bottom: 2px;
}

.header-titles h3 {
  margin: 0;
  font-size: 1.2rem;
  color: #1e293b;
  font-weight: 700;
}

.btn-close {
  background: transparent;
  border: none;
  font-size: 1.3rem;
  color: #64748b;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 4px;
}

.btn-close:hover {
  background: #e2e8f0;
  color: #0f172a;
}

.send-loading,
.send-error {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  color: #64748b;
}

.send-modal-body {
  flex: 1;
  display: grid;
  grid-template-columns: 1.6fr 1fr;
  overflow: hidden;
}

/* ฝั่งซ้าย: Preview */
.preview-column {
  display: flex;
  flex-direction: column;
  border-right: 1px solid #e2e8f0;
  background: #1e293b;
  overflow: hidden;
}

.preview-tabs-bar {
  display: flex;
  gap: 6px;
  padding: 8px 12px;
  background: #0f172a;
  border-bottom: 1px solid #334155;
  overflow-x: auto;
}

.preview-tab-btn {
  background: #1e293b;
  border: 1px solid #334155;
  color: #94a3b8;
  padding: 5px 12px;
  border-radius: 6px;
  font-size: 0.8rem;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.12s;
}

.preview-tab-btn:hover {
  background: #334155;
  color: #ffffff;
}

.preview-tab-btn.active {
  background: #0284c7;
  border-color: #0284c7;
  color: #ffffff;
  font-weight: 600;
}

.iframe-container {
  flex: 1;
  background: #334155;
  position: relative;
}

.pdf-frame {
  width: 100%;
  height: 100%;
  border: none;
}

.empty-file-notice {
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
  gap: 8px;
}

.notice-icon {
  font-size: 40px;
}

/* ฝั่งขวา: รายละเอียด */
.details-column {
  display: flex;
  flex-direction: column;
  padding: 1.25rem 1.5rem;
  background: #f8fafc;
  overflow-y: auto;
  gap: 1rem;
}

.doc-summary-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 1rem 1.25rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.summary-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.6rem;
}

.badge-rnumber {
  background: #e0f2fe;
  color: #0369a1;
  font-weight: 700;
  font-size: 0.82rem;
  padding: 3px 10px;
  border-radius: 4px;
}

.badge-priority {
  font-size: 0.78rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 4px;
}

.prio-ด่วนที่สุด { background: #fee2e2; color: #b91c1c; }
.prio-ด่วน { background: #ffedd5; color: #c2410c; }
.prio-ปกติ { background: #f1f5f9; color: #475569; }

.doc-subject {
  margin: 0 0 0.85rem;
  font-size: 1.05rem;
  line-height: 1.5;
  color: #0f172a;
  font-weight: 700;
}

.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px 14px;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.info-label {
  font-size: 0.78rem;
  color: #64748b;
  font-weight: 600;
}

.info-val {
  font-size: 0.84rem;
  color: #1e293b;
  font-weight: 500;
}

.top-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-edit-inline {
  background: #f0f9ff;
  border: 1px solid #bae6fd;
  color: #0284c7;
  font-weight: 600;
  font-size: 0.78rem;
  padding: 3px 8px;
  border-radius: 4px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: all 0.15s ease;
}

.btn-edit-inline:hover {
  background: #e0f2fe;
  border-color: #0284c7;
  color: #0369a1;
}

.edit-mode-card {
  border: 2px solid #0284c7 !important;
  background: #ffffff;
}

.badge-editing-mode {
  background: #fef3c7;
  color: #b45309;
  font-size: 0.78rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 4px;
  border: 1px solid #fde68a;
}

.edit-form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-top: 6px;
}

.edit-field {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.edit-field.full-width {
  grid-column: span 2;
}

.edit-field label {
  font-size: 0.78rem;
  font-weight: 600;
  color: #475569;
}

.edit-input,
.edit-select {
  padding: 6px 10px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 0.86rem;
  outline: none;
  background: #ffffff;
  color: #1e293b;
  transition: border-color 0.15s;
}

.edit-input:focus,
.edit-select:focus {
  border-color: #0284c7;
  box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.15);
}

.select-profile {
  font-weight: 600;
  color: #1e40af;
  background: #f8fafc;
}

.font-bold {
  font-weight: 600;
}

.edit-actions-bar {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 12px;
  padding-top: 10px;
  border-top: 1px solid #e2e8f0;
}

.btn-save-edit {
  background: #0284c7;
  color: #ffffff;
  border: 1px solid #0369a1;
  padding: 6px 14px;
  border-radius: 6px;
  font-weight: 700;
  font-size: 0.85rem;
  cursor: pointer;
  transition: background 0.15s;
}

.btn-save-edit:hover:not(:disabled) {
  background: #0369a1;
}

.btn-cancel-edit {
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #cbd5e1;
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 0.85rem;
  cursor: pointer;
}

.btn-cancel-edit:hover:not(:disabled) {
  background: #e2e8f0;
}

.live-preview-badge {
  background: #fef08a;
  border: 1px solid #fde047;
  color: #854d0e;
  font-size: 0.78rem;
  font-weight: 700;
  padding: 1px 8px;
  border-radius: 12px;
  margin-left: auto;
}

.signing-route-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 1rem 1.25rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.route-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 0.85rem;
  font-size: 0.88rem;
  color: #1e293b;
}

.profile-tag {
  background: #f1f5f9;
  border: 1px solid #cbd5e1;
  color: #334155;
  padding: 1px 8px;
  border-radius: 12px;
  font-size: 0.78rem;
  font-weight: 600;
}

.route-timeline {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-left: 6px;
  border-left: 2px dashed #cbd5e1;
  margin-left: 10px;
}

.timeline-item {
  display: flex;
  align-items: center;
  gap: 12px;
  position: relative;
}

.step-circle {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #cbd5e1;
  color: #334155;
  display: grid;
  place-items: center;
  font-size: 0.78rem;
  font-weight: 700;
  margin-left: -19px;
  border: 2px solid #ffffff;
  flex-shrink: 0;
}

.step-circle.first-step {
  background: #0284c7;
  color: #ffffff;
}

.step-detail {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.step-role {
  font-size: 0.88rem;
  font-weight: 600;
  color: #1e293b;
}

.first-hint {
  font-size: 0.78rem;
  color: #0284c7;
  font-weight: 500;
}

.no-steps-text {
  font-size: 0.82rem;
  color: #94a3b8;
  margin: 0;
}

.watermark-notice-box {
  display: flex;
  gap: 10px;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 8px;
  padding: 10px 14px;
  color: #1e40af;
}

.notice-pin {
  font-size: 1.2rem;
}

.notice-text strong {
  display: block;
  font-size: 0.84rem;
  margin-bottom: 2px;
}

.notice-text p {
  margin: 0;
  font-size: 0.78rem;
  line-height: 1.4;
  color: #1d4ed8;
}

.details-footer {
  margin-top: auto;
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-top: 10px;
}

.btn-confirm-send {
  background: #0284c7;
  border: 1px solid #0369a1;
  color: #ffffff;
  padding: 12px 18px;
  border-radius: 8px;
  font-size: 0.95rem;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 3px 8px rgba(2, 132, 199, 0.3);
  transition: all 0.15s;
}

.btn-confirm-send:hover:not(:disabled) {
  background: #0369a1;
  box-shadow: 0 4px 12px rgba(2, 132, 199, 0.45);
  transform: translateY(-1px);
}

.btn-cancel {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  color: #475569;
  padding: 8px 14px;
  border-radius: 6px;
  font-size: 0.88rem;
  cursor: pointer;
}

.btn-cancel:hover {
  background: #f1f5f9;
}
</style>
