<template>
  <div id="app" class="app-shell">
    <nav class="navbar navbar-expand-lg shadow-sm py-3 navbar-light bg-white sticky-top">
      <div class="container">
        <router-link class="navbar-brand fw-bold text-primary" to="/feature-request">
          <!-- Simpefo Ticketing -->
           <img
            src="/simpefo-logo.png"
            alt="SIMPEFO Ticketing Logo"
            height="65"
            class="me-2"
          />
        </router-link>
        <button
          class="navbar-toggler"
          type="button"
          :class="{ collapsed: !mainNavOpen }"
          @click="toggleMainNav"
          aria-controls="mainNav"
          :aria-expanded="mainNavOpen ? 'true' : 'false'"
          aria-label="Toggle navigation"
        >
          <span class="navbar-toggler-icon"></span>
        </button>
        <div
          ref="mainNavRef"
          class="collapse navbar-collapse justify-content-end"
          id="mainNav"
        >
          <ul class="navbar-nav align-items-lg-center gap-lg-3">
            <li v-if="!isLoggedIn" class="nav-item">
              <router-link class="nav-link" to="/login" @click="handleNavLinkClick">Login</router-link>
            </li>
            <li v-if="!isLoggedIn" class="nav-item">
              <router-link class="nav-link" to="/register" @click="handleNavLinkClick">
                Register
              </router-link>
            </li>
            <template v-else>
              <li class="nav-item">
                <router-link class="nav-link" to="/feature-request" @click="handleNavLinkClick">
                  Daftar Ticket
                </router-link>
              </li>
              <li class="nav-item">
                <router-link
                  class="nav-link"
                  to="/feature-request/monitoring"
                  @click="handleNavLinkClick"
                >
                  Monitoring Ticket
                </router-link>
              </li>
              <li v-if="isAdmin" class="nav-item">
                <router-link class="nav-link" to="/admin/master" @click="handleNavLinkClick">
                  Master Data
                </router-link>
              </li>
              <li v-if="isJangmedManager" class="nav-item">
                <router-link
                  class="nav-link"
                  to="/manager/jangmed/priorities"
                  @click="handleNavLinkClick"
                >
                  Prioritas Ticket
                </router-link>
              </li>
              <li v-if="canRequest" class="nav-item">
                <router-link class="nav-link" to="/feature-request/create" @click="handleNavLinkClick">
                  Ajukan Form
                </router-link>
              </li>
              <li class="nav-item dropdown" ref="accountMenuRef" :class="{ show: accountMenuOpen }">
                <button
                  class="btn btn-outline-primary dropdown-toggle px-4"
                  type="button"
                  @click="toggleAccountMenu"
                  :aria-expanded="accountMenuOpen ? 'true' : 'false'"
                  :class="{ show: accountMenuOpen }"
                >
                  <span class="d-flex flex-column text-start">
                    <span class="fw-semibold">{{ auth.user?.name }}</span>
                    <small class="text-danger text-capitalize">{{ auth.user?.username }}</small>
                    <small class="text-muted text-capitalize">{{ roleLabel }}</small>
                  </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" :class="{ show: accountMenuOpen }">
                  <li>
                    <button class="dropdown-item" @click="openKodeSignModal">Atur Kode ACC</button>
                  </li>
                  <li><hr class="dropdown-divider" /></li>
                  <li>
                    <button class="dropdown-item text-danger" @click="handleLogout">Keluar</button>
                  </li>
                </ul>
              </li>
            </template>
          </ul>
        </div>
      </div>
    </nav>

    <main class="flex-grow-1 py-5 bg-light">
      <div class="container">
        <router-view />
      </div>
    </main>

    <footer class="py-4 bg-white border-top">
      <div class="container text-center text-muted small">
        &copy; {{ new Date().getFullYear() }} Simpefo Ticketing. All rights reserved. IT RSUWH
      </div>
    </footer>

    <div
      class="modal fade"
      tabindex="-1"
      ref="kodeSignModal"
      aria-labelledby="modalKodeSignLabel"
      aria-hidden="true"
    >
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modalKodeSignLabel">Simpan Kode ACC</h5>
            <button type="button" class="btn-close" @click="closeKodeSignModal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p class="text-muted small mb-3">
              Kode ACC digunakan sebagai pengganti tanda tangan digital pada setiap tahap persetujuan.
            </p>
            <input
              v-model="kodeSign"
              type="password"
              placeholder="Masukkan kode ACC rahasia"
              class="form-control"
            />
            <div v-if="kodeSignError" class="alert alert-danger mt-3 py-2">
              {{ kodeSignError }}
            </div>
            <div v-if="kodeSignSuccess" class="alert alert-success mt-3 py-2">
              {{ kodeSignSuccess }}
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" @click="closeKodeSignModal">
              Batal
            </button>
            <button type="button" class="btn btn-primary" :disabled="savingKode" @click="submitKodeSign">
              <span v-if="savingKode" class="spinner-border spinner-border-sm me-2"></span>
              Simpan Kode
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Collapse } from 'bootstrap'
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useAppShell } from '@/ticketing/composables'

const {
  auth,
  isLoggedIn,
  roleLabel,
  canRequest,
  isAdmin,
  isJangmedManager,
  accountMenuRef,
  accountMenuOpen,
  toggleAccountMenu,
  kodeSign,
  kodeSignError,
  kodeSignSuccess,
  kodeSignModal,
  savingKode,
  openKodeSignModal,
  closeKodeSignModal,
  submitKodeSign,
  logout
} = useAppShell()

const mainNavRef = ref(null)
const mainNavOpen = ref(false)
const mainNavCollapse = ref(null)

const toggleMainNav = () => {
  if (!mainNavCollapse.value) {
    return
  }

  mainNavCollapse.value.toggle()
}

const closeMainNav = () => {
  if (!mainNavCollapse.value || !mainNavOpen.value) {
    return
  }

  mainNavCollapse.value.hide()
}

const handleNavLinkClick = () => {
  closeMainNav()
}

const handleLogout = async () => {
  closeMainNav()
  await logout()
}

const handleMainNavShown = () => {
  mainNavOpen.value = true
}

const handleMainNavHidden = () => {
  mainNavOpen.value = false
}

onMounted(() => {
  if (!mainNavRef.value) {
    return
  }

  mainNavCollapse.value = new Collapse(mainNavRef.value, {
    toggle: false
  })

  mainNavRef.value.addEventListener('shown.bs.collapse', handleMainNavShown)
  mainNavRef.value.addEventListener('hidden.bs.collapse', handleMainNavHidden)
})

onBeforeUnmount(() => {
  if (!mainNavRef.value) {
    return
  }

  mainNavRef.value.removeEventListener('shown.bs.collapse', handleMainNavShown)
  mainNavRef.value.removeEventListener('hidden.bs.collapse', handleMainNavHidden)
  if (mainNavCollapse.value) {
    mainNavCollapse.value.hide()
    mainNavCollapse.value.dispose()
  }
})

</script>

