<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { request, type DocCatItem, type DocCatDocumentItem } from '../api'
import { showSuccess, showError, showConfirm } from '../utils/alert'
import { formatThaiDate } from '../utils/date'

const categories = ref<DocCatItem[]>([])
const loading = ref(false)
const error = ref('')
const searchKeyword = ref('')

// Modal States
const showCreateModal = ref(false)
const showEditModal = ref(false)
const showDocsModal = ref(false)
const submitting = ref(false)
const docsLoading = ref(false)

// Forms
const createForm = ref<{ name: string; note: string }>({
  name: '',
  note: '',
})

const editForm = ref<{ id: number; uid?: string; name: string; note: string }>({
  id: 0,
  uid: '',
  name: '',
  note: '',
})

// Viewing Documents in Category
const activeCategory = ref<DocCatItem | null>(null)
const categoryDocs = ref<DocCatDocumentItem[]>([])

const filteredCategories = computed(() => {
  const q = searchKeyword.value.toLowerCase().trim()
  if (!q) return categories.value
  return categories.value.filter(
    (c) =>
      c.name.toLowerCase().includes(q) ||
      (c.note && c.note.toLowerCase().includes(q))
  )
})

const totalDocsCount = computed(() => {
  return categories.value.reduce((acc, c) => acc + (c.docCount || 0), 0)
})

async function loadData() {
  loading.value = true
  error.value = ''
  try {
    const res = await request<{ data: DocCatItem[] }>('admin/doc-cats')
    categories.value = res.data
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'ไม่สามารถโหลดรายการที่เก็บเอกสารได้'
  } finally {
    loading.value = false
  }
}

function openCreate() {
  createForm.value = {
    name: '',
    note: '',
  }
  showCreateModal.value = true
}

async function submitCreate() {
  if (!createForm.value.name.trim()) {
    showError('กรุณากรอกชื่อที่เก็บเอกสาร')
    return
  }

  submitting.value = true
  try {
    const res = await request<{ data: DocCatItem; message: string }>('admin/doc-cats/create', {}, {
      name: createForm.value.name.trim(),
      note: createForm.value.note.trim() || null,
    })

    showCreateModal.value = false
    showSuccess(res.message || 'เพิ่มที่เก็บเอกสารเรียบร้อยแล้ว')
    await loadData()
  } catch (e) {
    showError(e instanceof Error ? e.message : 'เกิดข้อผิดพลาดในการเพิ่มที่เก็บเอกสาร')
  } finally {
    submitting.value = false
  }
}

function openEdit(cat: DocCatItem) {
  editForm.value = {
    id: cat.id,
    uid: cat.uid || '',
    name: cat.name,
    note: cat.note || '',
  }
  showEditModal.value = true
}

async function submitEdit() {
  if (!editForm.value.name.trim()) {
    showError('กรุณากรอกชื่อที่เก็บเอกสาร')
    return
  }

  submitting.value = true
  try {
    const res = await request<{ data: DocCatItem; message: string }>('admin/doc-cats/update', {}, {
      id: editForm.value.id,
      uid: editForm.value.uid,
      name: editForm.value.name.trim(),
      note: editForm.value.note.trim() || null,
    })

    showEditModal.value = false
    showSuccess(res.message || 'บันทึกการแก้ไขเรียบร้อยแล้ว')
    await loadData()
  } catch (e) {
    showError(e instanceof Error ? e.message : 'เกิดข้อผิดพลาดในการแก้ไขที่เก็บเอกสาร')
  } finally {
    submitting.value = false
  }
}

async function confirmDelete(cat: DocCatItem) {
  const confirmMsg = cat.docCount > 0
    ? `ที่เก็บเอกสาร "${cat.name}" มีเอกสารจัดเก็บอยู่ ${cat.docCount} ฉบับ\nการลบจะยกเลิกการจัดเก็บเอกสารในหมวดนี้ทั้งหมด ยืนยันการลบหรือไม่?`
    : `คุณต้องการลบที่เก็บเอกสาร "${cat.name}" หรือไม่?`

  const confirmed = await showConfirm(
    'ยืนยันการลบที่เก็บเอกสาร',
    confirmMsg,
    {
      confirmText: 'ใช่, ต้องการลบ',
      isDanger: true,
    }
  )

  if (!confirmed) return

  try {
    const res = await request<{ message: string }>('admin/doc-cats/delete', {}, {
      id: cat.id,
      uid: cat.uid,
    })
    showSuccess(res.message || 'ลบที่เก็บเอกสารเรียบร้อยแล้ว')
    await loadData()
  } catch (e) {
    showError(e instanceof Error ? e.message : 'เกิดข้อผิดพลาดในการลบที่เก็บเอกสาร')
  }
}

async function openViewDocs(cat: DocCatItem) {
  activeCategory.value = cat
  showDocsModal.value = true
  categoryDocs.value = []
  docsLoading.value = true
  try {
    const res = await request<{ data: DocCatDocumentItem[] }>(`admin/doc-cats/${cat.uid || cat.id}/documents`)
    categoryDocs.value = res.data
  } catch (e) {
    showError(e instanceof Error ? e.message : 'ไม่สามารถโหลดเอกสารในหมวดนี้ได้')
  } finally {
    docsLoading.value = false
  }
}

const statusLabels: Record<number, string> = {
  1: 'รอเสนอ',
  2: 'อยู่ระหว่างดำเนินการ',
  3: 'เกษียนครบ',
  4: 'แจกจ่ายแล้ว',
  5: 'ถูกตีกลับ',
}
function statusText(st: number) {
  return statusLabels[st] || `สถานะ ${st}`
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="admin-categories-workspace">
    <!-- Header -->
    <div class="page-heading">
      <div>
        <span class="eyebrow">ADMINISTRATION WORKSPACE</span>
        <h1>📁 จัดการที่เก็บเอกสาร</h1>
        <p class="muted">
          กำหนดหมวดแฟ้มจัดเก็บเอกสารและที่เก็บเอกสารสำหรับจัดเก็บหนังสือเมื่อเสร็จสิ้นกระบวนการ
        </p>
      </div>
      <div class="heading-actions">
        <button class="btn-create" type="button" @click="openCreate">
          <span>➕</span> เพิ่มที่เก็บเอกสาร
        </button>
        <div class="count-card">
          <strong>{{ categories.length.toLocaleString('th-TH') }}</strong>
          <span>หมวดที่เก็บ</span>
        </div>
        <div class="count-card">
          <strong>{{ totalDocsCount.toLocaleString('th-TH') }}</strong>
          <span>ฉบับที่จัดเก็บ</span>
        </div>
      </div>
    </div>

    <!-- Card -->
    <section class="admin-card">
      <div class="card-toolbar">
        <div class="search-wrap">
          <span class="search-icon">🔍</span>
          <input
            v-model="searchKeyword"
            type="text"
            placeholder="ค้นหาชื่อที่เก็บเอกสาร หรือคำอธิบาย..."
            class="search-input"
          />
        </div>
        <button
          v-if="searchKeyword"
          type="button"
          class="btn-reset"
          @click="searchKeyword = ''"
        >
          ล้างคำค้นหา
        </button>
        <button
          type="button"
          class="btn-refresh"
          title="โหลดข้อมูลใหม่"
          @click="loadData"
        >
          🔄 รีเฟรช
        </button>
      </div>

      <div v-if="loading" class="empty-state">
        <span class="spinner">⏳</span> กำลังโหลดข้อมูลที่เก็บเอกสาร...
      </div>

      <div v-else-if="error" class="error-state">
        {{ error }}
      </div>

      <div v-else-if="!filteredCategories.length" class="empty-state">
        <span class="empty-icon">📁</span>
        <h3>ไม่พบที่เก็บเอกสาร</h3>
        <p class="muted">ลองปรับคำค้นหา หรือกดปุ่ม "เพิ่มที่เก็บเอกสาร" ด้านบน</p>
      </div>

      <div v-else class="table-scroll">
        <table class="categories-table">
          <thead>
            <tr>
              <th style="width: 70px; text-align: center">#</th>
              <th>ชื่อที่เก็บเอกสาร (หมวดแฟ้ม)</th>
              <th>คำอธิบาย / หมายเหตุ</th>
              <th style="width: 160px; text-align: center">จำนวนเอกสาร</th>
              <th style="width: 190px; text-align: center">การจัดการ</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(cat, idx) in filteredCategories" :key="cat.id">
              <td style="text-align: center; color: #64748b; font-weight: 600">
                {{ idx + 1 }}
              </td>
              <td class="name-cell">
                <span class="cat-icon">📁</span>
                <span class="cat-name">{{ cat.name }}</span>
              </td>
              <td class="note-cell">
                <span v-if="cat.note" class="cat-note">{{ cat.note }}</span>
                <span v-else class="muted-dash">—</span>
              </td>
              <td style="text-align: center">
                <button
                  type="button"
                  class="btn-doc-count"
                  :title="`ดูเอกสารใน ${cat.name}`"
                  @click="openViewDocs(cat)"
                >
                  📄 {{ cat.docCount.toLocaleString('th-TH') }} ฉบับ
                </button>
              </td>
              <td style="text-align: center">
                <div class="row-actions">
                  <button
                    type="button"
                    class="btn-action btn-view"
                    title="ดูเอกสารในหมวดนี้"
                    @click="openViewDocs(cat)"
                  >
                    👁️ เอกสาร
                  </button>
                  <button
                    type="button"
                    class="btn-action btn-edit"
                    title="แก้ไขชื่อหรือคำอธิบาย"
                    @click="openEdit(cat)"
                  >
                    ✏️ แก้ไข
                  </button>
                  <button
                    type="button"
                    class="btn-action btn-del"
                    title="ลบที่เก็บเอกสารนี้"
                    @click="confirmDelete(cat)"
                  >
                    🗑️ ลบ
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Modal เพิ่มที่เก็บเอกสาร -->
    <div v-if="showCreateModal" class="modal-backdrop" @click.self="showCreateModal = false">
      <div class="modal-dialog">
        <div class="modal-header">
          <h2>➕ เพิ่มที่เก็บเอกสารใหม่</h2>
          <button type="button" class="btn-close" @click="showCreateModal = false">✕</button>
        </div>
        <form @submit.prevent="submitCreate">
          <div class="modal-body">
            <div class="form-group">
              <label for="create-cat-name">ชื่อที่เก็บเอกสาร (หมวดแฟ้ม) <span class="required">*</span></label>
              <input
                id="create-cat-name"
                v-model="createForm.name"
                type="text"
                placeholder="เช่น การเงิน/พัสดุ, คำสั่งศาล, หนังสือภายนอก..."
                required
                maxlength="200"
                class="form-control"
              />
            </div>
            <div class="form-group">
              <label for="create-cat-note">คำอธิบาย / หมายเหตุ</label>
              <textarea
                id="create-cat-note"
                v-model="createForm.note"
                rows="3"
                placeholder="ระบุรายละเอียดเพิ่มเติม หรือประเภทเอกสารที่จัดเก็บในแฟ้มนี้..."
                class="form-control"
              ></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn-secondary"
              :disabled="submitting"
              @click="showCreateModal = false"
            >
              ยกเลิก
            </button>
            <button type="submit" class="btn-primary" :disabled="submitting">
              {{ submitting ? 'กำลังบันทึก...' : '💾 บันทึกที่เก็บเอกสาร' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal แก้ไขที่เก็บเอกสาร -->
    <div v-if="showEditModal" class="modal-backdrop" @click.self="showEditModal = false">
      <div class="modal-dialog">
        <div class="modal-header">
          <h2>✏️ แก้ไขที่เก็บเอกสาร</h2>
          <button type="button" class="btn-close" @click="showEditModal = false">✕</button>
        </div>
        <form @submit.prevent="submitEdit">
          <div class="modal-body">
            <div class="form-group">
              <label for="edit-cat-name">ชื่อที่เก็บเอกสาร (หมวดแฟ้ม) <span class="required">*</span></label>
              <input
                id="edit-cat-name"
                v-model="editForm.name"
                type="text"
                required
                maxlength="200"
                class="form-control"
              />
            </div>
            <div class="form-group">
              <label for="edit-cat-note">คำอธิบาย / หมายเหตุ</label>
              <textarea
                id="edit-cat-note"
                v-model="editForm.note"
                rows="3"
                placeholder="ระบุรายละเอียดเพิ่มเติม..."
                class="form-control"
              ></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn-secondary"
              :disabled="submitting"
              @click="showEditModal = false"
            >
              ยกเลิก
            </button>
            <button type="submit" class="btn-primary" :disabled="submitting">
              {{ submitting ? 'กำลังบันทึก...' : '💾 บันทึกการแก้ไข' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal ดูเอกสารในแฟ้มนี้ -->
    <div v-if="showDocsModal" class="modal-backdrop" @click.self="showDocsModal = false">
      <div class="modal-dialog modal-dialog-lg">
        <div class="modal-header">
          <div>
            <h2>📁 เอกสารในแฟ้ม "{{ activeCategory?.name }}"</h2>
            <p class="muted modal-subtitle">
              พบเอกสารจัดเก็บทั้งหมด {{ categoryDocs.length }} ฉบับ
            </p>
          </div>
          <button type="button" class="btn-close" @click="showDocsModal = false">✕</button>
        </div>
        <div class="modal-body modal-body-scroll">
          <div v-if="docsLoading" class="empty-state">
            <span class="spinner">⏳</span> กำลังโหลดเอกสารในแฟ้มนี้...
          </div>
          <div v-else-if="!categoryDocs.length" class="empty-state">
            <span class="empty-icon">📄</span>
            <h3>ยังไม่มีเอกสารในแฟ้มนี้</h3>
            <p class="muted">เมื่อมีการจ่ายหนังสือและเลือกจัดเก็บเข้าแฟ้มนี้ รายการจะปรากฏที่นี่</p>
          </div>
          <table v-else class="modal-docs-table">
            <thead>
              <tr>
                <th style="width: 140px">เลขรับ / วันที่รับ</th>
                <th style="width: 150px">เลขที่หนังสือ</th>
                <th>เรื่อง</th>
                <th style="width: 110px; text-align: center">สถานะ</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="doc in categoryDocs" :key="doc.id">
                <td>
                  <strong>{{ doc.receiveNumber }}</strong>
                  <small class="muted-date">{{ formatThaiDate(doc.receivedAt) }}</small>
                </td>
                <td>{{ doc.letterNumber || '—' }}</td>
                <td>
                  <span class="doc-subject-text">{{ doc.subject }}</span>
                  <span v-if="doc.priority && doc.priority !== 'ปกติ'" class="priority-tag">{{ doc.priority }}</span>
                </td>
                <td style="text-align: center">
                  <span class="doc-status-badge" :class="`st-${doc.legacyStatus}`">
                    {{ statusText(doc.legacyStatus) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-secondary" @click="showDocsModal = false">
            ปิดหน้าต่าง
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admin-categories-workspace {
  padding: 1.5rem;
  max-width: 1200px;
  margin: 0 auto;
}

.page-heading {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
  gap: 1rem;
}

.eyebrow {
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  color: #0284c7;
  text-transform: uppercase;
}

.page-heading h1 {
  font-size: 1.5rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0.25rem 0 0.5rem;
}

.muted {
  color: #64748b;
  font-size: 0.9rem;
  margin: 0;
}

.heading-actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.btn-create {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  background: #0284c7;
  color: #ffffff;
  border: none;
  border-radius: 8px;
  padding: 0.6rem 1.1rem;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
  white-space: nowrap;
}

.btn-create:hover {
  background: #0369a1;
  box-shadow: 0 4px 10px rgba(2, 132, 199, 0.25);
}

.count-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 0.4rem 0.85rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 90px;
}

.count-card strong {
  font-size: 1.15rem;
  color: #0f172a;
}

.count-card span {
  font-size: 0.78rem;
  color: #64748b;
}

.admin-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  overflow: hidden;
}

.card-toolbar {
  padding: 1rem 1.25rem;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  gap: 0.75rem;
  align-items: center;
  background: #fafafa;
}

.search-wrap {
  position: relative;
  flex: 1;
}

.search-icon {
  position: absolute;
  left: 0.8rem;
  top: 50%;
  transform: translateY(-50%);
  font-size: 0.9rem;
  color: #94a3b8;
}

.search-input {
  width: 100%;
  padding: 0.55rem 0.8rem 0.55rem 2.2rem;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.9rem;
  box-sizing: border-box;
  outline: none;
  transition: all 0.15s ease;
}

.search-input:focus {
  border-color: #0284c7;
  box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
}

.btn-reset,
.btn-refresh {
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #475569;
  padding: 0.55rem 0.9rem;
  border-radius: 8px;
  font-size: 0.85rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s ease;
  white-space: nowrap;
}

.btn-reset:hover,
.btn-refresh:hover {
  background: #f1f5f9;
  color: #1e293b;
}

.empty-state {
  text-align: center;
  padding: 3rem 1.5rem;
  color: #64748b;
}

.empty-icon {
  font-size: 2.5rem;
  display: block;
  margin-bottom: 0.5rem;
}

.empty-state h3 {
  font-size: 1.1rem;
  color: #1e293b;
  margin: 0.25rem 0;
}

.error-state {
  padding: 1.5rem;
  background: #fef2f2;
  color: #b91c1c;
  text-align: center;
  font-weight: 500;
}

.table-scroll {
  overflow-x: auto;
}

.categories-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}

.categories-table th {
  background: #f8fafc;
  color: #475569;
  font-weight: 600;
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #e2e8f0;
  text-align: left;
}

.categories-table td {
  padding: 0.85rem 1rem;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}

.categories-table tr:hover {
  background: #f8fafc;
}

.name-cell {
  font-weight: 600;
  color: #0f172a;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.cat-icon {
  font-size: 1.1rem;
}

.cat-name {
  word-break: break-word;
}

.cat-note {
  color: #64748b;
  font-size: 0.85rem;
}

.muted-dash {
  color: #cbd5e1;
}

.btn-doc-count {
  background: #f0fdf4;
  color: #15803d;
  border: 1px solid #bbf7d0;
  padding: 0.25rem 0.65rem;
  border-radius: 9999px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
  white-space: nowrap;
}

.btn-doc-count:hover {
  background: #dcfce7;
  border-color: #86efac;
}

.row-actions {
  display: flex;
  gap: 0.4rem;
  justify-content: center;
}

.btn-action {
  border: none;
  border-radius: 6px;
  padding: 0.35rem 0.65rem;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
  white-space: nowrap;
}

.btn-view {
  background: #eff6ff;
  color: #1d4ed8;
}

.btn-view:hover {
  background: #dbeafe;
}

.btn-edit {
  background: #f8fafc;
  color: #475569;
  border: 1px solid #cbd5e1;
}

.btn-edit:hover {
  background: #f1f5f9;
  color: #1e293b;
}

.btn-del {
  background: #fef2f2;
  color: #b91c1c;
}

.btn-del:hover {
  background: #fee2e2;
}

/* Modals */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(2px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 1rem;
}

.modal-dialog {
  background: #ffffff;
  border-radius: 12px;
  width: min(520px, 95vw);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.modal-dialog-lg {
  width: min(850px, 95vw);
  max-height: 85vh;
}

.modal-header {
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  background: #f8fafc;
}

.modal-header h2 {
  font-size: 1.15rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.modal-subtitle {
  font-size: 0.8rem;
  margin-top: 0.25rem;
}

.btn-close {
  background: transparent;
  border: none;
  font-size: 1.2rem;
  color: #94a3b8;
  cursor: pointer;
  padding: 0;
  line-height: 1;
}

.btn-close:hover {
  color: #0f172a;
}

.modal-body {
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1.1rem;
}

.modal-body-scroll {
  overflow-y: auto;
  max-height: 60vh;
  padding: 0;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.form-group label {
  font-size: 0.85rem;
  font-weight: 600;
  color: #334155;
}

.required {
  color: #ef4444;
}

.form-control {
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 0.6rem 0.8rem;
  font-size: 0.9rem;
  outline: none;
  transition: all 0.15s ease;
  font-family: inherit;
}

.form-control:focus {
  border-color: #0284c7;
  box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
}

.modal-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid #e2e8f0;
  background: #f8fafc;
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
}

.btn-primary {
  background: #0284c7;
  color: #ffffff;
  border: none;
  border-radius: 8px;
  padding: 0.55rem 1.2rem;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-primary:hover:not(:disabled) {
  background: #0369a1;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-secondary {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  color: #475569;
  border-radius: 8px;
  padding: 0.55rem 1.1rem;
  font-size: 0.9rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-secondary:hover:not(:disabled) {
  background: #f1f5f9;
  color: #1e293b;
}

/* Modal Docs Table */
.modal-docs-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.85rem;
}

.modal-docs-table th {
  background: #f8fafc;
  color: #475569;
  font-weight: 600;
  padding: 0.65rem 1rem;
  border-bottom: 1px solid #e2e8f0;
  text-align: left;
}

.modal-docs-table td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}

.modal-docs-table tr:hover {
  background: #f8fafc;
}

.muted-date {
  display: block;
  color: #64748b;
  font-size: 0.78rem;
}

.doc-subject-text {
  font-weight: 500;
  color: #0f172a;
}

.priority-tag {
  display: inline-block;
  margin-left: 0.4rem;
  font-size: 0.78rem;
  background: #fee2e2;
  color: #b91c1c;
  border-radius: 4px;
  padding: 1px 5px;
  font-weight: 600;
}

.doc-status-badge {
  font-size: 0.78rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 9999px;
  display: inline-block;
}

.st-1 { background: #fef3c7; color: #92400e; }
.st-2 { background: #e0f2fe; color: #0369a1; }
.st-3 { background: #f3e8ff; color: #6b21a8; }
.st-4 { background: #ecfdf5; color: #047857; }
</style>
