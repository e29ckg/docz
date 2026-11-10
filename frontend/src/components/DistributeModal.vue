<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { apiUrl, request } from '../api'
import { showAlert, showSuccess, showError, showWarning, showConfirm } from '../utils/alert'

interface UserItem {
  id: number
  username: string
  fullName: string
  depName: string
}

interface UserGroup {
  name: string
  users: UserItem[]
}

interface CategoryItem {
  id: number
  name: string
}

interface DocInfoData {
  id: number
  receiveNumber: string
  subject: string
  legacyStatus: number
  hasMainFile?: boolean
  attachments?: Array<{ id: number; name: string }>
}

const props = defineProps<{
  docId: number
}>()

const emit = defineEmits<{
  close: []
  distributed: [res: { docId: number; legacyStatus: number; recipientCount: number }]
}>()

const loading = ref(false)
const submitting = ref(false)
const error = ref('')

// Preview States
const showPreview = ref(true)
const selectedAttachmentId = ref<number | null>(null)
const activeRightTab = ref<'users' | 'categories'>('users')

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

function openFullscreen() {
  if (currentPreviewUrl.value) {
    window.open(currentPreviewUrl.value, '_blank')
  }
}

// ข้อมูลจาก API
const docInfo = ref<DocInfoData | null>(null)
const groups = ref<UserGroup[]>([])
const categories = ref<CategoryItem[]>([])

// State ที่ผู้ใช้เลือก
const selectedUserIds = ref<number[]>([])
const selectedCategoryIds = ref<number[]>([])
const catSearchQuery = ref('')

// Filter แฟ้มจัดเก็บตามคำค้น
const filteredCategories = computed(() => {
  const q = catSearchQuery.value.trim().toLowerCase()
  if (!q) return categories.value
  return categories.value.filter((c) => c.name.toLowerCase().includes(q))
})

// คำนวณจำนวนผู้ใช้ทั้งหมด
const allUserIds = computed(() => {
  const ids: number[] = []
  for (const g of groups.value) {
    for (const u of g.users) {
      ids.push(u.id)
    }
  }
  return ids
})

const isAllSelected = computed(() => {
  if (!allUserIds.value.length) return false
  return allUserIds.value.every((id) => selectedUserIds.value.includes(id))
})

function toggleSelectAll() {
  if (isAllSelected.value) {
    selectedUserIds.value = []
  } else {
    selectedUserIds.value = [...allUserIds.value]
  }
}

function isGroupAllSelected(group: UserGroup) {
  if (!group.users.length) return false
  return group.users.every((u) => selectedUserIds.value.includes(u.id))
}

function toggleGroup(group: UserGroup) {
  const groupUserIds = group.users.map((u) => u.id)
  const allIn = isGroupAllSelected(group)

  if (allIn) {
    // เอาออก
    selectedUserIds.value = selectedUserIds.value.filter((id) => !groupUserIds.includes(id))
  } else {
    // เพิ่มเข้าไป
    const set = new Set([...selectedUserIds.value, ...groupUserIds])
    selectedUserIds.value = Array.from(set)
  }
}

function toggleUser(userId: number) {
  const idx = selectedUserIds.value.indexOf(userId)
  if (idx > -1) {
    selectedUserIds.value.splice(idx, 1)
  } else {
    selectedUserIds.value.push(userId)
  }
}

function toggleCategory(catId: number) {
  const idx = selectedCategoryIds.value.indexOf(catId)
  if (idx > -1) {
    selectedCategoryIds.value.splice(idx, 1)
  } else {
    selectedCategoryIds.value.push(catId)
  }
}

async function loadData() {
  loading.value = true
  error.value = ''
  try {
    const res = await request<{
      data: {
        document: DocInfoData
        categories: CategoryItem[]
        selectedCategoryIds: number[]
        assignedUserIds: number[]
        groups: UserGroup[]
      }
    }>(`documents/${props.docId}/distribute-options`)

    docInfo.value = res.data.document
    categories.value = res.data.categories
    groups.value = res.data.groups

    // ค่าเริ่มต้นที่เคยเลือกไว้ (ถ้ามี)
    if (res.data.assignedUserIds?.length) {
      selectedUserIds.value = [...res.data.assignedUserIds]
    }
    if (res.data.selectedCategoryIds?.length) {
      selectedCategoryIds.value = [...res.data.selectedCategoryIds]
    }
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'ไม่สามารถโหลดข้อมูลจ่ายหนังสือได้'
  } finally {
    loading.value = false
  }
}

async function submitDistribute() {
  if (!selectedUserIds.value.length) {
    const confirmed = await showConfirm(
      'ยังไม่ได้เลือกผู้รับหนังสือ',
      'คุณยังไม่ได้เลือกผู้รับหนังสือเลย ต้องการจ่ายหนังสือโดยไม่ระบุผู้รับหรือไม่?',
      { confirmText: 'จ่ายหนังสือต่อไป', cancelText: 'กลับไปเลือกผู้รับ' }
    )
    if (!confirmed) {
      return
    }
  }

  submitting.value = true
  try {
    const res = await request<{
      data: { docId: number; legacyStatus: number; recipientCount: number }
      message: string
    }>(`documents/${props.docId}/distribute`, {}, {
      userIds: selectedUserIds.value,
      categoryIds: selectedCategoryIds.value,
    })

    const isEditing = docInfo.value?.legacyStatus === 4
    await showSuccess(
      'สำเร็จ',
      res.message || (isEditing ? 'บันทึกการแก้ไขการจ่ายหนังสือเรียบร้อยแล้ว' : 'จ่ายหนังสือเรียบร้อยแล้ว')
    )
    emit('distributed', res.data)
  } catch (err) {
    await showError('เกิดข้อผิดพลาด', err instanceof Error ? err.message : 'เกิดข้อผิดพลาดในการจ่ายหนังสือ')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="distribute-modal-backdrop" @click.self="emit('close')">
    <div class="distribute-modal-card" :class="{ 'with-preview-expanded': showPreview }">
      <header class="distribute-header">
        <div class="header-titles">
          <span class="eyebrow">{{ docInfo?.legacyStatus === 4 ? 'EDIT DOCUMENT DISTRIBUTION' : 'DOCUMENT DISTRIBUTION' }}</span>
          <h3>{{ docInfo?.legacyStatus === 4 ? '✏️ แก้ไขการจ่ายหนังสือและจัดเก็บเข้าแฟ้ม' : '📤 จ่ายหนังสือและจัดเก็บเข้าแฟ้ม' }}</h3>
          <p v-if="docInfo" class="doc-brief">
            <strong>เลขรับ {{ docInfo.receiveNumber }}</strong> — {{ docInfo.subject }}
          </p>
        </div>
        <div class="header-actions">
          <button
            type="button"
            class="btn-header-toggle"
            :class="{ active: showPreview }"
            :title="showPreview ? 'ซ่อนกรอบพรีวิวเอกสาร' : 'เปิดดูพรีวิวเอกสาร'"
            @click="showPreview = !showPreview"
          >
            {{ showPreview ? '👁️ ซ่อนพรีวิว' : '👁️ แสดงพรีวิวเอกสาร' }}
          </button>
          <button
            v-if="showPreview && (docInfo?.hasMainFile || selectedAttachmentId)"
            type="button"
            class="btn-header-external"
            title="เปิดดูไฟล์ในแท็บใหม่เต็มจอ"
            @click="openFullscreen"
          >
            ↗ เต็มจอ
          </button>
          <button class="btn-close" aria-label="ปิด" @click="emit('close')">✕</button>
        </div>
      </header>

      <div v-if="loading" class="distribute-loading">
        <span class="spinner">⏳</span> กำลังโหลดรายชื่อผู้ใช้และแฟ้มจัดเก็บ…
      </div>

      <div v-else-if="error" class="distribute-error">
        <p>{{ error }}</p>
        <button type="button" class="btn-retry" @click="loadData">ลองใหม่</button>
      </div>

      <div v-else class="distribute-body" :class="{ 'with-preview': showPreview }">
        <!-- ฝั่งซ้าย: PDF Viewer / Document Preview (เมื่อเปิดพรีวิว) -->
        <section v-if="showPreview" class="preview-col">
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
              v-for="att in docInfo?.attachments || []"
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
              v-if="docInfo?.hasMainFile || selectedAttachmentId"
              :src="currentPreviewUrl"
              class="pdf-frame"
              title="ตัวอย่างเอกสาร"
            ></iframe>
            <div v-else class="empty-file-notice">
              <span class="notice-icon">📄</span>
              <p>ไม่มีไฟล์หนังสือนำในระบบ</p>
            </div>
          </div>
        </section>

        <!-- ฝั่งขวา (หรือเนื้อหาหลักเมื่อปิดพรีวิว): ส่วนจ่ายหนังสือและจัดเก็บเข้าแฟ้ม -->
        <div class="controls-col" :class="{ 'as-tabs': showPreview }">
          <!-- เมื่อเปิดพรีวิว: แสดงแถบสลับแท็บระหว่างผู้รับหนังสือ กับแฟ้มจัดเก็บ -->
          <div v-if="showPreview" class="dist-tab-switcher">
            <button
              type="button"
              class="dist-tab-nav"
              :class="{ active: activeRightTab === 'users' }"
              @click="activeRightTab = 'users'"
            >
              👥 1. เลือกผู้รับหนังสือ
              <span class="tab-count-badge">{{ selectedUserIds.length }}</span>
            </button>
            <button
              type="button"
              class="dist-tab-nav"
              :class="{ active: activeRightTab === 'categories' }"
              @click="activeRightTab = 'categories'"
            >
              📁 2. จัดเก็บเข้าแฟ้ม
              <span class="tab-count-badge">{{ selectedCategoryIds.length }}</span>
            </button>
          </div>

          <!-- 1. เลือกผู้รับหนังสือแบ่งตามกลุ่มงาน -->
          <section
            v-show="!showPreview || activeRightTab === 'users'"
            class="recipients-col"
          >
            <div class="col-heading">
              <div>
                <h4>1. เลือกผู้รับหนังสือ</h4>
                <span class="col-subtitle">เลือกบุคลากรหรือกลุ่มงานที่ต้องการแจกจ่ายหนังสือถึง (เลือกแล้ว {{ selectedUserIds.length }} ท่าน)</span>
              </div>
              <button
                type="button"
                class="btn-select-all"
                @click="toggleSelectAll"
              >
                {{ isAllSelected ? 'ยกเลิกทั้งหมด' : '☑️ เลือกทั้งหมด' }}
              </button>
            </div>

            <div class="groups-accordion">
              <div
                v-for="group in groups"
                :key="group.name"
                class="group-section"
              >
                <div class="group-header" @click="toggleGroup(group)">
                  <label class="group-checkbox-label" @click.stop>
                    <input
                      type="checkbox"
                      :checked="isGroupAllSelected(group)"
                      @change="toggleGroup(group)"
                    />
                    <strong>{{ group.name }}</strong>
                  </label>
                  <span class="group-count">
                    {{ group.users.filter(u => selectedUserIds.includes(u.id)).length }} / {{ group.users.length }}
                  </span>
                </div>

                <div class="user-list">
                  <label
                    v-for="user in group.users"
                    :key="user.id"
                    class="user-item"
                    :class="{ selected: selectedUserIds.includes(user.id) }"
                  >
                    <input
                      type="checkbox"
                      :checked="selectedUserIds.includes(user.id)"
                      @change="toggleUser(user.id)"
                    />
                    <span class="user-name">{{ user.fullName }}</span>
                    <small v-if="user.depName" class="user-dep">({{ user.depName }})</small>
                  </label>
                </div>
              </div>
            </div>
          </section>

          <!-- 2. แฟ้มจัดเก็บเอกสาร -->
          <section
            v-show="!showPreview || activeRightTab === 'categories'"
            class="category-col"
          >
            <div class="col-heading">
              <div>
                <h4>2. จัดเก็บเข้าแฟ้ม</h4>
                <span class="col-subtitle">เลือกแฟ้มหมวดหมู่เอกสาร (เลือกแล้ว {{ selectedCategoryIds.length }} แฟ้ม)</span>
              </div>
            </div>

            <div class="cat-search-box">
              <span class="search-icon">🔍</span>
              <input
                v-model="catSearchQuery"
                placeholder="ค้นหาชื่อแฟ้ม…"
                class="cat-search-input"
              />
            </div>

            <div class="categories-scroll">
              <label
                v-for="cat in filteredCategories"
                :key="cat.id"
                class="cat-item"
                :class="{ selected: selectedCategoryIds.includes(cat.id) }"
              >
                <input
                  type="checkbox"
                  :checked="selectedCategoryIds.includes(cat.id)"
                  @change="toggleCategory(cat.id)"
                />
                <span class="cat-name">📁 {{ cat.name }}</span>
              </label>
            </div>
          </section>
        </div>
      </div>

      <footer class="distribute-footer">
        <div class="summary-info">
          <span>แจกจ่ายถึง <strong>{{ selectedUserIds.length }}</strong> ท่าน</span>
          <span>•</span>
          <span>จัดเก็บใน <strong>{{ selectedCategoryIds.length }}</strong> แฟ้ม</span>
        </div>
        <div class="footer-actions">
          <button type="button" class="btn-cancel" :disabled="submitting" @click="emit('close')">
            ยกเลิก
          </button>
          <button
            type="button"
            class="btn-submit-distribute"
            :disabled="submitting"
            @click="submitDistribute"
          >
            {{ submitting ? 'กำลังบันทึก…' : (docInfo?.legacyStatus === 4 ? '💾 บันทึกการแก้ไขการจ่าย' : '📨 บันทึกการจ่ายหนังสือ (เสร็จสิ้น)') }}
          </button>
        </div>
      </footer>
    </div>
  </div>
</template>

<style scoped>
.distribute-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.65);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 230;
  padding: 1.25rem;
}

.distribute-modal-card {
  background: #ffffff;
  border-radius: 10px;
  width: 100%;
  max-width: 980px;
  height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
  border-top: 5px solid #10b981;
  overflow: hidden;
  transition: max-width 0.25s ease;
}

.distribute-modal-card.with-preview-expanded {
  max-width: 1400px;
  width: 96vw;
  height: 92vh;
}

.distribute-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.btn-header-toggle {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  color: #047857;
  padding: 0.35rem 0.75rem;
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-header-toggle:hover {
  background: #ecfdf5;
  border-color: #a7f3d0;
}

.btn-header-toggle.active {
  background: #ecfdf5;
  border-color: #6ee7b7;
  color: #065f46;
}

.btn-header-external {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  color: #475569;
  padding: 0.35rem 0.65rem;
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
}

.btn-header-external:hover {
  background: #f1f5f9;
  color: #1e293b;
}

.header-titles .eyebrow {
  display: block;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 1.5px;
  color: #059669;
  margin-bottom: 2px;
}

.header-titles h3 {
  margin: 0;
  font-size: 1.2rem;
  color: #1e293b;
  font-weight: 700;
}

.doc-brief {
  margin: 4px 0 0;
  font-size: 0.85rem;
  color: #475569;
}

.btn-close {
  background: transparent;
  border: none;
  font-size: 1.25rem;
  color: #64748b;
  cursor: pointer;
  padding: 4px 8px;
}

.distribute-loading,
.distribute-error {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  color: #64748b;
  font-size: 0.95rem;
}

.distribute-body {
  flex: 1;
  display: grid;
  grid-template-columns: 1.5fr 1fr;
  overflow: hidden;
  divide-x: 1px solid #e2e8f0;
}

.distribute-body.with-preview {
  grid-template-columns: 1.15fr 1fr;
}

@media (max-width: 1024px) {
  .distribute-body.with-preview {
    grid-template-columns: 1fr;
  }
  .preview-col {
    display: none;
  }
}

/* Preview Column */
.preview-col {
  display: flex;
  flex-direction: column;
  background: #1e293b;
  border-right: 1px solid #cbd5e1;
  overflow: hidden;
}

.preview-tabs-bar {
  display: flex;
  align-items: center;
  gap: 4px;
  background: #0f172a;
  padding: 6px 10px;
  overflow-x: auto;
  border-bottom: 1px solid #334155;
}

.preview-tab-btn {
  background: #1e293b;
  color: #94a3b8;
  border: 1px solid #334155;
  padding: 5px 12px;
  border-radius: 6px;
  font-size: 0.78rem;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s ease;
}

.preview-tab-btn:hover {
  color: #f8fafc;
  background: #334155;
}

.preview-tab-btn.active {
  background: #059669;
  color: #ffffff;
  border-color: #10b981;
  font-weight: 600;
}

.iframe-container {
  flex: 1;
  width: 100%;
  height: 100%;
  position: relative;
  background: #334155;
}

.pdf-frame {
  width: 100%;
  height: 100%;
  border: none;
  background: #ffffff;
}

.empty-file-notice {
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
  gap: 0.5rem;
}

.notice-icon {
  font-size: 2.5rem;
}

/* Controls Column */
.controls-col {
  display: grid;
  grid-template-columns: 1.5fr 1fr;
  overflow: hidden;
  background: #ffffff;
  height: 100%;
}

.controls-col.as-tabs {
  display: flex;
  flex-direction: column;
}

.dist-tab-switcher {
  display: flex;
  background: #f1f5f9;
  border-bottom: 1px solid #e2e8f0;
  padding: 6px 12px 0;
  gap: 4px;
}

.dist-tab-nav {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 8px 12px;
  background: transparent;
  border: none;
  border-bottom: 3px solid transparent;
  color: #64748b;
  font-size: 0.88rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.dist-tab-nav:hover {
  color: #1e293b;
}

.dist-tab-nav.active {
  color: #059669;
  border-bottom-color: #059669;
  background: #ffffff;
  border-top-left-radius: 6px;
  border-top-right-radius: 6px;
}

.tab-count-badge {
  background: #e2e8f0;
  color: #334155;
  padding: 2px 7px;
  border-radius: 10px;
  font-size: 0.78rem;
  font-weight: 700;
}

.dist-tab-nav.active .tab-count-badge {
  background: #d1fae5;
  color: #047857;
}

.recipients-col,
.category-col {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  padding: 1rem 1.25rem;
}

.category-col {
  border-left: 1px solid #e2e8f0;
  background: #fafafa;
}

.col-heading {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 0.75rem;
}

.col-heading h4 {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 700;
  color: #1e293b;
}

.col-subtitle {
  font-size: 0.78rem;
  color: #64748b;
  display: block;
  margin-top: 2px;
}

.btn-select-all {
  background: #f1f5f9;
  border: 1px solid #cbd5e1;
  color: #334155;
  padding: 4px 10px;
  border-radius: 5px;
  font-size: 0.78rem;
  cursor: pointer;
  white-space: nowrap;
}

.btn-select-all:hover {
  background: #e2e8f0;
}

.groups-accordion {
  flex: 1;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-right: 6px;
}

.group-section {
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  background: #ffffff;
  overflow: visible;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.group-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #f1f5f9;
  padding: 10px 14px;
  cursor: pointer;
  border-bottom: 1px solid #e2e8f0;
}

.group-checkbox-label {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  font-size: 0.92rem;
  color: #0f172a;
}

.group-checkbox-label input[type="checkbox"] {
  width: 16px;
  height: 16px;
  cursor: pointer;
}

.group-count {
  font-size: 0.8rem;
  background: #e2e8f0;
  padding: 3px 9px;
  border-radius: 12px;
  color: #334155;
  font-weight: 700;
}

.user-list {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 6px 10px;
  background: #ffffff;
}

.user-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  border-radius: 5px;
  cursor: pointer;
  font-size: 0.88rem;
  line-height: 1.4;
  color: #1e293b;
  min-height: 38px;
  border: 1px solid transparent;
  transition: all 0.12s ease;
}

.user-item input[type="checkbox"] {
  width: 16px;
  height: 16px;
  margin: 0;
  flex-shrink: 0;
  cursor: pointer;
}

.user-item:hover {
  background: #f8fafc;
  border-color: #e2e8f0;
}

.user-item.selected {
  background: #ecfdf5;
  border-color: #a7f3d0;
  color: #065f46;
}

.user-name {
  font-weight: 600;
  color: #0f172a;
}

.user-item.selected .user-name {
  color: #047857;
}

.user-dep {
  font-size: 0.8rem;
  color: #64748b;
  margin-left: 4px;
}

/* Category column */
.cat-search-box {
  position: relative;
  margin-bottom: 0.75rem;
}

.cat-search-box .search-icon {
  position: absolute;
  left: 10px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 0.85rem;
}

.cat-search-input {
  width: 100%;
  height: 34px;
  padding: 0 10px 0 32px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 0.85rem;
  box-sizing: border-box;
}

.categories-scroll {
  flex: 1;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding-right: 4px;
}

.cat-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 7px 10px;
  border-radius: 5px;
  cursor: pointer;
  font-size: 0.84rem;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  transition: all 0.12s;
}

.cat-item:hover {
  background: #f8fafc;
}

.cat-item.selected {
  background: #f0fdf4;
  border-color: #86efac;
  color: #166534;
  font-weight: 600;
}

.distribute-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.85rem 1.5rem;
  border-top: 1px solid #e2e8f0;
  background: #ffffff;
}

.summary-info {
  display: flex;
  gap: 8px;
  align-items: center;
  font-size: 0.85rem;
  color: #475569;
}

.summary-info strong {
  color: #059669;
}

.footer-actions {
  display: flex;
  gap: 0.75rem;
}

.btn-cancel {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  color: #475569;
  padding: 0.5rem 1.25rem;
  border-radius: 6px;
  font-size: 0.88rem;
  cursor: pointer;
}

.btn-cancel:hover {
  background: #f1f5f9;
}

.btn-submit-distribute {
  background: #10b981;
  border: 1px solid #059669;
  color: #ffffff;
  padding: 0.5rem 1.5rem;
  border-radius: 6px;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  box-shadow: 0 2px 4px rgba(16, 185, 129, 0.25);
  transition: all 0.15s;
}

.btn-submit-distribute:hover:not(:disabled) {
  background: #059669;
  box-shadow: 0 4px 8px rgba(16, 185, 129, 0.35);
}

.btn-submit-distribute:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}
</style>
