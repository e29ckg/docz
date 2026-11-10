import { createApp } from 'vue'
import App from './App.vue'
import './style.css'
import Swal from 'sweetalert2'
import 'sweetalert2/dist/sweetalert2.min.css'

// ดักจับ window.alert เพื่อแสดงด้วย SweetAlert2 สวยงามเสมอทั่วทั้งระบบ
window.alert = (message?: any) => {
  Swal.fire({
    title: 'แจ้งเตือน',
    text: String(message ?? ''),
    icon: 'info',
    confirmButtonText: 'ตกลง',
    confirmButtonColor: '#0284c7',
  })
}

createApp(App).mount('#app')
