<script setup lang="ts">
import { ref, watch } from 'vue'
import { ApiError, apiUrl, request, uploadMedia } from '../api'
import { formatThaiDateTime } from '../utils/date'
import { showConfirm } from '../utils/alert'

const props = defineProps<{ docId: number; docUid?: string; destroyed?: boolean }>()
const emit = defineEmits<{ expired: [] }>()
interface Evidence { uid: string; name: string; size: number; note: string; createdAt: string }
const evidence = ref<Evidence[]>([])
const files = ref<File[]>([])
const note = ref('')
const loading = ref(false)
const saving = ref(false)
const deleting = ref<string | null>(null)
const error = ref('')
const success = ref('')
const input = ref<HTMLInputElement | null>(null)
let requestId = 0
const route = () => `documents/${props.docUid || props.docId}/action-files`
function fail(reason: unknown) {
  if (reason instanceof ApiError && reason.status === 401) emit('expired')
  error.value = reason instanceof Error ? reason.message : 'ไม่สามารถติดต่อระบบได้'
}
async function load() {
  const ticket = ++requestId
  loading.value = true
  try {
    const result = await request<{ data: Evidence[] }>(route())
    if (ticket === requestId) evidence.value = result.data
  } catch (reason) { if (ticket === requestId) fail(reason) }
  finally { if (ticket === requestId) loading.value = false }
}
watch(() => props.docId, () => { evidence.value = []; files.value = []; note.value = ''; error.value = ''; success.value = ''; load() }, { immediate: true })
function selectFiles(event: Event) {
  const selected = Array.from((event.target as HTMLInputElement).files || [])
  error.value = ''; success.value = ''
  if (selected.length > 5 || selected.some(file => !/\.pdf$/i.test(file.name) || file.size > 10 * 1024 * 1024)) {
    files.value = []; (event.target as HTMLInputElement).value = ''
    error.value = 'เลือก PDF ได้ครั้งละไม่เกิน 5 ไฟล์ ไฟล์ละไม่เกิน 10 MB'
    return
  }
  files.value = selected
}
async function save() {
  if (!files.value.length || saving.value || deleting.value) return
  saving.value = true; error.value = ''; success.value = ''
  const docId = props.docId
  const form = new FormData()
  files.value.forEach(file => form.append('files', file))
  form.append('note', note.value)
  try {
    await uploadMedia(route(), form)
    if (docId !== props.docId) return
    files.value = []; note.value = ''; if (input.value) input.value.value = ''
    success.value = 'แนบหลักฐานการปฏิบัติตามหนังสือเรียบร้อยแล้ว'
    await load()
  } catch (reason) { if (docId === props.docId) fail(reason) }
  finally { saving.value = false }
}

async function removeEvidence(file: Evidence) {
  if (saving.value || deleting.value) return
  const docId = props.docId
  const resource = `${route()}/${file.uid}`
  deleting.value = file.uid
  try {
    const confirmed = await showConfirm('ลบหลักฐานการปฏิบัติตามหนังสือ', `ต้องการลบ “${file.name}” ออกจากรายการหลักฐานของคุณหรือไม่?`, { confirmText: 'ลบหลักฐาน', isDanger: true })
    if (!confirmed || docId !== props.docId) return
    error.value = ''; success.value = ''
    await request(resource, {}, undefined, 'DELETE')
    if (docId !== props.docId) return
    evidence.value = evidence.value.filter(item => item.uid !== file.uid)
    success.value = 'ลบหลักฐานการปฏิบัติตามหนังสือเรียบร้อยแล้ว'
    await load()
  } catch (reason) { if (docId === props.docId) fail(reason) }
  finally { deleting.value = null }
}
</script>

<template>
  <section class="action-evidence file-section" aria-labelledby="action-evidence-title">
    <div class="file-section-head"><h3 id="action-evidence-title">หลักฐานการปฏิบัติตามหนังสือ</h3></div>
    <p class="evidence-hint">แนบ PDF หลังดำเนินการตามหนังสือ ไฟล์นี้เก็บแยกสำหรับบัญชีของคุณ</p>
    <p v-if="evidence.length" class="evidence-complete">✓ แนบหลักฐานแล้ว {{ evidence.length }} ไฟล์</p>
    <form v-if="!destroyed" class="evidence-form" @submit.prevent="save">
      <label>ไฟล์หลักฐาน PDF <input ref="input" type="file" accept=".pdf,application/pdf" multiple :disabled="saving || !!deleting" @change="selectFiles" /></label>
      <small>ครั้งละไม่เกิน 5 ไฟล์ ไฟล์ละไม่เกิน 10 MB</small>
      <label>หมายเหตุการดำเนินการ <textarea v-model="note" rows="3" maxlength="2000" :disabled="saving || !!deleting" placeholder="เช่น ดำเนินการเรียบร้อยแล้ว พร้อมแนบรายงานผล"></textarea></label>
      <button class="primary" type="submit" :disabled="saving || !!deleting || !files.length">{{ saving ? 'กำลังแนบไฟล์…' : 'แนบหลักฐานการดำเนินการ' }}</button>
    </form>
    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <p v-if="success" class="evidence-complete" role="status">{{ success }}</p>
    <p v-if="loading" role="status">กำลังโหลดหลักฐาน…</p>
    <ul v-else-if="evidence.length" class="evidence-list">
      <li v-for="file in evidence" :key="file.uid">
        <strong>{{ file.name }}</strong>
        <small>{{ formatThaiDateTime(file.createdAt) }} · {{ (file.size / 1024 / 1024).toFixed(2) }} MB</small>
        <p v-if="file.note" class="evidence-note">{{ file.note }}</p>
        <div class="evidence-links">
          <a :href="apiUrl(`${route()}/${file.uid}/file`)" target="_blank" rel="noopener">ดู PDF ↗</a>
          <a :href="apiUrl(`${route()}/${file.uid}/file`, { download: '1' })" target="_blank" rel="noopener">ดาวน์โหลด ↓</a>
          <button type="button" class="evidence-delete" :disabled="saving || !!deleting" :aria-label="`ลบหลักฐาน ${file.name}`" @click="removeEvidence(file)">{{ deleting === file.uid ? 'กำลังดำเนินการ…' : 'ลบหลักฐาน' }}</button>
        </div>
      </li>
    </ul>
    <p v-else-if="!error" class="evidence-hint">ยังไม่มีไฟล์หลักฐานการดำเนินการ</p>
  </section>
</template>

<style scoped>
.action-evidence { margin-top: 20px; border: 1px solid #bfdbfe; border-radius: 10px; padding: 16px; background: #f8fbff; }
.action-evidence h3 { margin: 0 0 8px; font-size: 1rem; }
.evidence-hint, .evidence-form small, .evidence-list small { color: #64748b; font-size: .82rem; }
.evidence-form { display: grid; gap: 10px; margin-block: 14px; }
.evidence-form label { display: grid; gap: 6px; font-weight: 500; }
.evidence-form input, .evidence-form textarea { width: 100%; min-width: 0; border: 1px solid #cbd5e1; border-radius: 6px; background: white; padding: 10px; color: #1e293b; }
.evidence-form input { font-size: .85rem; }
.evidence-form textarea { resize: vertical; }
.evidence-complete { color: #166534; margin-top: 10px; font-weight: 500; }
.evidence-list { list-style: none; margin: 14px 0 0; padding: 0; display: grid; gap: 12px; }
.evidence-list li { padding: 12px; border: 1px solid #dbeafe; border-radius: 8px; background: white; overflow-wrap: anywhere; }
.evidence-list strong, .evidence-list small { display: block; }
.evidence-note { white-space: pre-wrap; margin-top: 8px; }
.evidence-links { display: flex; flex-wrap: wrap; gap: 18px; color: #1d4ed8; margin-top: 8px; }
.evidence-delete { padding: 0; border: 0; border-radius: 3px; background: transparent; color: #b91c1c; font-size: inherit; text-decoration: underline; }
</style>
