<template>
  <div v-if="show" class="modal-backdrop fade show"></div>
  <div v-if="show" class="modal fade show d-block" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow">
        <div class="modal-header border-0 pb-0">
          <button type="button" class="btn-close" @click="$emit('close')" aria-label="Close"></button>
        </div>
        
        <div class="modal-body p-4 text-center">
            <div class="mb-4">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
            </div>
            
            <h3 class="fw-bold mb-2">Berhasil!</h3>
            <p class="text-muted mb-4">
                Silahkan salin link Approval Manager di bawah ini.
            </p>

            <div class="input-group mb-3">
                <input 
                type="text" 
                class="form-control" 
                :value="link" 
                id="linkInput" 
                readonly 
                style="background-color: #f8f9fa;"
                >
                <button class="btn btn-outline-primary" @click="copyLink">
                <i class="bi bi-clipboard me-1"></i>Salin
                </button>
            </div>

            <div v-if="expiresAt" class="alert alert-warning py-2 mb-4 border-0 shadow-sm d-flex align-items-center gap-2">
                <i class="bi bi-clock-history"></i>
                <span>Berakhir dalam: <strong>{{ countdownDisplay }}</strong></span>
            </div>

            <div v-if="localMessage" class="alert alert-success py-2 mb-4 border-0 shadow-sm animate__animated animate__fadeIn">
                <i class="bi bi-check2-all me-2"></i>{{ localMessage }}
            </div>

            <div class="d-grid gap-2">
                <button @click="$emit('close')" class="btn btn-primary py-2 fw-semibold">Tutup</button>
            </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
  show: Boolean,
  link: String,
  message: String,
  expiresAt: String
})

const emit = defineEmits(['close'])
const localMessage = ref(props.message)
const timeRemaining = ref(0)
let countdownInterval = null

const countdownDisplay = computed(() => {
  if (timeRemaining.value <= 0) {
    return 'Link sudah kadaluarsa'
  }
  
  const hours = Math.floor(timeRemaining.value / 3600)
  const minutes = Math.floor((timeRemaining.value % 3600) / 60)
  const seconds = timeRemaining.value % 60
  
  if (hours > 0) {
    return `${hours}j ${minutes}m ${seconds}d`
  } else if (minutes > 0) {
    return `${minutes}m ${seconds}d`
  } else {
    return `${seconds}d`
  }
})

const calculateTimeRemaining = () => {
  if (!props.expiresAt) return
  
  const expiryTime = new Date(props.expiresAt).getTime()
  const now = new Date().getTime()
  const remaining = Math.max(0, Math.floor((expiryTime - now) / 1000))
  
  timeRemaining.value = remaining
}

const startCountdown = () => {
  calculateTimeRemaining()
  
  countdownInterval = setInterval(() => {
    calculateTimeRemaining()
    
    if (timeRemaining.value <= 0) {
      clearInterval(countdownInterval)
    }
  }, 1000)
}

watch(() => props.show, (newShow) => {
  if (newShow) {
    startCountdown()
  } else {
    if (countdownInterval) {
      clearInterval(countdownInterval)
    }
  }
})

watch(() => props.message, (newVal) => {
  localMessage.value = newVal
})

watch(() => props.expiresAt, () => {
  calculateTimeRemaining()
})

onMounted(() => {
  if (props.show) {
    startCountdown()
  }
})

onBeforeUnmount(() => {
  if (countdownInterval) {
    clearInterval(countdownInterval)
  }
})

const copyLink = () => {
    const copyText = document.getElementById("linkInput");
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(copyText.value);
    localMessage.value = "Link berhasil disalin!";

    setTimeout(() => {
        localMessage.value = props.message
    }, 3000);
}
</script>