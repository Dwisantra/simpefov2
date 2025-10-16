<template>
  <div id="app" class="app-shell">
    <nav class="navbar navbar-expand-lg shadow-sm py-3 navbar-light bg-white sticky-top">
      <div class="container">
        <router-link class="navbar-brand fw-bold text-primary" to="/feature-request">
          Simpefo Ticketing
        </router-link>
        <button
          class="navbar-toggler"
          type="button"
          data-bs-toggle="collapse"
          data-bs-target="#mainNav"
          aria-controls="mainNav"
          aria-expanded="false"
          aria-label="Toggle navigation"
        >
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="mainNav">
          <ul class="navbar-nav align-items-lg-center gap-lg-3">
            <li v-if="!isLoggedIn" class="nav-item">
              <router-link class="nav-link" to="/login">Login</router-link>
            </li>
            <li v-if="!isLoggedIn" class="nav-item">
              <router-link class="nav-link" to="/register">Register</router-link>
            </li>
            <template v-else>
              <li class="nav-item">
                <router-link class="nav-link" to="/feature-request">Daftar Ticket</router-link>
              </li>
              <li v-if="isAdmin" class="nav-item">
                <router-link class="nav-link" to="/admin/master">Master Data</router-link>
              </li>
              <li v-if="isJangmedManager" class="nav-item">
                <router-link class="nav-link" to="/manager/jangmed/priorities">Prioritas Ticket</router-link>
              </li>
              <li v-if="canRequest" class="nav-item">
                <router-link class="nav-link" to="/feature-request/create">Ajukan Form</router-link>
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
                    <button class="dropdown-item text-danger" @click="logout">Keluar</button>
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

console.log(auth);

</script>

