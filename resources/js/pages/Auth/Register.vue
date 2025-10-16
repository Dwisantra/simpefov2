<template>
  <div class="auth-wrapper register-page">
    <div class="container py-5 py-lg-0">
      <div class="row align-items-center g-5">
        <div class="col-lg-6 d-none d-lg-block">
          <div class="auth-illustration">
            <span class="badge rounded-pill text-bg-light auth-badge">Akun Terproteksi</span>
            <h1 class="display-6 fw-semibold mt-4 mb-3">Mulai ajukan dan setujui pengajuan form SIMGOS secara digital.</h1>
            <p class="auth-copy">
              Setiap tanda tangan digital dienkripsi dengan hash unik dan URL berbasis UUID untuk keamanan maksimal.
            </p>
            <ul class="auth-list">
              <li>Pilih instansi dan unit Anda.</li>
              <li>Kolaborasi cepat melalui komentar dan unggahan dokumen.</li>
              <li>Pelacakan tahap persetujuan yang akurat dan real-time.</li>
            </ul>
          </div>
        </div>
        <div class="col-md-8 col-lg-5 col-xl-6 ms-lg-auto">
          <div class="card border-0 shadow-lg">
            <div class="card-body p-4 p-lg-5">
              <h2 class="fw-semibold text-center mb-1">Registrasi Pemohon</h2>
              <p class="text-muted text-center mb-4">
                Daftarkan data Anda. Admin akan memverifikasi sebelum Anda dapat masuk dan membuat kode ACC.
              </p>
              <form @submit.prevent="submit" class="needs-validation" novalidate>
                <div class="mb-3">
                  <label class="form-label">Nama Lengkap</label>
                  <input v-model="name" class="form-control" placeholder="Contoh: Nama Lengkap" required />
                </div>
                <div class="mb-3">
                  <label class="form-label">Username</label>
                  <input
                    v-model="username"
                    class="form-control"
                    placeholder="Gunakan huruf, angka, atau tanda hubung"
                    autocomplete="username"
                    required
                  />
                  <small class="text-muted">Username dipakai saat login, contoh: user_name.</small>
                </div>
                <div class="mb-3">
                  <label class="form-label">Email</label>
                  <input v-model="email" type="email" class="form-control" placeholder="nama@contoh.com" required />
                </div>
                <div class="mb-3">
                  <label class="form-label">No. HP Aktif</label>
                  <input v-model="phone" class="form-control" placeholder="Contoh: 081234567890" required />
                </div>
                <div class="mb-3">
                  <label class="form-label">Password</label>
                  <div class="input-group password-input-group">
                    <input
                      v-model="password"
                      :type="showPassword ? 'text' : 'password'"
                      class="form-control"
                      placeholder="Minimal 8 karakter, kombinasikan huruf & angka"
                      minlength="8"
                      autocomplete="new-password"
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
                        <path d="M1 12C1 12 5 4.5 12 4.5S23 12 23 12 19 19.5 12 19.5 1 12 1 12z" />
                        <circle cx="12" cy="12" r="3" />
                      </svg>
                    </button>
                  </div>
                  <small class="text-muted">Minimal 8 karakter dengan kombinasi huruf dan angka.</small>
                </div>
                <div class="mb-3">
                  <label class="form-label">Konfirmasi Password</label>
                  <div class="input-group password-input-group">
                    <input
                      v-model="passwordConfirmation"
                      :type="showPasswordConfirmation ? 'text' : 'password'"
                      class="form-control"
                      placeholder="Ulangi password Anda"
                      minlength="8"
                      autocomplete="new-password"
                      required
                    />
                    <button
                      type="button"
                      class="btn btn-outline-secondary"
                      :aria-label="showPasswordConfirmation ? 'Sembunyikan password' : 'Tampilkan password'"
                      @click="togglePasswordConfirmationVisibility"
                    >
                      <svg
                        v-if="showPasswordConfirmation"
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
                        <path d="M1 12C1 12 5 4.5 12 4.5S23 12 23 12 19 19.5 12 19.5 1 12 1 12z" />
                        <circle cx="12" cy="12" r="3" />
                      </svg>
                    </button>
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label">Instansi</label>
                  <select v-model="instansi" class="form-select" required>
                    <option value="wiradadi">RS Wiradadi Husada</option>
                    <option value="raffa">RS Raffa Majenang</option>
                  </select>
                </div>
                <div class="mb-4">
                  <label class="form-label">Unit</label>
                  <select v-model="unitId" class="form-select" :disabled="loadingUnits || units.length === 0" required>
                    <option value="" disabled>Pilih unit</option>
                    <option v-for="unit in units" :key="unit.id" :value="unit.id">
                      {{ unit.name }}
                    </option>
                  </select>
                  <small v-if="loadingUnits" class="text-muted">Memuat daftar unit...</small>
                  <small v-else-if="units.length === 0" class="text-danger d-block">Unit untuk instansi ini belum tersedia.</small>
                </div>

                <div v-if="error" class="alert alert-danger py-2">{{ error }}</div>
                <div v-if="success" class="alert alert-success py-2">{{ success }}</div>

                <button class="btn btn-primary w-100 py-2" :disabled="loading">
                  <span v-if="loading" class="spinner-border spinner-border-sm me-2"></span>
                  Daftar Sekarang
                </button>
              </form>
              <p class="text-center text-muted mt-4 mb-0">
                Sudah punya akun?
                <router-link to="/login" class="fw-semibold">Masuk disini</router-link>
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRegisterForm } from '@/ticketing/composables'

const {
  name,
  username,
  email,
  password,
  passwordConfirmation,
  phone,
  instansi,
  unitId,
  units,
  loadingUnits,
  loading,
  error,
  success,
  submit
} = useRegisterForm()

const showPassword = ref(false)
const showPasswordConfirmation = ref(false)

const togglePasswordVisibility = () => {
  showPassword.value = !showPassword.value
}

const togglePasswordConfirmationVisibility = () => {
  showPasswordConfirmation.value = !showPasswordConfirmation.value
}
</script>

