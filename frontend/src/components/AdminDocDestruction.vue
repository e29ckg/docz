<template>
  <div class="admin-destruction-container">
    <!-- Header -->
    <div class="admin-header">
      <div class="header-left">
        <h1 class="header-title">🗑️ จัดการทำลายเอกสาร</h1>
        <p class="header-sub">
          ทำลายเอกสารตามระเบียบงานสารบรรณ พ.ศ. 2526 (ทำลายเป็นรายปีและรายเรื่อง) พร้อมระบบสืบค้นย้อนหลังได้ตลอดไป
        </p>
      </div>
      <div class="header-actions">
        <div class="count-card">
          <strong>{{ batches.length.toLocaleString('th-TH') }}</strong>
          <span>รอบการทำลาย</span>
        </div>
        <div class="count-card danger-card">
          <strong>{{ totalDestroyedCount.toLocaleString('th-TH') }}</strong>
          <span>ฉบับที่ทำลายแล้ว</span>
        </div>
      </div>
    </div>

    <!-- Main Navigation Tabs -->
    <div class="destruction-tabs">
      <button
        type="button"
        class="tab-btn"
        :class="{ active: currentTab === 'annual' }"
        @click="currentTab = 'annual'"
      >
        <span>📅</span> ทำลายเอกสารรายปี (Annual Batch)
      </button>
      <button
        type="button"
        class="tab-btn"
        :class="{ active: currentTab === 'batches' }"
        @click="currentTab = 'batches'"
      >
        <span>📑</span> ประวัติรอบการทำลาย ({{ batches.length }})
      </button>
      <button
        type="button"
        class="tab-btn"
        :class="{ active: currentTab === 'register' }"
        @click="currentTab = 'register'"
      >
        <span>📋</span> ทะเบียนหนังสือที่ถูกทำลาย
      </button>
    </div>

    <!-- TAB 1: ทำลายเอกสารรายปี -->
    <section v-if="currentTab === 'annual'" class="tab-section">
      <div class="info-alert-box">
        <span class="info-icon">ℹ️</span>
        <div>
          <strong>แนวทางปฏิบัติตามระเบียบงานสารบรรณ:</strong>
          ระบบจะจัดกลุ่มเอกสารตามปี พ.ศ. ของวันลงรับ ท่านสามารถเลือกปีที่ครบกำหนดอายุการเก็บรักษา (เช่น 5 ปี หรือ 10 ปี)
          เพื่อตรวจสอบรายชื่อ คัดเรื่องที่ต้องเก็บรักษาถาวรออก และยืนยันการทำลายทั้งชุดพร้อมระบุเลขที่คำสั่ง
        </div>
      </div>

      <div v-if="loadingYears" class="empty-state">
        <span class="spinner">⏳</span> กำลังวิเคราะห์ข้อมูลเอกสารแยกตามปี พ.ศ....
      </div>

      <div v-else-if="yearStats.length === 0" class="empty-state">
        <span class="empty-icon">📂</span> ไม่พบเอกสารที่รอการทำลายในระบบ
      </div>

      <div v-else class="years-grid">
        <div
          v-for="stat in yearStats"
          :key="stat.year"
          class="year-card"
          :class="{ 'card-eligible': (currentBYear - stat.year) >= 5 }"
        >
          <div class="year-card-header">
            <div class="year-title">
              <span class="calendar-icon">📅</span>
              <h3>พ.ศ. {{ stat.year }}</h3>
            </div>
            <span v-if="(currentBYear - stat.year) >= 10" class="age-badge age-10">
              ครบ 10 ปี ({{ currentBYear - stat.year }} ปี)
            </span>
            <span v-else-if="(currentBYear - stat.year) >= 5" class="age-badge age-5">
              ครบ 5 ปี ({{ currentBYear - stat.year }} ปี)
            </span>
            <span v-else class="age-badge age-recent">
              อายุ {{ currentBYear - stat.year }} ปี
            </span>
          </div>

          <div class="year-card-body">
            <div class="stat-row">
              <span class="stat-label">จำนวนเอกสารทั้งหมด:</span>
              <strong class="stat-value text-primary">{{ stat.totalCount.toLocaleString('th-TH') }} เรื่อง</strong>
            </div>
            <div class="stat-row">
              <span class="stat-label">ช่วงวันที่ลงรับ:</span>
              <span class="stat-sub">
                {{ formatShortDate(stat.oldestDate) }} - {{ formatShortDate(stat.newestDate) }}
              </span>
            </div>
          </div>

          <div class="year-card-footer">
            <button
              type="button"
              class="btn-select-year"
              @click="openAnnualProcess(stat.year)"
            >
              <span>📋</span> ตรวจสอบและสั่งทำลายปีนี้
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- TAB 2: ประวัติรอบการทำลาย -->
    <section v-if="currentTab === 'batches'" class="tab-section">
      <div class="card-toolbar">
        <div class="search-wrap">
          <span class="search-icon">🔍</span>
          <input
            v-model="batchSearch"
            type="text"
            placeholder="ค้นหาเลขที่รอบ, ชื่อรอบ, หรือเลขคำสั่ง..."
            class="search-input"
          />
        </div>
        <button type="button" class="btn-refresh" @click="loadBatches">
          🔄 รีเฟรช
        </button>
      </div>

      <div v-if="loadingBatches" class="empty-state">
        <span class="spinner">⏳</span> กำลังโหลดประวัติรอบการทำลาย...
      </div>

      <div v-else-if="filteredBatches.length === 0" class="empty-state">
        <span class="empty-icon">📑</span> ไม่พบข้อมูลประวัติรอบการทำลาย
      </div>

      <div v-else class="table-responsive">
        <table class="admin-table">
          <thead>
            <tr>
              <th style="width: 140px;">เลขที่รอบทำลาย</th>
              <th>ชื่อรอบการทำลาย</th>
              <th style="width: 110px; text-align: center;">ปีเป้าหมาย</th>
              <th style="width: 120px; text-align: center;">จำนวนเรื่อง</th>
              <th>เลขที่คำสั่ง / หนังสืออนุมัติ</th>
              <th>ผู้ทำรายการ</th>
              <th style="width: 150px;">วันเวลาที่ทำลาย</th>
              <th style="width: 110px; text-align: center;">จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in filteredBatches" :key="b.id">
              <td>
                <span class="batch-pill">{{ b.batchNumber }}</span>
              </td>
              <td>
                <strong>{{ b.title }}</strong>
                <p v-if="b.note" class="muted small-text">{{ b.note }}</p>
              </td>
              <td style="text-align: center;">
                <span v-if="b.targetYear" class="year-badge">พ.ศ. {{ b.targetYear }}</span>
                <span v-else class="muted">—</span>
              </td>
              <td style="text-align: center;">
                <strong class="text-danger">{{ b.totalDocs.toLocaleString('th-TH') }}</strong>
              </td>
              <td>
                <div v-if="b.orderNumber">คำสั่ง: <strong>{{ b.orderNumber }}</strong></div>
                <div v-if="b.approvalNumber" class="muted small-text">อนุมัติ: {{ b.approvalNumber }} ({{ b.approvedDate || '—' }})</div>
                <span v-if="!b.orderNumber && !b.approvalNumber" class="muted">—</span>
              </td>
              <td>{{ b.creatorName }}</td>
              <td>{{ b.createdAt }}</td>
              <td style="text-align: center;">
                <button
                  type="button"
                  class="btn-view-items"
                  title="ดูรายการเอกสารในรอบนี้"
                  @click="openBatchDetail(b)"
                >
                  👁️ รายการ
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- TAB 3: ทะเบียนหนังสือที่ถูกทำลาย (ค้นหาและดูย้อนหลัง) -->
    <section v-if="currentTab === 'register'" class="tab-section">
      <div class="card-toolbar">
        <div class="search-wrap">
          <span class="search-icon">🔍</span>
          <input
            v-model="destroyedSearch"
            type="text"
            placeholder="ค้นหาเลขรับ, เลขที่หนังสือ, เรื่อง, หรือรอบทำลาย..."
            class="search-input"
            @keyup.enter="loadDestroyedDocs(1)"
          />
        </div>
        <select v-model="selectedDestroyedYear" class="filter-select" @change="loadDestroyedDocs(1)">
          <option :value="null">ปีที่ทำลาย: ทั้งหมด</option>
          <option v-for="y in availableDestroyedYears" :key="y" :value="y">
            พ.ศ. {{ y }}
          </option>
        </select>
        <button type="button" class="btn-refresh" @click="loadDestroyedDocs(1)">
          🔍 ค้นหา
        </button>
      </div>

      <div v-if="loadingDestroyedDocs" class="empty-state">
        <span class="spinner">⏳</span> กำลังโหลดทะเบียนหนังสือที่ถูกทำลาย...
      </div>

      <div v-else-if="destroyedDocs.length === 0" class="empty-state">
        <span class="empty-icon">🗑️</span> ไม่พบรายการเอกสารที่ถูกทำลาย
      </div>

      <div v-else>
        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th style="width: 140px;">เลขรับเดิม</th>
                <th style="width: 140px;">เลขที่หนังสือ</th>
                <th>เรื่อง</th>
                <th>คำสั่ง / เหตุผลการทำลาย</th>
                <th style="width: 130px;">รอบการทำลาย</th>
                <th style="width: 150px;">วันเวลาที่ทำลาย</th>
                <th style="width: 100px; text-align: center;">สถานะไฟล์</th>
                <th style="width: 100px; text-align: center;">จัดการ</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="d in destroyedDocs" :key="d.id">
                <td>
                  <strong class="text-primary">{{ d.receiveNumber }}</strong>
                </td>
                <td>{{ d.letterNumber }}</td>
                <td>
                  <div class="doc-subject">{{ d.subject }}</div>
                </td>
                <td>
                  <div class="reason-text">{{ d.reason }}</div>
                  <small v-if="d.orderNumber" class="muted">คำสั่ง: {{ d.orderNumber }}</small>
                </td>
                <td>
                  <span class="batch-pill-sub">{{ d.batchNumber }}</span>
                </td>
                <td>
                  <div>{{ d.destroyedAt }}</div>
                  <small class="muted">โดย: {{ d.destroyedByName }}</small>
                </td>
                <td style="text-align: center;">
                  <span v-if="d.purgeFiles" class="badge-purge" title="ลบไฟล์ต้นฉบับออกจากดิสก์แล้ว">
                    🗑️ ลบไฟล์แล้ว
                  </span>
                  <span v-else class="badge-retained" title="ยังคงเก็บไฟล์ไว้">
                    💾 เก็บไฟล์
                  </span>
                </td>
                <td style="text-align: center;">
                  <button
                    type="button"
                    class="btn-view-doc"
                    title="เปิดดูประวัติและรายละเอียดของหนังสือ"
                    @click="$emit('view-document', d.docId, d.docUid)"
                  >
                    📄 ดูข้อมูล
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="destroyedMeta.pages > 1" class="pagination-bar">
          <span class="page-info">
            หน้าที่ {{ destroyedMeta.page }} / {{ destroyedMeta.pages }} (ทั้งหมด {{ destroyedMeta.total.toLocaleString('th-TH') }} เรื่อง)
          </span>
          <div class="page-buttons">
            <button
              type="button"
              class="btn-page"
              :disabled="destroyedMeta.page <= 1"
              @click="loadDestroyedDocs(destroyedMeta.page - 1)"
            >
              ◀ ก่อนหน้า
            </button>
            <button
              type="button"
              class="btn-page"
              :disabled="destroyedMeta.page >= destroyedMeta.pages"
              @click="loadDestroyedDocs(destroyedMeta.page + 1)"
            >
              ถัดไป ▶
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- MODAL 1: ขั้นตอนทำลายเอกสารประจำปี (Annual Destruction Wizard) -->
    <div v-if="showAnnualModal" class="modal-backdrop" @click.self="showAnnualModal = false">
      <div class="modal-dialog modal-xl">
        <div class="modal-header header-danger">
          <div>
            <h3>🗑️ จัดการทำลายเอกสารประจำปี พ.ศ. {{ activeYear }}</h3>
            <p class="modal-sub">
              ตรวจสอบรายการเอกสาร คัดเรื่องที่ต้องเก็บรักษาถาวรออก และระบุคำสั่งอนุมัติทำลาย
            </p>
          </div>
          <button type="button" class="btn-close" @click="showAnnualModal = false">✕</button>
        </div>

        <div class="modal-body">
          <!-- Step Indicator -->
          <div class="wizard-steps">
            <div class="wizard-step" :class="{ active: annualStep === 1 }">
              <span class="step-num">1</span>
              <span>เลือกเอกสาร ({{ selectedDocIds.length }}/{{ previewDocs.length }} เรื่อง)</span>
            </div>
            <div class="wizard-step" :class="{ active: annualStep === 2 }">
              <span class="step-num">2</span>
              <span>ระบุคำสั่งและยืนยันการทำลาย</span>
            </div>
          </div>

          <!-- STEP 1: ตรวจสอบและคัดกรอง -->
          <div v-if="annualStep === 1" class="step-pane">
            <div class="preview-toolbar">
              <div class="select-all-box">
                <label class="checkbox-label">
                  <input
                    type="checkbox"
                    :checked="isAllSelected"
                    @change="toggleSelectAll"
                  />
                  <span>เลือกทั้งหมด ({{ selectedDocIds.length }} เรื่อง)</span>
                </label>
              </div>
              <div class="preview-search">
                <input
                  v-model="previewSearch"
                  type="text"
                  placeholder="ค้นหาในรายการปีนี้..."
                  class="preview-search-input"
                />
              </div>
            </div>

            <div v-if="loadingPreview" class="empty-state">
              <span class="spinner">⏳</span> กำลังโหลดรายการเอกสาร...
            </div>

            <div v-else class="preview-table-wrap">
              <table class="preview-table">
                <thead>
                  <tr>
                    <th style="width: 45px; text-align: center;">เลือก</th>
                    <th style="width: 130px;">เลขรับ</th>
                    <th style="width: 130px;">เลขที่หนังสือ</th>
                    <th style="width: 100px;">วันที่ลงรับ</th>
                    <th>เรื่อง</th>
                    <th style="width: 80px; text-align: center;">ไฟล์</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="doc in filteredPreviewDocs"
                    :key="doc.id"
                    :class="{ 'row-selected': selectedDocIds.includes(doc.id) }"
                    @click="toggleDocSelection(doc.id)"
                  >
                    <td style="text-align: center;" @click.stop>
                      <input
                        type="checkbox"
                        :value="doc.id"
                        v-model="selectedDocIds"
                      />
                    </td>
                    <td><strong class="text-primary">{{ doc.receiveNumber }}</strong></td>
                    <td>{{ doc.letterNumber || '—' }}</td>
                    <td>{{ doc.receivedAt ? doc.receivedAt.slice(0, 10) : '—' }}</td>
                    <td>{{ doc.subject }}</td>
                    <td style="text-align: center;">
                      <span v-if="doc.hasMainFile" title="มีไฟล์ PDF">📎</span>
                      <span v-else class="muted">—</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- STEP 2: ระบุคำสั่งและอนุมัติ -->
          <div v-if="annualStep === 2" class="step-pane">
            <div class="confirm-summary-box">
              <h4>สรุปรายการที่จะทำลาย</h4>
              <p>
                เอกสารปี พ.ศ. <strong>{{ activeYear }}</strong> ที่เลือกทำลายจำนวน
                <strong class="text-danger">{{ selectedDocIds.length }} เรื่อง</strong>
              </p>
            </div>

            <div class="form-grid">
              <div class="form-group full-width">
                <label>ชื่อรอบการทำลาย <span class="required">*</span></label>
                <input
                  v-model="batchForm.title"
                  type="text"
                  class="form-control"
                  placeholder="เช่น ทำลายหนังสือราชการประจำปี พ.ศ. 2558"
                />
              </div>

              <div class="form-group">
                <label>เลขที่คำสั่งแต่งตั้งคณะกรรมการทำลาย</label>
                <input
                  v-model="batchForm.orderNumber"
                  type="text"
                  class="form-control"
                  placeholder="เช่น คำสั่งศาลฯ ที่ 15/2569"
                />
              </div>

              <div class="form-group">
                <label>เลขที่หนังสืออนุมัติให้ทำลาย</label>
                <input
                  v-model="batchForm.approvalNumber"
                  type="text"
                  class="form-control"
                  placeholder="เช่น หนังสือที่ ศย 001/2569"
                />
              </div>

              <div class="form-group">
                <label>วันที่ได้รับอนุมัติ</label>
                <input
                  v-model="batchForm.approvedDate"
                  type="date"
                  class="form-control"
                />
              </div>

              <div class="form-group">
                <label>การจัดการไฟล์ต้นฉบับบน Server</label>
                <div class="purge-switch-box">
                  <label class="switch-label">
                    <input type="checkbox" v-model="batchForm.purgeFiles" />
                    <span>ลบไฟล์เอกสารต้นฉบับออกจากดิสก์ทันที (แนะนำเพื่อคืนพื้นที่)</span>
                  </label>
                  <p class="muted small-text">
                    *ข้อมูลประวัติ ทะเบียนรับ สายการลงนาม และ SHA-256 Hash จะยังคงถูกเก็บรักษาไว้ 100% ตลอดไป
                  </p>
                </div>
              </div>

              <div class="form-group full-width">
                <label>หมายเหตุเพิ่มเติม</label>
                <textarea
                  v-model="batchForm.note"
                  rows="2"
                  class="form-control"
                  placeholder="ระบุข้อความหรือข้อมูลประกอบเพิ่มเติม..."
                ></textarea>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button
            v-if="annualStep === 2"
            type="button"
            class="btn-secondary"
            @click="annualStep = 1"
          >
            ◀ ย้อนกลับไปเลือกเอกสาร
          </button>
          <button
            v-if="annualStep === 1"
            type="button"
            class="btn-primary"
            :disabled="selectedDocIds.length === 0"
            @click="annualStep = 2"
          >
            ถัดไป: ระบุข้อมูลคำสั่ง ({{ selectedDocIds.length }} เรื่อง) ▶
          </button>
          <button
            v-if="annualStep === 2"
            type="button"
            class="btn-danger"
            :disabled="submittingBatch"
            @click="submitAnnualDestruction"
          >
            <span v-if="submittingBatch" class="spinner">⏳</span>
            <span v-else>🔥</span>
            ยืนยันการทำลายเอกสาร ({{ selectedDocIds.length }} เรื่อง)
          </button>
          <button type="button" class="btn-cancel" @click="showAnnualModal = false">
            ยกเลิก
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL 2: รายละเอียดรอบการทำลาย (Batch Items Detail Modal) -->
    <div v-if="showBatchDetailModal" class="modal-backdrop" @click.self="showBatchDetailModal = false">
      <div class="modal-dialog modal-xl">
        <div class="modal-header">
          <div>
            <h3>📑 รายละเอียดรอบการทำลาย: {{ selectedBatch?.batchNumber }}</h3>
            <p class="modal-sub">{{ selectedBatch?.title }}</p>
          </div>
          <button type="button" class="btn-close" @click="showBatchDetailModal = false">✕</button>
        </div>

        <div class="modal-body">
          <div class="batch-meta-summary">
            <div><strong>คำสั่ง:</strong> {{ selectedBatch?.orderNumber || '—' }}</div>
            <div><strong>หนังสืออนุมัติ:</strong> {{ selectedBatch?.approvalNumber || '—' }} ({{ selectedBatch?.approvedDate || '—' }})</div>
            <div><strong>ผู้ทำรายการ:</strong> {{ selectedBatch?.creatorName }} ({{ selectedBatch?.createdAt }})</div>
            <div><strong>จำนวนเอกสาร:</strong> <span class="text-danger">{{ selectedBatch?.totalDocs }} เรื่อง</span></div>
          </div>

          <div v-if="loadingBatchItems" class="empty-state">
            <span class="spinner">⏳</span> กำลังโหลดรายการเอกสารในรอบนี้...
          </div>

          <div v-else class="preview-table-wrap">
            <table class="preview-table">
              <thead>
                <tr>
                  <th style="width: 130px;">เลขรับ</th>
                  <th style="width: 130px;">เลขที่หนังสือ</th>
                  <th>เรื่อง</th>
                  <th style="width: 140px;">สถานะไฟล์</th>
                  <th style="width: 180px;">SHA-256 Checksum</th>
                  <th style="width: 90px; text-align: center;">ดูข้อมูล</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="it in batchItems" :key="it.id">
                  <td><strong class="text-primary">{{ it.receiveNumber }}</strong></td>
                  <td>{{ it.letterNumber || '—' }}</td>
                  <td>{{ it.subject }}</td>
                  <td>
                    <span v-if="it.purgeFiles" class="badge-purge">ลบไฟล์แล้ว</span>
                    <span v-else class="badge-retained">ยังเก็บไฟล์</span>
                  </td>
                  <td>
                    <code v-if="it.fileHash" class="hash-code" :title="it.fileHash">{{ it.fileHash.slice(0, 16) }}...</code>
                    <span v-else class="muted">—</span>
                  </td>
                  <td style="text-align: center;">
                    <button
                      type="button"
                      class="btn-view-doc"
                      @click="$emit('view-document', it.docId, it.docUid)"
                    >
                      📄
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-cancel" @click="showBatchDetailModal = false">ปิด</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import {
  fetchDestructionYears,
  fetchDestructionPreview,
  submitBatchDestruction,
  fetchDestructionHistory,
  fetchDestructionBatchDetail,
  fetchDestroyedDocuments,
  DestructionYearStat,
  DestructionPreviewItem,
  DestructionBatchItem,
  DestroyedDocItem,
  Page,
} from '../api'
import { showSuccess, showError, showConfirm } from '../utils/alert'

const props = defineProps<{
  initialTab?: 'annual' | 'batches' | 'register'
}>()

const emit = defineEmits<{
  (e: 'view-document', docId: number, docUid?: string): void
}>()

const currentBYear = new Date().getFullYear() + 543
const currentTab = ref<'annual' | 'batches' | 'register'>(props.initialTab || 'annual')

watch(
  () => props.initialTab,
  (newTab) => {
    if (newTab) {
      currentTab.value = newTab
    }
  }
)

// State for Tab 1: Annual Batch
const loadingYears = ref(false)
const yearStats = ref<DestructionYearStat[]>([])
const showAnnualModal = ref(false)
const annualStep = ref<1 | 2>(1)
const activeYear = ref<number>(currentBYear)
const loadingPreview = ref(false)
const previewDocs = ref<DestructionPreviewItem[]>([])
const selectedDocIds = ref<number[]>([])
const previewSearch = ref('')
const submittingBatch = ref(false)
const batchForm = ref({
  title: '',
  orderNumber: '',
  approvalNumber: '',
  approvedDate: '',
  purgeFiles: true,
  note: '',
})

// State for Tab 2: Batches History
const loadingBatches = ref(false)
const batches = ref<DestructionBatchItem[]>([])
const batchSearch = ref('')
const showBatchDetailModal = ref(false)
const selectedBatch = ref<DestructionBatchItem | null>(null)
const loadingBatchItems = ref(false)
const batchItems = ref<any[]>([])

// State for Tab 3: Destroyed Documents Register
const loadingDestroyedDocs = ref(false)
const destroyedDocs = ref<DestroyedDocItem[]>([])
const destroyedSearch = ref('')
const selectedDestroyedYear = ref<number | null>(null)
const destroyedMeta = ref<Page>({ page: 1, pageSize: 20, total: 0, pages: 1 })

const totalDestroyedCount = computed(() => {
  return batches.value.reduce((sum, b) => sum + (b.totalDocs || 0), 0)
})

const filteredBatches = computed(() => {
  if (!batchSearch.value.trim()) return batches.value
  const q = batchSearch.value.toLowerCase().trim()
  return batches.value.filter(
    (b) =>
      b.batchNumber.toLowerCase().includes(q) ||
      b.title.toLowerCase().includes(q) ||
      (b.orderNumber && b.orderNumber.toLowerCase().includes(q))
  )
})

const filteredPreviewDocs = computed(() => {
  if (!previewSearch.value.trim()) return previewDocs.value
  const q = previewSearch.value.toLowerCase().trim()
  return previewDocs.value.filter(
    (d) =>
      d.receiveNumber.toLowerCase().includes(q) ||
      d.subject.toLowerCase().includes(q) ||
      (d.letterNumber && d.letterNumber.toLowerCase().includes(q))
  )
})

const isAllSelected = computed(() => {
  return previewDocs.value.length > 0 && selectedDocIds.value.length === previewDocs.value.length
})

const availableDestroyedYears = computed(() => {
  const yrs = new Set<number>()
  for (const d of destroyedDocs.value) {
    if (d.destroyedAt) {
      const yr = new Date(d.destroyedAt).getFullYear() + 543
      if (yr) yrs.add(yr)
    }
  }
  return Array.from(yrs).sort((a, b) => b - a)
})

function formatShortDate(dateStr?: string | null): string {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  if (isNaN(d.getTime())) return dateStr
  const bYear = d.getFullYear() + 543
  return `${d.getDate()}/${d.getMonth() + 1}/${bYear}`
}

function toggleSelectAll() {
  if (isAllSelected.value) {
    selectedDocIds.value = []
  } else {
    selectedDocIds.value = previewDocs.value.map((d) => d.id)
  }
}

function toggleDocSelection(id: number) {
  const idx = selectedDocIds.value.indexOf(id)
  if (idx > -1) {
    selectedDocIds.value.splice(idx, 1)
  } else {
    selectedDocIds.value.push(id)
  }
}

async function loadYears() {
  loadingYears.value = true
  try {
    yearStats.value = await fetchDestructionYears()
  } catch (err: any) {
    showError(err.message || 'เกิดข้อผิดพลาดในการโหลดสถิติรายปี')
  } finally {
    loadingYears.value = false
  }
}

async function loadBatches() {
  loadingBatches.value = true
  try {
    batches.value = await fetchDestructionHistory()
  } catch (err: any) {
    showError(err.message || 'เกิดข้อผิดพลาดในการโหลดประวัติรอบการทำลาย')
  } finally {
    loadingBatches.value = false
  }
}

async function loadDestroyedDocs(page = 1) {
  loadingDestroyedDocs.value = true
  try {
    const res = await fetchDestroyedDocuments({
      q: destroyedSearch.value,
      year: selectedDestroyedYear.value || undefined,
      page,
      pageSize: 20,
    })
    destroyedDocs.value = res.data
    destroyedMeta.value = res.meta
  } catch (err: any) {
    showError(err.message || 'เกิดข้อผิดพลาดในการโหลดทะเบียนหนังสือที่ทำลาย')
  } finally {
    loadingDestroyedDocs.value = false
  }
}

async function openAnnualProcess(year: number) {
  activeYear.value = year
  annualStep.value = 1
  selectedDocIds.value = []
  previewSearch.value = ''
  batchForm.value = {
    title: `ทำลายหนังสือราชการประจำปี พ.ศ. ${year}`,
    orderNumber: '',
    approvalNumber: '',
    approvedDate: new Date().toISOString().slice(0, 10),
    purgeFiles: true,
    note: '',
  }
  showAnnualModal.value = true

  loadingPreview.value = true
  try {
    const res = await fetchDestructionPreview(year, '', 1, 500)
    previewDocs.value = res.data
    // ค่าเริ่มต้น เลือกทั้งหมด
    selectedDocIds.value = res.data.map((d) => d.id)
  } catch (err: any) {
    showError(err.message || 'เกิดข้อผิดพลาดในการโหลดรายการตัวอย่างเอกสาร')
    showAnnualModal.value = false
  } finally {
    loadingPreview.value = false
  }
}

async function submitAnnualDestruction() {
  if (!batchForm.value.title.trim()) {
    showError('กรุณาระบุชื่อรอบการทำลาย')
    return
  }
  if (selectedDocIds.value.length === 0) {
    showError('กรุณาเลือกเอกสารที่ต้องการทำลายอย่างน้อย 1 รายการ')
    return
  }

  const confirmed = await showConfirm(
    'ยืนยันการทำลายเอกสาร',
    `คุณกำลังจะสั่งทำลายเอกสารปี พ.ศ. ${activeYear.value} จำนวน ${selectedDocIds.value.length} เรื่อง\nระบบจะปรับสถานะเป็น "ทำลายแล้ว" และลบไฟล์ตามเงื่อนไขที่เลือก\nยืนยันหรือไม่?`,
    {
      confirmText: 'ยืนยันสั่งทำลาย',
      isDanger: true,
    }
  )

  if (!confirmed) return

  submittingBatch.value = true
  try {
    const res = await submitBatchDestruction({
      targetYear: activeYear.value,
      title: batchForm.value.title,
      orderNumber: batchForm.value.orderNumber,
      approvalNumber: batchForm.value.approvalNumber,
      approvedDate: batchForm.value.approvedDate,
      purgeFiles: batchForm.value.purgeFiles,
      note: batchForm.value.note,
      docIds: selectedDocIds.value,
    })

    showSuccess(res.message || 'บันทึกการทำลายเอกสารเรียบร้อยแล้ว')
    showAnnualModal.value = false
    await loadYears()
    await loadBatches()
    currentTab.value = 'batches'
  } catch (err: any) {
    showError(err.message || 'เกิดข้อผิดพลาดในการทำลายเอกสาร')
  } finally {
    submittingBatch.value = false
  }
}

async function openBatchDetail(b: DestructionBatchItem) {
  selectedBatch.value = b
  showBatchDetailModal.value = true
  loadingBatchItems.value = true
  try {
    const res = await fetchDestructionBatchDetail(b.uid || b.id)
    batchItems.value = res.items
  } catch (err: any) {
    showError(err.message || 'เกิดข้อผิดพลาดในการโหลดรายการเอกสารในรอบนี้')
  } finally {
    loadingBatchItems.value = false
  }
}

onMounted(() => {
  loadYears()
  loadBatches()
  loadDestroyedDocs()
})
</script>

<style scoped>
.admin-destruction-container {
  padding: 24px 32px;
  background: #0f172a;
  min-height: 100vh;
  color: #f1f5f9;
}

.admin-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.header-title {
  font-size: 1.6rem;
  font-weight: 700;
  color: #ffffff;
  margin: 0 0 6px 0;
}

.header-sub {
  color: #94a3b8;
  font-size: 0.92rem;
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 12px;
}

.count-card {
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 10px;
  padding: 10px 18px;
  text-align: center;
  display: flex;
  flex-direction: column;
}

.count-card strong {
  font-size: 1.4rem;
  color: #38bdf8;
}

.count-card span {
  font-size: 0.8rem;
  color: #94a3b8;
}

.danger-card strong {
  color: #ef4444;
}

/* Tabs */
.destruction-tabs {
  display: flex;
  gap: 8px;
  border-bottom: 2px solid #334155;
  margin-bottom: 24px;
}

.tab-btn {
  background: transparent;
  border: none;
  color: #94a3b8;
  padding: 12px 20px;
  font-size: 0.95rem;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  transition: all 0.15s ease;
}

.tab-btn:hover {
  color: #ffffff;
}

.tab-btn.active {
  color: #38bdf8;
  border-bottom-color: #38bdf8;
}

/* Alert Box */
.info-alert-box {
  background: rgba(56, 189, 248, 0.1);
  border: 1px solid rgba(56, 189, 248, 0.3);
  border-radius: 8px;
  padding: 14px 18px;
  margin-bottom: 24px;
  display: flex;
  gap: 14px;
  align-items: flex-start;
  color: #bae6fd;
  font-size: 0.92rem;
  line-height: 1.5;
}

.info-icon {
  font-size: 1.3rem;
  flex-shrink: 0;
}

/* Year Cards Grid */
.years-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 18px;
}

.year-card {
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 12px;
  padding: 18px;
  display: flex;
  flex-direction: column;
  transition: transform 0.15s ease, border-color 0.15s ease;
}

.year-card:hover {
  transform: translateY(-2px);
  border-color: #475569;
}

.card-eligible {
  border-left: 4px solid #ef4444;
}

.year-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 14px;
}

.year-title {
  display: flex;
  align-items: center;
  gap: 8px;
}

.year-title h3 {
  margin: 0;
  font-size: 1.25rem;
  color: #ffffff;
}

.age-badge {
  font-size: 0.78rem;
  padding: 3px 8px;
  border-radius: 12px;
  font-weight: 600;
}

.age-10 {
  background: rgba(239, 68, 68, 0.2);
  color: #f87171;
  border: 1px solid rgba(239, 68, 68, 0.4);
}

.age-5 {
  background: rgba(245, 158, 11, 0.2);
  color: #fbbf24;
  border: 1px solid rgba(245, 158, 11, 0.4);
}

.age-recent {
  background: rgba(100, 116, 139, 0.2);
  color: #94a3b8;
}

.year-card-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 16px;
}

.stat-row {
  display: flex;
  justify-content: space-between;
  font-size: 0.88rem;
}

.stat-label {
  color: #94a3b8;
}

.stat-value {
  font-weight: 700;
}

.stat-sub {
  color: #cbd5e1;
  font-size: 0.82rem;
}

.text-primary {
  color: #38bdf8;
}

.text-danger {
  color: #ef4444;
}

.btn-select-year {
  width: 100%;
  background: #0284c7;
  color: #ffffff;
  border: none;
  padding: 10px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  transition: background 0.15s ease;
}

.btn-select-year:hover {
  background: #0369a1;
}

/* Toolbar & Tables */
.card-toolbar {
  display: flex;
  gap: 12px;
  margin-bottom: 18px;
}

.search-wrap {
  flex: 1;
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 12px;
  color: #94a3b8;
}

.search-input {
  width: 100%;
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 8px;
  padding: 10px 14px 10px 38px;
  color: #f1f5f9;
  font-size: 0.92rem;
}

.search-input:focus {
  outline: none;
  border-color: #38bdf8;
}

.filter-select {
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 8px;
  color: #f1f5f9;
  padding: 0 14px;
}

.btn-refresh {
  background: #334155;
  border: none;
  color: #ffffff;
  padding: 0 16px;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
}

.btn-refresh:hover {
  background: #475569;
}

.table-responsive {
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 10px;
  overflow-x: auto;
}

.admin-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}

.admin-table th {
  background: #0f172a;
  color: #94a3b8;
  padding: 12px 14px;
  font-weight: 600;
  text-align: left;
  border-bottom: 1px solid #334155;
}

.admin-table td {
  padding: 12px 14px;
  border-bottom: 1px solid #293548;
  color: #e2e8f0;
}

.admin-table tbody tr:hover {
  background: #243044;
}

.batch-pill {
  background: rgba(56, 189, 248, 0.15);
  color: #38bdf8;
  padding: 3px 8px;
  border-radius: 6px;
  font-weight: 700;
  font-size: 0.82rem;
  border: 1px solid rgba(56, 189, 248, 0.3);
}

.batch-pill-sub {
  background: #334155;
  color: #cbd5e1;
  padding: 2px 7px;
  border-radius: 4px;
  font-size: 0.8rem;
}

.year-badge {
  background: #334155;
  color: #ffffff;
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 0.82rem;
}

.badge-purge {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 0.78rem;
  border: 1px solid rgba(239, 68, 68, 0.3);
}

.badge-retained {
  background: rgba(16, 185, 129, 0.15);
  color: #10b981;
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 0.78rem;
}

.btn-view-items,
.btn-view-doc {
  background: #334155;
  border: 1px solid #475569;
  color: #ffffff;
  padding: 5px 12px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.82rem;
  transition: all 0.12s ease;
}

.btn-view-items:hover,
.btn-view-doc:hover {
  background: #0284c7;
  border-color: #0284c7;
}

.doc-subject {
  font-weight: 500;
  max-width: 380px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.reason-text {
  max-width: 260px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pagination-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 18px;
  background: #1e293b;
  border-top: 1px solid #334155;
  border-radius: 0 0 10px 10px;
}

.btn-page {
  background: #334155;
  border: none;
  color: #ffffff;
  padding: 6px 14px;
  border-radius: 6px;
  margin-left: 6px;
  cursor: pointer;
}

.btn-page:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

/* Modals */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.75);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}

.modal-dialog {
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 14px;
  width: 100%;
  max-width: 600px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.modal-xl {
  max-width: 960px;
}

.modal-header {
  padding: 18px 24px;
  background: #0f172a;
  border-bottom: 1px solid #334155;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.header-danger {
  border-left: 6px solid #ef4444;
}

.modal-header h3 {
  margin: 0 0 4px 0;
  color: #ffffff;
}

.modal-sub {
  margin: 0;
  color: #94a3b8;
  font-size: 0.85rem;
}

.btn-close {
  background: transparent;
  border: none;
  color: #94a3b8;
  font-size: 1.2rem;
  cursor: pointer;
}

.btn-close:hover {
  color: #ffffff;
}

.modal-body {
  padding: 20px 24px;
  overflow-y: auto;
  flex: 1;
}

.modal-footer {
  padding: 14px 24px;
  background: #0f172a;
  border-top: 1px solid #334155;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

/* Wizard Steps */
.wizard-steps {
  display: flex;
  gap: 16px;
  margin-bottom: 20px;
  border-bottom: 1px solid #334155;
  padding-bottom: 14px;
}

.wizard-step {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #64748b;
  font-size: 0.9rem;
}

.wizard-step.active {
  color: #38bdf8;
  font-weight: 700;
}

.step-num {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #334155;
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
}

.wizard-step.active .step-num {
  background: #38bdf8;
  color: #0f172a;
}

/* Preview Table */
.preview-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  font-size: 0.9rem;
  font-weight: 600;
}

.preview-search-input {
  background: #0f172a;
  border: 1px solid #334155;
  border-radius: 6px;
  padding: 6px 12px;
  color: #ffffff;
  font-size: 0.88rem;
}

.preview-table-wrap {
  max-height: 400px;
  overflow-y: auto;
  border: 1px solid #334155;
  border-radius: 8px;
}

.preview-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.85rem;
}

.preview-table th {
  background: #0f172a;
  padding: 10px 12px;
  color: #94a3b8;
  text-align: left;
  position: sticky;
  top: 0;
  border-bottom: 1px solid #334155;
}

.preview-table td {
  padding: 8px 12px;
  border-bottom: 1px solid #293548;
}

.preview-table tr:hover {
  background: #243044;
  cursor: pointer;
}

.row-selected {
  background: rgba(56, 189, 248, 0.08);
}

/* Form Grid */
.confirm-summary-box {
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.3);
  padding: 14px 18px;
  border-radius: 8px;
  margin-bottom: 18px;
}

.confirm-summary-box h4 {
  margin: 0 0 6px 0;
  color: #f87171;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.full-width {
  grid-column: span 2;
}

.form-group label {
  display: block;
  font-size: 0.88rem;
  color: #cbd5e1;
  margin-bottom: 6px;
}

.form-control {
  width: 100%;
  background: #0f172a;
  border: 1px solid #334155;
  border-radius: 8px;
  padding: 9px 12px;
  color: #ffffff;
  font-size: 0.92rem;
}

.form-control:focus {
  outline: none;
  border-color: #38bdf8;
}

.purge-switch-box {
  background: #0f172a;
  border: 1px solid #334155;
  border-radius: 8px;
  padding: 10px 14px;
}

.switch-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
  font-size: 0.88rem;
  color: #ef4444;
  cursor: pointer;
}

.btn-primary {
  background: #0284c7;
  color: #ffffff;
  border: none;
  padding: 9px 18px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
}

.btn-primary:hover {
  background: #0369a1;
}

.btn-secondary {
  background: #334155;
  color: #ffffff;
  border: none;
  padding: 9px 16px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
}

.btn-danger {
  background: #dc2626;
  color: #ffffff;
  border: none;
  padding: 9px 18px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
}

.btn-danger:hover {
  background: #b91c1c;
}

.btn-cancel {
  background: transparent;
  color: #94a3b8;
  border: 1px solid #334155;
  padding: 9px 16px;
  border-radius: 8px;
  cursor: pointer;
}

.batch-meta-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
  background: #0f172a;
  border: 1px solid #334155;
  border-radius: 8px;
  padding: 12px 16px;
  margin-bottom: 18px;
  font-size: 0.9rem;
}

.hash-code {
  background: #0f172a;
  padding: 2px 6px;
  border-radius: 4px;
  font-family: inherit;
  font-size: 0.78rem;
  color: #38bdf8;
}

.empty-state {
  text-align: center;
  padding: 40px;
  color: #94a3b8;
}

.empty-icon {
  font-size: 2rem;
  display: block;
  margin-bottom: 8px;
}

.required {
  color: #ef4444;
}

.muted {
  color: #64748b;
}

.small-text {
  font-size: 0.8rem;
  margin: 4px 0 0 0;
}
</style>
