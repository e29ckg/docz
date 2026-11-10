<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { request, type PendingSigningItem, type SignedHistoryItem } from '../api'
import SigningModal from './SigningModal.vue'
import { formatThaiDate, formatThaiDateTime } from '../utils/date'

const props = defineProps<{
  initialTab?: 'pending' | 'history'
}>()

const emit = defineEmits<{
  countChanged: [count: number]
}>()

const activeTab = ref<'pending' | 'returned' | 'history'>(
  props.initialTab === 'history' ? 'history' : 'pending'
)
const pendingList = ref<PendingSigningItem[]>([])
const historyList = ref<SignedHistoryItem[]>([])
const loading = ref(false)
const error = ref('')
const successMessage = ref('')

// รายการเอกสารที่ถูกตีกลับ
const returnedList = computed(() => pendingList.value.filter((item) => item.isReturned))
const returnedCount = computed(() => returnedList.value.length)
const normalPendingCount = computed(() => pendingList.value.filter((item) => !item.isReturned).length)

// ค้นหาและกรอง
const searchKeyword = ref('')
const priorityFilter = ref('')

// การเปิด Modal ลงนาม
const selectedDocId = ref<number | string | null>(null)
const showSigningModal = ref(false)

const filteredPending = computed(() => {
  return pendingList.value.filter((item) => {
    const q = searchKeyword.value.toLowerCase().trim()
    const matchQ =
      !q ||
      item.subject.toLowerCase().includes(q) ||
      item.receiveNumber.toLowerCase().includes(q) ||
      item.letterNumber.toLowerCase().includes(q)

    const matchP = !priorityFilter.value || item.priority === priorityFilter.value
    return matchQ && matchP
  })
})

const filteredReturned = computed(() => {
  return filteredPending.value.filter((item) => item.isReturned)
})

const filteredHistory = computed(() => {
  return historyList.value.filter((item) => {
    const q = searchKeyword.value.toLowerCase().trim()
    const matchQ =
      !q ||
      item.subject.toLowerCase().includes(q) ||
      item.receiveNumber.toLowerCase().includes(q) ||
      item.letterNumber.toLowerCase().includes(q)

    const matchP = !priorityFilter.value || item.priority === priorityFilter.value
    return matchQ && matchP
  })
})

async function loadPending() {
  loading.value = true
  error.value = ''
  try {
    const res = await request<{ data: PendingSigningItem[] }>('signing/pending')
    pendingList.value = res.data
    emit('countChanged', res.data.length)
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'ไม่สามารถโหลดรายการรอลงนามได้'
  } finally {
    loading.value = false
  }
}

async function loadHistory() {
  loading.value = true
  error.value = ''
  try {
    const res = await request<{ data: SignedHistoryItem[] }>('signing/history')
    historyList.value = res.data
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'ไม่สามารถโหลดประวัติการลงนามได้'
  } finally {
    loading.value = false
  }
}

function switchTab(tab: 'pending' | 'returned' | 'history') {
  activeTab.value = tab
  if (tab === 'pending' || tab === 'returned') {
    loadPending()
  } else {
    loadHistory()
  }
}

function openSign(docId: number | string) {
  selectedDocId.value = docId
  showSigningModal.value = true
}

function onSignedSuccess(data: { docId: number; message: string }) {
  showSigningModal.value = false
  selectedDocId.value = null
  successMessage.value = data.message || 'บันทึกการลงนามเรียบร้อยแล้ว'
  setTimeout(() => {
    successMessage.value = ''
  }, 6000)

  // รีเฟรชทั้งสองแท็บ
  loadPending()
  if (activeTab.value === 'history') {
    loadHistory()
  }
}

onMounted(() => {
  if (activeTab.value === 'pending') {
    loadPending()
  } else {
    loadHistory()
  }
})
</script>

<template>
  <div class="signing-workspace">
    <!-- Header -->
    <div class="page-heading">
      <div>
        <span class="eyebrow">DOCUMENT SIGNING WORKFLOW</span>
        <h1>การลงนามในเอกสาร</h1>
        <p class="muted">
          ตรวจสอบเนื้อหาเอกสาร บันทึกข้อความเกษียน และลงนามส่งต่อไปยังขั้นตอนถัดไป
        </p>
      </div>
      <div class="count-card">
        <strong>{{ pendingList.length }}</strong>
        <span>รายการรอลงนาม</span>
      </div>
    </div>

    <!-- Alert ข้อความสำเร็จ -->
    <div v-if="successMessage" class="alert-success-banner" role="status">
      <span>✅</span>
      <div>
        <strong>ดำเนินการสำเร็จ!</strong>
        <p>{{ successMessage }}</p>
      </div>
      <button type="button" @click="successMessage = ''">✕</button>
    </div>

    <!-- Navigation Tabs -->
    <div class="tabs-bar">
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'pending' }"
        @click="switchTab('pending')"
      >
        <span>✍️ รอลงนาม</span>
        <span v-if="normalPendingCount" class="badge-tab-count">{{ normalPendingCount }}</span>
      </button>
      <button
        class="tab-btn tab-btn-returned"
        :class="{ active: activeTab === 'returned' }"
        @click="switchTab('returned')"
      >
        <span>⛔ ถูกตีกลับ</span>
        <span v-if="returnedCount > 0" class="badge-tab-count badge-returned">{{ returnedCount }}</span>
      </button>
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'history' }"
        @click="switchTab('history')"
      >
        <span>📋 ประวัติการลงนาม</span>
      </button>
    </div>

    <!-- แถบแจ้งเตือนเอกสารถูกตีกลับ (In-App Notification) -->
    <div v-if="returnedCount > 0 && activeTab !== 'returned'" class="alert-returned-banner">
      <span class="banner-returned-icon">⚠️</span>
      <div class="banner-returned-text">
        <strong>คุณมีเอกสารที่ถูกตีกลับจำนวน {{ returnedCount }} ฉบับ</strong>
        <p>กรุณาตรวจสอบเหตุผลการตีกลับ ปรับปรุงแก้ไขไฟล์หรือข้อความ และลงนามเสนอใหม่อีกครั้ง</p>
      </div>
      <button type="button" class="btn-jump-returned" @click="switchTab('returned')">
        ดูเอกสารที่ถูกตีกลับ ({{ returnedCount }}) →
      </button>
    </div>

    <!-- Search & Filter Bar -->
    <section class="signing-card">
      <div class="filters-row">
        <div class="search-input-wrap">
          <span class="search-icon">🔍</span>
          <input
            v-model="searchKeyword"
            type="text"
            placeholder="ค้นหาเรื่อง เลขรับ หรือเลขที่หนังสือ..."
            class="search-input"
          />
        </div>
        <div class="filter-select-wrap">
          <select v-model="priorityFilter" class="select-priority">
            <option value="">ชั้นความเร็วทั้งหมด</option>
            <option value="ปกติ">ปกติ</option>
            <option value="ด่วน">ด่วน</option>
            <option value="ด่วนที่สุด">ด่วนที่สุด</option>
          </select>
        </div>
        <button
          type="button"
          class="btn-refresh"
          title="รีเฟรชรายการ"
          @click="activeTab === 'history' ? loadHistory() : loadPending()"
        >
          🔄 รีเฟรช
        </button>
      </div>

      <div v-if="loading" class="empty-state">
        <span class="spinner">⏳</span> กำลังโหลดข้อมูล...
      </div>

      <div v-else-if="error" class="error-state">
        {{ error }}
      </div>

      <!-- รายการแท็บ "ถูกตีกลับ" -->
      <template v-else-if="activeTab === 'returned'">
        <div v-if="!filteredReturned.length" class="empty-state">
          <span class="empty-icon">🎉</span>
          <h3>ไม่มีเอกสารถูกตีกลับในขณะนี้</h3>
          <p class="muted">เมื่อมีเอกสารถูกตีกลับมายังขั้นตอนของคุณ รายการจะปรากฏที่นี่</p>
        </div>

        <div v-else class="table-scroll">
          <table class="signing-table">
            <thead>
              <tr>
                <th style="width: 130px">เลขรับ / วันที่</th>
                <th>เรื่อง / เหตุผลที่ถูกตีกลับ</th>
                <th style="width: 170px">ขั้นตอนที่ต้องลงนาม</th>
                <th style="width: 130px">ความเร่งด่วน</th>
                <th style="width: 160px; text-align: center">การดำเนินการ</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in filteredReturned" :key="item.docManageId" class="row-returned-highlight">
                <td class="cell-receive">
                  <strong>{{ item.receiveNumber }}</strong>
                  <small class="muted">{{ formatThaiDate(item.receivedAt) }}</small>
                </td>
                <td class="cell-subject">
                  <div class="subject-title-line">
                    <span class="inline-returned-tag">⛔ ถูกตีกลับ</span>
                    <button class="btn-subject-link" @click="openSign(item.docUid || item.docId)">
                      {{ item.subject }}
                    </button>
                  </div>
                  <div v-if="item.returnReason" class="returned-reason-box">
                    <strong>เหตุผลตีกลับ:</strong> {{ item.returnReason }}
                    <span v-if="item.returnerName" class="returner-name">• โดย {{ item.returnerName }} ({{ item.returnerRole }})</span>
                  </div>
                  <small class="muted">
                    เลขที่: {{ item.letterNumber || '—' }} | เรียน: {{ item.docTo || '—' }}
                  </small>
                </td>
                <td>
                  <span class="role-badge">
                    <span class="step-num-circle">{{ item.stepOrder }}</span>
                    {{ item.roleName }}
                  </span>
                </td>
                <td>
                  <span
                    class="badge-priority"
                    :class="`p-${item.priority}`"
                  >
                    {{ item.priority || 'ปกติ' }}
                  </span>
                </td>
                <td style="text-align: center">
                  <button
                    type="button"
                    class="btn-action-returned"
                    @click="openSign(item.docUid || item.docId)"
                  >
                    ✏️ ตรวจสอบ & แก้ไข
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <!-- รายการแท็บ "รอลงนาม" -->
      <template v-else-if="activeTab === 'pending'">
        <div v-if="!filteredPending.filter((i) => !i.isReturned).length" class="empty-state">
          <span class="empty-icon">🎉</span>
          <h3>ไม่มีเอกสารรอลงนามปกติในขณะนี้</h3>
          <p class="muted">
            <template v-if="returnedCount > 0">
              คุณมีเอกสารถูกตีกลับ {{ returnedCount }} ฉบับ สามารถสลับไปดูที่แท็บ "⛔ ถูกตีกลับ" ได้
            </template>
            <template v-else>
              เมื่อมีหนังสือส่งมาถึงขั้นตอนของคุณ รายการจะปรากฏที่นี่
            </template>
          </p>
        </div>

        <div v-else class="table-scroll">
          <table class="signing-table">
            <thead>
              <tr>
                <th style="width: 130px">เลขรับ / วันที่</th>
                <th>เรื่อง / เลขที่หนังสือ</th>
                <th style="width: 170px">ขั้นตอนที่ต้องลงนาม</th>
                <th style="width: 130px">ความเร่งด่วน</th>
                <th style="width: 140px; text-align: center">การดำเนินการ</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in filteredPending.filter((i) => !i.isReturned)" :key="item.docManageId">
                <td class="cell-receive">
                  <strong>{{ item.receiveNumber }}</strong>
                  <small class="muted">{{ formatThaiDate(item.receivedAt) }}</small>
                </td>
                <td class="cell-subject">
                  <button class="btn-subject-link" @click="openSign(item.docUid || item.docId)">
                    {{ item.subject }}
                  </button>
                  <small class="muted">
                    เลขที่: {{ item.letterNumber || '—' }} | เรียน: {{ item.docTo || '—' }}
                  </small>
                </td>
                <td>
                  <span class="role-badge">
                    <span class="step-num-circle">{{ item.stepOrder }}</span>
                    {{ item.roleName }}
                  </span>
                </td>
                <td>
                  <span
                    class="badge-priority"
                    :class="`p-${item.priority}`"
                  >
                    {{ item.priority || 'ปกติ' }}
                  </span>
                </td>
                <td style="text-align: center">
                  <button
                    type="button"
                    class="btn-action-sign"
                    @click="openSign(item.docUid || item.docId)"
                  >
                    ✍️ ลงนาม / ตรวจสอบ
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <!-- รายการแท็บ "ประวัติการลงนาม" -->
      <template v-else>
        <div v-if="!filteredHistory.length" class="empty-state">
          <span class="empty-icon">📂</span>
          <h3>ยังไม่มีประวัติการลงนาม</h3>
          <p class="muted">รายการที่คุณได้ลงนามแล้วจะแสดงที่นี่</p>
        </div>

        <div v-else class="table-scroll">
          <table class="signing-table">
            <thead>
              <tr>
                <th style="width: 130px">เลขรับ / วันที่</th>
                <th>เรื่อง / เลขที่หนังสือ</th>
                <th style="width: 160px">บทบาทที่ลงนาม</th>
                <th>ข้อความเกษียน / ตรายาง</th>
                <th style="width: 150px">วันที่ลงนาม</th>
                <th style="width: 100px; text-align: center">ดูข้อมูล</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in filteredHistory" :key="item.docManageId">
                <td class="cell-receive">
                  <strong>{{ item.receiveNumber }}</strong>
                  <small class="muted">{{ formatThaiDate(item.receivedAt) }}</small>
                </td>
                <td class="cell-subject">
                  <button class="btn-subject-link" @click="openSign(item.docUid || item.docId)">
                    {{ item.subject }}
                  </button>
                  <small class="muted">
                    เลขที่: {{ item.letterNumber || '—' }} | เรียน: {{ item.docTo || '—' }}
                  </small>
                </td>
                <td>
                  <span class="role-badge done">
                    {{ item.roleName }}
                  </span>
                </td>
                <td>
                  <span v-if="item.stamp" class="stamp-chip">{{ item.stamp }}</span>
                  <small v-if="item.signDetail" class="sign-note">{{ item.signDetail }}</small>
                </td>
                <td>
                  <small>{{ formatThaiDateTime(item.signedAt) }}</small>
                </td>
                <td style="text-align: center">
                  <button
                    type="button"
                    class="btn-view-signed"
                    @click="openSign(item.docUid || item.docId)"
                  >
                    รายละเอียด ↗
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </section>

    <!-- Modal ลงนามในเอกสาร -->
    <SigningModal
      v-if="showSigningModal && selectedDocId"
      :doc-id="selectedDocId"
      @close="showSigningModal = false; selectedDocId = null"
      @signed="onSignedSuccess"
    />
  </div>
</template>

<style scoped>
.signing-workspace {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.page-heading {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 20px;
}

.eyebrow {
  display: block;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 2.1px;
  color: #0284c7;
  margin-bottom: 8px;
}

.page-heading h1 {
  margin: 0 0 6px 0;
  font-size: 2rem;
  font-weight: 700;
  color: #0f172a;
}

.count-card {
  border-left: 1px solid #dce4dc;
  padding: 0 24px;
  display: flex;
  flex-direction: column;
  min-width: 145px;
}

.count-card strong {
  font-size: 2.2857rem;
  font-weight: 700;
  color: #0284c7;
}

.count-card span {
  font-size: 0.7857rem;
  color: #64748b;
}

/* Alert Banner */
.alert-success-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  background: #e8f8f0;
  border: 1px solid #a3e0c0;
  border-radius: 8px;
  padding: 12px 18px;
  color: #175e3c;
}

.alert-success-banner p {
  margin: 0;
  font-size: 0.9286rem;
}

.alert-success-banner button {
  margin-left: auto;
  background: transparent;
  border: none;
  font-size: 1.1429rem;
  cursor: pointer;
  color: #175e3c;
}

/* Tabs */
.tabs-bar {
  display: flex;
  gap: 8px;
  border-bottom: 2px solid #e2e8f0;
}

.tab-btn {
  background: transparent;
  border: none;
  border-bottom: 3px solid transparent;
  padding: 10px 18px;
  font-size: 1rem;
  font-weight: 600;
  color: #64748b;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: -2px;
  transition: all 0.15s;
}

.tab-btn:hover {
  color: #0284c7;
}

.tab-btn.active {
  color: #0284c7;
  border-bottom-color: #0284c7;
}

.badge-tab-count {
  background: #ef4444;
  color: #ffffff;
  font-size: 0.7857rem;
  padding: 1px 7px;
  border-radius: 10px;
  font-weight: 700;
}

/* Signing Card */
.signing-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.filters-row {
  display: flex;
  gap: 12px;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
  align-items: center;
}

.search-input-wrap {
  flex: 1;
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 12px;
  font-size: 1rem;
  color: #94a3b8;
}

.search-input {
  width: 100%;
  padding: 8px 12px 8px 36px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 0.9286rem;
  outline: none;
}

.search-input:focus {
  border-color: #0284c7;
}

.select-priority {
  padding: 8px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 0.9286rem;
  background: #ffffff;
  outline: none;
}

.btn-refresh {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  padding: 8px 14px;
  font-size: 0.9286rem;
  cursor: pointer;
}

.btn-refresh:hover {
  background: #f1f5f9;
}

/* Table */
.table-scroll {
  overflow-x: auto;
}

.signing-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}

.signing-table th {
  background: #f8fafc;
  color: #475569;
  font-size: 0.8571rem;
  font-weight: 600;
  padding: 12px 16px;
  border-bottom: 1px solid #e2e8f0;
}

.signing-table td {
  padding: 14px 16px;
  border-bottom: 1px solid #f1f5f9;
  font-size: 0.9286rem;
  vertical-align: middle;
}

.signing-table tbody tr:hover {
  background: #f8fafc;
}

.cell-receive strong {
  display: block;
  font-size: 0.9286rem;
  color: #0f172a;
}

.cell-receive small {
  display: block;
  font-size: 0.7857rem;
  margin-top: 3px;
}

.btn-subject-link {
  background: transparent;
  border: none;
  padding: 0;
  text-align: left;
  font-weight: 600;
  font-size: 0.9286rem;
  color: #0f172a;
  cursor: pointer;
  line-height: 1.4;
}

.btn-subject-link:hover {
  color: #0284c7;
  text-decoration: underline;
}

.cell-subject small {
  display: block;
  font-size: 0.7857rem;
  margin-top: 4px;
}

.role-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #e0f2fe;
  color: #0369a1;
  border: 1px solid #bae6fd;
  border-radius: 20px;
  padding: 3px 10px;
  font-size: 0.8571rem;
  font-weight: 600;
}

.role-badge.done {
  background: #f1f5f9;
  color: #334155;
  border-color: #cbd5e1;
}

.step-num-circle {
  background: #0284c7;
  color: #ffffff;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  font-size: 0.78rem;
}

.badge-priority {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 4px;
  font-size: 0.7857rem;
  background: #f1f5f9;
  color: #475569;
}

.badge-priority.p-ด่วน,
.badge-priority.p-ด่วนที่สุด {
  background: #fee2e2;
  color: #b91c1c;
  font-weight: 600;
}

.btn-action-sign {
  background: #00c0ef;
  border: 1px solid #00acd6;
  color: #ffffff;
  padding: 6px 14px;
  border-radius: 6px;
  font-weight: 600;
  font-size: 0.8571rem;
  cursor: pointer;
  box-shadow: 0 1px 3px rgba(0, 192, 239, 0.3);
  transition: all 0.15s;
}

.btn-action-sign:hover {
  background: #00acd6;
  transform: translateY(-1px);
}

/* สไตล์สำหรับเอกสารถูกตีกลับ */
.alert-returned-banner {
  display: flex;
  align-items: center;
  gap: 14px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-left: 5px solid #ef4444;
  border-radius: 8px;
  padding: 12px 18px;
  margin-bottom: 16px;
  color: #991b1b;
  box-shadow: 0 2px 6px rgba(239, 68, 68, 0.08);
}

.banner-returned-icon {
  font-size: 1.7143rem;
  line-height: 1;
}

.banner-returned-text {
  flex: 1;
}

.banner-returned-text strong {
  display: block;
  font-size: 1rem;
  color: #b91c1c;
  margin-bottom: 2px;
}

.banner-returned-text p {
  margin: 0;
  font-size: 0.8929rem;
  color: #7f1d1d;
}

.btn-jump-returned {
  background: #dc2626;
  border: none;
  color: #ffffff;
  padding: 6px 14px;
  border-radius: 6px;
  font-size: 0.9286rem;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s ease;
}

.btn-jump-returned:hover {
  background: #b91c1c;
}

.badge-returned {
  background: #dc2626 !important;
  color: #ffffff !important;
}

.tab-btn-returned:hover {
  color: #dc2626;
}

.tab-btn-returned.active {
  border-color: #dc2626 !important;
  color: #dc2626 !important;
}

.row-returned-highlight {
  background: #fff8f8;
}

.row-returned-highlight:hover {
  background: #fef2f2 !important;
}

.subject-title-line {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.inline-returned-tag {
  display: inline-flex;
  align-items: center;
  background: #fee2e2;
  color: #b91c1c;
  border: 1px solid #fca5a5;
  font-size: 0.7857rem;
  font-weight: 700;
  padding: 1px 7px;
  border-radius: 4px;
  white-space: nowrap;
}

.returned-reason-box {
  background: #fff1f2;
  border: 1px dashed #fca5a5;
  border-radius: 4px;
  padding: 4px 10px;
  margin: 5px 0 3px 0;
  font-size: 0.8571rem;
  color: #991b1b;
  line-height: 1.4;
}

.returned-reason-box strong {
  color: #b91c1c;
}

.returner-name {
  color: #7f1d1d;
  font-size: 0.8214rem;
  margin-left: 4px;
}

.btn-action-returned {
  background: #dc2626;
  border: 1px solid #b91c1c;
  color: #ffffff;
  padding: 6px 14px;
  border-radius: 6px;
  font-weight: 600;
  font-size: 0.8571rem;
  cursor: pointer;
  box-shadow: 0 1px 3px rgba(220, 38, 38, 0.3);
  transition: all 0.15s;
  white-space: nowrap;
}

.btn-action-returned:hover {
  background: #b91c1c;
  transform: translateY(-1px);
}

.btn-view-signed {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  color: #334155;
  padding: 5px 12px;
  border-radius: 4px;
  font-size: 0.8571rem;
  cursor: pointer;
}

.btn-view-signed:hover {
  background: #f1f5f9;
}

.stamp-chip {
  display: block;
  font-size: 0.8571rem;
  font-weight: 600;
  color: #1e40af;
}

.sign-note {
  display: block;
  font-size: 0.7857rem;
  color: #64748b;
  margin-top: 2px;
}

.empty-state {
  text-align: center;
  padding: 4rem 1rem;
}

.empty-icon {
  font-size: 2.5rem;
  display: block;
  margin-bottom: 0.5rem;
}

.empty-state h3 {
  margin: 0 0 0.25rem 0;
  font-size: 1.1rem;
  color: #334155;
}

.error-state {
  color: #ef4444;
  padding: 2rem;
  text-align: center;
}
</style>
