<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { sessionRequest, type User } from './api'
import LoginView from './components/LoginView.vue'
import DocumentWorkspace from './components/DocumentWorkspace.vue'

const user = ref<User | null>(null)
const ready = ref(false)
const error = ref('')
async function bootstrap() {
  error.value = ''
  try { user.value = (await sessionRequest()).user }
  catch (e) { error.value = e instanceof Error ? e.message : 'ไม่สามารถติดต่อระบบได้' }
  finally { ready.value = true }
}
async function logout() {
  try { user.value = (await sessionRequest('session/logout', {})).user }
  catch (e) { error.value = e instanceof Error ? e.message : 'ออกจากระบบไม่สำเร็จ' }
}
onMounted(bootstrap)
</script>

<template>
  <main v-if="!ready" class="initial-state" aria-live="polite">กำลังเชื่อมต่อ DOCZ…</main>
  <main v-else-if="error" class="initial-state">
    <p role="alert">{{ error }}</p><button @click="bootstrap">ลองเชื่อมต่ออีกครั้ง</button>
  </main>
  <DocumentWorkspace v-else-if="user" :user="user" @logout="logout" @expired="user = null" />
  <LoginView v-else @authenticated="user = $event" />
</template>
