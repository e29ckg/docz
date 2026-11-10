<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { apiUrl, request } from '../api'
import { showAlert, showSuccess, showError, showWarning, showConfirm } from '../utils/alert'

interface SigningStep {
  id: number
  roleId: number
  roleName: string
  stepOrder: number
}

interface DocProfile {
  id: number
  code: string
  name: string
  steps: SigningStep[]
}

interface ExistingAttachment {
  id: number
  name: string
  extension: string | null
  file?: string
  created?: string | null
  isDeleted?: boolean
  isEditingName?: boolean
  newName?: string
}

const props = defineProps<{
  docId: number
}>()

const emit = defineEmits<{
  close: []
  updated: [doc: { id: number; receiveNumber: string; subject: string }]
}>()

const loading = ref(false)
const submitting = ref(false)
const error = ref('')

// ตัวเลือก
const toOptions = ref<string[]>(['ผู้อำนวยการฯ', 'ผู้พิพากษาหัวหน้าศาลฯ'])
const priorityOptions = ref<string[]>(['ปกติ', 'ด่วน', 'ด่วนที่สุด'])
const docTypeOptions = ref<string[]>(['E', 'ศาลากลาง', '(ค)'])
const profileOptions = ref<DocProfile[]>([])

// ฟอร์ม
const form = ref({
  receivePrefix: 'E',
  customPrefix: '',
  receiveSeq: '',
  receiveYear: '',
  receivedAt: '',
  priority: 'ปกติ',
  letterNumber: '',
  letterDate: '',
  to: 'ผู้อำนวยการฯ',
  profileCode: '',
  subject: '',
})

// คำนวณ prefix สรุป
const effectivePrefix = computed(() => {
  if (form.value.receivePrefix === '__custom__') {
    return form.value.customPrefix.trim()
  }
  return form.value.receivePrefix.trim()
})

// ข้อมูลไฟล์เดิมและไฟล์ใหม่ (หนังสือนำ)
const existingMainFile = ref<string | null>(null)
const mainFileInput = ref<HTMLInputElement | null>(null)
const newMainFile = ref<File | null>(null)

// จัดการเอกสารแนบ (Attachments)
const existingAttachments = ref<ExistingAttachment[]>([])
const newAttachmentFiles = ref<File[]>([])
const attachmentFileInput = ref<HTMLInputElement | null>(null)

// คำนวณโปรไฟล์ที่เลือกอยู่
const selectedProfile = computed(() => {
  return profileOptions.value.find((p) => p.code === form.value.profileCode)
})

function onToChange() {
  if (!profileOptions.value.length) return
  if (form.value.to === 'ผู้อำนวยการฯ') {
    const poProf = profileOptions.value.find(
      (p) =>
        p.code.toLowerCase() === 'doczpo' ||
        p.name.includes('ผอ.') ||
        p.name.includes('ผู้อำนวยการ')
    )
    if (poProf) form.value.profileCode = poProf.code
  } else if (form.value.to === 'ผู้พิพากษาหัวหน้าศาลฯ') {
    const headProf = profileOptions.value.find(
      (p) =>
        p.code.toLowerCase() === 'docz' ||
        p.name.includes('หัวหน้าศาล') ||
        p.name.includes('ผู้พิพากษา')
    )
    if (headProf) form.value.profileCode = headProf.code
  }
}

function onMainFileChange(e: Event) {
  const target = e.target as HTMLInputElement
  if (target.files && target.files[0]) {
    const f = target.files[0]
    if (!f.name.toLowerCase().endsWith('.pdf')) {
      showWarning('รูปแบบไฟล์ไม่ถูกต้อง', 'หนังสือนำต้องเป็นไฟล์ PDF เท่านั้น')
      target.value = ''
      newMainFile.value = null
      return
    }
    newMainFile.value = f
  }
}

function onAttachmentFilesChange(e: Event) {
  const target = e.target as HTMLInputElement
  if (target.files && target.files.length) {
    for (let i = 0; i < target.files.length; i++) {
      newAttachmentFiles.value.push(target.files[i])
    }
  }
  target.value = ''
}

function removeNewAttachment(index: number) {
  newAttachmentFiles.value.splice(index, 1)
}

function toggleDeleteAttachment(att: ExistingAttachment) {
  att.isDeleted = !att.isDeleted
}

function startRenameAttachment(att: ExistingAttachment) {
  att.isEditingName = true
  att.newName = att.name
}

function saveRenameAttachment(att: ExistingAttachment) {
  if (att.newName && att.newName.trim()) {
    att.name = att.newName.trim()
  }
  att.isEditingName = false
}

function cancelRenameAttachment(att: ExistingAttachment) {
  att.newName = att.name
  att.isEditingName = false
}

function formatBytes(bytes: number) {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i]
}

async function loadData() {
  loading.value = true
  error.value = ''
  try {
    const [optRes, docRes] = await Promise.all([
      request<{
        data: {
          to: string[]
          priorities: string[]
          docTypes?: string[]
          profiles: DocProfile[]
        }
      }>('incoming/options'),
      request<{
        data: {
          id: number
          receiveNumber: string
          receivedAt: string | null
          letterNumber: string | null
          letterDate: string | null
          to: string
          docForm: string
          priority: string | null
          subject: string
          hasMainFile: boolean
          attachments?: { id: number; name: string; extension: string | null; file?: string; created?: string | null }[]
        }
      }>(`documents/${props.docId}`),
    ])

    if (optRes.data.to?.length) toOptions.value = optRes.data.to
    if (optRes.data.priorities?.length) priorityOptions.value = optRes.data.priorities
    if (optRes.data.docTypes?.length) docTypeOptions.value = optRes.data.docTypes
    if (optRes.data.profiles?.length) profileOptions.value = optRes.data.profiles

    const d = docRes.data
    // แยก receiveNumber ออกเป็น ประเภท (Prefix), เลข, และ ปี พ.ศ.
    if (d.receiveNumber && d.receiveNumber.includes('/')) {
      const parts = d.receiveNumber.split('/')
      const beforeSlash = parts[0].trim()
      form.value.receiveYear = parts[1].trim()

      const spaceIdx = beforeSlash.lastIndexOf(' ')
      if (spaceIdx > 0) {
        const pfx = beforeSlash.substring(0, spaceIdx).trim()
        const seq = beforeSlash.substring(spaceIdx + 1).trim()
        if (docTypeOptions.value.includes(pfx)) {
          form.value.receivePrefix = pfx
        } else if (pfx) {
          form.value.receivePrefix = '__custom__'
          form.value.customPrefix = pfx
        } else {
          form.value.receivePrefix = ''
        }
        form.value.receiveSeq = seq
      } else {
        form.value.receivePrefix = ''
        form.value.receiveSeq = beforeSlash
      }
    } else {
      form.value.receivePrefix = ''
      form.value.receiveSeq = d.receiveNumber || ''
      form.value.receiveYear = String(new Date().getFullYear() + 543)
    }

    form.value.receivedAt = d.receivedAt ? d.receivedAt.slice(0, 16) : ''
    form.value.letterNumber = d.letterNumber || ''
    form.value.letterDate = d.letterDate || ''
    form.value.to = d.to || 'ผู้อำนวยการฯ'
    form.value.profileCode = d.docForm || ''
    form.value.priority = d.priority || 'ปกติ'
    form.value.subject = d.subject || ''
    existingMainFile.value = d.hasMainFile ? 'มีไฟล์หนังสือนำเดิมในระบบ' : null

    // จัดเก็บรายการไฟล์แนบเดิม
    existingAttachments.value = (d.attachments || []).map((att) => ({
      id: att.id,
      name: att.name,
      extension: att.extension,
      file: att.file,
      created: att.created,
      isDeleted: false,
      isEditingName: false,
      newName: att.name,
    }))

    if (!form.value.profileCode) {
      onToChange()
    }
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'ไม่สามารถโหลดข้อมูลหนังสือได้'
  } finally {
    loading.value = false
  }
}

async function submitEdit() {
  if (!form.value.receiveSeq.trim() || !form.value.receiveYear.trim()) {
    await showWarning('ข้อมูลไม่ครบถ้วน', 'กรุณาระบุเลขลงรับ และ ปี พ.ศ.')
    return
  }
  if (!form.value.subject.trim()) {
    await showWarning('ข้อมูลไม่ครบถ้วน', 'กรุณาระบุเรื่องของหนังสือ')
    return
  }

  submitting.value = true
  try {
    const pfx = effectivePrefix.value
    const numPart = `${form.value.receiveSeq.trim()}/${form.value.receiveYear.trim()}`
    const fullReceiveNumber = pfx ? `${pfx} ${numPart}` : numPart

    const formData = new FormData()
    formData.append('receiveNumber', fullReceiveNumber)
    formData.append('receivedAt', form.value.receivedAt)
    formData.append('priority', form.value.priority)
    formData.append('letterNumber', form.value.letterNumber.trim())
    formData.append('letterDate', form.value.letterDate)
    formData.append('docTo', form.value.to)
    formData.append('profileCode', form.value.profileCode)
    formData.append('subject', form.value.subject.trim())

    // 1. ส่งไฟล์หนังสือนำฉบับใหม่ (ถ้ามี)
    if (newMainFile.value) {
      formData.append('file', newMainFile.value)
    }

    // 2. ส่งรายการไฟล์แนบเดิมที่ต้องการลบ
    const deletedIds = existingAttachments.value.filter((a) => a.isDeleted).map((a) => a.id)
    if (deletedIds.length > 0) {
      formData.append('deletedAttachmentIds', JSON.stringify(deletedIds))
    }

    // 3. ส่งรายการชื่อไฟล์แนบเดิมที่ต้องการเปลี่ยนชื่อ
    const renameMap: Record<number, string> = {}
    for (const a of existingAttachments.value) {
      if (!a.isDeleted && a.newName && a.newName.trim() && a.newName.trim() !== a.name) {
        renameMap[a.id] = a.newName.trim()
      }
    }
    if (Object.keys(renameMap).length > 0) {
      formData.append('updatedAttachmentNames', JSON.stringify(renameMap))
    }

    // 4. ส่งไฟล์แนบใหม่เพิ่มเติม
    for (const file of newAttachmentFiles.value) {
      formData.append('attachments', file)
    }

    const updateUrl = apiUrl(`documents/${props.docId}/update`)
    const authToken = localStorage.getItem('docz_auth_token') || ''

    const res = await fetch(updateUrl, {
      method: 'POST',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        ...(authToken ? { Authorization: `Bearer ${authToken}` } : {}),
      },
      body: formData,
    })

    const payload = await res.json().catch(() => null)
    if (!res.ok) {
      throw new Error(payload?.message || 'เกิดข้อผิดพลาดในการบันทึกการแก้ไข')
    }

    await showSuccess('สำเร็จ', 'แก้ไขข้อมูลหนังสือเรียบร้อยแล้ว')

    emit('updated', {
      id: props.docId,
      receiveNumber: `${form.value.receiveSeq.trim()}/${form.value.receiveYear.trim()}`,
      subject: form.value.subject.trim(),
    })
  } catch (err) {
    await showError('เกิดข้อผิดพลาด', err instanceof Error ? err.message : 'เกิดข้อผิดพลาดในการแก้ไขหนังสือ')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="edit-modal-backdrop" @click.self="emit('close')">
    <div class="edit-modal-card">
      <header class="edit-modal-header">
        <div class="header-titles">
          <span class="eyebrow">EDIT DOCUMENT</span>
          <h3>แก้ไขข้อมูลหนังสือ</h3>
        </div>
        <button class="btn-close" aria-label="ปิด" @click="emit('close')">✕</button>
      </header>

      <div v-if="loading" class="edit-loading">
        <span class="spinner">⏳</span> กำลังโหลดข้อมูลหนังสือ…
      </div>

      <div v-else-if="error" class="alert-error">
        {{ error }}
      </div>

      <form v-else class="edit-modal-body" @submit.prevent="submitEdit">
        <!-- แถวที่ 1: เลขลงรับ & ลงรับวันที่ -->
        <div class="form-row">
          <div class="form-group">
            <label>เลขลงรับ *</label>
            <div class="receive-number-inputs">
              <div class="input-prefix-wrap">
                <select v-model="form.receivePrefix" class="select-prefix">
                  <option v-for="t in docTypeOptions" :key="t" :value="t">{{ t }}</option>
                  <option value="">(ไม่มี)</option>
                  <option value="__custom__">กำหนดเอง...</option>
                </select>
                <span class="sub-label">ประเภท</span>
              </div>
              <div v-if="form.receivePrefix === '__custom__'" class="input-custom-prefix-wrap">
                <input
                  v-model="form.customPrefix"
                  type="text"
                  placeholder="เช่น บก."
                  class="input-custom-prefix"
                />
                <span class="sub-label">ระบุเอง</span>
              </div>
              <div class="input-seq-wrap">
                <input
                  v-model="form.receiveSeq"
                  type="text"
                  required
                  placeholder="เช่น 1 หรือ 000001"
                  class="input-seq"
                />
                <span class="sub-label">เลข</span>
              </div>
              <span class="slash-divider">/</span>
              <div class="input-year-wrap">
                <input
                  v-model="form.receiveYear"
                  type="text"
                  required
                  maxlength="4"
                  placeholder="2569"
                  class="input-year"
                />
                <span class="sub-label">ปี พ.ศ.</span>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>ลงรับวันที่ *</label>
            <input
              v-model="form.receivedAt"
              type="datetime-local"
              required
            />
          </div>
        </div>

        <!-- แถวที่ 2: ชั้นความเร็ว & ที่ ศย & หนังสือลงวันที่ -->
        <div class="form-row three-cols">
          <div class="form-group">
            <label>ชั้นความเร็ว</label>
            <select v-model="form.priority">
              <option v-for="p in priorityOptions" :key="p" :value="p">{{ p }}</option>
            </select>
          </div>
          <div class="form-group">
            <label>ที่ ศย (เลขที่หนังสือ)</label>
            <input
              v-model="form.letterNumber"
              type="text"
              placeholder="ศย"
            />
          </div>
          <div class="form-group">
            <label>หนังสือลงวันที่</label>
            <input
              v-model="form.letterDate"
              type="date"
            />
          </div>
        </div>

        <!-- แถวที่ 3: เรื่อง -->
        <div class="form-group">
          <label>เรื่อง *</label>
          <input
            v-model="form.subject"
            type="text"
            required
            placeholder="เรื่องของหนังสือ"
            maxlength="1000"
          />
        </div>

        <!-- แถวที่ 4: เรียน & โปรไฟล์ลำดับการลงนาม -->
        <div class="form-row">
          <div class="form-group">
            <label>เรียน *</label>
            <select v-model="form.to" required @change="onToChange">
              <option v-for="opt in toOptions" :key="opt" :value="opt">{{ opt }}</option>
            </select>
          </div>
          <div class="form-group">
            <label>โปรไฟล์ลำดับการลงนาม *</label>
            <select v-model="form.profileCode" required>
              <option value="" disabled>-- เลือกโปรไฟล์การลงนาม --</option>
              <option v-for="p in profileOptions" :key="p.code" :value="p.code">
                {{ p.name }} ({{ p.code }}) {{ p.steps?.length ? '— [' + p.steps.map(s => s.roleName).join(' ➔ ') + ']' : '' }}
              </option>
            </select>
          </div>
        </div>

        <!-- พรีวิวสายการลงนามตามโปรไฟล์ -->
        <div v-if="selectedProfile && selectedProfile.steps?.length" class="signing-steps-preview">
          <div class="steps-preview-header">
            <span class="steps-icon">📝</span>
            <span class="steps-title">ลำดับขั้นตอนการลงนาม:</span>
            <span class="profile-badge">{{ selectedProfile.code }}</span>
          </div>
          <div class="steps-flow">
            <div
              v-for="(step, idx) in selectedProfile.steps"
              :key="step.id"
              class="step-badge-wrap"
            >
              <span class="step-badge">
                <span class="step-num">{{ idx + 1 }}</span>
                <span class="step-name">{{ step.roleName }}</span>
              </span>
              <span v-if="idx < selectedProfile.steps.length - 1" class="step-arrow">➔</span>
            </div>
          </div>
        </div>

        <!-- แถวที่ 5: ไฟล์หนังสือนำ (Main PDF) -->
        <div class="form-group">
          <label>หนังสือนำ (ไฟล์หลัก PDF)</label>
          <div class="file-edit-box">
            <div v-if="existingMainFile && !newMainFile" class="existing-file-info">
              <span>📄 {{ existingMainFile }}</span>
              <a
                :href="apiUrl('documents/view-file', { id: props.docId })"
                target="_blank"
                class="btn-preview-link"
              >
                เปิดดูไฟล์เดิม ↗
              </a>
            </div>
            <div v-else-if="newMainFile" class="new-file-info">
              <span>📎 ไฟล์ใหม่ที่จะอัปโหลด: <strong>{{ newMainFile.name }}</strong></span>
              <button type="button" class="btn-clear-new" @click="newMainFile = null">ยกเลิกไฟล์ใหม่</button>
            </div>
            <div class="file-picker-row">
              <button
                type="button"
                class="btn-pick-file"
                @click="mainFileInput?.click()"
              >
                {{ existingMainFile ? '🔄 เลือกไฟล์ PDF ใหม่เพื่อแทนที่' : '+ อัปโหลดไฟล์ PDF หนังสือนำ' }}
              </button>
              <input
                ref="mainFileInput"
                type="file"
                accept=".pdf"
                style="display: none"
                @change="onMainFileChange"
              />
            </div>
          </div>
        </div>

        <!-- แถวที่ 6: จัดการเอกสารแนบเพิ่มเติม (Attachments) -->
        <div class="form-group">
          <div class="attachments-header-row">
            <label>เอกสารแนบเพิ่มเติม ({{ existingAttachments.filter(a => !a.isDeleted).length + newAttachmentFiles.length }} รายการ)</label>
            <button
              type="button"
              class="btn-add-attachment"
              @click="attachmentFileInput?.click()"
            >
              <span>➕</span> เพิ่มไฟล์แนบ
            </button>
            <input
              ref="attachmentFileInput"
              type="file"
              multiple
              accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp"
              style="display: none"
              @change="onAttachmentFilesChange"
            />
          </div>

          <div class="attachments-list-container">
            <div v-if="!existingAttachments.length && !newAttachmentFiles.length" class="empty-attachments">
              <span>ยังไม่มีเอกสารแนบในระบบ</span>
            </div>

            <!-- รายการไฟล์แนบเดิม -->
            <div
              v-for="att in existingAttachments"
              :key="att.id"
              class="attachment-row"
              :class="{ 'is-deleted': att.isDeleted }"
            >
              <div class="att-badge" :class="`badge-${att.extension?.toLowerCase() || 'file'}`">
                {{ (att.extension || 'FILE').toUpperCase() }}
              </div>

              <div class="att-info">
                <template v-if="att.isEditingName && !att.isDeleted">
                  <div class="rename-box">
                    <input
                      v-model="att.newName"
                      class="att-rename-input"
                      placeholder="ชื่อไฟล์แนบ"
                      @keyup.enter="saveRenameAttachment(att)"
                      @keyup.esc="cancelRenameAttachment(att)"
                    />
                    <div class="rename-actions">
                      <button type="button" class="btn-att-save" @click="saveRenameAttachment(att)">ตกลง</button>
                      <button type="button" class="btn-att-cancel" @click="cancelRenameAttachment(att)">ยกเลิก</button>
                    </div>
                  </div>
                </template>
                <template v-else>
                  <span class="att-name" :title="att.name">{{ att.name }}</span>
                  <button
                    v-if="!att.isDeleted"
                    type="button"
                    class="btn-inline-rename"
                    title="เปลี่ยนชื่อไฟล์แนบ"
                    @click="startRenameAttachment(att)"
                  >
                    ✏️
                  </button>
                </template>
              </div>

              <div class="att-actions">
                <template v-if="!att.isDeleted">
                  <a
                    :href="apiUrl('documents/download', { id: props.docId, attachmentId: att.id })"
                    target="_blank"
                    class="btn-att-download"
                    title="ดาวน์โหลดไฟล์แนบ"
                  >
                    ↓ โหลด
                  </a>
                  <button
                    type="button"
                    class="btn-att-delete"
                    title="ลบไฟล์แนบนี้ออกจากระบบ"
                    @click="toggleDeleteAttachment(att)"
                  >
                    🗑️ ลบ
                  </button>
                </template>
                <template v-else>
                  <span class="deleted-tag">รอลบเมื่อบันทึก</span>
                  <button
                    type="button"
                    class="btn-att-restore"
                    title="ยกเลิกการลบ"
                    @click="toggleDeleteAttachment(att)"
                  >
                    ↩ กู้คืน
                  </button>
                </template>
              </div>
            </div>

            <!-- รายการไฟล์แนบใหม่ที่จะอัปโหลด -->
            <div
              v-for="(nFile, idx) in newAttachmentFiles"
              :key="idx"
              class="attachment-row is-new"
            >
              <div class="att-badge badge-new">ใหม่</div>
              <div class="att-info">
                <span class="att-name">📎 {{ nFile.name }}</span>
                <small class="att-size">{{ formatBytes(nFile.size) }}</small>
              </div>
              <div class="att-actions">
                <button
                  type="button"
                  class="btn-att-remove-new"
                  title="ยกเลิกไฟล์นี้"
                  @click="removeNewAttachment(idx)"
                >
                  ✕ ยกเลิก
                </button>
              </div>
            </div>
          </div>
        </div>

        <footer class="edit-modal-footer">
          <button type="button" class="btn-secondary" :disabled="submitting" @click="emit('close')">
            ยกเลิก
          </button>
          <button type="submit" class="btn-save" :disabled="submitting">
            {{ submitting ? 'กำลังบันทึก…' : '💾 บันทึกการแก้ไข' }}
          </button>
        </footer>
      </form>
    </div>
  </div>
</template>

<style scoped>
.edit-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 220;
  padding: 1.25rem;
}

.edit-modal-card {
  background: #ffffff;
  border-radius: 8px;
  width: 100%;
  max-width: 820px;
  max-height: 92vh;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
  overflow-y: auto;
  border-top: 4px solid #f59e0b;
}

.edit-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
}

.header-titles .eyebrow {
  display: block;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 1.5px;
  color: #d97706;
  margin-bottom: 2px;
}

.header-titles h3 {
  margin: 0;
  font-size: 1.15rem;
  color: #1e293b;
  font-weight: 600;
}

.btn-close {
  background: transparent;
  border: none;
  font-size: 1.25rem;
  color: #64748b;
  cursor: pointer;
  padding: 4px 8px;
}

.btn-close:hover {
  color: #0f172a;
}

.edit-loading {
  padding: 3rem;
  text-align: center;
  font-size: 1rem;
  color: #64748b;
}

.alert-error {
  background: #fee2e2;
  color: #b91c1c;
  padding: 0.75rem 1rem;
  margin: 1.25rem;
  border-radius: 6px;
  border: 1px solid #fecaca;
  font-size: 0.9rem;
}

.edit-modal-body {
  padding: 1.25rem 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1.15rem;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.25rem;
}

.form-row.three-cols {
  grid-template-columns: 1fr 1fr 1fr;
}

@media (max-width: 768px) {
  .form-row,
  .form-row.three-cols {
    grid-template-columns: 1fr;
    gap: 0.85rem;
  }
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.form-group label {
  font-size: 0.88rem;
  font-weight: 600;
  color: #334155;
}

.receive-number-inputs {
  display: flex;
  align-items: flex-start;
  gap: 8px;
}

.input-prefix-wrap {
  width: 110px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.select-prefix {
  font-weight: 600;
  color: #15803d;
  background-color: #f0fdf4;
  border-color: #86efac !important;
}

.input-custom-prefix-wrap {
  width: 90px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.input-custom-prefix {
  font-weight: 600;
  color: #15803d;
}

.input-seq-wrap {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.input-year-wrap {
  width: 105px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.slash-divider {
  font-size: 1.4rem;
  font-weight: 700;
  color: #777;
  line-height: 40px;
}

.sub-label {
  font-size: 0.78rem;
  color: #888;
  padding-left: 2px;
}

.form-group input,
.form-group select {
  padding: 0.55rem 0.75rem;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 0.92rem;
  outline: none;
  background: #ffffff;
}

.form-group input:focus,
.form-group select:focus {
  border-color: #d97706;
  box-shadow: 0 0 0 2px rgba(217, 119, 6, 0.2);
}

/* Signing steps preview */
.signing-steps-preview {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 10px 14px;
  margin-top: -4px;
}

.steps-preview-header {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.82rem;
  color: #475569;
  font-weight: 600;
  margin-bottom: 8px;
}

.profile-badge {
  background: #e0e7ff;
  color: #3730a3;
  font-size: 0.78rem;
  padding: 1px 6px;
  border-radius: 3px;
  font-weight: 700;
  margin-left: auto;
}

.steps-flow {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
}

.step-badge-wrap {
  display: flex;
  align-items: center;
  gap: 6px;
}

.step-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 20px;
  padding: 3px 10px;
  font-size: 0.8rem;
  color: #1e293b;
}

.step-num {
  background: #d97706;
  color: #ffffff;
  width: 17px;
  height: 17px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  font-size: 0.78rem;
  font-weight: 700;
}

.step-name {
  font-weight: 500;
}

.step-arrow {
  color: #94a3b8;
  font-size: 0.8rem;
}

/* File edit box */
.file-edit-box {
  background: #f8fafc;
  border: 1px dashed #cbd5e1;
  border-radius: 6px;
  padding: 0.85rem 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}

.existing-file-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.85rem;
  color: #334155;
}

.btn-preview-link {
  color: #0284c7;
  font-size: 0.82rem;
  text-decoration: none;
}

.btn-preview-link:hover {
  text-decoration: underline;
}

.new-file-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.85rem;
  color: #166534;
  background: #dcfce7;
  padding: 6px 10px;
  border-radius: 4px;
}

.btn-clear-new {
  background: transparent;
  border: none;
  color: #b91c1c;
  font-size: 0.8rem;
  cursor: pointer;
}

.btn-pick-file {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  color: #334155;
  padding: 6px 14px;
  border-radius: 6px;
  font-size: 0.85rem;
  cursor: pointer;
  align-self: flex-start;
}

.btn-pick-file:hover {
  background: #f1f5f9;
}

.edit-modal-footer {
  padding-top: 1rem;
  border-top: 1px solid #e2e8f0;
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
}

.btn-secondary {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  color: #475569;
  padding: 0.55rem 1.25rem;
  border-radius: 6px;
  font-size: 0.9rem;
  cursor: pointer;
}

.btn-secondary:hover {
  background: #f1f5f9;
}

.btn-save {
  background: #f59e0b;
  border: 1px solid #d97706;
  color: #ffffff;
  padding: 0.55rem 1.75rem;
  border-radius: 6px;
  font-size: 0.92rem;
  font-weight: 600;
  cursor: pointer;
  box-shadow: 0 2px 4px rgba(245, 158, 11, 0.25);
  transition: all 0.15s;
}

.btn-save:hover:not(:disabled) {
  background: #d97706;
  box-shadow: 0 3px 6px rgba(245, 158, 11, 0.35);
}

.btn-save:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

/* Attachments section styling */
.attachments-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.btn-add-attachment {
  background: #f1f5f9;
  border: 1px solid #cbd5e1;
  color: #1e293b;
  padding: 4px 12px;
  border-radius: 5px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  transition: all 0.15s ease;
}

.btn-add-attachment:hover {
  background: #e2e8f0;
  border-color: #94a3b8;
}

.attachments-list-container {
  display: flex;
  flex-direction: column;
  gap: 6px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 8px;
  max-height: 240px;
  overflow-y: auto;
}

.empty-attachments {
  text-align: center;
  padding: 1rem;
  color: #94a3b8;
  font-size: 0.82rem;
}

.attachment-row {
  display: flex;
  align-items: center;
  gap: 10px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 6px 10px;
  font-size: 0.82rem;
  transition: all 0.15s;
}

.attachment-row.is-deleted {
  background: #fef2f2;
  border-color: #fecaca;
  opacity: 0.75;
}

.attachment-row.is-deleted .att-name {
  text-decoration: line-through;
  color: #991b1b;
}

.attachment-row.is-new {
  background: #f0fdf4;
  border-color: #bbf7d0;
}

.att-badge {
  font-size: 0.78rem;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
  text-transform: uppercase;
  flex-shrink: 0;
  background: #64748b;
  color: #ffffff;
}

.badge-pdf { background: #ef4444; }
.badge-doc, .badge-docx { background: #2563eb; }
.badge-xls, .badge-xlsx { background: #16a34a; }
.badge-jpg, .badge-jpeg, .badge-png { background: #8b5cf6; }
.badge-new { background: #059669; }

.att-info {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 6px;
}

.att-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: #1e293b;
  font-weight: 500;
}

.att-size {
  color: #64748b;
  font-size: 0.78rem;
  flex-shrink: 0;
}

.btn-inline-rename {
  background: transparent;
  border: none;
  font-size: 0.7857rem;
  cursor: pointer;
  padding: 2px 4px;
  border-radius: 3px;
  opacity: 0.6;
}

.btn-inline-rename:hover {
  opacity: 1;
  background: #f1f5f9;
}

.rename-box {
  display: flex;
  align-items: center;
  gap: 6px;
  flex: 1;
}

.att-rename-input {
  height: 28px !important;
  padding: 0 8px !important;
  font-size: 0.8571rem !important;
  flex: 1;
  border: 1px solid #3b82f6 !important;
}

.rename-actions {
  display: flex;
  gap: 4px;
  flex-shrink: 0;
}

.btn-att-save {
  background: #10b981;
  color: #fff;
  border: none;
  border-radius: 4px;
  padding: 2px 8px;
  font-size: 0.7857rem;
  cursor: pointer;
}

.btn-att-cancel {
  background: #94a3b8;
  color: #fff;
  border: none;
  border-radius: 4px;
  padding: 2px 8px;
  font-size: 0.7857rem;
  cursor: pointer;
}

.att-actions {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}

.btn-att-download {
  color: #0284c7;
  text-decoration: none;
  font-size: 0.7857rem;
  padding: 3px 6px;
  border-radius: 4px;
  background: #f0f9ff;
  border: 1px solid #bae6fd;
}

.btn-att-download:hover {
  background: #e0f2fe;
}

.btn-att-delete {
  background: #fee2e2;
  border: 1px solid #fca5a5;
  color: #dc2626;
  font-size: 0.7857rem;
  padding: 2px 6px;
  border-radius: 4px;
  cursor: pointer;
}

.btn-att-delete:hover {
  background: #fecaca;
}

.deleted-tag {
  font-size: 0.7857rem;
  color: #b91c1c;
  font-style: italic;
}

.btn-att-restore {
  background: #f1f5f9;
  border: 1px solid #cbd5e1;
  color: #334155;
  font-size: 0.7857rem;
  padding: 2px 8px;
  border-radius: 4px;
  cursor: pointer;
}

.btn-att-restore:hover {
  background: #e2e8f0;
}

.btn-att-remove-new {
  background: transparent;
  border: none;
  color: #dc2626;
  font-size: 0.7857rem;
  cursor: pointer;
  padding: 2px 6px;
}

.btn-att-remove-new:hover {
  text-decoration: underline;
}
</style>
