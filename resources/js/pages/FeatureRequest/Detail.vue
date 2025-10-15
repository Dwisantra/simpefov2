<template>
  <div class="detail-wrapper">
    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status"></div>
      <p class="text-muted mt-3">Memuat detail tiket...</p>
    </div>

    <div v-else-if="!feature" class="alert alert-danger">
      Data tiket tidak ditemukan.
    </div>

    <div v-else class="row g-4">
      <div class="col-12">
        <div class="card border-0 shadow-lg rounded-4">
          <div class="card-body p-4 p-lg-5">
            <div class="row align-items-start g-3 detail-header">
              <div class="col-md-9">
                <h2 class="fw-semibold mb-2">{{ feature.request_types_label }}</h2>

                <div class="d-flex flex-wrap gap-2 mb-3">
                  <span
                    v-for="type in requestTypes"
                    :key="type.value"
                    class="badge bg-primary-subtle text-primary"
                  >
                    {{ type.label }}
                  </span>
                </div>

                <p class="text-muted mb-3">
                  {{ feature.description || 'Tidak ada deskripsi tambahan.' }}
                </p>

                <div class="text-muted small mb-3">
                  <span class="d-inline-flex align-items-center gap-2 me-3">
                    <span class="fw-semibold">Diajukan Oleh:</span> {{ feature.user?.name }}
                  </span>
                  <span class="d-inline-flex align-items-center gap-2 me-3">
                    <span class="fw-semibold">Unit:</span> {{ feature.requester_unit || feature.user?.unit?.name || '-' }}
                  </span>
                  <span class="d-inline-flex align-items-center gap-2">
                    <span class="fw-semibold">Instansi:</span>
                    {{ instansiLabel(feature.requester_instansi || feature.user?.instansi) }}
                  </span>
                </div>
                <div class="d-flex flex-wrap gap-2">
                  <span class="badge rounded-pill px-3 py-2 status-badge" :class="statusClass">
                    {{ statusLabel }}
                  </span>
                  <span
                    v-if="developmentStatusLabel"
                    class="badge rounded-pill px-3 py-2 dev-status-badge"
                    :class="developmentStatusClass"
                  >
                    {{ developmentStatusLabel }}
                  </span>
                  <span
                    class="badge rounded-pill px-3 py-2 priority-badge"
                    :class="priorityBadgeClass(feature.priority)"
                  >
                    {{ feature.priority_label }}
                  </span>
                </div>
              </div>
              <div class="col-md-3 text-md-end">
                <p class="text-muted small mb-0">
                  {{ formatDate(feature.created_at) }}
                </p>
              </div>
            </div>

            <div class="timeline mt-5">
              <div
                v-for="(step, index) in steps"
                :key="step.role"
                class="timeline-step"
                :class="{ completed: step.completed, current: step.current }"
              >
                <div class="step-indicator">
                  <span v-if="step.completed">✓</span>
                  <span v-else>{{ index + 1 }}</span>
                </div>
                <div class="step-content">
                  <h6 class="fw-semibold mb-1">{{ step.title }}</h6>
                  <p class="text-muted small mb-2">{{ step.description }}</p>
                  <div v-if="step.approval" class="approval-info">
                    <p class="mb-1">
                      <strong>{{ step.approval.user?.name }}</strong>
                      <span class="text-muted"> • {{ formatDate(step.approval.approved_at) }}</span>
                    </p>
                    <p v-if="step.approval.note" class="note">"{{ step.approval.note }}"</p>
                  </div>
                  <div v-else-if="step.current" class="text-primary small fw-semibold">Menunggu persetujuan</div>
                  <div v-else class="text-muted small">Belum diproses</div>
                </div>
              </div>
            </div>

            <div v-if="feature.attachment_url" class="attachment-panel mt-5 p-4 border rounded-4 bg-light">
              <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                  <h6 class="fw-semibold mb-1">Lampiran Permintaan</h6>
                  <p class="text-muted small mb-0">Berkas pendukung dari pemohon untuk pertimbangan tambahan.</p>
                  <p class="text-muted small mb-0 mt-1">{{ feature.attachment_name || 'Lampiran permintaan' }}</p>
                </div>
                <button
                  type="button"
                  class="btn btn-outline-primary px-4"
                  :disabled="downloadingAttachment"
                  @click="downloadAttachment"
                >
                  <span v-if="downloadingAttachment" class="spinner-border spinner-border-sm me-2"></span>
                  Unduh Lampiran
                </button>
              </div>
              <p v-if="attachmentError" class="text-danger small mb-0 mt-3">{{ attachmentError }}</p>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
          <div class="card-body p-4">
            <h5 class="fw-semibold mb-3">Riwayat Persetujuan</h5>
            <ul class="list-unstyled mb-0">
              <li v-for="history in approvalHistory" :key="history.id" class="approval-item">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <strong>{{ history.user?.name }}</strong>
                    <p class="text-muted small mb-0">{{ roleText(history.role) }}</p>
                  </div>
                  <span class="text-muted small">{{ formatDate(history.approved_at) }}</span>
                </div>
                <p v-if="history.note" class="text-muted small fst-italic mt-2">"{{ history.note }}"</p>
              </li>
            </ul>
            <p v-if="approvalHistory.length === 0" class="text-muted small mb-0">Belum ada persetujuan tercatat.</p>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
          <div class="card-body p-4">
            <h5 class="fw-semibold mb-3">Aksi Persetujuan</h5>
            <p class="text-muted small mb-4">
              Masukkan kode ACC pribadi Anda untuk menandatangani dan melanjutkan proses sesuai dengan peran yang dimiliki.
            </p>

            <div v-if="successMessage" class="alert alert-success py-2">{{ successMessage }}</div>
            <div v-if="errorMessage" class="alert alert-danger py-2">{{ errorMessage }}</div>

            <div v-if="canApprove" class="approval-form">
              <form @submit.prevent="approve" class="d-grid gap-3">
                <div>
                  <label class="form-label">Kode ACC</label>
                  <input
                    v-model="signCode"
                    type="password"
                    class="form-control form-control-lg"
                    placeholder="Masukkan kode ACC"
                    required
                  />
                </div>
                <div>
                  <label class="form-label">Catatan (opsional)</label>
                  <textarea
                    v-model="note"
                    class="form-control"
                    rows="3"
                    placeholder="Tambahkan catatan singkat jika diperlukan"
                  ></textarea>
                </div>
                <button type="submit" class="btn btn-primary py-2" :disabled="submitting">
                  <span v-if="submitting" class="spinner-border spinner-border-sm me-2"></span>
                  Setujui Tahap Ini
                </button>
              </form>
            </div>
            <div v-else class="alert alert-info py-2">
              {{ approvalHint }}
            </div>
          </div>
        </div>
      </div>

      <div v-if="isAdmin" class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
          <div class="card-body p-4">
            <h5 class="fw-semibold mb-3">Kelola Tiket</h5>
            <p class="text-muted small mb-4">
              Tetapkan prioritas tindak lanjut, tandai progres pengerjaan, dan hapus tiket jika pengajuan dibatalkan.
            </p>

            <div class="gitlab-sync-panel border rounded-4 p-3 mb-4">
              <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div class="flex-grow-1">
                  <h6 class="fw-semibold mb-1">Sinkronisasi GitLab</h6>
                  <p class="text-muted small mb-2">
                    Buat atau perbarui issue di GitLab agar tim pengembang menerima tugas ini secara otomatis.
                  </p>
                  <div v-if="gitlabIssue?.iid" class="text-muted small">
                    <span class="d-block">Issue #{{ gitlabIssue.iid }}</span>
                    <span v-if="gitlabIssue?.state" class="d-block mt-1">
                      <span
                        class="badge"
                        :class="gitlabIssue.state === 'closed'
                          ? 'bg-secondary-subtle text-secondary'
                          : 'bg-success-subtle text-success'"
                      >
                        Issue {{ formatGitlabState(gitlabIssue.state) }}
                      </span>
                    </span>
                    <span v-if="gitlabSyncedAtLabel" class="d-block mt-2">Sinkron terakhir {{ gitlabSyncedAtLabel }}</span>
                  </div>
                  <div v-else class="text-muted small fst-italic">
                    Belum ada issue GitLab untuk tiket ini.
                  </div>
                </div>
                <div class="gitlab-sync-actions d-flex flex-column align-items-end gap-2">
                  <a
                    v-if="gitlabIssue?.url"
                    :href="gitlabIssue.url"
                    target="_blank"
                    rel="noopener"
                    class="btn btn-outline-secondary btn-sm w-100"
                  >
                    Buka di GitLab
                  </a>
                  <button
                    type="button"
                    class="btn btn-primary w-100"
                    :disabled="gitlabSyncing"
                    @click="syncGitlabIssue"
                  >
                    <span v-if="gitlabSyncing" class="spinner-border spinner-border-sm me-2"></span>
                    {{ gitlabIssue?.iid ? 'Perbarui Issue' : 'Buat Issue GitLab' }}
                  </button>
                </div>
              </div>
            <div v-if="gitlabSuccess" class="alert alert-success py-2 mt-3 mb-0">{{ gitlabSuccess }}</div>
            <div v-if="gitlabError" class="alert alert-danger py-2 mt-3 mb-0">{{ gitlabError }}</div>
          </div>

          <div class="development-status-panel border rounded-4 p-3 mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
              <div>
                <h6 class="fw-semibold mb-1">Status Pengembangan</h6>
                <p class="text-muted small mb-0">
                  Gunakan status ini untuk melacak progres pengerjaan oleh tim development.
                </p>
              </div>
              <span
                v-if="developmentStatusLabel"
                class="badge rounded-pill px-3 py-2 current-status-badge"
                :class="developmentStatusClass"
              >
                {{ developmentStatusLabel }}
              </span>
            </div>
            <div class="row g-3 align-items-end mt-1">
              <div class="col-md-7 col-lg-8">
                <label class="form-label small fw-semibold" for="development-status-select">Tahap pengerjaan</label>
                <select
                  id="development-status-select"
                  v-model.number="selectedDevelopmentStatus"
                  class="form-select"
                  :disabled="developmentStatusSaving"
                >
                  <option v-for="option in developmentStatusOptions" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </option>
                </select>
              </div>
              <div class="col-md-5 col-lg-4 d-grid">
                <button
                  type="button"
                  class="btn btn-outline-primary"
                  :disabled="developmentStatusSaving || deleteLoading"
                  @click="updateDevelopmentStatus"
                >
                  <span v-if="developmentStatusSaving" class="spinner-border spinner-border-sm me-2"></span>
                  Simpan Status
                </button>
              </div>
            </div>
            <div v-if="developmentStatusSuccess" class="alert alert-success py-2 mt-3 mb-0">{{ developmentStatusSuccess }}</div>
            <div v-if="developmentStatusError" class="alert alert-danger py-2 mt-3 mb-0">{{ developmentStatusError }}</div>
          </div>

          <div v-if="prioritySuccess" class="alert alert-success py-2">{{ prioritySuccess }}</div>
          <div v-if="priorityError" class="alert alert-danger py-2">{{ priorityError }}</div>
          <div v-if="deleteError" class="alert alert-danger py-2">{{ deleteError }}</div>

            <div class="mb-3">
              <label class="form-label">Prioritas Tiket</label>
              <select v-model="selectedPriority" class="form-select">
                <option v-for="option in priorityOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </select>
            </div>

            <div class="d-flex flex-wrap gap-2">
              <button
                type="button"
                class="btn btn-primary"
                :disabled="prioritySaving || deleteLoading"
                @click="updatePriority"
              >
                <span v-if="prioritySaving" class="spinner-border spinner-border-sm me-2"></span>
                Simpan Prioritas
              </button>
              <button
                type="button"
                class="btn btn-outline-danger ms-auto"
                :disabled="prioritySaving || deleteLoading"
                @click="deleteFeature"
              >
                <span v-if="deleteLoading" class="spinner-border spinner-border-sm me-2"></span>
                Hapus Tiket
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4">
          <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
              <h5 class="fw-semibold mb-0">Komentar Admin</h5>
              <span class="badge bg-primary-subtle text-primary">{{ comments.length }} komentar</span>
            </div>

            <div v-if="commentError" class="alert alert-danger py-2">{{ commentError }}</div>

            <div v-if="canComment" class="comment-form mb-4">
              <form @submit.prevent="postComment" class="d-grid gap-3">
                <div>
                  <label class="form-label">Tanggapan Admin</label>
                  <textarea
                    v-model="adminComment"
                    class="form-control"
                    rows="3"
                    placeholder="Tulis komentar atau saran untuk pemohon"
                  ></textarea>
                </div>
                <div>
                  <label class="form-label">Lampiran (opsional)</label>
                  <input
                    ref="commentInput"
                    type="file"
                    class="form-control"
                    @change="handleCommentFileChange"
                    accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                  />
                  <div v-if="commentFileName" class="selected-attachment mt-2">
                    <span class="badge bg-primary-subtle text-primary px-3 py-2">{{ commentFileName }}</span>
                    <button type="button" class="btn btn-link text-decoration-none ms-2" @click="resetCommentFile">
                      Hapus lampiran
                    </button>
                  </div>
                  <p class="text-muted small mt-2 mb-0">
                    Format diperbolehkan: PDF, DOC, DOCX, JPG, PNG. Maksimal 5MB.
                  </p>
                </div>
                <button type="submit" class="btn btn-primary px-4" :disabled="commentSubmitting">
                  <span v-if="commentSubmitting" class="spinner-border spinner-border-sm me-2"></span>
                  Kirim Komentar
                </button>
              </form>
            </div>

            <div v-if="comments.length === 0" class="text-muted small fst-italic">
              Belum ada komentar admin untuk tiket ini.
            </div>

            <ul v-else class="list-unstyled mb-0 d-grid gap-3">
              <li v-for="comment in comments" :key="comment.id" class="comment-item p-3 border rounded-4">
                <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                  <div>
                    <strong>{{ comment.user?.name }}</strong>
                    <p class="text-muted small mb-0">{{ formatDate(comment.created_at) }}</p>
                  </div>
                  <p class="mb-0 d-flex align-items-center">
                    <i class="bi bi-person me-2"></i>
                    <span class="badge bg-secondary-subtle text-secondary">
                      Administrator
                    </span>
                  </p>
                </div>
                <p class="mb-2">{{ comment.comment }}</p>
                <div v-if="comment.attachment_url" class="mt-2 d-flex flex-column gap-2">
                  <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="text-muted small">{{ comment.attachment_name || 'Lampiran komentar' }}</span>
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-primary d-flex align-items-center gap-2"
                      @click="downloadCommentAttachment(comment)"
                      :disabled="isCommentDownloading(comment.id)"
                    >
                      <span
                        v-if="isCommentDownloading(comment.id)"
                        class="spinner-border spinner-border-sm"
                        role="status"
                        aria-hidden="true"
                      ></span>
                      <span>Unduh Lampiran</span>
                    </button>
                  </div>
                  <p v-if="commentDownloadError(comment.id)" class="text-danger small mb-0">
                    {{ commentDownloadError(comment.id) }}
                  </p>
                </div>
              </li>
            </ul>
          </div>
        </div>
      </div>
      <transition name="fade">
        <div
          v-if="showAttachmentViewer"
          class="attachment-viewer-backdrop"
          @click.self="closeAttachmentViewer"
        >
          <div class="attachment-viewer-modal card border-0 shadow-lg">
            <div class="attachment-viewer-header card-header d-flex justify-content-between align-items-start gap-3">
              <div>
                <h6 class="mb-1">Pratinjau Lampiran</h6>
                <p class="text-muted small mb-0">{{ feature?.attachment_name || 'Lampiran permintaan' }}</p>
                <p v-if="attachmentPreviewType" class="text-muted small mb-0">
                  Jenis berkas: {{ attachmentPreviewType }}
                </p>
              </div>
              <button type="button" class="btn-close" aria-label="Tutup pratinjau" @click="closeAttachmentViewer"></button>
            </div>
            <div class="attachment-viewer-body card-body">
              <template v-if="isAttachmentImage">
                <img
                  :src="attachmentPreviewUrl"
                  :alt="feature?.attachment_name || 'Lampiran permintaan'"
                  class="img-fluid rounded-3"
                />
              </template>
              <template v-else-if="isAttachmentPdf">
                <iframe :src="attachmentPreviewUrl" title="Pratinjau lampiran"></iframe>
              </template>
              <template v-else>
                <div class="d-flex flex-column justify-content-center align-items-center text-center h-100">
                  <p class="text-muted mb-3">
                    Pratinjau tidak tersedia untuk jenis berkas ini. Anda dapat membuka berkas di tab baru.
                  </p>
                  <a :href="attachmentPreviewUrl" target="_blank" rel="noopener" class="btn btn-primary">
                    Buka di Tab Baru
                  </a>
                </div>
              </template>
            </div>
          </div>
        </div>
      </transition>
    </div>
  </div>
</template>

<script setup>
import { useFeatureRequestDetail } from '@/ticketing/composables'

const {
  feature,
  loading,
  signCode,
  note,
  submitting,
  successMessage,
  errorMessage,
  isAdmin,
  statusLabel,
  statusClass,
  developmentStatusLabel,
  developmentStatusClass,
  requestTypes,
  instansiLabel,
  steps,
  approvalHistory,
  canApprove,
  approvalHint,
  roleText,
  formatDate,
  approve,
  priorityOptions,
  selectedPriority,
  prioritySaving,
  prioritySuccess,
  priorityError,
  priorityBadgeClass,
  updatePriority,
  developmentStatusOptions,
  selectedDevelopmentStatus,
  developmentStatusSaving,
  developmentStatusSuccess,
  developmentStatusError,
  updateDevelopmentStatus,
  deleteFeature,
  deleteLoading,
  deleteError,
  comments,
  adminComment,
  commentFileName,
  commentError,
  commentSubmitting,
  commentInput,
  canComment,
  handleCommentFileChange,
  resetCommentFile,
  postComment,
  downloadCommentAttachment,
  isCommentDownloading,
  commentDownloadError,
  downloadAttachment,
  downloadingAttachment,
  attachmentError,
  gitlabIssue,
  gitlabSyncedAtLabel,
  gitlabSyncing,
  gitlabSuccess,
  gitlabError,
  syncGitlabIssue,
  formatGitlabState
} = useFeatureRequestDetail()
</script>

<style scoped>
.attachment-viewer-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.65);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
  z-index: 1080;
}

.attachment-viewer-modal {
  width: min(960px, 100%);
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  border-radius: 1rem;
  overflow: hidden;
}

.attachment-viewer-body {
  flex: 1;
  background: #f8fafc;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
  min-height: 320px;
}

.attachment-viewer-body iframe {
  width: 100%;
  height: 70vh;
  border: 0;
  border-radius: 0.75rem;
  background: #fff;
}

.attachment-viewer-body img {
  max-height: 70vh;
  width: 100%;
  object-fit: contain;
  background: #fff;
  border-radius: 0.75rem;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.1);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>


