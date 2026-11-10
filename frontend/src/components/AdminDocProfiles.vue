<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { request, type DocProfileItem, type RoleNameItem } from '../api'
import { showAlert, showSuccess, showError, showWarning, showConfirm } from '../utils/alert'

const profiles = ref<DocProfileItem[]>([])
const roleNames = ref<RoleNameItem[]>([])
const loading = ref(false)
const error = ref('')
const successMessage = ref('')

// Modal States
const showCreateModal = ref(false)
const showEditModal = ref(false)
const showAddStepModal = ref(false)
const submitting = ref(false)

// Form Models
const createForm = ref<{ code: string; name: string; selectedRoles: number[] }>({
  code: '',
  name: '',
  selectedRoles: [],
})

const editForm = ref<{ id: number; code: string; name: string }>({
  id: 0,
  code: '',
  name: '',
})

const activeProfile = ref<DocProfileItem | null>(null)
const selectedRoleIdForStep = ref<number | ''>('')
const selectedRoleForCreate = ref<number | ''>('')

async function loadData() {
  loading.value = true
  error.value = ''
  try {
    const [profileRes, rolesRes] = await Promise.all([
      request<{ data: DocProfileItem[] }>('admin/doc-profiles'),
      request<{ data: { roleNames: RoleNameItem[] } }>('admin/roles'),
    ])
    profiles.value = profileRes.data
    roleNames.value = rolesRes.data.roleNames
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'ไม่สามารถโหลดข้อมูลโปรไฟล์การลงนามได้'
  } finally {
    loading.value = false
  }
}

function notifySuccess(msg: string) {
  successMessage.value = msg
  setTimeout(() => {
    successMessage.value = ''
  }, 4000)
}

function openCreate() {
  createForm.value = {
    code: '',
    name: '',
    selectedRoles: [],
  }
  selectedRoleForCreate.value = roleNames.value[0]?.id || ''
  showCreateModal.value = true
}

function addRoleToCreate(roleId: number) {
  createForm.value.selectedRoles.push(roleId)
}

function removeRoleFromCreate(index: number) {
  createForm.value.selectedRoles.splice(index, 1)
}

function getRoleName(id: number) {
  return roleNames.value.find((r) => r.id === id)?.name || `บทบาท #${id}`
}

async function submitCreate() {
  if (!createForm.value.code || !createForm.value.name) {
    await showWarning('กรุณากรอกข้อมูล', 'กรุณากรอกรหัส Code และชื่อโปรไฟล์')
    return
  }
  submitting.value = true
  try {
    const res = await request<{ data: { message: string } }>('admin/doc-profiles/create', {}, {
      code: createForm.value.code.trim(),
      name: createForm.value.name.trim(),
      roleIds: createForm.value.selectedRoles,
    })
    showCreateModal.value = false
    await showSuccess('สำเร็จ', res.data.message)
    await loadData()
  } catch (e) {
    await showError('เกิดข้อผิดพลาด', e instanceof Error ? e.message : 'เกิดข้อผิดพลาดในการสร้างโปรไฟล์')
  } finally {
    submitting.value = false
  }
}

function openEdit(profile: DocProfileItem) {
  editForm.value = {
    id: profile.id,
    code: profile.code,
    name: profile.name,
  }
  showEditModal.value = true
}

async function submitEdit() {
  if (!editForm.value.code || !editForm.value.name) {
    await showWarning('ข้อมูลไม่ครบถ้วน', 'กรุณากรอกรหัส Code และชื่อโปรไฟล์')
    return
  }
  submitting.value = true
  try {
    const res = await request<{ data: { message: string } }>('admin/doc-profiles/update', {}, editForm.value)
    showEditModal.value = false
    await showSuccess('สำเร็จ', res.data.message)
    await loadData()
  } catch (e) {
    await showError('เกิดข้อผิดพลาด', e instanceof Error ? e.message : 'เกิดข้อผิดพลาดในการแก้ไขข้อมูล')
  } finally {
    submitting.value = false
  }
}

async function deleteProfile(profile: DocProfileItem) {
  const confirmed = await showConfirm(
    'ยืนยันการลบโปรไฟล์',
    `คุณต้องการลบโปรไฟล์ "${profile.name}" (${profile.code}) และขั้นตอนทั้งหมดหรือไม่?`,
    { isDanger: true }
  )
  if (!confirmed) return

  try {
    const res = await request<{ data: { message: string } }>('admin/doc-profiles/delete', {}, { id: profile.id })
    await showSuccess('สำเร็จ', res.data.message)
    await loadData()
  } catch (e) {
    await showError('เกิดข้อผิดพลาด', e instanceof Error ? e.message : 'เกิดข้อผิดพลาดในการลบโปรไฟล์')
  }
}

function openAddStep(profile: DocProfileItem) {
  activeProfile.value = profile
  selectedRoleIdForStep.value = roleNames.value[0]?.id || ''
  showAddStepModal.value = true
}

async function submitAddStep() {
  if (!activeProfile.value || !selectedRoleIdForStep.value) return
  submitting.value = true
  try {
    const res = await request<{ data: { message: string } }>('admin/doc-profiles/add-step', {}, {
      docProfileId: activeProfile.value.id,
      roleId: Number(selectedRoleIdForStep.value),
    })
    showAddStepModal.value = false
    await showSuccess('สำเร็จ', res.data.message)
    await loadData()
  } catch (e) {
    await showError('เกิดข้อผิดพลาด', e instanceof Error ? e.message : 'เกิดข้อผิดพลาดในการเพิ่มขั้นตอน')
  } finally {
    submitting.value = false
  }
}

async function removeStep(stepId: number, roleName: string) {
  const confirmed = await showConfirm(
    'ยืนยันการลบขั้นตอน',
    `คุณต้องการลบขั้นตอน "${roleName}" ออกจากโปรไฟล์นี้หรือไม่?`,
    { isDanger: true }
  )
  if (!confirmed) return

  try {
    const res = await request<{ data: { message: string } }>('admin/doc-profiles/remove-step', {}, { stepId })
    await showSuccess('สำเร็จ', res.data.message)
    await loadData()
  } catch (e) {
    await showError('เกิดข้อผิดพลาด', e instanceof Error ? e.message : 'เกิดข้อผิดพลาดในการลบขั้นตอน')
  }
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="admin-container">
    <div class="admin-header">
      <div>
        <span class="eyebrow">WORKFLOW & ENDORSEMENT</span>
        <h2>โปรไฟล์การลงนามในเอกสาร (Signing Profiles)</h2>
        <p class="muted">
          กำหนดลำดับขั้นตอนและบทบาทผู้มีอำนาจลงนาม/เกษียนในหนังสือแต่ละประเภท (เช่น หัวหน้ากลุ่มงาน ➔ ผู้อำนวยการ ➔ ผู้บริหารสูงสุด)
        </p>
      </div>
      <button class="primary add-button" @click="openCreate">
        <span aria-hidden="true">+</span> เพิ่มโปรไฟล์ใหม่
      </button>
    </div>

    <!-- Alert Messages -->
    <div v-if="successMessage" class="alert-success" role="alert">
      ✓ {{ successMessage }}
    </div>
    <div v-if="error" class="error inset" role="alert">
      {{ error }}
    </div>

    <div v-if="loading" class="empty-state">กำลังโหลดข้อมูลโปรไฟล์การลงนาม…</div>

    <!-- Profiles List -->
    <div v-else class="profiles-grid">
      <div v-for="profile in profiles" :key="profile.id" class="profile-card">
        <div class="profile-card-header">
          <div class="profile-title-area">
            <span class="code-badge">{{ profile.code }}</span>
            <h3>{{ profile.name }}</h3>
          </div>
          <div class="card-actions">
            <button class="btn-icon" title="แก้ไขชื่อ/รหัสโปรไฟล์" @click="openEdit(profile)">✎</button>
            <button class="btn-icon btn-delete" title="ลบโปรไฟล์นี้" @click="deleteProfile(profile)">🗑️</button>
          </div>
        </div>

        <div class="workflow-steps-container">
          <div class="workflow-label">
            <span>ลำดับขั้นตอนการลงนาม ({{ profile.steps.length }} ขั้นตอน):</span>
            <button class="btn-add-step" @click="openAddStep(profile)">+ เพิ่มผู้ลงนาม</button>
          </div>

          <div v-if="profile.steps.length" class="steps-flow">
            <div v-for="(step, idx) in profile.steps" :key="step.id" class="step-wrapper">
              <div class="step-badge">
                <span class="step-number">{{ idx + 1 }}</span>
                <span class="step-name">{{ step.roleName }}</span>
                <button
                  class="btn-remove-step"
                  title="ลบขั้นตอนนี้"
                  @click="removeStep(step.id, step.roleName)"
                >
                  ×
                </button>
              </div>
              <span v-if="idx < profile.steps.length - 1" class="step-arrow" aria-hidden="true">➔</span>
            </div>
          </div>
          <div v-else class="empty-steps">
            ยังไม่ได้กำหนดขั้นตอนการลงนาม (คลิก "+ เพิ่มผู้ลงนาม" ด้านบน)
          </div>
        </div>
      </div>

      <div v-if="!profiles.length" class="empty-state">
        <span class="empty-icon" aria-hidden="true">📝</span>
        <h3>ยังไม่มีโปรไฟล์การลงนาม</h3>
        <p>เริ่มต้นสร้างโปรไฟล์ เช่น "หนังสือถึงผู้บริหาร" หรือ "หนังสือถึงผู้อำนวยการ"</p>
      </div>
    </div>

    <!-- Modal Create -->
    <div v-if="showCreateModal" class="modal-overlay" @click.self="showCreateModal = false">
      <div class="modal-card">
        <header class="modal-header">
          <h3>เพิ่มโปรไฟล์การลงนามใหม่</h3>
          <button class="close-btn" @click="showCreateModal = false">×</button>
        </header>
        <form class="modal-body" @submit.prevent="submitCreate">
          <div class="form-row">
            <div class="form-group">
              <label>รหัสโปรไฟล์ (Code) *</label>
              <input v-model="createForm.code" required placeholder="เช่น DocZ, DocBoss" />
              <small class="muted">ใช้ระบุประเภทเอกสารในระบบ</small>
            </div>
            <div class="form-group">
              <label>ชื่อโปรไฟล์ *</label>
              <input v-model="createForm.name" required placeholder="เช่น หนังสือถึงผู้บริหาร" />
            </div>
          </div>

          <!-- Add Steps in Create -->
          <div class="form-group">
            <label>เลือกลำดับขั้นตอนผู้ลงนาม:</label>
            <div class="role-selector-bar">
              <select v-model="selectedRoleForCreate" class="role-select">
                <option v-for="r in roleNames" :key="r.id" :value="r.id">{{ r.name }}</option>
              </select>
              <button
                type="button"
                class="btn-secondary"
                :disabled="!selectedRoleForCreate"
                @click="selectedRoleForCreate && addRoleToCreate(Number(selectedRoleForCreate))"
              >
                + เพิ่มขั้นตอนนี้
              </button>
            </div>

            <!-- Preview Steps -->
            <div class="selected-roles-preview">
              <div v-if="!createForm.selectedRoles.length" class="muted text-sm">
                (ยังไม่ได้เพิ่มขั้นตอน สามารถเพิ่มภายหลังได้)
              </div>
              <div v-else class="preview-flow">
                <div v-for="(rId, idx) in createForm.selectedRoles" :key="idx" class="step-wrapper">
                  <div class="step-badge">
                    <span class="step-number">{{ idx + 1 }}</span>
                    <span>{{ getRoleName(rId) }}</span>
                    <button type="button" class="btn-remove-step" @click="removeRoleFromCreate(idx)">×</button>
                  </div>
                  <span v-if="idx < createForm.selectedRoles.length - 1" class="step-arrow">➔</span>
                </div>
              </div>
            </div>
          </div>

          <footer class="modal-footer">
            <button type="button" class="btn-cancel" @click="showCreateModal = false">ยกเลิก</button>
            <button type="submit" class="primary" :disabled="submitting">
              {{ submitting ? 'กำลังบันทึก…' : 'ยืนยันสร้างโปรไฟล์' }}
            </button>
          </footer>
        </form>
      </div>
    </div>

    <!-- Modal Edit -->
    <div v-if="showEditModal" class="modal-overlay" @click.self="showEditModal = false">
      <div class="modal-card modal-sm">
        <header class="modal-header">
          <h3>แก้ไขโปรไฟล์</h3>
          <button class="close-btn" @click="showEditModal = false">×</button>
        </header>
        <form class="modal-body" @submit.prevent="submitEdit">
          <div class="form-group">
            <label>รหัสโปรไฟล์ (Code) *</label>
            <input v-model="editForm.code" required />
          </div>
          <div class="form-group">
            <label>ชื่อโปรไฟล์ *</label>
            <input v-model="editForm.name" required />
          </div>
          <footer class="modal-footer">
            <button type="button" class="btn-cancel" @click="showEditModal = false">ยกเลิก</button>
            <button type="submit" class="primary" :disabled="submitting">
              {{ submitting ? 'กำลังบันทึก…' : 'บันทึกการแก้ไข' }}
            </button>
          </footer>
        </form>
      </div>
    </div>

    <!-- Modal Add Step -->
    <div v-if="showAddStepModal" class="modal-overlay" @click.self="showAddStepModal = false">
      <div class="modal-card modal-sm">
        <header class="modal-header">
          <h3>เพิ่มผู้ลงนามในขั้นตอนถัดไป</h3>
          <button class="close-btn" @click="showAddStepModal = false">×</button>
        </header>
        <form class="modal-body" @submit.prevent="submitAddStep">
          <p>
            โปรไฟล์: <strong>{{ activeProfile?.name }}</strong>
          </p>
          <div class="form-group">
            <label>เลือกบทบาทผู้ลงนาม *</label>
            <select v-model="selectedRoleIdForStep" required>
              <option v-for="r in roleNames" :key="r.id" :value="r.id">{{ r.name }}</option>
            </select>
          </div>
          <footer class="modal-footer">
            <button type="button" class="btn-cancel" @click="showAddStepModal = false">ยกเลิก</button>
            <button type="submit" class="primary" :disabled="submitting">
              {{ submitting ? 'กำลังเพิ่ม…' : 'ยืนยันเพิ่มขั้นตอน' }}
            </button>
          </footer>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admin-container {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.admin-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
}

.admin-header h2 {
  margin: 0.2rem 0;
  font-size: 1.5rem;
  color: #111827;
}

.add-button {
  padding: 0.6rem 1.2rem;
  font-size: 0.95rem;
}

.alert-success {
  background: #ecfdf5;
  color: #065f46;
  border: 1px solid #a7f3d0;
  border-radius: 6px;
  padding: 0.75rem 1rem;
  font-weight: 500;
}

.profiles-grid {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.profile-card {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 1.25rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.profile-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #f3f4f6;
  padding-bottom: 0.75rem;
}

.profile-title-area {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.code-badge {
  background: #eff6ff;
  color: #1d4ed8;
  font-size: 0.8rem;
  font-weight: 700;
  padding: 0.25rem 0.6rem;
  border-radius: 6px;
  border: 1px solid #bfdbfe;
}

.profile-title-area h3 {
  margin: 0;
  font-size: 1.2rem;
  color: #111827;
}

.card-actions {
  display: flex;
  gap: 0.4rem;
}

.btn-icon {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 0.35rem 0.6rem;
  cursor: pointer;
  font-size: 0.9rem;
}

.btn-icon:hover {
  background: #f3f4f6;
}

.btn-delete:hover {
  background: #fee2e2;
  border-color: #fca5a5;
}

.workflow-steps-container {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.workflow-label {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.88rem;
  color: #4b5563;
  font-weight: 600;
}

.btn-add-step {
  background: #f0fdf4;
  color: #16a34a;
  border: 1px solid #bbf7d0;
  border-radius: 6px;
  padding: 0.25rem 0.6rem;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
}

.btn-add-step:hover {
  background: #dcfce7;
}

.steps-flow {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.5rem;
  background: #f9fafb;
  padding: 0.85rem 1rem;
  border-radius: 8px;
  border: 1px solid #f3f4f6;
}

.step-wrapper {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.step-badge {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: #ffffff;
  border: 1px solid #d1d5db;
  border-radius: 20px;
  padding: 0.3rem 0.75rem;
  font-size: 0.88rem;
  color: #1f2937;
  font-weight: 500;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.step-number {
  background: #3b82f6;
  color: #ffffff;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.78rem;
  font-weight: 700;
}

.btn-remove-step {
  background: transparent;
  border: none;
  color: #9ca3af;
  font-size: 1.1rem;
  line-height: 1;
  cursor: pointer;
  padding: 0 0.2rem;
}

.btn-remove-step:hover {
  color: #ef4444;
}

.step-arrow {
  color: #9ca3af;
  font-weight: 700;
  font-size: 0.95rem;
}

.empty-steps {
  background: #f9fafb;
  padding: 1rem;
  border-radius: 8px;
  color: #9ca3af;
  font-size: 0.9rem;
  text-align: center;
}

/* Modals */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
  padding: 1rem;
}

.modal-card {
  background: #ffffff;
  border-radius: 10px;
  width: 100%;
  max-width: 600px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.modal-card.modal-sm {
  max-width: 440px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid #e5e7eb;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.15rem;
}

.close-btn {
  background: transparent;
  border: none;
  font-size: 1.4rem;
  cursor: pointer;
  color: #6b7280;
}

.modal-body {
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.form-group label {
  font-size: 0.85rem;
  font-weight: 600;
  color: #374151;
}

.form-group input,
.form-group select {
  padding: 0.5rem 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.9rem;
}

.role-selector-bar {
  display: flex;
  gap: 0.5rem;
}

.role-select {
  flex: 1;
}

.btn-secondary {
  background: #f3f4f6;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  padding: 0.5rem 0.85rem;
  cursor: pointer;
  font-size: 0.88rem;
  font-weight: 600;
}

.btn-secondary:hover {
  background: #e5e7eb;
}

.selected-roles-preview {
  background: #f9fafb;
  padding: 0.75rem;
  border-radius: 6px;
  border: 1px dashed #d1d5db;
  margin-top: 0.5rem;
}

.preview-flow {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  margin-top: 0.5rem;
}

.btn-cancel {
  background: #f3f4f6;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  padding: 0.5rem 1rem;
  cursor: pointer;
}
</style>
