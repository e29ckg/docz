<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { apiUrl, request } from '../api'
import { showAlert, showSuccess, showError, showWarning } from '../utils/alert'
import { validateClientPdfA4Portrait, ClientPdfValidationResult } from '../utils/pdfValidator'

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

const props = withDefaults(
  defineProps<{
    initialDaily?: boolean
  }>(),
  {
    initialDaily: false,
  }
)

const emit = defineEmits<{
  close: []
  created: [doc: { id: number; receiveNumber: string; subject: string }]
}>()

// โหมดเอกสารวันทำการ
const isDaily = ref(Boolean(props.initialDaily))
const suggestedDailyNumber = ref('')

// ตัวเลือกที่ดึงจากระบบ
const toOptions = ref<string[]>(['ผู้อำนวยการฯ', 'ผู้พิพากษาหัวหน้าศาลฯ'])
const priorityOptions = ref<string[]>(['ปกติ', 'ด่วน', 'ด่วนที่สุด'])
const docTypeOptions = ref<string[]>(['E', 'ศาลากลาง', '(ค)', 'วันทำการ'])
const profileOptions = ref<DocProfile[]>([])
const suggestedSeq = ref('')
const suggestedYear = ref('')

// ฟอร์มข้อมูล
const form = ref({
  receivePrefix: isDaily.value ? 'วันทำการ' : 'E',
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

// โปรไฟล์ที่เลือกอยู่ขณะนี้
const selectedProfile = computed(() => {
  return profileOptions.value.find((p) => p.code === form.value.profileCode)
})

// ไฟล์หนังสือนำ (Main PDF)
const mainFileInput = ref<HTMLInputElement | null>(null)
const mainFile = ref<File | null>(null)
const validatingMainFile = ref(false)
const mainFileValidation = ref<ClientPdfValidationResult | null>(null)

// ไฟล์แนบเพิ่มเติม (Attachments)
const attachmentFileInput = ref<HTMLInputElement | null>(null)
const attachmentFiles = ref<File[]>([])

const loading = ref(false)
const submitting = ref(false)
const submitProgress = ref('')
const error = ref('')

function getCurrentDateTimeBangkok() {
  const now = new Date()
  const pad = (n: number) => String(n).padStart(2, '0')
  const yyyy = now.getFullYear()
  const mm = pad(now.getMonth() + 1)
  const dd = pad(now.getDate())
  const hh = pad(now.getHours())
  const min = pad(now.getMinutes())
  return `${yyyy}-${mm}-${dd}T${hh}:${min}`
}

function getCurrentDateBangkok() {
  const now = new Date()
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`
}

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

function resetToSuggestedSeq() {
  if (isDaily.value) {
    form.value.receiveSeq = suggestedDailyNumber.value
    form.value.receiveYear = ''
  } else {
    form.value.receiveSeq = suggestedSeq.value
    form.value.receiveYear = suggestedYear.value
  }
}

function switchMode(daily: boolean) {
  isDaily.value = daily
  if (daily) {
    form.value.receivePrefix = 'วันทำการ'
    form.value.receiveSeq = suggestedDailyNumber.value || form.value.receiveSeq
    form.value.receiveYear = ''
    if (mainFile.value) {
      form.value.subject = mainFile.value.name.replace(/\.[^/.]+$/, '')
    }
  } else {
    form.value.receivePrefix = 'E'
    form.value.receiveSeq = suggestedSeq.value
    form.value.receiveYear = suggestedYear.value
  }
}

async function loadOptions() {
  loading.value = true
  try {
    const res = await request<{
      data: {
        to: string[]
        priorities: string[]
        docTypes?: string[]
        currentYear: string
        suggestedSeq: string
        suggestedDailyNumber?: string
        profiles: DocProfile[]
        serverTime: string
      }
    }>('incoming/options')

    if (res.data.to?.length) toOptions.value = res.data.to
    if (res.data.priorities?.length) priorityOptions.value = res.data.priorities
    if (res.data.docTypes?.length) docTypeOptions.value = res.data.docTypes
    if (res.data.suggestedSeq) suggestedSeq.value = res.data.suggestedSeq
    if (res.data.currentYear) suggestedYear.value = res.data.currentYear
    if (res.data.suggestedDailyNumber) suggestedDailyNumber.value = res.data.suggestedDailyNumber

    if (isDaily.value) {
      form.value.receivePrefix = 'วันทำการ'
      if (!form.value.receiveSeq) form.value.receiveSeq = suggestedDailyNumber.value
      form.value.receiveYear = ''
    } else {
      if (!form.value.receiveSeq) form.value.receiveSeq = suggestedSeq.value
      if (!form.value.receiveYear) form.value.receiveYear = suggestedYear.value
    }
    if (!form.value.priority) form.value.priority = 'ปกติ'

    if (res.data.profiles?.length) {
      profileOptions.value = res.data.profiles
      onToChange()
      if (!form.value.profileCode && profileOptions.value[0]) {
        form.value.profileCode = profileOptions.value[0].code
      }
    }
  } catch (e) {
    console.warn('Cannot load incoming options, using defaults.', e)
  } finally {
    loading.value = false
  }
}

async function onMainFileChange(e: Event) {
  const target = e.target as HTMLInputElement
  if (target.files && target.files[0]) {
    const f = target.files[0]
    if (!f.name.toLowerCase().endsWith('.pdf')) {
      await showWarning('รูปแบบไฟล์ไม่ถูกต้อง', 'หนังสือนำต้องเป็นไฟล์ PDF เท่านั้น')
      target.value = ''
      mainFile.value = null
      mainFileValidation.value = null
      return
    }

    validatingMainFile.value = true
    try {
      const check = await validateClientPdfA4Portrait(f, { allowLetter: true })
      if (!check.valid) {
        await showError('ขนาดหรือแนวเอกสารไม่ถูกต้อง', check.message)
        target.value = ''
        mainFile.value = null
        mainFileValidation.value = null
        return
      }

      mainFile.value = f
      mainFileValidation.value = check

      // สำหรับเอกสารวันทำการเท่านั้น: ดึงเรื่องมาจากชื่อไฟล์หลัก (ตัดนามสกุล .pdf ออก)
      if (isDaily.value) {
        const fileNameWithoutExt = f.name.replace(/\.[^/.]+$/, '')
        form.value.subject = fileNameWithoutExt
      }
    } catch (err: any) {
      console.error('[PDF Validation Error]', err)
      await showError('ตรวจสอบไฟล์ไม่สำเร็จ', 'ไม่สามารถอ่านโครงสร้างไฟล์ PDF ได้ กรุณาลองใหม่อีกครั้ง')
      target.value = ''
      mainFile.value = null
      mainFileValidation.value = null
    } finally {
      validatingMainFile.value = false
    }
  }
}

function onAttachmentChange(e: Event) {
  const target = e.target as HTMLInputElement
  if (target.files) {
    for (let i = 0; i < target.files.length; i++) {
      if (attachmentFiles.value.length >= 20) {
        showWarning('จำนวนไฟล์เกินกำหนด', 'แนบไฟล์เพิ่มเติมได้สูงสุด 20 ไฟล์')
        break
      }
      attachmentFiles.value.push(target.files[i])
    }
    target.value = ''
  }
}

function removeAttachment(index: number) {
  attachmentFiles.value.splice(index, 1)
}

async function uploadSingleFile(file: File, kind: 'MAIN' | 'ATTACHMENT') {
  const formData = new FormData()
  formData.append('file', file)
  formData.append('kind', kind)

  const uploadUrl = apiUrl('incoming/upload')
  const authToken = localStorage.getItem('docz_auth_token') || ''

  const res = await fetch(uploadUrl, {
    method: 'POST',
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      ...(authToken ? { Authorization: `Bearer ${authToken}` } : {}),
    },
    body: formData,
  })

  const payload = await res.json().catch(() => null)
  if (!res.ok || !payload?.data) {
    throw new Error(payload?.message || `อัปโหลดไฟล์ ${file.name} ไม่สำเร็จ`)
  }
  return payload.data as { token: string; storageKey: string; name: string; extension: string }
}

async function submitForm() {
  if (isDaily.value) {
    if (!form.value.receiveSeq.trim()) {
      await showWarning('ข้อมูลไม่ครบถ้วน', 'กรุณากรอกเลขหนังสือวันทำการ เช่น 20261102-001')
      return
    }
  } else {
    if (!form.value.receiveSeq.trim() || !form.value.receiveYear.trim()) {
      await showWarning('ข้อมูลไม่ครบถ้วน', 'กรุณากรอกเลขลงรับ และ ปี พ.ศ.')
      return
    }
  }
  if (!form.value.profileCode) {
    await showWarning('ข้อมูลไม่ครบถ้วน', 'กรุณาเลือกโปรไฟล์ลำดับการลงนาม')
    return
  }
  if (!form.value.subject.trim()) {
    await showWarning('ข้อมูลไม่ครบถ้วน', 'กรุณากรอกเรื่องของหนังสือ')
    return
  }
  if (!mainFile.value) {
    await showWarning('ข้อมูลไม่ครบถ้วน', 'กรุณาเลือกไฟล์หนังสือนำ (ไฟล์ PDF ขนาด A4 หรือ Letter แนวตั้ง)')
    return
  }
  if (!mainFileValidation.value?.valid) {
    await showWarning('เอกสารไม่ตรงตามข้อกำหนด', 'ไฟล์หลักหนังสือนำต้องเป็นขนาด A4 หรือ Letter แนวตั้งเท่านั้น')
    return
  }

  submitting.value = true
  error.value = ''
  try {
    // 1. อัปโหลดหนังสือนำ (Main File)
    submitProgress.value = 'กำลังอัปโหลดหนังสือนำ...'
    const uploadedMain = await uploadSingleFile(mainFile.value, 'MAIN')

    // 2. อัปโหลดไฟล์แนบเพิ่มเติม (ถ้ามี)
    const uploadedAttachments: { token: string; storageKey: string; name: string; extension: string }[] = []
    if (attachmentFiles.value.length > 0) {
      for (let i = 0; i < attachmentFiles.value.length; i++) {
        submitProgress.value = `กำลังอัปโหลดเอกสารแนบ (${i + 1}/${attachmentFiles.value.length})...`
        const attRes = await uploadSingleFile(attachmentFiles.value[i], 'ATTACHMENT')
        uploadedAttachments.push(attRes)
      }
    }

    // 3. ส่งคำขอยืนยันลงรับหนังสือ (Register)
    submitProgress.value = 'กำลังออกเลขรับหนังสือและบันทึกข้อมูล...'
    const registerPayload = {
      isDaily: isDaily.value,
      receivePrefix: effectivePrefix.value,
      receiveSeq: form.value.receiveSeq.trim(),
      receiveYear: isDaily.value ? '' : form.value.receiveYear.trim(),
      profileCode: form.value.profileCode,
      subject: form.value.subject.trim(),
      to: form.value.to,
      priority: form.value.priority,
      letterNumber: form.value.letterNumber.trim(),
      letterDate: form.value.letterDate || null,
      receivedAt: form.value.receivedAt.replace('T', ' ') + ':00',
      mainToken: uploadedMain.token,
      attachmentTokens: uploadedAttachments,
    }

    const regResult = await request<{ data: { id: number; receiveNumber: string; subject: string }; message: string }>(
      'incoming/register',
      {},
      registerPayload
    )

    emit('created', regResult.data)
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'เกิดข้อผิดพลาดในการลงรับหนังสือ'
  } finally {
    submitting.value = false
    submitProgress.value = ''
  }
}

onMounted(() => {
  const currentYear = new Date().getFullYear() + 543
  form.value.receivedAt = getCurrentDateTimeBangkok()
  form.value.letterDate = getCurrentDateBangkok()
  suggestedYear.value = String(currentYear)
  if (!isDaily.value) {
    form.value.receiveYear = String(currentYear)
  }
  loadOptions()
})
</script>

<template>
  <div class="incoming-modal-backdrop" @click.self="emit('close')">
    <div class="incoming-modal-card">
      <header class="incoming-modal-header">
        <div class="header-title-wrap">
          <h3 class="incoming-modal-title">
            {{ isDaily ? '📅 เพิ่มเอกสารวันทำการเข้าระบบ' : '📥 เพิ่มเอกสารเข้าระบบ' }}
          </h3>
          <div class="header-mode-switch">
            <button
              type="button"
              class="btn-switch-mode"
              :class="{ active: !isDaily }"
              @click="switchMode(false)"
            >
              📥 เอกสารทั่วไป
            </button>
            <button
              type="button"
              class="btn-switch-mode"
              :class="{ active: isDaily }"
              @click="switchMode(true)"
            >
              📅 เอกสารวันทำการ
            </button>
          </div>
        </div>
        <button class="incoming-modal-close" aria-label="ปิด" @click="emit('close')">×</button>
      </header>

      <form class="incoming-modal-body" @submit.prevent="submitForm">
        <div v-if="error" class="alert-error" role="alert">
          {{ error }}
        </div>

        <!-- แถวที่ 1: เลขลงรับ / เลขเอกสารวันทำการ & ลงรับวันที่ -->
        <div class="form-row">
          <div class="form-group">
            <div class="label-with-action">
              <label>{{ isDaily ? 'เลขเอกสารวันทำการ *' : 'เลขลงรับ *' }}</label>
              <button
                v-if="isDaily ? suggestedDailyNumber : suggestedSeq"
                type="button"
                class="btn-suggested"
                title="คลิกเพื่อใช้เลขถัดไปที่ระบบแนะนำ"
                @click="resetToSuggestedSeq"
              >
                <span>⚡ แนะนำ:</span>
                <strong>{{ isDaily ? suggestedDailyNumber : `${suggestedSeq}/${suggestedYear}` }}</strong>
              </button>
            </div>
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
              <div class="input-seq-wrap" :class="{ 'input-seq-daily': isDaily }">
                <input
                  v-model="form.receiveSeq"
                  type="text"
                  required
                  :placeholder="isDaily ? 'เช่น 20261102-001' : 'เช่น 1 หรือ 000001'"
                  class="input-seq"
                />
                <span class="sub-label">{{ isDaily ? 'เลขวันที่ (YYYYMMDD-ลำดับ)' : 'เลข' }}</span>
              </div>
              <template v-if="!isDaily">
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
              </template>
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
            <label>ที่ ศย</label>
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
          <div class="label-with-action">
            <label>เรื่อง *</label>
            <span v-if="isDaily && mainFile" class="subject-auto-badge">⚡ ดึงจากชื่อไฟล์หลักอัตโนมัติ</span>
          </div>
          <input
            v-model="form.subject"
            type="text"
            required
            :placeholder="isDaily ? 'เรื่อง (ดึงจากชื่อไฟล์หลักอัตโนมัติ หรือพิมพ์แก้ไขได้)' : 'เรื่อง'"
            maxlength="1000"
          />
        </div>

        <!-- แถวที่ 4: เรียน & โปรไฟล์ลำดับการลงนาม -->
        <div class="form-row">
          <div class="form-group">
            <label>เรียน *</label>
            <select v-model="form.to" required @change="onToChange">
              <option value="" disabled>เลือก..</option>
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

        <!-- กล่องแสดงตัวอย่างขั้นตอนลำดับการลงนามของโปรไฟล์ที่เลือก -->
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

        <!-- แถวที่ 5: File (หนังสือนำ PDF) -->
        <div class="form-group">
          <label>
            หนังสือนำ (ไฟล์หลัก PDF) *
            <span v-if="isDaily" class="label-hint-daily">✨ สำหรับวันทำการ: รองรับขนาด A4 / Letter แนวตั้ง (ชื่อไฟล์จะถูกตั้งเป็น "เรื่อง" อัตโนมัติ)</span>
          </label>
          <div class="file-picker-wrapper">
            <input
              ref="mainFileInput"
              type="file"
              accept=".pdf"
              :disabled="validatingMainFile"
              @change="onMainFileChange"
            />
          </div>

          <!-- สถานะการตรวจสอบไฟล์ PDF -->
          <div v-if="validatingMainFile" class="file-validating-msg">
            <span class="spinner-small"></span> กำลังตรวจสอบขนาดหน้าและแนวเอกสาร PDF...
          </div>

          <div v-else-if="mainFileValidation?.valid" class="file-success-badge">
            <span class="badge-icon">✅</span>
            <div class="badge-text">
              <strong>ผ่านการตรวจสอบ: เอกสารขนาด {{ mainFileValidation.detectedFormat }} แนวตั้ง</strong>
              <small>
                ขนาด {{ mainFileValidation.pages[0]?.widthMm }} × {{ mainFileValidation.pages[0]?.heightMm }} มม.
                (จำนวน {{ mainFileValidation.pageCount }} หน้า)
              </small>
            </div>
          </div>

          <small class="muted">
            ⚠️ <strong>ข้อกำหนด:</strong> ต้องเป็นไฟล์ PDF ขนาด <strong>A4 หรือ Letter แนวตั้ง</strong> เท่านั้น (210 × 297 มม. หรือ 8.5 × 11 นิ้ว) ขนาดไม่เกิน 20 MB
          </small>
        </div>

        <!-- เอกสารแนบเพิ่มเติม (Attachments) -->
        <div class="attachments-area">
          <div class="attachments-header">
            <label>เอกสารแนบเพิ่มเติม (ถ้ามี)</label>
            <button
              type="button"
              class="btn-add-file"
              @click="attachmentFileInput?.click()"
            >
              + แนบไฟล์เพิ่ม
            </button>
            <input
              ref="attachmentFileInput"
              type="file"
              multiple
              style="display: none"
              accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp"
              @change="onAttachmentChange"
            />
          </div>

          <div v-if="attachmentFiles.length" class="attachments-list">
            <div v-for="(file, idx) in attachmentFiles" :key="idx" class="attachment-item">
              <span class="file-icon">📎</span>
              <span class="file-name">{{ file.name }}</span>
              <span class="file-size muted">({{ (file.size / 1024).toFixed(0) }} KB)</span>
              <button type="button" class="btn-remove-att" @click="removeAttachment(idx)">×</button>
            </div>
          </div>
          <small v-else class="muted">สามารถแนบไฟล์ PDF, Word, Excel หรือไฟล์ภาพเพิ่มเติมได้</small>
        </div>

        <footer class="incoming-modal-footer">
          <div v-if="submitting" class="submitting-status">
            <span class="spinner" aria-hidden="true">⏳</span> {{ submitProgress }}
          </div>
          <div class="footer-buttons">
            <button type="button" class="btn-secondary" :disabled="submitting" @click="emit('close')">ยกเลิก</button>
            <button type="submit" class="btn-submit-cyan" :disabled="submitting">
              {{ submitting ? 'กำลังบันทึก…' : (isDaily ? '📅 บันทึกเอกสารวันทำการ' : '💾 บันทึกนำเข้าหนังสือ') }}
            </button>
          </div>
        </footer>
      </form>
    </div>
  </div>
</template>

<style scoped>
.incoming-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 200;
  padding: 1rem;
}

.incoming-modal-card {
  background: #ffffff;
  border-radius: 6px;
  width: 100%;
  max-width: 820px;
  max-height: 90vh;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
  overflow-y: auto;
  border-top: 4px solid #00a65a; /* แถบสีเขียวสารบรรณ */
}

.incoming-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.85rem 1.25rem;
  border-bottom: 1px solid #e5e7eb;
}

.header-title-wrap {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.header-mode-switch {
  display: inline-flex;
  background: #f1f5f9;
  border-radius: 20px;
  padding: 2px;
  gap: 2px;
}

.btn-switch-mode {
  background: transparent;
  border: none;
  font-size: 0.78rem;
  font-weight: 600;
  padding: 4px 10px;
  border-radius: 16px;
  color: #64748b;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-switch-mode:hover {
  color: #0f172a;
}

.btn-switch-mode.active {
  background: #ffffff;
  color: #0f172a;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.incoming-modal-title {
  margin: 0;
  font-size: 1.15rem;
  color: #333333;
  font-weight: 600;
}

.incoming-modal-close {
  background: transparent;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  color: #999999;
  line-height: 1;
}

.incoming-modal-close:hover {
  color: #333333;
}

.incoming-modal-body {
  padding: 1.25rem 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1.1rem;
}

.alert-error {
  background: #fee2e2;
  color: #b91c1c;
  padding: 0.75rem 1rem;
  border-radius: 4px;
  border: 1px solid #fecaca;
  font-size: 0.9rem;
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
  font-size: 0.9rem;
  font-weight: 600;
  color: #333333;
}

.label-with-action {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.btn-suggested {
  background: #eef7f2;
  border: 1px solid #b7dfc8;
  color: #17653f;
  font-size: 0.78rem;
  padding: 2px 8px;
  border-radius: 4px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: all 0.15s ease;
}

.btn-suggested:hover {
  background: #dcf1e5;
  border-color: #92caa9;
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

.input-seq-daily input {
  font-weight: 700;
  color: #0369a1;
  background-color: #f0f9ff;
  border-color: #7dd3fc !important;
}

.subject-auto-badge {
  font-size: 0.78rem;
  background: #e0f2fe;
  color: #0369a1;
  font-weight: 700;
  padding: 1px 7px;
  border-radius: 10px;
}

.label-hint-daily {
  font-size: 0.78rem;
  color: #0284c7;
  font-weight: 500;
  margin-left: 8px;
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
  border: 1px solid #cccccc;
  border-radius: 4px;
  font-size: 0.92rem;
  outline: none;
}

.form-group input:focus,
.form-group select:focus {
  border-color: #3c8dbc;
  box-shadow: 0 0 5px rgba(60, 141, 188, 0.3);
}

/* ลำดับขั้นตอนการลงนาม Preview */
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
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}

.step-num {
  background: #0284c7;
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

.file-picker-wrapper input[type="file"] {
  font-size: 0.88rem;
  padding: 0.35rem 0;
}

.file-validating-msg {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.84rem;
  color: #1d4ed8;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  padding: 0.45rem 0.75rem;
  border-radius: 4px;
  margin-top: 0.35rem;
  margin-bottom: 0.35rem;
}

.spinner-small {
  display: inline-block;
  width: 14px;
  height: 14px;
  border: 2px solid #bfdbfe;
  border-top-color: #2563eb;
  border-radius: 50%;
  animation: spin-kf 0.8s linear infinite;
}

@keyframes spin-kf {
  to { transform: rotate(360deg); }
}

.file-success-badge {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: #166534;
  padding: 0.5rem 0.75rem;
  border-radius: 4px;
  margin-top: 0.35rem;
  margin-bottom: 0.35rem;
}

.file-success-badge .badge-icon {
  font-size: 1.15rem;
}

.file-success-badge .badge-text {
  display: flex;
  flex-direction: column;
  line-height: 1.35;
}

.file-success-badge .badge-text strong {
  font-size: 0.86rem;
  color: #15803d;
}

.file-success-badge .badge-text small {
  color: #166534;
  font-size: 0.78rem;
}

.attachments-area {
  background: #fdfdfd;
  border: 1px dashed #d1d5db;
  border-radius: 4px;
  padding: 1rem;
}

.attachments-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.attachments-header label {
  font-weight: 600;
  font-size: 0.88rem;
}

.btn-add-file {
  background: #f3f4f6;
  border: 1px solid #d1d5db;
  color: #374151;
  padding: 0.35rem 0.75rem;
  border-radius: 4px;
  font-size: 0.82rem;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-add-file:hover {
  background: #e5e7eb;
}

.attachments-list {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  margin-top: 0.5rem;
}

.attachment-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.4rem 0.6rem;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 4px;
  font-size: 0.85rem;
}

.attachment-item .file-name {
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.btn-remove-att {
  background: transparent;
  border: none;
  color: #ef4444;
  font-size: 1.1rem;
  cursor: pointer;
  padding: 0 4px;
  line-height: 1;
}

.btn-remove-att:hover {
  color: #b91c1c;
}

.incoming-modal-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 1rem;
  border-top: 1px solid #e5e7eb;
  margin-top: 0.5rem;
}

.submitting-status {
  font-size: 0.88rem;
  color: #0284c7;
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.footer-buttons {
  margin-left: auto;
  display: flex;
  gap: 0.75rem;
}

.btn-secondary {
  background: #ffffff;
  border: 1px solid #cccccc;
  color: #444444;
  padding: 0.55rem 1.25rem;
  border-radius: 4px;
  font-size: 0.9rem;
  cursor: pointer;
}

.btn-secondary:hover:not(:disabled) {
  background: #f7f7f7;
}

/* ปุ่มบันทึกสีฟ้าสด (Cyan / #00c0ef) ตามแบบระบบเดิม */
.btn-submit-cyan {
  background-color: #00c0ef;
  border: 1px solid #00acd6;
  color: #ffffff;
  padding: 0.55rem 1.75rem;
  border-radius: 4px;
  font-size: 0.92rem;
  font-weight: 600;
  cursor: pointer;
  box-shadow: 0 2px 4px rgba(0, 192, 239, 0.25);
  transition: all 0.15s ease-in-out;
}

.btn-submit-cyan:hover:not(:disabled) {
  background-color: #00acd6;
  border-color: #0087a8;
  box-shadow: 0 3px 6px rgba(0, 192, 239, 0.35);
}

.btn-submit-cyan:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.muted {
  font-size: 0.8rem;
  color: #6b7280;
}
</style>
