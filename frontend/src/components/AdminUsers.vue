<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { request, uploadMedia, resolveMediaUrl, type AdminMeta, type AdminUser } from '../api'
import { showAlert, showSuccess, showError, showWarning, showConfirm } from '../utils/alert'

const users = ref<AdminUser[]>([])
const meta = ref<AdminMeta>({ pfnames: [], departments: [], groupWorks: [], roleNames: [] })
const loading = ref(false)
const search = ref('')
const filterStatus = ref<'all' | 'active' | 'inactive'>('all')
const error = ref('')
const successMessage = ref('')

// Modal States
const showCreateModal = ref(false)
const showEditModal = ref(false)
const showResetModal = ref(false)
const submitting = ref(false)

const uploadingPhoto = ref(false)
const uploadingSign = ref(false)
const photoInput = ref<HTMLInputElement | null>(null)
const signInput = ref<HTMLInputElement | null>(null)

// Form Models
const createForm = ref({
  username: '',
  password: 'password',
  pfname: '',
  name: '',
  sname: '',
  id_card: '',
  email: '',
  dep_name: '',
  group_work: '',
  phone: '',
  line_id: '',
})

const editForm = ref<{
  id: number
  username: string
  pfname: string
  name: string
  sname: string
  id_card: string
  email: string
  dep_name: string
  group_work: string
  phone: string
  line_id: string
  photo: string
  sign_photo: string
}>({
  id: 0,
  username: '',
  pfname: '',
  name: '',
  sname: '',
  id_card: '',
  email: '',
  dep_name: '',
  group_work: '',
  phone: '',
  line_id: '',
  photo: '',
  sign_photo: '',
})

const resetForm = ref<{ id: number; username: string; fullName: string; newPassword: '' }>({
  id: 0,
  username: '',
  fullName: '',
  newPassword: '',
})

async function loadData() {
  loading.value = true
  error.value = ''
  try {
    const [userData, metaData] = await Promise.all([
      request<{ data: AdminUser[] }>('admin/users'),
      request<{ data: AdminMeta }>('admin/meta'),
    ])
    users.value = userData.data
    meta.value = metaData.data
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'ไม่สามารถโหลดข้อมูลสมาชิกได้'
  } finally {
    loading.value = false
  }
}

function formatIdCard(id?: string) {
  if (!id) return ''
  const clean = id.replace(/\D/g, '')
  if (clean.length === 13) {
    return `${clean.slice(0, 1)}-${clean.slice(1, 5)}-${clean.slice(5, 10)}-${clean.slice(10, 12)}-${clean.slice(12)}`
  }
  return id
}

const filteredUsers = computed(() => {
  const q = search.value.trim().toLowerCase()
  return users.value.filter((u) => {
    if (filterStatus.value === 'active' && !u.isActive) return false
    if (filterStatus.value === 'inactive' && u.isActive) return false
    if (!q) return true
    return (
      u.fullName.toLowerCase().includes(q) ||
      u.username.toLowerCase().includes(q) ||
      (u.id_card && u.id_card.replace(/[-\s]/g, '').includes(q.replace(/[-\s]/g, ''))) ||
      u.dep_name.toLowerCase().includes(q) ||
      u.group_work.toLowerCase().includes(q) ||
      u.phone.includes(q)
    )
  })
})

function notifySuccess(msg: string) {
  successMessage.value = msg
  setTimeout(() => {
    successMessage.value = ''
  }, 4000)
}

function openCreate() {
  createForm.value = {
    username: '',
    password: 'password',
    pfname: meta.value.pfnames[0] || 'นาย',
    name: '',
    sname: '',
    id_card: '',
    email: '',
    dep_name: meta.value.departments[0] || '',
    group_work: meta.value.groupWorks[0] || '',
    phone: '',
    line_id: '',
  }
  showCreateModal.value = true
}

async function submitCreate() {
  if (!createForm.value.username || !createForm.value.name || !createForm.value.password) {
    await showWarning('กรุณากรอกข้อมูล', 'กรุณากรอก Username, Password และชื่อจริง')
    return
  }
  submitting.value = true
  try {
    await request('admin/create-user', {}, createForm.value)
    showCreateModal.value = false
    await showSuccess('สำเร็จ', `เพิ่มสมาชิก ${createForm.value.username} เรียบร้อยแล้ว`)
    await loadData()
  } catch (e) {
    await showError('เกิดข้อผิดพลาด', e instanceof Error ? e.message : 'เกิดข้อผิดพลาดในการสร้างสมาชิก')
  } finally {
    submitting.value = false
  }
}

function openEdit(user: AdminUser) {
  editForm.value = {
    id: user.id,
    username: user.username,
    pfname: user.pfname,
    name: user.name,
    sname: user.sname,
    id_card: user.id_card || '',
    email: user.email,
    dep_name: user.dep_name,
    group_work: user.group_work,
    phone: user.phone,
    line_id: user.line_id,
    photo: user.photo || '',
    sign_photo: user.sign_photo || '',
  }
  showEditModal.value = true
}

function triggerPhotoUpload() {
  photoInput.value?.click()
}

async function onPhotoFileChange(event: Event) {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file || !editForm.value.id) return

  uploadingPhoto.value = true
  try {
    const fd = new FormData()
    fd.append('userId', String(editForm.value.id))
    fd.append('type', 'photo')
    fd.append('file', file)

    const res = await uploadMedia<{ data: { url: string; message: string } }>('admin/upload-user-media', fd)
    editForm.value.photo = res.data.url
    const u = users.value.find((x) => x.id === editForm.value.id)
    if (u) u.photo = res.data.url
    await showSuccess('สำเร็จ', res.data.message)
  } catch (e) {
    await showError('เกิดข้อผิดพลาด', e instanceof Error ? e.message : 'ไม่สามารถอัปโหลดรูปโปรไฟล์ได้')
  } finally {
    uploadingPhoto.value = false
    target.value = ''
  }
}

async function removePhoto() {
  if (!editForm.value.id) return
  const confirmed = await showConfirm(
    'ยืนยันลบรูปโปรไฟล์',
    'คุณต้องการลบรูปโปรไฟล์ของผู้ใช้นี้หรือไม่?',
    { isDanger: true }
  )
  if (!confirmed) return

  uploadingPhoto.value = true
  try {
    const res = await request<{ data: { message: string } }>('admin/delete-user-media', {}, {
      userId: editForm.value.id,
      type: 'photo',
    })
    editForm.value.photo = ''
    const u = users.value.find((x) => x.id === editForm.value.id)
    if (u) u.photo = ''
    await showSuccess('สำเร็จ', res.data.message)
  } catch (e) {
    await showError('เกิดข้อผิดพลาด', e instanceof Error ? e.message : 'ไม่สามารถลบรูปโปรไฟล์ได้')
  } finally {
    uploadingPhoto.value = false
  }
}

function triggerSignUpload() {
  signInput.value?.click()
}

async function onSignFileChange(event: Event) {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file || !editForm.value.id) return

  uploadingSign.value = true
  try {
    const fd = new FormData()
    fd.append('userId', String(editForm.value.id))
    fd.append('type', 'sign_photo')
    fd.append('file', file)

    const res = await uploadMedia<{ data: { url: string; message: string } }>('admin/upload-user-media', fd)
    editForm.value.sign_photo = res.data.url
    const u = users.value.find((x) => x.id === editForm.value.id)
    if (u) u.sign_photo = res.data.url
    await showSuccess('สำเร็จ', res.data.message)
  } catch (e) {
    await showError('เกิดข้อผิดพลาด', e instanceof Error ? e.message : 'ไม่สามารถอัปโหลดรูปลายเซ็นได้')
  } finally {
    uploadingSign.value = false
    target.value = ''
  }
}

async function removeSign() {
  if (!editForm.value.id) return
  const confirmed = await showConfirm(
    'ยืนยันลบลายเซ็นดิจิทัล',
    'คุณต้องการลบรูปลายเซ็นดิจิทัลของผู้ใช้นี้หรือไม่?',
    { isDanger: true }
  )
  if (!confirmed) return

  uploadingSign.value = true
  try {
    const res = await request<{ data: { message: string } }>('admin/delete-user-media', {}, {
      userId: editForm.value.id,
      type: 'sign_photo',
    })
    editForm.value.sign_photo = ''
    const u = users.value.find((x) => x.id === editForm.value.id)
    if (u) u.sign_photo = ''
    await showSuccess('สำเร็จ', res.data.message)
  } catch (e) {
    await showError('เกิดข้อผิดพลาด', e instanceof Error ? e.message : 'ไม่สามารถลบลายเซ็นได้')
  } finally {
    uploadingSign.value = false
  }
}

async function submitEdit() {
  if (!editForm.value.name) {
    await showWarning('กรุณากรอกข้อมูล', 'กรุณากรอกชื่อจริง')
    return
  }
  submitting.value = true
  try {
    await request('admin/update-user', {}, editForm.value)
    showEditModal.value = false
    await showSuccess('สำเร็จ', `บันทึกข้อมูล ${editForm.value.username} เรียบร้อยแล้ว`)
    await loadData()
  } catch (e) {
    await showError('เกิดข้อผิดพลาด', e instanceof Error ? e.message : 'เกิดข้อผิดพลาดในการแก้ไขข้อมูล')
  } finally {
    submitting.value = false
  }
}

async function toggleStatus(user: AdminUser) {
  const actionText = user.isActive ? 'ระงับการใช้งาน' : 'เปิดใช้งาน'
  const confirmed = await showConfirm(
    'ยืนยันเปลี่ยนสถานะ',
    `คุณต้องการ ${actionText} บัญชีของ "${user.fullName}" หรือไม่?`,
    { isDanger: user.isActive }
  )
  if (!confirmed) return

  try {
    const res = await request<{ data: { message: string } }>('admin/toggle-user-status', {}, { id: user.id })
    await showSuccess('สำเร็จ', res.data.message)
    await loadData()
  } catch (e) {
    await showError('เกิดข้อผิดพลาด', e instanceof Error ? e.message : 'เกิดข้อผิดพลาดในการเปลี่ยนสถานะ')
  }
}

function openResetPassword(user: AdminUser) {
  resetForm.value = {
    id: user.id,
    username: user.username,
    fullName: user.fullName,
    newPassword: '',
  }
  showResetModal.value = true
}

async function submitResetPassword() {
  const pass = resetForm.value.newPassword.trim() || 'password'
  submitting.value = true
  try {
    const res = await request<{ data: { message: string } }>('admin/reset-password', {}, {
      id: resetForm.value.id,
      password: pass,
    })
    showResetModal.value = false
    await showSuccess('สำเร็จ', res.data.message)
  } catch (e) {
    await showError('เกิดข้อผิดพลาด', e instanceof Error ? e.message : 'เกิดข้อผิดพลาดในการรีเซ็ตรหัสผ่าน')
  } finally {
    submitting.value = false
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
        <h2>จัดการสมาชิก (User Management)</h2>
        <p class="muted">จัดการบัญชีผู้ใช้งาน ข้อมูลโปรไฟล์ และสถานะการเข้าใช้งานในระบบ</p>
      </div>
      <button class="primary add-button" @click="openCreate">
        <span aria-hidden="true">+</span> เพิ่มสมาชิกใหม่
      </button>
    </div>

    <!-- Alert Messages -->
    <div v-if="successMessage" class="alert-success" role="alert">
      ✓ {{ successMessage }}
    </div>
    <div v-if="error" class="error inset" role="alert">
      {{ error }}
    </div>

    <!-- Toolbar / Filters -->
    <div class="admin-toolbar">
      <div class="search-field">
        <span aria-hidden="true">⌕</span>
        <input v-model="search" placeholder="ค้นหาชื่อ, username, ตำแหน่ง หรือกลุ่มงาน…" />
      </div>
      <div class="filter-group">
        <button
          :class="{ active: filterStatus === 'all' }"
          @click="filterStatus = 'all'"
        >
          ทั้งหมด ({{ users.length }})
        </button>
        <button
          :class="{ active: filterStatus === 'active' }"
          @click="filterStatus = 'active'"
        >
          เปิดใช้งาน ({{ users.filter(u => u.isActive).length }})
        </button>
        <button
          :class="{ active: filterStatus === 'inactive' }"
          @click="filterStatus = 'inactive'"
        >
          ระงับการใช้งาน ({{ users.filter(u => !u.isActive).length }})
        </button>
      </div>
    </div>

    <!-- Table -->
    <div v-if="loading" class="empty-state">กำลังโหลดข้อมูลสมาชิก…</div>
    <div v-else-if="!filteredUsers.length" class="empty-state">
      <span class="empty-icon" aria-hidden="true">👤</span>
      <h3>ไม่พบข้อมูลสมาชิก</h3>
      <p>ลองปรับคำค้นหาหรือตัวกรองสถานะ</p>
    </div>
    <div v-else class="table-scroll">
      <table class="admin-table">
        <thead>
          <tr>
            <th style="width: 50px">#</th>
            <th>ชื่อ - นามสกุล / Username</th>
            <th>ตำแหน่ง / กลุ่มงาน</th>
            <th>การติดต่อ</th>
            <th>สิทธิ์ในระบบ</th>
            <th style="width: 120px">สถานะ</th>
            <th style="width: 160px; text-align: center">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(user, idx) in filteredUsers" :key="user.id">
            <td class="muted">{{ idx + 1 }}</td>
            <td>
              <div class="user-cell">
                <div class="user-avatar-wrap">
                  <img
                    v-if="user.photo"
                    :src="resolveMediaUrl(user.photo)"
                    class="user-avatar-img"
                    alt="Avatar"
                  />
                  <div v-else class="user-avatar">
                    {{ user.name.slice(0, 1) || user.username.slice(0, 1).toUpperCase() }}
                  </div>
                </div>
                <div class="user-info-col">
                  <div class="user-fullname-row">
                    <strong>{{ user.fullName }}</strong>
                    <span
                      v-if="user.sign_photo"
                      class="badge-has-sign"
                      title="มีรูปลายเซ็นดิจิทัลแล้ว"
                    >
                      ✍️
                    </span>
                  </div>
                  <div class="user-sub-details">
                    <small class="muted">@{{ user.username }}</small>
                    <span v-if="user.id_card" class="badge-id-card" title="เลขบัตรประจำตัวประชาชน">
                      🪪 {{ formatIdCard(user.id_card) }}
                    </span>
                  </div>
                </div>
              </div>
            </td>
            <td>
              <div>{{ user.dep_name || '—' }}</div>
              <small class="muted">{{ user.group_work || '—' }}</small>
            </td>
            <td>
              <div v-if="user.phone">📞 {{ user.phone }}</div>
              <div v-if="user.line_id" class="muted"><small>LINE: {{ user.line_id }}</small></div>
              <span v-if="!user.phone && !user.line_id" class="muted">—</span>
            </td>
            <td>
              <div v-if="user.roles.length" class="role-badges">
                <span v-for="r in user.roles" :key="r.id" class="role-badge">{{ r.name }}</span>
              </div>
              <span v-else class="muted">สมาชิกทั่วไป</span>
            </td>
            <td>
              <span class="status-pill" :class="user.isActive ? 'active' : 'inactive'">
                {{ user.isActive ? 'เปิดใช้งาน' : 'ระงับการใช้งาน' }}
              </span>
            </td>
            <td>
              <div class="action-buttons">
                <button
                  class="btn-icon"
                  title="แก้ไขข้อมูล"
                  @click="openEdit(user)"
                >
                  ✎
                </button>
                <button
                  class="btn-icon"
                  title="รีเซ็ตรหัสผ่าน"
                  @click="openResetPassword(user)"
                >
                  🔑
                </button>
                <button
                  class="btn-toggle"
                  :class="user.isActive ? 'deactivate' : 'activate'"
                  :title="user.isActive ? 'กดเพื่อระงับการใช้งาน' : 'กดเพื่อเปิดใช้งาน'"
                  @click="toggleStatus(user)"
                >
                  {{ user.isActive ? 'ระงับ' : 'เปิด' }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create Modal -->
    <div v-if="showCreateModal" class="modal-overlay" @click.self="showCreateModal = false">
      <div class="modal-card">
        <header class="modal-header">
          <h3>เพิ่มสมาชิกใหม่</h3>
          <button class="close-btn" @click="showCreateModal = false">×</button>
        </header>
        <form class="modal-body" @submit.prevent="submitCreate">
          <div class="form-row">
            <div class="form-group">
              <label>Username (เข้าสู่ระบบ) *</label>
              <input v-model="createForm.username" required placeholder="เช่น somchai" />
            </div>
            <div class="form-group">
              <label>Password เริ่มต้น *</label>
              <input v-model="createForm.password" required type="text" placeholder="password" />
            </div>
          </div>

          <div class="form-row three-cols">
            <div class="form-group">
              <label>คำนำหน้า</label>
              <select v-model="createForm.pfname">
                <option v-for="p in meta.pfnames" :key="p" :value="p">{{ p }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>ชื่อจริง *</label>
              <input v-model="createForm.name" required placeholder="ชื่อ" />
            </div>
            <div class="form-group">
              <label>นามสกุล</label>
              <input v-model="createForm.sname" placeholder="นามสกุล" />
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>ตำแหน่ง / ฝ่าย</label>
              <input v-model="createForm.dep_name" list="dept-list" placeholder="เลือกหรือกรอกตำแหน่ง" />
              <datalist id="dept-list">
                <option v-for="d in meta.departments" :key="d" :value="d" />
              </datalist>
            </div>
            <div class="form-group">
              <label>กลุ่มงาน</label>
              <input v-model="createForm.group_work" list="group-list" placeholder="เลือกหรือกรอกกลุ่มงาน" />
              <datalist id="group-list">
                <option v-for="g in meta.groupWorks" :key="g" :value="g" />
              </datalist>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>เบอร์โทรศัพท์</label>
              <input v-model="createForm.phone" placeholder="08x-xxxxxxx" />
            </div>
            <div class="form-group">
              <label>LINE ID / Token</label>
              <input v-model="createForm.line_id" placeholder="ID LINE" />
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>เลขบัตรประจำตัวประชาชน (13 หลัก)</label>
              <input
                v-model="createForm.id_card"
                maxlength="17"
                placeholder="เช่น 1-2345-67890-12-3"
              />
            </div>
            <div class="form-group">
              <label>อีเมล</label>
              <input v-model="createForm.email" type="email" placeholder="email@example.com" />
            </div>
          </div>

          <footer class="modal-footer">
            <button type="button" class="btn-cancel" @click="showCreateModal = false">ยกเลิก</button>
            <button type="submit" class="primary" :disabled="submitting">
              {{ submitting ? 'กำลังบันทึก…' : 'ยืนยันเพิ่มสมาชิก' }}
            </button>
          </footer>
        </form>
      </div>
    </div>

    <!-- Edit Modal -->
    <div v-if="showEditModal" class="modal-overlay" @click.self="showEditModal = false">
      <div class="modal-card">
        <header class="modal-header">
          <h3>แก้ไขข้อมูลสมาชิก (@{{ editForm.username }})</h3>
          <button class="close-btn" @click="showEditModal = false">×</button>
        </header>
        <form class="modal-body" @submit.prevent="submitEdit">
          <!-- รูปโปรไฟล์ & ลายเซ็นดิจิทัล -->
          <div class="media-management-section">
            <!-- 1. รูปโปรไฟล์ -->
            <div class="media-box">
              <label class="media-heading">📷 รูปภาพโปรไฟล์</label>
              <div class="media-content">
                <div class="avatar-preview-wrapper">
                  <img
                    v-if="editForm.photo"
                    :src="resolveMediaUrl(editForm.photo)"
                    class="avatar-preview-img"
                    alt="Profile Photo"
                  />
                  <div v-else class="avatar-preview-fallback">
                    {{ editForm.name?.slice(0, 1) || editForm.username?.slice(0, 1).toUpperCase() || '?' }}
                  </div>
                </div>
                <div class="media-actions">
                  <input
                    ref="photoInput"
                    type="file"
                    accept="image/png,image/jpeg,image/webp,image/gif"
                    style="display: none"
                    @change="onPhotoFileChange"
                  />
                  <button
                    type="button"
                    class="btn-media-action btn-upload-pic"
                    :disabled="uploadingPhoto"
                    @click="triggerPhotoUpload"
                  >
                    {{ uploadingPhoto ? '⏳ กำลังอัปโหลด…' : (editForm.photo ? 'เปลี่ยนรูป' : 'อัปโหลดรูป') }}
                  </button>
                  <button
                    v-if="editForm.photo"
                    type="button"
                    class="btn-media-action btn-delete-pic"
                    :disabled="uploadingPhoto"
                    @click="removePhoto"
                  >
                    ลบรูป
                  </button>
                </div>
              </div>
            </div>

            <!-- 2. ลายเซ็นดิจิทัล -->
            <div class="media-box">
              <label class="media-heading">✍️ ลายเซ็นดิจิทัล (สแตมป์ตอนเกษียน)</label>
              <div class="media-content">
                <div class="sign-preview-wrapper" title="รูปลายเซ็นสำหรับประทับลงในเอกสาร">
                  <img
                    v-if="editForm.sign_photo"
                    :src="resolveMediaUrl(editForm.sign_photo)"
                    class="sign-preview-img"
                    alt="Digital Signature"
                  />
                  <div v-else class="sign-preview-empty">
                    <span class="muted">(ยังไม่มีรูปลายเซ็น)</span>
                  </div>
                </div>
                <div class="media-actions">
                  <input
                    ref="signInput"
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    style="display: none"
                    @change="onSignFileChange"
                  />
                  <button
                    type="button"
                    class="btn-media-action btn-upload-sign"
                    :disabled="uploadingSign"
                    @click="triggerSignUpload"
                  >
                    {{ uploadingSign ? '⏳ กำลังอัปโหลด…' : (editForm.sign_photo ? 'เปลี่ยนลายเซ็น' : 'อัปโหลดลายเซ็น') }}
                  </button>
                  <button
                    v-if="editForm.sign_photo"
                    type="button"
                    class="btn-media-action btn-delete-pic"
                    :disabled="uploadingSign"
                    @click="removeSign"
                  >
                    ลบลายเซ็น
                  </button>
                </div>
              </div>
              <small class="sign-hint">* แนะนำใช้ไฟล์ PNG พื้นหลังโปร่งใส</small>
            </div>
          </div>

          <div class="form-row three-cols">
            <div class="form-group">
              <label>คำนำหน้า</label>
              <select v-model="editForm.pfname">
                <option value="">(ไม่ระบุ)</option>
                <option v-for="p in meta.pfnames" :key="p" :value="p">{{ p }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>ชื่อจริง *</label>
              <input v-model="editForm.name" required />
            </div>
            <div class="form-group">
              <label>นามสกุล</label>
              <input v-model="editForm.sname" />
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>ตำแหน่ง / ฝ่าย</label>
              <input v-model="editForm.dep_name" list="dept-list-edit" />
              <datalist id="dept-list-edit">
                <option v-for="d in meta.departments" :key="d" :value="d" />
              </datalist>
            </div>
            <div class="form-group">
              <label>กลุ่มงาน</label>
              <input v-model="editForm.group_work" list="group-list-edit" />
              <datalist id="group-list-edit">
                <option v-for="g in meta.groupWorks" :key="g" :value="g" />
              </datalist>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>เบอร์โทรศัพท์</label>
              <input v-model="editForm.phone" />
            </div>
            <div class="form-group">
              <label>LINE ID</label>
              <input v-model="editForm.line_id" />
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>เลขบัตรประจำตัวประชาชน (13 หลัก)</label>
              <input
                v-model="editForm.id_card"
                maxlength="17"
                placeholder="เช่น 1-2345-67890-12-3"
              />
            </div>
            <div class="form-group">
              <label>อีเมล</label>
              <input v-model="editForm.email" type="email" />
            </div>
          </div>

          <footer class="modal-footer">
            <button type="button" class="btn-cancel" @click="showEditModal = false">ยกเลิก</button>
            <button type="submit" class="primary" :disabled="submitting">
              {{ submitting ? 'กำลังบันทึก…' : 'บันทึกการเปลี่ยนแปลง' }}
            </button>
          </footer>
        </form>
      </div>
    </div>

    <!-- Reset Password Modal -->
    <div v-if="showResetModal" class="modal-overlay" @click.self="showResetModal = false">
      <div class="modal-card modal-sm">
        <header class="modal-header">
          <h3>รีเซ็ตรหัสผ่าน</h3>
          <button class="close-btn" @click="showResetModal = false">×</button>
        </header>
        <form class="modal-body" @submit.prevent="submitResetPassword">
          <p>
            คุณกำลังรีเซ็ตรหัสผ่านของผู้ใช้ <strong>{{ resetForm.fullName }}</strong> (@{{ resetForm.username }})
          </p>
          <div class="form-group">
            <label>รหัสผ่านใหม่ (หากเว้นว่างจะตั้งเป็น 'password')</label>
            <input v-model="resetForm.newPassword" placeholder="password" />
          </div>
          <footer class="modal-footer">
            <button type="button" class="btn-cancel" @click="showResetModal = false">ยกเลิก</button>
            <button type="submit" class="primary" :disabled="submitting">
              {{ submitting ? 'กำลังรีเซ็ต…' : 'ยืนยันรีเซ็ตรหัสผ่าน' }}
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

.admin-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  background: #ffffff;
  padding: 0.75rem 1rem;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
}

.search-field {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex: 1;
  max-width: 420px;
}

.search-field input {
  width: 100%;
  padding: 0.5rem 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.9rem;
}

.filter-group {
  display: flex;
  gap: 0.25rem;
  background: #f3f4f6;
  padding: 0.25rem;
  border-radius: 6px;
}

.filter-group button {
  background: transparent;
  border: none;
  padding: 0.35rem 0.75rem;
  font-size: 0.85rem;
  border-radius: 4px;
  cursor: pointer;
  color: #4b5563;
}

.filter-group button.active {
  background: #ffffff;
  color: #111827;
  font-weight: 600;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.admin-table {
  width: 100%;
  border-collapse: collapse;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
}

.admin-table th,
.admin-table td {
  padding: 0.85rem 1rem;
  border-bottom: 1px solid #e5e7eb;
  text-align: left;
  font-size: 0.9rem;
}

.admin-table th {
  background: #f9fafb;
  color: #4b5563;
  font-weight: 600;
}

.user-cell {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.user-avatar-wrap {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  overflow: hidden;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f3f4f6;
  border: 1px solid #e5e7eb;
}

.user-avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.user-info-col {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.user-fullname-row {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}

.user-sub-details {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  flex-wrap: wrap;
}

.badge-id-card {
  display: inline-flex;
  align-items: center;
  font-size: 0.78rem;
  padding: 0.05rem 0.35rem;
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  font-family: inherit;
}

.badge-has-sign {
  display: inline-flex;
  align-items: center;
  font-size: 0.78rem;
  padding: 0.1rem 0.35rem;
  background: #f0fdf4;
  color: #15803d;
  border: 1px solid #bbf7d0;
  border-radius: 4px;
  font-weight: 600;
}

.user-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: #10b981;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.95rem;
  flex-shrink: 0;
}

/* Media Management in Edit Modal */
.media-management-section {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 1rem;
}

@media (max-width: 600px) {
  .media-management-section {
    grid-template-columns: 1fr;
  }
}

.media-box {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.media-heading {
  font-size: 0.88rem;
  font-weight: 700;
  color: #1e293b;
}

.media-content {
  display: flex;
  align-items: center;
  gap: 0.85rem;
}

.avatar-preview-wrapper {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  overflow: hidden;
  flex-shrink: 0;
  background: #e2e8f0;
  border: 2px solid #cbd5e1;
  display: flex;
  align-items: center;
  justify-content: center;
}

.avatar-preview-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-preview-fallback {
  font-size: 1.5rem;
  font-weight: 700;
  color: #64748b;
}

.sign-preview-wrapper {
  width: 140px;
  height: 52px;
  background-color: #ffffff;
  background-image: linear-gradient(45deg, #f1f5f9 25%, transparent 25%),
    linear-gradient(-45deg, #f1f5f9 25%, transparent 25%),
    linear-gradient(45deg, transparent 75%, #f1f5f9 75%),
    linear-gradient(-45deg, transparent 75%, #f1f5f9 75%);
  background-size: 12px 12px;
  background-position: 0 0, 0 6px, 6px -6px, -6px 0px;
  border: 1px dashed #cbd5e1;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  padding: 0.2rem;
  flex-shrink: 0;
}

.sign-preview-img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}

.sign-preview-empty {
  font-size: 0.78rem;
  color: #94a3b8;
  text-align: center;
}

.sign-hint {
  font-size: 0.78rem;
  color: #64748b;
  margin-top: 0.1rem;
}

.media-actions {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  flex: 1;
}

.btn-media-action {
  padding: 0.35rem 0.65rem;
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
  text-align: center;
}

.btn-upload-pic {
  background: #eff6ff;
  color: #2563eb;
  border: 1px solid #bfdbfe;
}

.btn-upload-pic:hover:not(:disabled) {
  background: #dbeafe;
}

.btn-upload-sign {
  background: #f0fdf4;
  color: #16a34a;
  border: 1px solid #bbf7d0;
}

.btn-upload-sign:hover:not(:disabled) {
  background: #dcfce7;
}

.btn-delete-pic {
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
  font-size: 0.78rem;
}

.btn-delete-pic:hover:not(:disabled) {
  background: #fee2e2;
}

.btn-media-action:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.role-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.role-badge {
  background: #e0e7ff;
  color: #3730a3;
  padding: 0.2rem 0.5rem;
  border-radius: 4px;
  font-size: 0.78rem;
  font-weight: 600;
}

.status-pill {
  display: inline-block;
  padding: 0.25rem 0.6rem;
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

.action-buttons {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
}

.btn-icon {
  background: #f3f4f6;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  padding: 0.35rem 0.55rem;
  cursor: pointer;
  font-size: 0.85rem;
}

.btn-icon:hover {
  background: #e5e7eb;
}

.btn-toggle {
  border: none;
  border-radius: 4px;
  padding: 0.35rem 0.6rem;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
}

.btn-toggle.deactivate {
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fca5a5;
}

.btn-toggle.deactivate:hover {
  background: #fee2e2;
}

.btn-toggle.activate {
  background: #f0fdf4;
  color: #16a34a;
  border: 1px solid #86efac;
}

.btn-toggle.activate:hover {
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
  max-width: 600px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
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
  overflow-y: auto;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.form-row.three-cols {
  grid-template-columns: 100px 1fr 1fr;
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
