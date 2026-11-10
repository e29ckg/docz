<script setup lang="ts">
import { ref } from 'vue'
import { sessionRequest, type User } from '../api'
const emit = defineEmits<{ authenticated: [user: User] }>()
const username = ref('')
const password = ref('')
const busy = ref(false)
const error = ref('')
async function login() {
  if (busy.value) return
  busy.value = true
  error.value = ''
  try {
    // Refresh CSRF after idle/expired sessions before sending credentials.
    await sessionRequest()
    const session = await sessionRequest('session/login', { username: username.value, password: password.value })
    password.value = ''
    if (session.user) emit('authenticated', session.user)
  } catch (e) { error.value = e instanceof Error ? e.message : 'เข้าสู่ระบบไม่สำเร็จ' }
  finally { busy.value = false }
}
</script>

<template>
  <main class="login-page">
    <section class="login-story">
      <a class="brand" href="./"><span class="brand-icon">D</span>DOCZ<span class="brand-period">.</span></a>
      <div><span class="eyebrow light">DOCUMENT MANAGEMENT</span><h1>ทุกหนังสือ<br>เชื่อมถึงกัน</h1>
        <p>พื้นที่ทำงานสำหรับหนังสือและเอกสารขององค์กร<br>ค้นหา ติดตาม และเข้าถึงเอกสารที่คุณรับผิดชอบ</p></div>
      <span class="story-footer">ระบบจัดการหนังสือภายในองค์กร</span>
    </section>
    <section class="login-form-panel">
      <form class="login-form" @submit.prevent="login">
        <span class="eyebrow">ยินดีต้อนรับสู่ DOCZ</span>
        <h2>เข้าสู่พื้นที่ทำงาน</h2><p class="muted">ใช้ชื่อผู้ใช้และรหัสผ่านบัญชีเดิมของคุณ</p>
        <label for="username">ชื่อผู้ใช้</label><input id="username" v-model="username" autocomplete="username" required maxlength="255" autofocus />
        <label for="password">รหัสผ่าน</label><input id="password" v-model="password" type="password" autocomplete="current-password" required maxlength="1024" />
        <p v-if="error" class="error" role="alert">{{ error }}</p>
        <button class="primary" type="submit" :disabled="busy">{{ busy ? 'กำลังเข้าสู่ระบบ…' : 'เข้าสู่ระบบ →' }}</button>
        <p class="login-note">หากเข้าใช้งานไม่ได้ กรุณาติดต่อผู้ดูแลระบบขององค์กร</p>
      </form>
    </section>
  </main>
</template>
