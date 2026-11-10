<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { apiUrl, request, resolveMediaUrl, type SigningDetailData } from '../api'
import { formatThaiDate, formatThaiDateTime } from '../utils/date'
import { showAlert, showSuccess, showError, showWarning, showConfirm } from '../utils/alert'
import { validateClientPdfA4Portrait } from '../utils/pdfValidator'

const props = defineProps<{
  docId: number | string
  canSign?: boolean
}>()

const emit = defineEmits<{
  close: []
  signed: [data: { docId: number; message: string }]
}>()

const loading = ref(false)
const submitting = ref(false)
const error = ref('')
const detail = ref<SigningDetailData | null>(null)

// ฟอร์มลงนาม
const selectedStamp = ref('')
const customStamp = ref('')
const signDetail = ref('')

// การเลือกการดำเนินการ (แนวทางที่ 1: เสนอต่อ vs สั่งการเสร็จสิ้นที่ขั้นตอนนี้)
const signActionType = ref<'forward' | 'finish'>('forward')
const customForwardRoleId = ref<number | null>(null)

// ฟอร์มตีกลับ
const showReturnBox = ref(false)
const returnReason = ref('')

// การเปลี่ยนสายการลงนาม (แนวทางที่ 2: สลับโปรไฟล์สายการลงนาม)
const showChangeRouteModal = ref(false)
const selectedNewProfileCode = ref('')
const isChangingRoute = ref(false)

// การเปลี่ยน/แก้ไขไฟล์หนังสือนำ
const editFileInput = ref<HTMLInputElement | null>(null)
const isReplacingFile = ref(false)
const fileVersion = ref(Date.now())

// การสลับพรีวิวไฟล์หนังสือนำ หรือ เอกสารแนบ
const selectedAttachmentId = ref<number | null>(null)

// คำนวณตรายางสุดท้ายที่ใช้ส่ง
const finalStamp = computed(() => {
  if (selectedStamp.value === '__custom__') {
    return customStamp.value.trim()
  }
  return selectedStamp.value
})

// ค้นหาขั้นตอนที่กำลังรอลงนามของเอกสารนี้
const activeStep = computed(() => {
  return detail.value?.steps.find((s) => s.status === 2) || null
})

// ขั้นตอนถัดไปในสายการลงนามปัจจุบัน
const nextStep = computed(() => {
  if (!activeStep.value || !detail.value) return null
  return detail.value.steps.find((s) => s.sort === activeStep.value!.sort + 1) || null
})

// ตรวจสอบว่าสามารถเลือกการดำเนินการ (เสนอต่อ / จบเรื่อง) ได้หรือไม่
const canChooseAction = computed(() => {
  if (!activeStep.value) return false
  // ผู้อำนวยการฯ (role 8) หรือ หัวหน้ากลุ่มงานสารบรรณ (role 2)
  return activeStep.value.roleId === 8 || activeStep.value.roleId === 2
})

// ข้อความแสดงปลายทางที่จะเสนอต่อ
const forwardTargetLabel = computed(() => {
  if (!activeStep.value) return 'ขั้นตอนถัดไป'
  if (nextStep.value) {
    return nextStep.value.roleName
  }
  if (activeStep.value.roleId === 8) {
    return 'ผู้พิพากษาหัวหน้าศาลฯ (ส่งต่อเพิ่มเติม)'
  }
  return 'ผู้บริหารระดับถัดไป'
})

// โปรไฟล์เป้าหมายที่เลือกใน Modal เปลี่ยนสายการลงนาม
const selectedTargetProfile = computed(() => {
  if (!detail.value?.availableProfiles) return null
  return detail.value.availableProfiles.find((p) => p.code === selectedNewProfileCode.value) || null
})

// รายการข้อความตรายางที่ปรับเปลี่ยนแบบ Dynamic ตามการเลือก (เสนอต่อ vs จบเรื่อง)
const dynamicSuggestedStamps = computed(() => {
  if (!activeStep.value) return []
  const roleId = activeStep.value.roleId

  // กรณีเลือกสั่งการเสร็จสิ้น (ยุติเรื่องที่ขั้นตอนนี้)
  if (signActionType.value === 'finish') {
    return [
      '- ทราบ / - ดำเนินการตามเสนอ',
      '- ทราบ',
      '- มอบกลุ่มงาน... ดำเนินการต่อไป',
      '- อนุญาต / - ดำเนินการตามเสนอ',
    ]
  }

  // กรณีเลือกเสนอต่อ (Forward)
  if (roleId === 8) {
    return [
      'เรียน ผู้พิพากษาหัวหน้าศาลฯ / เพื่อโปรดพิจารณา',
      'เรียน ผู้พิพากษาหัวหน้าศาลฯ / เพื่อโปรดทราบและพิจารณา',
      'เรียน ผู้พิพากษาหัวหน้าศาลฯ / เพื่อโปรดทราบ',
      '- ทราบ / - ดำเนินการตามเสนอ',
    ]
  } else if (roleId === 2) {
    return [
      'เรียน ผู้อำนวยการสำนักงานฯ / เพื่อโปรดพิจารณา',
      'เรียน ผู้อำนวยการสำนักงานฯ / เพื่อโปรดทราบ',
      'เรียน ผู้พิพากษาหัวหน้าศาลฯ / เพื่อโปรดทราบ',
      'เรียน ผู้พิพากษาหัวหน้าศาลฯ / เพื่อโปรดพิจารณา',
    ]
  } else if (roleId === 9) {
    return [
      '- ทราบ / - ดำเนินการตามเสนอ',
      '- อนุญาต / - ดำเนินการตามเสนอ',
      '- ทราบ / - แจ้งเข้าร่วม / - ดำเนินการตามเสนอ',
      '- ทราบ',
    ]
  }

  return detail.value?.suggestedStamps || []
})

function onActionTypeChange(type: 'forward' | 'finish') {
  signActionType.value = type
  if (type === 'finish') {
    selectedStamp.value = '- ทราบ / - ดำเนินการตามเสนอ'
  } else {
    if (activeStep.value?.roleId === 8) {
      selectedStamp.value = 'เรียน ผู้พิพากษาหัวหน้าศาลฯ / เพื่อโปรดพิจารณา'
    } else if (activeStep.value?.roleId === 2) {
      selectedStamp.value = 'เรียน ผู้อำนวยการสำนักงานฯ / เพื่อโปรดพิจารณา'
    } else {
      selectedStamp.value = dynamicSuggestedStamps.value[0] || ''
    }
  }
}

function openChangeRoute() {
  if (detail.value?.document.docForm) {
    selectedNewProfileCode.value = detail.value.document.docForm
  } else if (detail.value?.availableProfiles?.[0]) {
    selectedNewProfileCode.value = detail.value.availableProfiles[0].code
  }
  showChangeRouteModal.value = true
}

async function confirmChangeRoute() {
  if (!selectedNewProfileCode.value) {
    await showWarning('กรุณาเลือกสายการลงนาม', 'กรุณาเลือกสายการลงนามที่ต้องการเปลี่ยน')
    return
  }
  const confirmed = await showConfirm(
    'ยืนยันเปลี่ยนสายการลงนาม',
    'คุณต้องการเปลี่ยนสายการลงนามของหนังสือฉบับนี้ ใช่หรือไม่?'
  )
  if (!confirmed) {
    return
  }
  isChangingRoute.value = true
  try {
    const res = await request<{ message: string }>(
      'signing/change-route',
      {},
      {
        docId: props.docId,
        profileCode: selectedNewProfileCode.value,
      }
    )
    await showSuccess('สำเร็จ', res.message || 'เปลี่ยนสายการลงนามเรียบร้อยแล้ว')
    showChangeRouteModal.value = false
    await loadDetail()
  } catch (e) {
    await showError('เกิดข้อผิดพลาด', e instanceof Error ? e.message : 'เกิดข้อผิดพลาดในการเปลี่ยนสายการลงนาม')
  } finally {
    isChangingRoute.value = false
  }
}

// URL สำหรับดูไฟล์ PDF / เอกสารแนบ (เรียกผ่าน API view-file เพื่อให้ Browser แสดงผล inline 100%)
const previewFileUrl = computed(() => {
  if (!detail.value?.document.id) return ''
  if (selectedAttachmentId.value) {
    return apiUrl('documents/view-file', {
      id: detail.value.document.id,
      uid: detail.value.document.uid,
      attachmentId: selectedAttachmentId.value,
      v: fileVersion.value,
    })
  }
  if (!detail.value.document.mainFile) return ''
  return apiUrl('documents/view-file', {
    id: detail.value.document.id,
    uid: detail.value.document.uid,
    v: fileVersion.value,
  })
})

// ชื่อไฟล์ที่กำลังพรีวิวอยู่
const currentPreviewTitle = computed(() => {
  if (selectedAttachmentId.value) {
    const att = detail.value?.document.attachments.find((a) => a.id === selectedAttachmentId.value)
    return att ? `📎 ${att.name}` : 'ไฟล์แนบ'
  }
  return '📄 หนังสือนำ (ไฟล์หลัก)'
})

async function onEditMainFileChange(e: Event) {
  const target = e.target as HTMLInputElement
  if (!target.files || !target.files[0]) return
  const newFile = target.files[0]
  if (!newFile.name.toLowerCase().endsWith('.pdf')) {
    await showWarning('รูปแบบไฟล์ไม่ถูกต้อง', 'หนังสือนำต้องเป็นไฟล์ PDF เท่านั้น')
    target.value = ''
    return
  }

  // ตรวจสอบขนาด A4 หรือ Letter แนวตั้ง
  try {
    const check = await validateClientPdfA4Portrait(newFile, { allowLetter: true })
    if (!check.valid) {
      await showError('ขนาดหรือแนวเอกสารไม่ถูกต้อง', check.message)
      target.value = ''
      return
    }
  } catch (err: any) {
    console.error('[PDF Validation Error]', err)
    await showError('ตรวจสอบไฟล์ไม่สำเร็จ', 'ไม่สามารถอ่านโครงสร้างไฟล์ PDF ได้')
    target.value = ''
    return
  }

  const confirmed = await showConfirm(
    'ยืนยันเปลี่ยนไฟล์หนังสือนำ',
    `คุณต้องการเปลี่ยนไฟล์หนังสือนำเป็น "${newFile.name}" ใช่หรือไม่?`
  )
  if (!confirmed) {
    target.value = ''
    return
  }

  isReplacingFile.value = true
  try {
    const formData = new FormData()
    formData.append('file', newFile)

    const updateUrl = apiUrl(`documents/${props.docId}/update-main-file`)
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
      throw new Error(payload?.message || 'ไม่สามารถอัปเดตไฟล์หนังสือนำได้')
    }

    await showSuccess('สำเร็จ', 'อัปเดตและเปลี่ยนไฟล์หนังสือนำเรียบร้อยแล้ว')
    fileVersion.value = Date.now()
    await loadDetail()
  } catch (err) {
    await showError('เกิดข้อผิดพลาด', err instanceof Error ? err.message : 'เกิดข้อผิดพลาดในการเปลี่ยนไฟล์')
  } finally {
    isReplacingFile.value = false
    target.value = ''
  }
}

// อัปเดต/ประทับหน้าเกษียนหนังสือท้ายไฟล์หลักใหม่
const isRestamping = ref(false)
async function reStampEndSlip() {
  if (!detail.value?.document.id) return
  isRestamping.value = true
  try {
    await request(`documents/${detail.value.document.id}/stamp-end`, {}, {})
    await showSuccess('สำเร็จ', 'อัปเดตหน้าบันทึกเกษียนหนังสือเข้าสู่ไฟล์หลักเรียบร้อยแล้ว')
    fileVersion.value = Date.now()
    await loadDetail()
  } catch (err) {
    await showError('เกิดข้อผิดพลาด', err instanceof Error ? err.message : 'ไม่สามารถอัปเดตหน้าเกษียนหนังสือได้')
  } finally {
    isRestamping.value = false
  }
}

// ใช้งานฟังก์ชันแปลงเวลาภาษาไทยแบบย่อ
const formatDateTime = formatThaiDateTime

async function loadDetail() {
  loading.value = true
  error.value = ''
  try {
    const res = await request<{ data: SigningDetailData }>(`signing/detail/${props.docId}`)
    detail.value = res.data

    // ถ้าไม่มีขั้นตอนถัดไปในสายการลงนาม (เช่น ผอ. เป็นขั้นตอนสุดท้ายของสายนี้) ให้เลือก "สั่งการเสร็จสิ้น" เป็นค่าเริ่มต้น
    if (!nextStep.value) {
      signActionType.value = 'finish'
    } else {
      signActionType.value = 'forward'
    }

    // ตั้งค่าตรายางเริ่มต้นตามการคำนวณแบบ Dynamic
    if (dynamicSuggestedStamps.value.length) {
      selectedStamp.value = dynamicSuggestedStamps.value[0]
    } else if (res.data.suggestedStamps.length) {
      selectedStamp.value = res.data.suggestedStamps[0]
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'ไม่สามารถโหลดรายละเอียดเอกสารได้'
  } finally {
    loading.value = false
  }
}

async function submitSign() {
  if (!activeStep.value) {
    await showWarning('ไม่พบขั้นตอน', 'ไม่พบขั้นตอนที่รอลงนาม')
    return
  }
  if (!finalStamp.value) {
    await showWarning('กรุณาระบุตรายาง', 'กรุณาเลือกหรือระบุข้อความตรายางเกษียน')
    return
  }

  const isFinish = signActionType.value === 'finish'
  const confirmTitle = isFinish ? 'ยืนยันสั่งการเสร็จสิ้น' : 'ยืนยันการลงนาม'
  const confirmMsg = isFinish
    ? 'คุณต้องการบันทึกสั่งการเสร็จสิ้นและยุติเรื่อง (เกษียนครบ) ใช่หรือไม่?'
    : 'คุณต้องการบันทึกและลงนามส่งต่อเอกสารนี้ ใช่หรือไม่?'

  const confirmed = await showConfirm(confirmTitle, confirmMsg)
  if (!confirmed) {
    return
  }

  submitting.value = true
  try {
    const forwardRoleIdToSend =
      signActionType.value === 'forward'
        ? (nextStep.value?.roleId || (activeStep.value.roleId === 8 ? 9 : customForwardRoleId.value))
        : undefined

    const res = await request<{ data: { docId: number; message: string } }>(
      'signing/sign',
      {},
      {
        docId: props.docId,
        docManageId: activeStep.value.id,
        stamp: finalStamp.value,
        detail: signDetail.value.trim(),
        actionType: signActionType.value,
        forwardRoleId: forwardRoleIdToSend,
      }
    )

    await showSuccess('สำเร็จ', res.data.message || 'บันทึกเรียบร้อยแล้ว')
    emit('signed', res.data)
  } catch (e) {
    await showError('เกิดข้อผิดพลาด', e instanceof Error ? e.message : 'เกิดข้อผิดพลาดในการลงนาม')
  } finally {
    submitting.value = false
  }
}

async function submitReturn() {
  if (!activeStep.value) return
  if (!returnReason.value.trim()) {
    await showWarning('ระบุเหตุผล', 'กรุณาระบุเหตุผลในการตีกลับเอกสาร')
    return
  }

  const confirmed = await showConfirm(
    'ยืนยันตีกลับเอกสาร',
    'คุณต้องการตีกลับเอกสารนี้ ใช่หรือไม่?',
    { isDanger: true }
  )
  if (!confirmed) {
    return
  }

  submitting.value = true
  try {
    const res = await request<{ data: { docId: number; message: string } }>(
      'signing/return',
      {},
      {
        docId: props.docId,
        docManageId: activeStep.value.id,
        reason: returnReason.value.trim(),
      }
    )

    await showSuccess('สำเร็จ', res.data.message || 'ตีกลับเอกสารเรียบร้อยแล้ว')
    emit('signed', res.data)
  } catch (e) {
    await showError('เกิดข้อผิดพลาด', e instanceof Error ? e.message : 'เกิดข้อผิดพลาดในการตีกลับเอกสาร')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadDetail()
})
</script>

<template>
  <div class="signing-modal-backdrop" @click.self="emit('close')">
    <div class="signing-modal-container">
      <header class="signing-modal-header">
        <div class="header-info">
          <span class="eyebrow-badge">บันทึกลงนาม / เกษียนหนังสือ</span>
          <h2 v-if="detail" class="modal-title">
            เลขรับ: <strong>{{ detail.document.receiveNumber }}</strong> — {{ detail.document.subject }}
          </h2>
          <h2 v-else class="modal-title">กำลังเปิดเอกสาร…</h2>
        </div>
        <button class="btn-close" aria-label="ปิด" @click="emit('close')">✕</button>
      </header>

      <div v-if="loading" class="signing-loading">
        <span class="spinner">⏳</span> กำลังโหลดข้อมูลเอกสารและสายการลงนาม…
      </div>

      <div v-else-if="error" class="signing-error">
        {{ error }}
      </div>

      <template v-else-if="detail">
        <!-- แถบแจ้งเตือนเอกสารถูกตีกลับ (In-App Reverted Banner) -->
        <div v-if="detail.returnInfo" class="modal-returned-banner">
          <div class="banner-returned-icon">⛔</div>
          <div class="banner-returned-info">
            <div class="banner-returned-title">
              <strong>เอกสารนี้ถูกตีกลับเพื่อขอให้ตรวจสอบ / ปรับปรุงแก้ไข</strong>
              <span v-if="detail.returnInfo.returnedAt" class="banner-returned-time">
                {{ formatDateTime(detail.returnInfo.returnedAt) }}
              </span>
            </div>
            <div class="banner-returned-meta">
              ตีกลับโดย: <strong>{{ detail.returnInfo.returnerName }}</strong> ({{ detail.returnInfo.returnerRole }})
            </div>
            <div class="banner-returned-reason">
              <strong>เหตุผลการตีกลับ:</strong> {{ detail.returnInfo.reason }}
            </div>
          </div>
        </div>

        <div class="signing-split-body">
        <!-- ฝั่งซ้าย: ดูเอกสาร PDF และไฟล์แนบ (PDF Viewer & Attachments) -->
        <section class="doc-viewer-col">
          <div class="viewer-card">
            <!-- แท็บสลับดูหนังสือนำ หรือไฟล์แนบต่าง ๆ -->
            <div class="viewer-tabs-bar">
              <button
                type="button"
                class="viewer-tab-btn"
                :class="{ active: selectedAttachmentId === null }"
                @click="selectedAttachmentId = null"
              >
                📄 หนังสือนำ (ไฟล์หลัก)
              </button>
              <button
                v-for="att in detail.document.attachments"
                :key="att.id"
                type="button"
                class="viewer-tab-btn"
                :class="{ active: selectedAttachmentId === att.id }"
                @click="selectedAttachmentId = att.id"
              >
                📎 {{ att.name }}
              </button>
            </div>

            <div class="viewer-header">
              <div class="viewer-title-group">
                <span class="preview-title-text">{{ currentPreviewTitle }}</span>
                <span v-if="selectedAttachmentId === null && detail.document.mainFile" class="file-status-badge">PDF หนังสือนำ</span>
                <span v-else-if="selectedAttachmentId !== null" class="file-status-badge att-badge">เอกสารแนบ</span>
              </div>
              <div class="viewer-actions">
                <!-- ปุ่มสร้าง/ประทับหน้าเกษียนใหม่ (เมื่อเกษียนครบ) -->
                <button
                  v-if="selectedAttachmentId === null && (detail.document.status === 3 || detail.document.status === 4)"
                  type="button"
                  class="btn-restamp-slip"
                  title="ประทับ/อัปเดตหน้าเกษียนหนังสือท้ายไฟล์หลักใหม่"
                  :disabled="isRestamping"
                  @click="reStampEndSlip"
                >
                  <span v-if="isRestamping">⏳ กำลังประทับ…</span>
                  <span v-else>📝 อัปเดตหน้าเกษียน</span>
                </button>

                <!-- ปุ่มเปลี่ยนไฟล์หลัก แสดงเฉพาะเมื่อดูหนังสือนำ -->
                <button
                  v-if="selectedAttachmentId === null"
                  type="button"
                  class="btn-change-file"
                  title="เลือกไฟล์ PDF ใหม่เพื่อแทนที่หนังสือนำเดิม"
                  :disabled="isReplacingFile"
                  @click="editFileInput?.click()"
                >
                  <span v-if="isReplacingFile">⏳ กำลังเปลี่ยนไฟล์…</span>
                  <span v-else>🔄 แก้ไข/เปลี่ยนไฟล์หลัก</span>
                </button>
                <input
                  ref="editFileInput"
                  type="file"
                  accept=".pdf"
                  style="display: none"
                  @change="onEditMainFileChange"
                />

                <!-- ปุ่มดาวน์โหลด / เปิดดูเต็มจอ -->
                <a
                  v-if="previewFileUrl"
                  :href="previewFileUrl"
                  target="_blank"
                  class="btn-open-full"
                  title="เปิดไฟล์นี้ในแท็บใหม่เต็มหน้าจอ"
                >
                  เปิดไฟล์เต็มหน้าจอ ↗
                </a>
              </div>
            </div>

            <div class="viewer-content">
              <iframe
                v-if="previewFileUrl"
                :key="previewFileUrl"
                :src="previewFileUrl"
                class="pdf-frame"
                title="ตัวอย่างเอกสาร"
              />
              <div v-else class="no-preview">
                <span class="no-file-icon">📑</span>
                <p>ไม่มีไฟล์ หรือไม่สามารถแสดงตัวอย่างได้</p>
                <a
                  v-if="detail.document.id"
                  :href="apiUrl('documents/download', {
                    id: detail.document.id,
                    ...(selectedAttachmentId ? { attachmentId: selectedAttachmentId } : {})
                  })"
                  target="_blank"
                  class="btn-download"
                >
                  ดาวน์โหลดไฟล์
                </a>
              </div>
            </div>
          </div>

          <!-- กล่องรายการไฟล์แนบเพิ่มเติมแบบ Interactive -->
          <div v-if="detail.document.attachments?.length" class="attachments-card">
            <div class="att-card-header">
              <h4>ไฟล์แนบเพิ่มเติม ({{ detail.document.attachments.length }} รายการ)</h4>
              <button
                v-if="selectedAttachmentId !== null"
                type="button"
                class="btn-back-to-main"
                @click="selectedAttachmentId = null"
              >
                ↩ สลับกลับไปดูหนังสือนำ
              </button>
            </div>
            <div class="att-grid">
              <div
                v-for="att in detail.document.attachments"
                :key="att.id"
                class="att-chip-item"
                :class="{ 'is-active': selectedAttachmentId === att.id }"
              >
                <button
                  type="button"
                  class="btn-select-att"
                  title="คลิกเพื่อพรีวิวไฟล์นี้ในกรอบด้านบน"
                  @click="selectedAttachmentId = att.id"
                >
                  <span class="att-icon">👁️</span>
                  <span class="att-title">{{ att.name }}</span>
                  <span class="att-ext">{{ att.extension || 'file' }}</span>
                </button>
                <a
                  :href="apiUrl('documents/download', { id: detail.document.id, attachmentId: att.id })"
                  target="_blank"
                  class="att-download-link"
                  title="ดาวน์โหลดไฟล์นี้"
                >
                  ↓ โหลด
                </a>
              </div>
            </div>
          </div>
        </section>

        <!-- ฝั่งขวา: ข้อมูลหนังสือ, ไทม์ไลน์ และแบบฟอร์มลงนาม -->
        <section class="doc-signing-col">
          <!-- ข้อมูลสรุปหนังสือ -->
          <div class="doc-summary-box">
            <div class="summary-grid">
              <div>
                <small class="muted">เลขที่หนังสือ</small>
                <strong>{{ detail.document.letterNumber || '—' }}</strong>
              </div>
              <div>
                <small class="muted">วันที่รับ</small>
                <span>{{ formatDateTime(detail.document.receivedAt) }}</span>
              </div>
              <div>
                <small class="muted">เรียน</small>
                <strong>{{ detail.document.docTo || '—' }}</strong>
              </div>
              <div>
                <small class="muted">ความเร่งด่วน</small>
                <span class="badge-priority" :class="`p-${detail.document.priority}`">
                  {{ detail.document.priority || 'ปกติ' }}
                </span>
              </div>
            </div>
            <div class="summary-subject">
              <small class="muted">เรื่อง</small>
              <p>{{ detail.document.subject }}</p>
            </div>
          </div>

          <!-- ไทม์ไลน์สายการลงนาม (Signing Timeline) -->
          <div class="timeline-container">
            <div class="timeline-header-bar">
              <h4 class="timeline-title">สายการลงนามและข้อความเกษียน</h4>
              <button
                v-if="detail.availableProfiles?.length"
                type="button"
                class="btn-change-route"
                title="คลิกเพื่อเปลี่ยนหรือสลับสายการลงนามของหนังสือฉบับนี้"
                @click="openChangeRoute"
              >
                ⚙️ เปลี่ยนสายการลงนาม
              </button>
            </div>
            <div class="timeline-list">
              <div
                v-for="(step, idx) in detail.steps"
                :key="step.id"
                class="timeline-item"
                :class="{
                  'is-done': step.status === 3,
                  'is-active': step.status === 2,
                  'is-waiting': step.status === 1,
                }"
              >
                <!-- ไอคอนจุดสถานะ -->
                <div class="step-marker">
                  <span v-if="step.detail && step.detail.startsWith('[ตีกลับ]')" class="marker-icon returned">⛔</span>
                  <span v-else-if="step.status === 3" class="marker-icon done">✓</span>
                  <span v-else-if="step.status === 2" class="marker-icon active">✍</span>
                  <span v-else class="marker-icon wait">{{ idx + 1 }}</span>
                </div>

                <!-- กล่องข้อความเกษียน -->
                <div class="step-card">
                  <header class="step-card-header">
                    <span class="step-role">{{ step.roleName }}</span>
                    <span v-if="step.status === 3" class="step-time">
                      {{ formatDateTime(step.signedAt) }}
                    </span>
                    <span v-else-if="step.status === 2" class="step-tag-active">
                      ขั้นตอนปัจจุบัน (รอลงนาม)
                    </span>
                    <span v-else class="step-tag-wait">
                      รอดำเนินการ
                    </span>
                  </header>

                  <!-- กรณีขั้นตอนที่ลงนามแล้ว (Done) -->
                  <div v-if="step.status === 3" class="step-card-body">
                    <div v-if="step.stamp" class="stamp-badge">
                      {{ step.stamp }}
                    </div>
                    <p v-if="step.detail" class="step-detail-text">
                      {{ step.detail }}
                    </p>

                    <!-- ภาพลายมือชื่อผู้ลงนาม -->
                    <div v-if="step.signPhoto" class="step-signature-wrap">
                      <img :src="resolveMediaUrl(step.signPhoto)" class="step-signature-img" alt="ลายมือชื่อ" />
                    </div>

                    <div class="step-signer-footer">
                      <span class="signer-label">ผู้ลงนาม:</span>
                      <strong>{{ step.signerName || '—' }}</strong>
                    </div>
                  </div>

                  <!-- กรณีขั้นตอนปัจจุบันที่กำลังรอลงนาม (Active Form) -->
                  <div v-else-if="step.status === 2" class="active-signing-form">
                    <div v-if="!showReturnBox">
                      <!-- ตัวเลือกการดำเนินการในขั้นตอนนี้: เสนอต่อ vs สั่งการเสร็จสิ้น (ยุติเรื่อง) -->
                      <div v-if="canChooseAction" class="signing-action-box">
                        <label class="action-box-label">การดำเนินการในขั้นตอนนี้:</label>
                        <div class="action-options-grid">
                          <!-- กรณีไม่มีคนถัดไปในสายเดิม ให้แสดง "สั่งการเสร็จสิ้น" เป็นอันแรก -->
                          <template v-if="!nextStep">
                            <!-- ตัวเลือก: สั่งการเสร็จสิ้น (ยุติเรื่อง) -->
                            <div
                              class="action-option-card"
                              :class="{ 'is-selected': signActionType === 'finish' }"
                              @click="onActionTypeChange('finish')"
                            >
                              <input
                                type="radio"
                                name="signActionType"
                                value="finish"
                                :checked="signActionType === 'finish'"
                              />
                              <div class="action-option-content">
                                <span class="action-title">
                                  🏁 สั่งการเสร็จสิ้น (ยุติเรื่องที่ขั้นตอนนี้)
                                </span>
                                <small class="action-desc">
                                  สั่งจบเรื่องทันที (เกษียนครบ) พร้อมจ่ายหนังสือ
                                </small>
                              </div>
                            </div>

                            <!-- ตัวเลือก: เสนอต่อ (ส่งต่อเพิ่มเติม) -->
                            <div
                              class="action-option-card"
                              :class="{ 'is-selected': signActionType === 'forward' }"
                              @click="onActionTypeChange('forward')"
                            >
                              <input
                                type="radio"
                                name="signActionType"
                                value="forward"
                                :checked="signActionType === 'forward'"
                              />
                              <div class="action-option-content">
                                <span class="action-title">
                                  📤 เสนอต่อ {{ forwardTargetLabel }}
                                </span>
                                <small class="action-desc">
                                  ส่งต่อไปยังผู้บริหารระดับถัดไปเพื่อพิจารณาลงนาม
                                </small>
                              </div>
                            </div>
                          </template>

                          <!-- กรณีมีคนถัดไปในสายเดิม ให้แสดง "เสนอต่อ" เป็นอันแรก -->
                          <template v-else>
                            <!-- ตัวเลือก: เสนอต่อ -->
                            <div
                              class="action-option-card"
                              :class="{ 'is-selected': signActionType === 'forward' }"
                              @click="onActionTypeChange('forward')"
                            >
                              <input
                                type="radio"
                                name="signActionType"
                                value="forward"
                                :checked="signActionType === 'forward'"
                              />
                              <div class="action-option-content">
                                <span class="action-title">
                                  📤 เสนอต่อ {{ forwardTargetLabel }}
                                </span>
                                <small class="action-desc">
                                  ส่งต่อไปยังผู้บริหารระดับถัดไปเพื่อพิจารณาลงนาม
                                </small>
                              </div>
                            </div>

                            <!-- ตัวเลือก: สั่งการเสร็จสิ้น (ยุติเรื่อง) -->
                            <div
                              class="action-option-card"
                              :class="{ 'is-selected': signActionType === 'finish' }"
                              @click="onActionTypeChange('finish')"
                            >
                              <input
                                type="radio"
                                name="signActionType"
                                value="finish"
                                :checked="signActionType === 'finish'"
                              />
                              <div class="action-option-content">
                                <span class="action-title">
                                  🏁 สั่งการเสร็จสิ้น (ยุติเรื่องที่ขั้นตอนนี้)
                                </span>
                                <small class="action-desc">
                                  สั่งจบเรื่องทันที (เกษียนครบ) โดยไม่ต้องส่งต่อท่านหัวหน้าศาลฯ
                                </small>
                              </div>
                            </div>
                          </template>
                        </div>
                      </div>

                      <div class="form-field">
                        <label>เลือกตรายางข้อความเกษียน *</label>
                        <select v-model="selectedStamp" class="select-stamp">
                          <option
                            v-for="s in dynamicSuggestedStamps"
                            :key="s"
                            :value="s"
                          >
                            {{ s }}
                          </option>
                          <option value="__custom__">-- กำหนดข้อความตรายางเอง --</option>
                        </select>
                      </div>

                      <div v-if="selectedStamp === '__custom__'" class="form-field">
                        <input
                          v-model="customStamp"
                          type="text"
                          placeholder="พิมพ์ข้อความตรายาง..."
                          class="input-custom-stamp"
                        />
                      </div>

                      <div class="form-field">
                        <label>บันทึกข้อความ / ข้อสั่งการเพิ่มเติม (ถ้ามี)</label>
                        <textarea
                          v-model="signDetail"
                          rows="3"
                          placeholder="ระบุข้อคิดเห็น ข้อสั่งการ หรือคำสั่ง..."
                          class="textarea-detail"
                        />
                      </div>

                      <!-- แสดงพรีวิวภาพลายเซ็นของผู้ลงนามปัจจุบัน -->
                      <div class="current-user-sign-preview">
                        <label class="sign-preview-label">ลายมือชื่อที่จะประทับลงนาม:</label>
                        <div v-if="detail.currentUserSignPhoto" class="sign-preview-card">
                          <img :src="resolveMediaUrl(detail.currentUserSignPhoto)" class="sign-preview-img" alt="ลายมือชื่อของคุณ" />
                          <span class="sign-preview-name">({{ detail.currentUserName || 'คุณ' }})</span>
                        </div>
                        <div v-else class="sign-preview-warning">
                          ⚠️ ยังไม่ได้บันทึกภาพลายมือชื่อในระบบ (ระบบจะลงนามด้วยชื่อ-ตำแหน่ง หากต้องการให้แสดงภาพลายเซ็น สามารถอัปโหลดได้ที่เมนูข้อมูลส่วนตัว)
                        </div>
                      </div>

                      <div class="form-actions">
                        <button
                          type="button"
                          class="btn-sign-submit"
                          :class="{ 'is-finish': signActionType === 'finish' }"
                          :disabled="submitting"
                          @click="submitSign"
                        >
                          <span v-if="submitting">⏳ กำลังบันทึก…</span>
                          <span v-else-if="signActionType === 'finish'">🏁 บันทึกสั่งการเสร็จสิ้น (เกษียนครบ)</span>
                          <span v-else>✍️ บันทึกและลงนามส่งต่อ</span>
                        </button>
                        <button
                          type="button"
                          class="btn-toggle-return"
                          :disabled="submitting"
                          @click="showReturnBox = true"
                        >
                          ↩️ ตีกลับเอกสาร
                        </button>
                      </div>
                    </div>

                    <!-- กล่องสำหรับตีกลับเอกสาร -->
                    <div v-else class="return-box">
                      <label>เหตุผลในการตีกลับเอกสาร *</label>
                      <textarea
                        v-model="returnReason"
                        rows="3"
                        placeholder="ระบุเหตุผลที่ต้องตีกลับเอกสารนี้..."
                        class="textarea-detail return"
                      />
                      <div class="form-actions">
                        <button
                          type="button"
                          class="btn-confirm-return"
                          :disabled="submitting"
                          @click="submitReturn"
                        >
                          ยืนยันตีกลับเอกสาร
                        </button>
                        <button
                          type="button"
                          class="btn-cancel-return"
                          :disabled="submitting"
                          @click="showReturnBox = false"
                        >
                          ยกเลิก
                        </button>
                      </div>
                    </div>
                  </div>

                  <!-- กรณีขั้นตอนที่ถูกตีกลับ หรือยังไม่ถึงคิว (Waiting) -->
                  <div v-else-if="step.detail && step.detail.startsWith('[ตีกลับ]')" class="step-card-body step-returned-body">
                    <div class="returned-tag-small">⛔ ตีกลับเอกสาร</div>
                    <p class="step-return-reason">
                      <strong>เหตุผล:</strong> {{ step.detail.replace(/^\[ตีกลับ\]\s*/, '') }}
                    </p>
                    <div class="step-signer-footer">
                      <span class="signer-label">ผู้ตีกลับ:</span>
                      <strong>{{ step.signerName || '—' }}</strong>
                      <span v-if="step.signedAt" class="return-date">({{ formatDateTime(step.signedAt) }})</span>
                    </div>
                  </div>

                  <!-- กรณีขั้นตอนที่ยังไม่ถึงคิว (Waiting) -->
                  <div v-else class="waiting-preview">
                    <small class="muted">รอขั้นตอนก่อนหน้าดำเนินการเสร็จสิ้น</small>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
      </template>

      <!-- Modal ปรับเปลี่ยนสายการลงนาม (Change Route Modal) -->
      <div
        v-if="showChangeRouteModal"
        class="route-change-overlay"
        @click.self="showChangeRouteModal = false"
      >
        <div class="route-change-dialog">
          <div class="route-change-header">
            <h4>⚙️ ปรับเปลี่ยนสายการลงนามของหนังสือ</h4>
            <button type="button" class="btn-close-route-modal" @click="showChangeRouteModal = false">×</button>
          </div>
          <div class="route-change-body">
            <p class="route-change-desc">
              คุณสามารถเปลี่ยนโปรไฟล์สายการลงนามของหนังสือฉบับนี้ได้ (เช่น ปรับให้ถึงผู้พิพากษาหัวหน้าศาลฯ หรือปรับให้จบที่ ผอ.) โดยระบบจะรักษาขั้นตอนที่ลงนามไปแล้วไว้ และปรับเฉพาะขั้นตอนที่ยังไม่ถึงคิว
            </p>

            <div class="route-field">
              <label>เลือกสายการลงนามใหม่ *</label>
              <select v-model="selectedNewProfileCode" class="select-route-profile">
                <option
                  v-for="prof in detail?.availableProfiles || []"
                  :key="prof.code"
                  :value="prof.code"
                >
                  {{ prof.name }} (รหัส: {{ prof.code }})
                </option>
              </select>
            </div>

            <!-- กล่องพรีวิวลำดับขั้นตอนของโปรไฟล์ใหม่ -->
            <div v-if="selectedTargetProfile" class="profile-preview-card">
              <div class="preview-card-title">ลำดับขั้นตอนผู้ลงนาม:</div>
              <div class="preview-steps-flow">
                <span
                  v-for="(st, sIdx) in selectedTargetProfile.steps"
                  :key="st.id || sIdx"
                  class="preview-step-pill"
                >
                  {{ sIdx + 1 }}. {{ st.roleName }}
                  <span v-if="sIdx < selectedTargetProfile.steps.length - 1" class="step-arrow">→</span>
                </span>
              </div>
            </div>
          </div>
          <div class="route-change-footer">
            <button
              type="button"
              class="btn-confirm-route"
              :disabled="isChangingRoute"
              @click="confirmChangeRoute"
            >
              <span v-if="isChangingRoute">⏳ กำลังบันทึก…</span>
              <span v-else>ยืนยันเปลี่ยนสายการลงนาม</span>
            </button>
            <button
              type="button"
              class="btn-cancel-route"
              :disabled="isChangingRoute"
              @click="showChangeRouteModal = false"
            >
              ยกเลิก
            </button>
          </div>
        </div>
      </div>

      <footer class="signing-modal-footer">
        <button type="button" class="btn-cancel" @click="emit('close')">ปิดหน้าต่าง</button>
      </footer>
    </div>
  </div>
</template>

<style scoped>
.signing-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.65);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 250;
  padding: 1.25rem;
}

.signing-modal-container {
  background: #ffffff;
  border-radius: 8px;
  width: 95vw;
  max-width: 1400px;
  height: 92vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
  overflow: hidden;
  border-top: 4px solid #0284c7;
}

.signing-modal-header {
  padding: 1rem 1.5rem;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #f8fafc;
}

.eyebrow-badge {
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 1px;
  color: #0284c7;
  text-transform: uppercase;
  margin-bottom: 2px;
  display: block;
}

.modal-title {
  margin: 0;
  font-size: 1.15rem;
  color: #1e293b;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 1000px;
}

.btn-close {
  background: transparent;
  border: none;
  font-size: 1.25rem;
  color: #64748b;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 4px;
}

.btn-close:hover {
  background: #e2e8f0;
  color: #0f172a;
}

.signing-loading,
.signing-error {
  padding: 3rem;
  text-align: center;
  font-size: 1.1rem;
}

.signing-error {
  color: #ef4444;
}

.signing-split-body {
  flex: 1;
  display: grid;
  grid-template-columns: 58% 42%;
  overflow: hidden;
}

@media (max-width: 1024px) {
  .signing-split-body {
    grid-template-columns: 1fr;
    overflow-y: auto;
  }
}

/* ฝั่งซ้าย: ดูเอกสาร PDF */
.doc-viewer-col {
  padding: 1.25rem;
  background: #f1f5f9;
  border-right: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  overflow-y: auto;
}

.viewer-card {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 520px;
  overflow: hidden;
}

/* Tabs Bar */
.viewer-tabs-bar {
  display: flex;
  gap: 6px;
  padding: 8px 12px;
  background: #0f172a;
  border-bottom: 1px solid #334155;
  overflow-x: auto;
  flex-shrink: 0;
}

.viewer-tab-btn {
  background: #1e293b;
  border: 1px solid #334155;
  color: #94a3b8;
  padding: 5px 12px;
  border-radius: 5px;
  font-size: 0.8rem;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.12s ease;
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.viewer-tab-btn:hover {
  background: #334155;
  color: #ffffff;
}

.viewer-tab-btn.active {
  background: #0284c7;
  border-color: #0284c7;
  color: #ffffff;
  font-weight: 600;
}

.viewer-header {
  padding: 0.65rem 1rem;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-weight: 600;
  font-size: 0.88rem;
  color: #334155;
  gap: 10px;
  flex-wrap: wrap;
}

.viewer-title-group {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  flex: 1;
}

.preview-title-text {
  font-size: 0.9rem;
  font-weight: 600;
  color: #1e293b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.file-status-badge {
  background: #dcfce7;
  color: #166534;
  border: 1px solid #bbf7d0;
  padding: 1px 6px;
  border-radius: 4px;
  font-size: 0.78rem;
  font-weight: 600;
  white-space: nowrap;
}

.file-status-badge.att-badge {
  background: #e0f2fe;
  color: #0369a1;
  border-color: #bae6fd;
}

.viewer-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.btn-restamp-slip {
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
  color: #065f46;
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: all 0.15s;
}

.btn-restamp-slip:hover:not(:disabled) {
  background: #d1fae5;
  border-color: #34d399;
}

.btn-restamp-slip:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-change-file {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  color: #0369a1;
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: all 0.15s;
}

.btn-change-file:hover:not(:disabled) {
  background: #f0f9ff;
  border-color: #38bdf8;
}

.btn-change-file:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-open-full {
  font-size: 0.8rem;
  color: #0284c7;
  text-decoration: none;
}

.btn-open-full:hover {
  text-decoration: underline;
}

.viewer-content {
  flex: 1;
  display: flex;
  position: relative;
  min-height: 480px;
}

.pdf-frame {
  width: 100%;
  height: 100%;
  border: none;
  background: #525659;
}

.no-preview {
  margin: auto;
  text-align: center;
  padding: 2rem;
  color: #64748b;
}

.no-file-icon {
  font-size: 3rem;
  display: block;
  margin-bottom: 0.5rem;
}

.btn-download {
  display: inline-block;
  margin-top: 0.75rem;
  background: #0284c7;
  color: #ffffff;
  padding: 0.45rem 1rem;
  border-radius: 4px;
  text-decoration: none;
  font-size: 0.85rem;
}

.attachments-card {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  padding: 0.85rem 1rem;
}

.att-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.65rem;
}

.att-card-header h4 {
  margin: 0;
  font-size: 0.85rem;
  color: #475569;
}

.btn-back-to-main {
  background: #f1f5f9;
  border: 1px solid #cbd5e1;
  color: #0284c7;
  font-size: 0.78rem;
  font-weight: 500;
  padding: 3px 8px;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.15s;
}

.btn-back-to-main:hover {
  background: #e0f2fe;
  border-color: #38bdf8;
}

.att-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.att-chip-item {
  display: inline-flex;
  align-items: center;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 5px;
  overflow: hidden;
  transition: all 0.15s;
}

.att-chip-item:hover {
  border-color: #94a3b8;
}

.att-chip-item.is-active {
  background: #f0f9ff;
  border-color: #0284c7;
  box-shadow: 0 0 0 1px #0284c7;
}

.btn-select-att {
  background: transparent;
  border: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 10px;
  cursor: pointer;
  font-size: 0.82rem;
  color: #1e293b;
  text-align: left;
}

.att-chip-item.is-active .btn-select-att {
  font-weight: 600;
  color: #0369a1;
}

.att-icon {
  font-size: 0.85rem;
}

.att-title {
  max-width: 180px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.att-download-link {
  background: #f1f5f9;
  border-left: 1px solid #e2e8f0;
  padding: 5px 8px;
  font-size: 0.78rem;
  color: #475569;
  text-decoration: none;
  transition: background 0.15s;
}

.att-download-link:hover {
  background: #e2e8f0;
  color: #0f172a;
}

.att-ext {
  font-size: 0.78rem;
  text-transform: uppercase;
  background: #e2e8f0;
  padding: 1px 4px;
  border-radius: 2px;
  color: #475569;
}

/* ฝั่งขวา: สรุปเอกสาร และ ไทม์ไลน์ลงนาม */
.doc-signing-col {
  padding: 1.25rem 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  overflow-y: auto;
  background: #ffffff;
}

.doc-summary-box {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 1rem;
}

.summary-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem 1rem;
  margin-bottom: 0.75rem;
}

.summary-grid small,
.summary-subject small {
  display: block;
  font-size: 0.78rem;
}

.summary-subject p {
  margin: 0.2rem 0 0;
  font-size: 0.95rem;
  font-weight: 500;
  color: #1e293b;
}

.badge-priority {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 0.78rem;
  background: #e2e8f0;
  color: #334155;
}

.badge-priority.p-ด่วน,
.badge-priority.p-ด่วนที่สุด {
  background: #fee2e2;
  color: #b91c1c;
  font-weight: 600;
}

/* Timeline */
.timeline-title {
  margin: 0 0 0.85rem 0;
  font-size: 0.95rem;
  color: #334155;
  font-weight: 600;
}

.timeline-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  position: relative;
  padding-left: 0.5rem;
}

.timeline-list::before {
  content: '';
  position: absolute;
  top: 15px;
  bottom: 20px;
  left: 17px;
  width: 2px;
  background: #cbd5e1;
}

.timeline-item {
  display: flex;
  gap: 0.85rem;
  position: relative;
}

.step-marker {
  z-index: 2;
}

.marker-icon {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  font-size: 0.8rem;
  font-weight: 700;
}

.marker-icon.done {
  background: #10b981;
  color: #ffffff;
}

.marker-icon.active {
  background: #0284c7;
  color: #ffffff;
  box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.25);
}

.marker-icon.wait {
  background: #cbd5e1;
  color: #475569;
}

.step-card {
  flex: 1;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 0.85rem 1rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.is-active .step-card {
  border-color: #38bdf8;
  background: #f0f9ff;
  box-shadow: 0 2px 6px rgba(2, 132, 199, 0.1);
}

.step-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.4rem;
}

.step-role {
  font-weight: 700;
  font-size: 0.9rem;
  color: #1e293b;
}

.step-time {
  font-size: 0.78rem;
  color: #64748b;
}

.step-tag-active {
  background: #bae6fd;
  color: #0369a1;
  font-size: 0.78rem;
  padding: 2px 6px;
  border-radius: 4px;
  font-weight: 600;
}

.step-tag-wait {
  background: #f1f5f9;
  color: #64748b;
  font-size: 0.78rem;
  padding: 2px 6px;
  border-radius: 4px;
}

.stamp-badge {
  display: inline-block;
  background: #dbeafe;
  color: #1e40af;
  border: 1px solid #bfdbfe;
  padding: 0.35rem 0.65rem;
  border-radius: 4px;
  font-size: 0.85rem;
  font-weight: 600;
  margin-bottom: 0.4rem;
  white-space: pre-line;
}

.step-detail-text {
  margin: 0.25rem 0 0.5rem 0;
  font-size: 0.88rem;
  color: #334155;
  white-space: pre-line;
}

.step-signer-footer {
  font-size: 0.78rem;
  color: #64748b;
  text-align: right;
  border-top: 1px dashed #e2e8f0;
  padding-top: 0.35rem;
  margin-top: 0.35rem;
}

.signer-label {
  margin-right: 4px;
}

/* แสดงภาพลายเซ็นในประวัติขั้นตอน */
.step-signature-wrap {
  display: flex;
  justify-content: flex-end;
  margin: 0.35rem 0 0.2rem 0;
}

.step-signature-img {
  max-height: 48px;
  max-width: 170px;
  object-fit: contain;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 4px;
  padding: 2px 6px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}

/* พรีวิวภาพลายเซ็นของผู้ลงนามปัจจุบัน */
.current-user-sign-preview {
  margin: 0.75rem 0;
  padding: 0.65rem 0.8rem;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}

.sign-preview-label {
  display: block;
  font-size: 0.8rem;
  font-weight: 600;
  color: #475569;
  margin-bottom: 0.4rem;
}

.sign-preview-card {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.sign-preview-img {
  max-height: 52px;
  max-width: 200px;
  object-fit: contain;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  padding: 4px 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

.sign-preview-name {
  font-size: 0.85rem;
  font-weight: 500;
  color: #1e293b;
}

.sign-preview-warning {
  font-size: 0.78rem;
  color: #b45309;
  background: #fffbeb;
  border: 1px dashed #fde68a;
  border-radius: 6px;
  padding: 0.45rem 0.6rem;
  line-height: 1.4;
}

/* ฟอร์มลงนามในขั้นตอนปัจจุบัน */
.active-signing-form {
  margin-top: 0.65rem;
  padding-top: 0.65rem;
  border-top: 1px solid #bae6fd;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  margin-bottom: 0.75rem;
}

.form-field label {
  font-size: 0.85rem;
  font-weight: 600;
  color: #0369a1;
}

.select-stamp,
.input-custom-stamp,
.textarea-detail {
  padding: 0.55rem 0.75rem;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  font-size: 0.9rem;
  outline: none;
  background: #ffffff;
}

.select-stamp:focus,
.input-custom-stamp:focus,
.textarea-detail:focus {
  border-color: #0284c7;
  box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.2);
}

.form-actions {
  display: flex;
  gap: 0.75rem;
  margin-top: 0.85rem;
}

.btn-sign-submit {
  flex: 1;
  background: #00c0ef;
  border: 1px solid #00acd6;
  color: #ffffff;
  padding: 0.65rem 1rem;
  border-radius: 4px;
  font-weight: 600;
  font-size: 0.92rem;
  cursor: pointer;
  box-shadow: 0 2px 4px rgba(0, 192, 239, 0.25);
  transition: all 0.15s;
}

.btn-sign-submit:hover:not(:disabled) {
  background: #00acd6;
}

.btn-toggle-return {
  background: #ffffff;
  border: 1px solid #f87171;
  color: #dc2626;
  padding: 0.65rem 1rem;
  border-radius: 4px;
  font-size: 0.88rem;
  cursor: pointer;
}

.btn-toggle-return:hover {
  background: #fef2f2;
}

/* กล่องตีกลับ */
.modal-returned-banner {
  background: #fef2f2;
  border-bottom: 2px solid #fecaca;
  padding: 0.85rem 1.5rem;
  display: flex;
  align-items: flex-start;
  gap: 12px;
  flex-shrink: 0;
}

.banner-returned-icon {
  font-size: 1.5rem;
  line-height: 1;
}

.banner-returned-info {
  flex: 1;
}

.banner-returned-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  color: #991b1b;
  font-size: 0.95rem;
  margin-bottom: 2px;
}

.banner-returned-time {
  font-size: 0.8rem;
  color: #b91c1c;
  font-weight: 500;
}

.banner-returned-meta {
  font-size: 0.85rem;
  color: #7f1d1d;
  margin-bottom: 4px;
}

.banner-returned-reason {
  background: #ffffff;
  border: 1px solid #fca5a5;
  border-radius: 6px;
  padding: 6px 12px;
  font-size: 0.9rem;
  color: #b91c1c;
  display: inline-block;
  margin-top: 4px;
}

.marker-icon.returned {
  background: #fee2e2;
  border-color: #ef4444;
  color: #b91c1c;
}

.step-returned-body {
  background: #fff1f2;
  border-left: 3px solid #ef4444;
}

.returned-tag-small {
  display: inline-block;
  background: #fee2e2;
  color: #b91c1c;
  font-weight: 700;
  font-size: 0.78rem;
  padding: 2px 8px;
  border-radius: 4px;
  margin-bottom: 4px;
}

.step-return-reason {
  font-size: 0.88rem;
  color: #991b1b;
  margin: 4px 0 8px 0;
}

.return-box {
  background: #fff1f2;
  border: 1px solid #fecdd3;
  padding: 0.85rem;
  border-radius: 4px;
}

.return-box label {
  color: #be123c;
  font-weight: 600;
  font-size: 0.85rem;
  display: block;
  margin-bottom: 0.35rem;
}

.textarea-detail.return {
  border-color: #fca5a5;
  width: 100%;
}

.btn-confirm-return {
  background: #dc2626;
  color: #ffffff;
  border: none;
  padding: 0.55rem 1.25rem;
  border-radius: 4px;
  font-weight: 600;
  cursor: pointer;
}

.btn-cancel-return {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  padding: 0.55rem 1rem;
  border-radius: 4px;
  cursor: pointer;
}

.signing-modal-footer {
  padding: 0.75rem 1.5rem;
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
  display: flex;
  justify-content: flex-end;
}

.btn-cancel {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  color: #475569;
  padding: 0.45rem 1.25rem;
  border-radius: 4px;
  font-size: 0.88rem;
  cursor: pointer;
}

.btn-cancel:hover {
  background: #f1f5f9;
}

/* แถบหัวข้อไทม์ไลน์ และปุ่มเปลี่ยนสายการลงนาม */
.timeline-header-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.85rem;
}

.timeline-header-bar .timeline-title {
  margin: 0;
}

.btn-change-route {
  background: #f1f5f9;
  border: 1px solid #cbd5e1;
  color: #0369a1;
  font-size: 0.8rem;
  font-weight: 600;
  padding: 4px 10px;
  border-radius: 4px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: all 0.15s;
}

.btn-change-route:hover {
  background: #e0f2fe;
  border-color: #38bdf8;
}

/* กล่องตัวเลือกการดำเนินการ (เสนอต่อ vs จบเรื่อง) */
.signing-action-box {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 0.75rem 0.85rem;
  margin-bottom: 0.85rem;
}

.action-box-label {
  display: block;
  font-size: 0.83rem;
  font-weight: 600;
  color: #334155;
  margin-bottom: 0.5rem;
}

.action-options-grid {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.action-option-card {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  background: #ffffff;
  border: 1.5px solid #cbd5e1;
  border-radius: 6px;
  padding: 0.65rem 0.85rem;
  cursor: pointer;
  transition: all 0.15s;
}

.action-option-card:hover {
  border-color: #94a3b8;
}

.action-option-card.is-selected {
  background: #f0f9ff;
  border-color: #0284c7;
  box-shadow: 0 0 0 1px #0284c7;
}

.action-option-card input[type="radio"] {
  margin-top: 3px;
  cursor: pointer;
}

.action-option-content {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.action-title {
  font-size: 0.88rem;
  font-weight: 600;
  color: #0f172a;
}

.action-option-card.is-selected .action-title {
  color: #0369a1;
}

.action-desc {
  font-size: 0.78rem;
  color: #64748b;
}

.btn-sign-submit.is-finish {
  background: #16a34a;
}

.btn-sign-submit.is-finish:hover:not(:disabled) {
  background: #15803d;
}

/* Overlay & Dialog สำหรับเปลี่ยนสายการลงนาม */
.route-change-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.6);
  z-index: 300;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

.route-change-dialog {
  background: #ffffff;
  border-radius: 8px;
  max-width: 520px;
  width: 100%;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15);
  overflow: hidden;
  animation: fadeIn 0.15s ease-out;
}

.route-change-header {
  padding: 1rem 1.25rem;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.route-change-header h4 {
  margin: 0;
  font-size: 1rem;
  color: #0f172a;
  font-weight: 600;
}

.btn-close-route-modal {
  background: transparent;
  border: none;
  font-size: 1.25rem;
  line-height: 1;
  color: #64748b;
  cursor: pointer;
  padding: 0 4px;
}

.btn-close-route-modal:hover {
  color: #0f172a;
}

.route-change-body {
  padding: 1.25rem;
}

.route-change-desc {
  font-size: 0.83rem;
  color: #475569;
  line-height: 1.45;
  margin-top: 0;
  margin-bottom: 1rem;
}

.route-field {
  margin-bottom: 1rem;
}

.route-field label {
  display: block;
  font-size: 0.85rem;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 0.35rem;
}

.select-route-profile {
  width: 100%;
  padding: 0.55rem 0.75rem;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  font-size: 0.88rem;
  color: #1e293b;
  background: #ffffff;
}

.profile-preview-card {
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 0.75rem 1rem;
}

.preview-card-title {
  font-size: 0.78rem;
  font-weight: 600;
  color: #475569;
  margin-bottom: 0.45rem;
}

.preview-steps-flow {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}

.preview-step-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.82rem;
  color: #1e293b;
  font-weight: 500;
}

.step-arrow {
  color: #94a3b8;
  font-weight: 400;
}

.route-change-footer {
  padding: 0.85rem 1.25rem;
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.btn-confirm-route {
  background: #0284c7;
  color: #ffffff;
  border: none;
  padding: 0.55rem 1.25rem;
  border-radius: 4px;
  font-size: 0.88rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}

.btn-confirm-route:hover:not(:disabled) {
  background: #0369a1;
}

.btn-confirm-route:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-cancel-route {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  color: #475569;
  padding: 0.55rem 1rem;
  border-radius: 4px;
  font-size: 0.88rem;
  cursor: pointer;
}

.btn-cancel-route:hover {
  background: #f1f5f9;
}
</style>
