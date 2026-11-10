<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { request, type AdminUser, type RoleNameItem, type RolePowerItem } from '../api'
import { showAlert, showSuccess, showError, showWarning, showConfirm } from '../utils/alert'

const rolePowers = ref<RolePowerItem[]>([])
const roleNames = ref<RoleNameItem[]>([])
const users = ref<AdminUser[]>([])
const loading = ref(false)
const error = ref('')
const successMessage = ref('')

const showAssignModal = ref(false)
const showEditPowerModal = ref(false)
const showCreateRoleModal = ref(false)
const showEditRoleModal = ref(false)
const submitting = ref(false)

const assignForm = ref<{
  userId: number | ''
  roleId: number | ''
  name_dep1: string
  name_dep2: string
  name_dep3: string
}>({
  userId: '',
  roleId: '',
  name_dep1: '',
  name_dep2: '',
  name_dep3: '',
})

const editPowerForm = ref<{
  id: number
  userId: number
  fullName: string
  username: string
  userDepName: string
  roleId: number | ''
  name_dep1: string
  name_dep2: string
  name_dep3: string
}>({
  id: 0,
  userId: 0,
  fullName: '',
  username: '',
  userDepName: '',
  roleId: '',
  name_dep1: '',
  name_dep2: '',
  name_dep3: '',
})

const selectedAssignUser = computed(() => {
  return users.value.find((u) => u.id === assignForm.value.userId)
})

function generatePositionLines(userDepName: string, roleName: string, mode: 'substitute' | 'acting' | 'direct' = 'substitute') {
  const dep = (userDepName || '').trim()
  const r = (roleName || '').trim()

  if (mode === 'direct') {
    if (r.includes('ผู้อำนวยการ')) {
      return {
        dep1: 'ผู้อำนวยการสำนักงานประจำศาลเยาวชนและครอบครัวจังหวัดประจวบคีรีขันธ์',
        dep2: '',
        dep3: '',
      }
    }
    if (r.includes('ผู้พิพากษาหัวหน้าศาล')) {
      return {
        dep1: 'ผู้พิพากษาหัวหน้าศาลเยาวชนและครอบครัวจังหวัดประจวบคีรีขันธ์',
        dep2: '',
        dep3: '',
      }
    }
    return { dep1: r || dep, dep2: '', dep3: '' }
  }

  if (mode === 'substitute') {
    let targetDep2 = r
    let targetDep3 = ''
    if (r.includes('ผู้อำนวยการ')) {
      targetDep2 = 'ผู้อำนวยการสำนักงานประจำศาลเยาวชนและครอบครัว'
      targetDep3 = 'จังหวัดประจวบคีรีขันธ์'
    } else if (r.includes('ผู้พิพากษาหัวหน้าศาล')) {
      targetDep2 = 'ผู้พิพากษาหัวหน้าศาลเยาวชนและครอบครัว'
      targetDep3 = 'จังหวัดประจวบคีรีขันธ์'
    } else {
      targetDep2 = r
      targetDep3 = 'ศาลเยาวชนและครอบครัวจังหวัดประจวบคีรีขันธ์'
    }
    return {
      dep1: `${dep ? dep + ' ' : ''}รักษาราชการแทน`,
      dep2: targetDep2,
      dep3: targetDep3,
    }
  }

  if (mode === 'acting') {
    let targetDep2 = r
    let targetDep3 = ''
    if (r.includes('ผู้พิพากษาหัวหน้าศาล')) {
      targetDep2 = 'ทำการแทน ผู้พิพากษาหัวหน้าศาลเยาวชนและครอบครัว'
      targetDep3 = 'จังหวัดประจวบคีรีขันธ์'
    } else if (r.includes('ผู้อำนวยการ')) {
      targetDep2 = 'ปฏิบัติราชการแทน ผู้อำนวยการสำนักงานประจำศาลเยาวชนและครอบครัว'
      targetDep3 = 'จังหวัดประจวบคีรีขันธ์'
    } else {
      targetDep2 = `ปฏิบัติราชการแทน ${r}`
      targetDep3 = 'ศาลเยาวชนและครอบครัวจังหวัดประจวบคีรีขันธ์'
    }
    return {
      dep1: dep,
      dep2: targetDep2,
      dep3: targetDep3,
    }
  }

  return { dep1: dep, dep2: r, dep3: '' }
}

function onAssignSelectionChange() {
  const selectedUser = users.value.find((u) => u.id === assignForm.value.userId)
  const selectedRole = roleNames.value.find((r) => r.id === assignForm.value.roleId)
  if (!selectedUser || !selectedRole) return

  const userDep = (selectedUser.dep_name || '').trim()
  const roleTitle = (selectedRole.name || '').trim()

  const isSame = userDep && (userDep === roleTitle || roleTitle.includes(userDep) || userDep.includes(roleTitle))
  const lines = generatePositionLines(userDep, roleTitle, isSame ? 'direct' : 'substitute')
  assignForm.value.name_dep1 = lines.dep1
  assignForm.value.name_dep2 = lines.dep2
  assignForm.value.name_dep3 = lines.dep3
}

function applyAssignPreset(mode: 'substitute' | 'acting' | 'direct' | 'clear') {
  if (mode === 'clear') {
    assignForm.value.name_dep1 = ''
    assignForm.value.name_dep2 = ''
    assignForm.value.name_dep3 = ''
    return
  }
  const selectedUser = users.value.find((u) => u.id === assignForm.value.userId)
  const selectedRole = roleNames.value.find((r) => r.id === assignForm.value.roleId)
  const lines = generatePositionLines(selectedUser?.dep_name || '', selectedRole?.name || '', mode)
  assignForm.value.name_dep1 = lines.dep1
  assignForm.value.name_dep2 = lines.dep2
  assignForm.value.name_dep3 = lines.dep3
}

const createRoleForm = ref<{ name: string; sort: number }>({
  name: '',
  sort: 0,
})

const editRoleForm = ref<{ id: number; name: string; sort: number; status: number }>({
  id: 0,
  name: '',
  sort: 0,
  status: 1,
})

async function loadData() {
  loading.value = true
  error.value = ''
  try {
    const [rolesData, usersData] = await Promise.all([
      request<{ data: { roleNames: RoleNameItem[]; rolePowers: RolePowerItem[] } }>('admin/roles'),
      request<{ data: AdminUser[] }>('admin/users'),
    ])
    roleNames.value = rolesData.data.roleNames
    rolePowers.value = rolesData.data.rolePowers
    users.value = usersData.data.filter((u) => u.isActive)
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'ไม่สามารถโหลดข้อมูลสิทธิ์ได้'
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

function openAssign() {
  const initialUserId = users.value[0]?.id || ''
  const initialRoleId = roleNames.value[0]?.id || ''
  assignForm.value = {
    userId: initialUserId,
    roleId: initialRoleId,
    name_dep1: '',
    name_dep2: '',
    name_dep3: '',
  }
  onAssignSelectionChange()
  showAssignModal.value = true
}

async function submitAssign() {
  if (!assignForm.value.userId || !assignForm.value.roleId) {
    await showWarning('กรุณาเลือกข้อมูล', 'กรุณาเลือกผู้ใช้งานและสิทธิ์ที่ต้องการมอบหมาย')
    return
  }
  submitting.value = true
  try {
    const res = await request<{ data: { message: string } }>('admin/assign-role', {}, {
      userId: Number(assignForm.value.userId),
      roleId: Number(assignForm.value.roleId),
      name_dep1: assignForm.value.name_dep1,
      name_dep2: assignForm.value.name_dep2,
      name_dep3: assignForm.value.name_dep3,
    })
    showAssignModal.value = false
    await showSuccess('สำเร็จ', res.data.message)
    await loadData()
  } catch (e) {
    await showError('เกิดข้อผิดพลาด', e instanceof Error ? e.message : 'เกิดข้อผิดพลาดในการมอบหมายสิทธิ์')
  } finally {
    submitting.value = false
  }
}

function openEditPower(power: RolePowerItem) {
  editPowerForm.value = {
    id: power.id,
    userId: power.userId,
    fullName: power.fullName,
    username: power.username,
    userDepName: power.dep_name || '',
    roleId: power.roleId,
    name_dep1: power.name_dep1 || '',
    name_dep2: power.name_dep2 || '',
    name_dep3: power.name_dep3 || '',
  }
  showEditPowerModal.value = true
}

function onEditRoleChange() {
  const selectedRole = roleNames.value.find((r) => r.id === editPowerForm.value.roleId)
  if (!selectedRole) return

  const isSame =
    editPowerForm.value.userDepName &&
    (editPowerForm.value.userDepName === selectedRole.name ||
      selectedRole.name.includes(editPowerForm.value.userDepName))
  const lines = generatePositionLines(editPowerForm.value.userDepName, selectedRole.name, isSame ? 'direct' : 'substitute')
  editPowerForm.value.name_dep1 = lines.dep1
  editPowerForm.value.name_dep2 = lines.dep2
  editPowerForm.value.name_dep3 = lines.dep3
}

function applyEditPreset(mode: 'substitute' | 'acting' | 'direct' | 'clear') {
  if (mode === 'clear') {
    editPowerForm.value.name_dep1 = ''
    editPowerForm.value.name_dep2 = ''
    editPowerForm.value.name_dep3 = ''
    return
  }
  const selectedRole = roleNames.value.find((r) => r.id === editPowerForm.value.roleId)
  const lines = generatePositionLines(editPowerForm.value.userDepName, selectedRole?.name || '', mode)
  editPowerForm.value.name_dep1 = lines.dep1
  editPowerForm.value.name_dep2 = lines.dep2
  editPowerForm.value.name_dep3 = lines.dep3
}

async function submitEditPower() {
  if (!editPowerForm.value.id || !editPowerForm.value.roleId) {
    await showWarning('กรุณาเลือกข้อมูล', 'กรุณาระบุสิทธิ์การใช้งาน')
    return
  }
  submitting.value = true
  try {
    const res = await request<{ data: { message: string } }>('admin/update-role-power', {}, {
      id: editPowerForm.value.id,
      roleId: Number(editPowerForm.value.roleId),
      name_dep1: editPowerForm.value.name_dep1,
      name_dep2: editPowerForm.value.name_dep2,
      name_dep3: editPowerForm.value.name_dep3,
    })
    showEditPowerModal.value = false
    await showSuccess('สำเร็จ', res.data.message)
    await loadData()
  } catch (e) {
    await showError('เกิดข้อผิดพลาด', e instanceof Error ? e.message : 'เกิดข้อผิดพลาดในการแก้ไขข้อมูลสิทธิ์')
  } finally {
    submitting.value = false
  }
}

async function removeRole(power: RolePowerItem) {
  const confirmed = await showConfirm(
    'ยืนยันการลบสิทธิ์',
    `คุณต้องการลบสิทธิ์ "${power.roleName}" ของ "${power.fullName}" หรือไม่?`,
    { isDanger: true }
  )
  if (!confirmed) return

  try {
    const res = await request<{ data: { message: string } }>('admin/remove-role', {}, { id: power.id })
    await showSuccess('สำเร็จ', res.data.message)
    await loadData()
  } catch (e) {
    await showError('เกิดข้อผิดพลาด', e instanceof Error ? e.message : 'เกิดข้อผิดพลาดในการลบสิทธิ์')
  }
}

// ==============================
// จัดการชื่อสิทธิ์ (RoleName)
// ==============================
function openCreateRole() {
  createRoleForm.value = {
    name: '',
    sort: roleNames.value.length ? Math.max(...roleNames.value.map((r) => r.sort || 0)) + 1 : 1,
  }
  showCreateRoleModal.value = true
}

async function submitCreateRole() {
  if (!createRoleForm.value.name.trim()) {
    await showWarning('กรุณากรอกข้อมูล', 'กรุณากรอกชื่อสิทธิ์')
    return
  }
  submitting.value = true
  try {
    const res = await request<{ data: { message: string } }>('admin/roles/create', {}, {
      name: createRoleForm.value.name.trim(),
      sort: Number(createRoleForm.value.sort),
    })
    showCreateRoleModal.value = false
    await showSuccess('สำเร็จ', res.data.message)
    await loadData()
  } catch (e) {
    await showError('เกิดข้อผิดพลาด', e instanceof Error ? e.message : 'เกิดข้อผิดพลาดในการสร้างชื่อสิทธิ์')
  } finally {
    submitting.value = false
  }
}

function openEditRole(role: RoleNameItem) {
  editRoleForm.value = {
    id: role.id,
    name: role.name,
    sort: role.sort || 0,
    status: role.status,
  }
  showEditRoleModal.value = true
}

async function submitEditRole() {
  if (!editRoleForm.value.name.trim()) {
    await showWarning('กรุณากรอกข้อมูล', 'กรุณากรอกชื่อสิทธิ์')
    return
  }
  submitting.value = true
  try {
    const res = await request<{ data: { message: string } }>('admin/roles/update', {}, {
      id: editRoleForm.value.id,
      name: editRoleForm.value.name.trim(),
      sort: Number(editRoleForm.value.sort),
      status: Number(editRoleForm.value.status),
    })
    showEditRoleModal.value = false
    await showSuccess('สำเร็จ', res.data.message)
    await loadData()
  } catch (e) {
    await showError('เกิดข้อผิดพลาด', e instanceof Error ? e.message : 'เกิดข้อผิดพลาดในการแก้ไขชื่อสิทธิ์')
  } finally {
    submitting.value = false
  }
}

async function toggleRoleStatus(role: RoleNameItem) {
  if (role.id === 1) {
    await showWarning('ไม่สามารถดำเนินการได้', 'ไม่สามารถปิดการใช้งานสิทธิ์สารบรรณ/ผู้ดูแลระบบได้')
    return
  }
  const actionText = role.status === 1 ? 'ปิดการใช้งาน' : 'เปิดใช้งาน'
  const confirmed = await showConfirm(
    'ยืนยันเปลี่ยนสถานะ',
    `คุณต้องการ ${actionText} สิทธิ์ "${role.name}" หรือไม่?`,
    { isDanger: role.status === 1 }
  )
  if (!confirmed) return

  try {
    const res = await request<{ data: { message: string } }>('admin/roles/toggle-status', {}, { id: role.id })
    await showSuccess('สำเร็จ', res.data.message)
    await loadData()
  } catch (e) {
    await showError('เกิดข้อผิดพลาด', e instanceof Error ? e.message : 'เกิดข้อผิดพลาดในการเปลี่ยนสถานะสิทธิ์')
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
        <span class="eyebrow">ADMINISTRATION</span>
        <h2>กำหนดสิทธิ์การใช้งาน (Role & Permissions)</h2>
        <p class="muted">มอบหมายสิทธิ์ในการลงนาม เกษียนเอกสาร และหน้าที่รับผิดชอบตามขั้นตอน</p>
      </div>
      <button class="primary add-button" @click="openAssign">
        <span aria-hidden="true">+</span> มอบหมายสิทธิ์ให้สมาชิก
      </button>
    </div>

    <!-- Alert Messages -->
    <div v-if="successMessage" class="alert-success" role="alert">
      ✓ {{ successMessage }}
    </div>
    <div v-if="error" class="error inset" role="alert">
      {{ error }}
    </div>

    <div v-if="loading" class="empty-state">กำลังโหลดข้อมูลสิทธิ์…</div>

    <div v-else class="roles-layout">
      <!-- ผู้มีอำนาจ / ผู้ได้รับมอบหมายสิทธิ์ -->
      <section class="role-section">
        <div class="section-header">
          <h3>ผู้ได้รับมอบหมายสิทธิ์ (Role Assignments)</h3>
          <span class="badge-count">{{ rolePowers.length }} รายการ</span>
        </div>
        <div class="table-scroll">
          <table class="admin-table">
            <thead>
              <tr>
                <th style="width: 40px">#</th>
                <th style="width: 140px">สิทธิ์ / หน้าที่</th>
                <th>ผู้รับผิดชอบ</th>
                <th>ข้อความลงนาม (ท้ายเอกสาร)</th>
                <th style="width: 110px; text-align: center">จัดการ</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(power, idx) in rolePowers" :key="power.id">
                <td class="muted">{{ idx + 1 }}</td>
                <td>
                  <strong class="role-highlight">{{ power.roleName }}</strong>
                </td>
                <td>
                  <div class="user-info">
                    <strong>{{ power.fullName }}</strong>
                    <small class="muted">@{{ power.username }}</small>
                    <span v-if="power.dep_name" class="user-dep-tag">{{ power.dep_name }}</span>
                  </div>
                </td>
                <td>
                  <div v-if="power.name_dep1 || power.name_dep2 || power.name_dep3" class="sign-block-mini">
                    <div class="sign-line line-primary">{{ power.name_dep1 }}</div>
                    <div v-if="power.name_dep2" class="sign-line">{{ power.name_dep2 }}</div>
                    <div v-if="power.name_dep3" class="sign-line">{{ power.name_dep3 }}</div>
                  </div>
                  <span v-else class="sign-default-label">(ใช้ชื่อสิทธิ์ตามปกติ)</span>
                </td>
                <td style="text-align: center">
                  <div class="action-buttons-sm">
                    <button
                      class="btn-edit-sm"
                      title="แก้ไขสิทธิ์และข้อความลงนาม"
                      @click="openEditPower(power)"
                    >
                      ✏️ แก้ไข
                    </button>
                    <button
                      class="btn-delete"
                      title="ลบสิทธิ์นี้"
                      @click="removeRole(power)"
                    >
                      ลบ
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!rolePowers.length">
                <td colspan="5" class="empty-cell">ยังไม่มีข้อมูลการมอบหมายสิทธิ์</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- สิทธิ์ทั้งหมดในระบบ -->
      <section class="role-section">
        <div class="section-header">
          <div>
            <h3>สิทธิ์ทั้งหมดในระบบ (Roles)</h3>
            <span class="badge-count">{{ roleNames.length }} สิทธิ์</span>
          </div>
          <button class="btn-secondary-sm" @click="openCreateRole">
            + เพิ่มชื่อสิทธิ์
          </button>
        </div>
        <div class="table-scroll">
          <table class="admin-table">
            <thead>
              <tr>
                <th style="width: 50px">#</th>
                <th>ชื่อสิทธิ์</th>
                <th style="width: 70px; text-align: center">ลำดับ</th>
                <th style="width: 80px; text-align: center">สถานะ</th>
                <th style="width: 100px; text-align: center">จัดการ</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="rn in roleNames" :key="rn.id">
                <td class="muted">{{ rn.id }}</td>
                <td>
                  <strong>{{ rn.name }}</strong>
                </td>
                <td style="text-align: center" class="muted">{{ rn.sort || 0 }}</td>
                <td style="text-align: center">
                  <span class="status-pill" :class="rn.status === 1 ? 'active' : 'inactive'">
                    {{ rn.status === 1 ? 'เปิด' : 'ปิด' }}
                  </span>
                </td>
                <td style="text-align: center">
                  <div class="action-buttons-sm">
                    <button class="btn-icon" title="แก้ไขชื่อสิทธิ์" @click="openEditRole(rn)">✎</button>
                    <button
                      v-if="rn.id !== 1"
                      class="btn-toggle-sm"
                      :class="rn.status === 1 ? 'deactivate' : 'activate'"
                      :title="rn.status === 1 ? 'กดเพื่อปิดใช้งาน' : 'กดเพื่อเปิดใช้งาน'"
                      @click="toggleRoleStatus(rn)"
                    >
                      {{ rn.status === 1 ? 'ปิด' : 'เปิด' }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <!-- Assign Modal -->
    <div v-if="showAssignModal" class="modal-overlay" @click.self="showAssignModal = false">
      <div class="modal-card modal-lg">
        <header class="modal-header">
          <div>
            <h3>มอบหมายสิทธิ์ให้สมาชิก</h3>
            <p class="muted-header">กำหนดสิทธิ์และข้อความระบุตำแหน่งขณะลงนาม</p>
          </div>
          <button class="close-btn" @click="showAssignModal = false">×</button>
        </header>
        <form class="modal-body" @submit.prevent="submitAssign">
          <div class="form-group">
            <label>เลือกสมาชิกผู้รับผิดชอบ *</label>
            <select v-model="assignForm.userId" required @change="onAssignSelectionChange">
              <option value="" disabled>-- เลือกสมาชิก --</option>
              <option v-for="u in users" :key="u.id" :value="u.id">
                {{ u.fullName }} (@{{ u.username }}) {{ u.dep_name ? '- ' + u.dep_name : '' }}
              </option>
            </select>
          </div>

          <div class="form-group">
            <label>เลือกสิทธิ์ที่ต้องการมอบหมาย *</label>
            <select v-model="assignForm.roleId" required @change="onAssignSelectionChange">
              <option value="" disabled>-- เลือกสิทธิ์ --</option>
              <option v-for="rn in roleNames" :key="rn.id" :value="rn.id">
                {{ rn.name }} (ID: {{ rn.id }})
              </option>
            </select>
          </div>

          <!-- Signing Block Config -->
          <div class="signing-block-config">
            <div class="config-title-row">
              <label class="section-label">✍️ ข้อความระบุตำแหน่งขณะลงนาม (ท้ายเอกสาร/ใบปะหน้า)</label>
              <div class="preset-group">
                <button type="button" class="btn-preset" title="ตั้งค่าเป็น รักษาราชการแทน" @click="applyAssignPreset('substitute')">⚡ รักษาราชการแทน</button>
                <button type="button" class="btn-preset" title="ตั้งค่าเป็น ทำการแทน" @click="applyAssignPreset('acting')">⚡ ทำการแทน</button>
                <button type="button" class="btn-preset" title="ตั้งค่าเป็น ตำแหน่งตรงตามสิทธิ์" @click="applyAssignPreset('direct')">⚡ ตำแหน่งตรงตัวจริง</button>
                <button type="button" class="btn-preset btn-preset-clear" title="ล้างข้อความ" @click="applyAssignPreset('clear')">ล้าง</button>
              </div>
            </div>

            <div class="form-group">
              <label class="sub-label">ตำแหน่งบรรทัดที่ 1 (เช่น นิติกรชำนาญการพิเศษ รักษาราชการแทน)</label>
              <input v-model="assignForm.name_dep1" placeholder="ระบุตำแหน่งและสถานะรักษาราชการแทน..." />
            </div>

            <div class="form-group">
              <label class="sub-label">ตำแหน่งบรรทัดที่ 2 (เช่น ผู้อำนวยการสำนักงานประจำศาลเยาวชนและครอบครัว)</label>
              <input v-model="assignForm.name_dep2" placeholder="ระบุตำแหน่งของสิทธิ์ที่มอบหมาย (เว้นว่างได้ถ้าตำแหน่งตรง)..." />
            </div>

            <div class="form-group">
              <label class="sub-label">ตำแหน่งบรรทัดที่ 3 (เช่น จังหวัดประจวบคีรีขันธ์)</label>
              <input v-model="assignForm.name_dep3" placeholder="ระบุสังกัดหรือจังหวัด (เว้นว่างได้)..." />
            </div>

            <!-- Live Preview -->
            <div class="sign-preview-box">
              <div class="preview-tag">ตัวอย่างบล็อกลงนามที่จะปรากฏในเอกสาร:</div>
              <div class="preview-content">
                <div class="preview-sign-img">( ลายมือชื่อดิจิทัล )</div>
                <div class="preview-name">({{ selectedAssignUser?.fullName || 'ชื่อ - นามสกุล' }})</div>
                <div class="preview-dep1">{{ assignForm.name_dep1 || '—' }}</div>
                <div v-if="assignForm.name_dep2" class="preview-dep2">{{ assignForm.name_dep2 }}</div>
                <div v-if="assignForm.name_dep3" class="preview-dep3">{{ assignForm.name_dep3 }}</div>
                <div class="preview-date">1 พ.ย. 2569 (วันเวลาที่ลงนาม)</div>
              </div>
            </div>
          </div>

          <footer class="modal-footer">
            <button type="button" class="btn-cancel" @click="showAssignModal = false">ยกเลิก</button>
            <button type="submit" class="primary" :disabled="submitting">
              {{ submitting ? 'กำลังบันทึก…' : 'ยืนยันมอบหมายสิทธิ์' }}
            </button>
          </footer>
        </form>
      </div>
    </div>

    <!-- Edit Power Modal -->
    <div v-if="showEditPowerModal" class="modal-overlay" @click.self="showEditPowerModal = false">
      <div class="modal-card modal-lg">
        <header class="modal-header">
          <div>
            <h3>แก้ไขผู้ได้รับมอบหมายสิทธิ์</h3>
            <p class="muted-header">{{ editPowerForm.fullName }} (@{{ editPowerForm.username }})</p>
          </div>
          <button class="close-btn" @click="showEditPowerModal = false">×</button>
        </header>
        <form class="modal-body" @submit.prevent="submitEditPower">
          <div class="info-banner">
            <div><strong>ผู้รับผิดชอบ:</strong> {{ editPowerForm.fullName }}</div>
            <div><strong>ตำแหน่งปัจจุบัน:</strong> {{ editPowerForm.userDepName || '—' }}</div>
          </div>

          <div class="form-group">
            <label>สิทธิ์ / หน้าที่รับผิดชอบ *</label>
            <select v-model="editPowerForm.roleId" required @change="onEditRoleChange">
              <option v-for="rn in roleNames" :key="rn.id" :value="rn.id">
                {{ rn.name }} (ID: {{ rn.id }})
              </option>
            </select>
          </div>

          <!-- Signing Block Config -->
          <div class="signing-block-config">
            <div class="config-title-row">
              <label class="section-label">✍️ ข้อความระบุตำแหน่งขณะลงนาม (ท้ายเอกสาร/ใบปะหน้า)</label>
              <div class="preset-group">
                <button type="button" class="btn-preset" title="ตั้งค่าเป็น รักษาราชการแทน" @click="applyEditPreset('substitute')">⚡ รักษาราชการแทน</button>
                <button type="button" class="btn-preset" title="ตั้งค่าเป็น ทำการแทน" @click="applyEditPreset('acting')">⚡ ทำการแทน</button>
                <button type="button" class="btn-preset" title="ตั้งค่าเป็น ตำแหน่งตรงตามสิทธิ์" @click="applyEditPreset('direct')">⚡ ตำแหน่งตรงตัวจริง</button>
                <button type="button" class="btn-preset btn-preset-clear" title="ล้างข้อความ" @click="applyEditPreset('clear')">ล้าง</button>
              </div>
            </div>

            <div class="form-group">
              <label class="sub-label">ตำแหน่งบรรทัดที่ 1 (เช่น นิติกรชำนาญการพิเศษ รักษาราชการแทน)</label>
              <input v-model="editPowerForm.name_dep1" placeholder="ระบุตำแหน่งและสถานะรักษาราชการแทน..." />
            </div>

            <div class="form-group">
              <label class="sub-label">ตำแหน่งบรรทัดที่ 2 (เช่น ผู้อำนวยการสำนักงานประจำศาลเยาวชนและครอบครัว)</label>
              <input v-model="editPowerForm.name_dep2" placeholder="ระบุตำแหน่งของสิทธิ์ที่มอบหมาย (เว้นว่างได้ถ้าตำแหน่งตรง)..." />
            </div>

            <div class="form-group">
              <label class="sub-label">ตำแหน่งบรรทัดที่ 3 (เช่น จังหวัดประจวบคีรีขันธ์)</label>
              <input v-model="editPowerForm.name_dep3" placeholder="ระบุสังกัดหรือจังหวัด (เว้นว่างได้)..." />
            </div>

            <!-- Live Preview -->
            <div class="sign-preview-box">
              <div class="preview-tag">ตัวอย่างบล็อกลงนามที่จะปรากฏในเอกสาร:</div>
              <div class="preview-content">
                <div class="preview-sign-img">( ลายมือชื่อดิจิทัล )</div>
                <div class="preview-name">({{ editPowerForm.fullName }})</div>
                <div class="preview-dep1">{{ editPowerForm.name_dep1 || '—' }}</div>
                <div v-if="editPowerForm.name_dep2" class="preview-dep2">{{ editPowerForm.name_dep2 }}</div>
                <div v-if="editPowerForm.name_dep3" class="preview-dep3">{{ editPowerForm.name_dep3 }}</div>
                <div class="preview-date">1 พ.ย. 2569 (วันเวลาที่ลงนาม)</div>
              </div>
            </div>
          </div>

          <footer class="modal-footer">
            <button type="button" class="btn-cancel" @click="showEditPowerModal = false">ยกเลิก</button>
            <button type="submit" class="primary" :disabled="submitting">
              {{ submitting ? 'กำลังบันทึก…' : 'บันทึกการแก้ไข' }}
            </button>
          </footer>
        </form>
      </div>
    </div>

    <!-- Create Role Modal -->
    <div v-if="showCreateRoleModal" class="modal-overlay" @click.self="showCreateRoleModal = false">
      <div class="modal-card modal-sm">
        <header class="modal-header">
          <h3>เพิ่มชื่อสิทธิ์ใหม่</h3>
          <button class="close-btn" @click="showCreateRoleModal = false">×</button>
        </header>
        <form class="modal-body" @submit.prevent="submitCreateRole">
          <div class="form-group">
            <label>ชื่อสิทธิ์ (Role Name) *</label>
            <input v-model="createRoleForm.name" required placeholder="เช่น หัวหน้าฝ่ายอำนวยการ" />
          </div>
          <div class="form-group">
            <label>ลำดับการแสดงผล (Sort)</label>
            <input v-model="createRoleForm.sort" type="number" placeholder="เช่น 1, 2, 3" />
          </div>
          <footer class="modal-footer">
            <button type="button" class="btn-cancel" @click="showCreateRoleModal = false">ยกเลิก</button>
            <button type="submit" class="primary" :disabled="submitting">
              {{ submitting ? 'กำลังบันทึก…' : 'ยืนยันเพิ่มชื่อสิทธิ์' }}
            </button>
          </footer>
        </form>
      </div>
    </div>

    <!-- Edit Role Modal -->
    <div v-if="showEditRoleModal" class="modal-overlay" @click.self="showEditRoleModal = false">
      <div class="modal-card modal-sm">
        <header class="modal-header">
          <h3>แก้ไขชื่อสิทธิ์</h3>
          <button class="close-btn" @click="showEditRoleModal = false">×</button>
        </header>
        <form class="modal-body" @submit.prevent="submitEditRole">
          <div class="form-group">
            <label>ชื่อสิทธิ์ (Role Name) *</label>
            <input v-model="editRoleForm.name" required />
          </div>
          <div class="form-group">
            <label>ลำดับการแสดงผล (Sort)</label>
            <input v-model="editRoleForm.sort" type="number" />
          </div>
          <div class="form-group">
            <label>สถานะการใช้งาน</label>
            <select v-model="editRoleForm.status" :disabled="editRoleForm.id === 1">
              <option :value="1">เปิดใช้งาน (Active)</option>
              <option :value="0">ปิดการใช้งาน (Inactive)</option>
            </select>
            <small v-if="editRoleForm.id === 1" class="muted">สิทธิ์สารบรรณ/ผู้ดูแลระบบไม่สามารถปิดได้</small>
          </div>
          <footer class="modal-footer">
            <button type="button" class="btn-cancel" @click="showEditRoleModal = false">ยกเลิก</button>
            <button type="submit" class="primary" :disabled="submitting">
              {{ submitting ? 'กำลังบันทึก…' : 'บันทึกการแก้ไข' }}
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

.roles-layout {
  display: grid;
  grid-template-columns: 1.4fr 1fr;
  gap: 1.5rem;
  align-items: flex-start;
}

@media (max-width: 1024px) {
  .roles-layout {
    grid-template-columns: 1fr;
  }
}

.role-section {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.section-header h3 {
  margin: 0;
  font-size: 1.1rem;
  color: #1f2937;
}

.badge-count {
  background: #f3f4f6;
  color: #4b5563;
  padding: 0.2rem 0.6rem;
  border-radius: 9999px;
  font-size: 0.8rem;
  font-weight: 600;
}

.admin-table {
  width: 100%;
  border-collapse: collapse;
}

.admin-table th,
.admin-table td {
  padding: 0.75rem 0.85rem;
  border-bottom: 1px solid #e5e7eb;
  text-align: left;
  font-size: 0.88rem;
}

.admin-table th {
  background: #f9fafb;
  color: #4b5563;
  font-weight: 600;
}

.role-highlight {
  color: #1d4ed8;
  font-size: 0.92rem;
}

.user-info {
  display: flex;
  flex-direction: column;
}

.btn-delete {
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
  border-radius: 4px;
  padding: 0.3rem 0.6rem;
  font-size: 0.8rem;
  cursor: pointer;
  font-weight: 600;
}

.btn-delete:hover {
  background: #fee2e2;
}

.btn-edit-sm {
  background: #eff6ff;
  color: #2563eb;
  border: 1px solid #bfdbfe;
  border-radius: 4px;
  padding: 0.3rem 0.6rem;
  font-size: 0.8rem;
  cursor: pointer;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
}

.btn-edit-sm:hover {
  background: #dbeafe;
}

.user-dep-tag {
  display: inline-block;
  margin-top: 0.25rem;
  font-size: 0.78rem;
  color: #4b5563;
  background: #f3f4f6;
  padding: 0.1rem 0.4rem;
  border-radius: 4px;
  width: fit-content;
}

.sign-block-mini {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  font-size: 0.82rem;
  line-height: 1.3;
}

.sign-line {
  color: #4b5563;
}

.sign-line.line-primary {
  font-weight: 600;
  color: #1e3a8a;
}

.sign-default-label {
  font-size: 0.8rem;
  color: #9ca3af;
  font-style: italic;
}

.empty-cell {
  text-align: center;
  color: #9ca3af;
  padding: 2rem !important;
}

.status-pill {
  display: inline-block;
  padding: 0.2rem 0.5rem;
  border-radius: 9999px;
  font-size: 0.78rem;
  font-weight: 600;
}

.status-pill.active {
  background: #dcfce7;
  color: #15803d;
}

.status-pill.inactive {
  background: #fee2e2;
  color: #b91c1c;
}

.btn-secondary-sm {
  background: #f0fdf4;
  color: #16a34a;
  border: 1px solid #bbf7d0;
  border-radius: 6px;
  padding: 0.35rem 0.75rem;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
}

.btn-secondary-sm:hover {
  background: #dcfce7;
}

.action-buttons-sm {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
}

.btn-icon {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 4px;
  padding: 0.25rem 0.5rem;
  font-size: 0.82rem;
  cursor: pointer;
}

.btn-icon:hover {
  background: #f3f4f6;
}

.btn-toggle-sm {
  border: none;
  border-radius: 4px;
  padding: 0.25rem 0.5rem;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
}

.btn-toggle-sm.deactivate {
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
}

.btn-toggle-sm.deactivate:hover {
  background: #fee2e2;
}

.btn-toggle-sm.activate {
  background: #f0fdf4;
  color: #16a34a;
  border: 1px solid #86efac;
}

.btn-toggle-sm.activate:hover {
  background: #dcfce7;
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
  max-width: 500px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
}

.modal-card.modal-lg {
  max-width: 620px;
}

.muted-header {
  margin: 0.2rem 0 0 0;
  font-size: 0.82rem;
  color: #6b7280;
}

.info-banner {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 0.6rem 0.85rem;
  font-size: 0.85rem;
  color: #334155;
  display: flex;
  gap: 1.5rem;
}

.signing-block-config {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.config-title-row {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.section-label {
  font-size: 0.88rem;
  font-weight: 700;
  color: #1e293b;
}

.sub-label {
  font-size: 0.8rem;
  font-weight: 500;
  color: #475569;
}

.preset-group {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.btn-preset {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  padding: 0.25rem 0.55rem;
  font-size: 0.78rem;
  font-weight: 600;
  color: #2563eb;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-preset:hover {
  background: #eff6ff;
  border-color: #93c5fd;
}

.btn-preset-clear {
  color: #dc2626;
}

.btn-preset-clear:hover {
  background: #fef2f2;
  border-color: #fca5a5;
}

.sign-preview-box {
  margin-top: 0.25rem;
  background: #ffffff;
  border: 1px dashed #cbd5e1;
  border-radius: 6px;
  padding: 0.85rem;
}

.preview-tag {
  font-size: 0.78rem;
  font-weight: 600;
  color: #64748b;
  margin-bottom: 0.5rem;
  text-align: center;
}

.preview-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 0.2rem;
  color: #1e293b;
}

.preview-sign-img {
  font-size: 0.8rem;
  color: #94a3b8;
  font-style: italic;
  padding: 0.2rem 0;
}

.preview-name {
  font-weight: 700;
  font-size: 0.95rem;
  color: #0f172a;
}

.preview-dep1 {
  font-weight: 600;
  font-size: 0.85rem;
  color: #1e3a8a;
}

.preview-dep2,
.preview-dep3 {
  font-size: 0.83rem;
  color: #334155;
}

.preview-date {
  margin-top: 0.2rem;
  font-size: 0.78rem;
  color: #64748b;
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
  overflow-y: auto;
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

.form-group select,
.form-group input {
  padding: 0.55rem 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.9rem;
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
