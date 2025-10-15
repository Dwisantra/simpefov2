<template>
  <div class="auth-wrapper login-page">
    <div class="container py-5 py-lg-0">
      <div class="row align-items-center g-5">
        <div class="col-lg-6 d-none d-lg-block">
          <div class="auth-illustration">
            <span class="badge rounded-pill text-bg-light auth-badge">SIMPEFO TICKETING</span>
            <h1 class="display-6 fw-semibold mt-4 mb-3">Pengajuan Form SIMGOS Wajib Melalui Simpefo Ticketing.</h1>
            <p class="auth-copy">
              Pantau status pengajuan dengan tracker proses yang jelas dan keamanan kode tanda tangan berlapis.
            </p>
            <ul class="auth-list">
              <li>Akses multi-level untuk pemohon hingga direktur.</li>
              <li>Upload lampiran dokumen (PDF, DOCX, JPG, PNG).</li>
              <li>Sistem notifikasi tahap persetujuan yang transparan.</li>
            </ul>
          </div>
        </div>
        <div class="col-md-8 col-lg-5 col-xl-5 ms-lg-auto">
          <div class="card border-0 shadow-lg">
            <div class="card-body p-4 p-lg-5">
              <h2 class="fw-semibold text-center mb-2">Selamat Datang</h2>
              <p class="text-muted text-center mb-4">
                Masuk untuk melanjutkan pengajuan atau persetujuan form.
              </p>
              <form @submit.prevent="submit" novalidate>
                <div class="mb-3">
                  <label class="form-label">Email</label>
                  <input v-model="email" type="email" class="form-control" placeholder="nama@contoh.com" required />
                </div>
                <div class="mb-4">
                  <label class="form-label">Password</label>
                  <div class="input-group password-input-group">
                    <input
                      v-model="password"
                      :type="showPassword ? 'text' : 'password'"
                      class="form-control"
                      placeholder="Masukkan password"
                      autocomplete="current-password"
                      required
                    />
                    <button
                      type="button"
                      class="btn btn-outline-secondary"
                      :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                      @click="togglePasswordVisibility"
                    >
                      <svg
                        v-if="showPassword"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        width="20"
                        height="20"
                        aria-hidden="true"
                      >
                        <path
                          d="M3 3l18 18M10.585 10.586a2 2 0 102.829 2.828M9.88 4.824A9.707 9.707 0 0112 4.5c5.523 0 10 4.477 10 6.5 0 1.093-1.028 2.663-2.764 3.995M6.226 6.222C4.172 7.414 2 9.305 2 11c0 2.023 4.477 6.5 10 6.5 1.271 0 2.49-.218 3.616-.62"
                        />
                      </svg>
                      <svg
                        v-else
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        width="20"
                        height="20"
                        aria-hidden="true"
                      >
                        <path
                          d="M1 12C1 12 5 4.5 12 4.5S23 12 23 12 19 19.5 12 19.5 1 12 1 12z"
                        />
                        <circle cx="12" cy="12" r="3" />
                      </svg>
                    </button>
                  </div>
                </div>

                <div v-if="error" class="alert alert-danger py-2">{{ error }}</div>

                <button class="btn btn-primary w-100 py-2" :disabled="loading">
                  <span v-if="loading" class="spinner-border spinner-border-sm me-2"></span>
                  Masuk
                </button>
              </form>
              <p class="text-center text-muted mt-4 mb-0">
                Belum punya akun?
                <router-link to="/register" class="fw-semibold">Daftar sekarang</router-link>
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useLoginForm } from '@/ticketing/composables'

const { email, password, showPassword, loading, error, togglePasswordVisibility, submit } = useLoginForm()
</script>

