<template>
  <div class="page-wrapper ticket-create">
    <div class="row justify-content-center">
      <div class="col-lg-10 col-xl-8">
        <div v-if="canSubmit" class="card border-0 shadow-lg">
          <div class="card-body p-4 p-lg-5">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4 form-header">
              <div class="form-intro">
                <h2 class="fw-semibold mb-1">Pengajuan Permintaan Form</h2>
                <p class="text-muted mb-0">
                  Lengkapi informasi form dan konfirmasi dengan kode ACC Anda untuk mengirimkan tiket pengajuan.
                </p>
              </div>
              <router-link to="/feature-request" class="btn btn-danger border back-button">Kembali</router-link>
            </div>

            <form @submit.prevent="submit" class="row g-4">
              <div class="col-12">
                <label class="form-label">Jenis Permintaan</label>
                <div class="request-type-grid">
                  <label
                    v-for="option in requestTypeOptions"
                    :key="option.value"
                    class="request-type-card"
                  >
                    <input v-model="requestTypes" type="checkbox" :value="option.value" />
                    <span>{{ option.label }}</span>
                  </label>
                </div>
                <small class="text-muted d-block mt-1">Pilih minimal satu jenis permintaan.</small>
              </div>

              <div class="col-12">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Nama Pemohon</label>
                    <input type="text" class="form-control" :value="requesterName" disabled />
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Instansi</label>
                    <input type="text" class="form-control" :value="requesterInstansi" disabled />
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Unit</label>
                    <input type="text" class="form-control" :value="requesterUnit" disabled />
                  </div>
                </div>
              </div>

              <div class="col-12">
                <label class="form-label">Deskripsi Pengajuan</label>
                <textarea
                  v-model="description"
                  class="form-control"
                  rows="5"
                  placeholder="Jelaskan kebutuhan form, tujuan penggunaan, serta informasi pendukung lain."
                  required
                ></textarea>
              </div>

              <div class="col-12">
                <label class="form-label">Lampiran Pendukung (opsional)</label>
                <input
                  ref="attachmentInput"
                  type="file"
                  class="form-control"
                  @change="handleAttachmentChange"
                  accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                />
                <div v-if="attachmentName" class="selected-attachment mt-2">
                  <span class="badge bg-primary-subtle text-primary px-3 py-2">{{ attachmentName }}</span>
                  <button type="button" class="btn btn-link text-decoration-none ms-2" @click="resetAttachmentField">
                    Hapus lampiran
                  </button>
                </div>
                <div v-if="attachmentError" class="text-danger small mt-2">{{ attachmentError }}</div>
                <p class="text-muted small mt-2 mb-0">
                  Format diperbolehkan: PDF, DOC, DOCX, JPG, PNG. Maksimal 5MB.
                </p>
              </div>

              <div class="col-12">
                <div class="p-4 bg-light rounded-4 border">
                  <h5 class="fw-semibold">Konfirmasi Pemohon</h5>
                  <p class="text-muted mb-3 small">
                    Sebagai pemohon Anda perlu memasukkan kode ACC pribadi sebagai tanda tangan digital sebelum tiket diproses.
                  </p>
                  <input
                    v-model="signCode"
                    type="password"
                    class="form-control form-control-lg"
                    placeholder="Masukkan kode ACC Anda"
                    required
                  />
                  <textarea
                    v-model="note"
                    class="form-control mt-3"
                    rows="3"
                    placeholder="Catatan untuk manager atau direktur (opsional)"
                  ></textarea>
                </div>
              </div>

              <div v-if="message" :class="['alert', messageTypeClass, 'py-2']">
                {{ message }}
              </div>

              <div class="col-12 d-flex justify-content-end gap-3">
                <router-link to="/feature-request" class="btn btn-outline-secondary px-4">Batal</router-link>
                <button type="submit" class="btn btn-primary px-4" :disabled="loading">
                  <span v-if="loading" class="spinner-border spinner-border-sm me-2"></span>
                  Kirim Pengajuan
                </button>
              </div>
            </form>
          </div>
        </div>
        <div v-else class="card border-0 shadow-lg">
          <div class="card-body p-4 p-lg-5 text-center">
            <h2 class="fw-semibold mb-3">Akses Pengajuan Terbatas</h2>
            <p class="text-muted mb-4">
              Hanya pemohon yang dapat mengajukan tiket permintaan form baru. Silakan hubungi pemohon jika Anda perlu
              memulai proses pengajuan.
            </p>
            <router-link to="/feature-request" class="btn btn-outline-primary px-4">Kembali ke Daftar Tiket</router-link>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useFeatureRequestCreate } from '@/ticketing/composables'

const {
  requestTypes,
  requestTypeOptions,
  requesterName,
  requesterUnit,
  requesterInstansi,
  description,
  signCode,
  note,
  canSubmit,
  attachmentName,
  attachmentError,
  attachmentInput,
  loading,
  message,
  messageTypeClass,
  handleAttachmentChange,
  resetAttachmentField,
  submit
} = useFeatureRequestCreate()
</script>
