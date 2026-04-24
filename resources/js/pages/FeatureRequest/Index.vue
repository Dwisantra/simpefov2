<template>
  <div class="page-wrapper ticket-index py-2">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 section-heading">
      <div class="heading-copy">
        <h2 class="fw-semibold mb-1">Ticketing Pengajuan Form</h2>
        <p class="text-muted mb-0">Pantau status pengajuan dan proses persetujuan lintas level secara real-time.</p>
      </div>
      <router-link
        v-if="canCreate"
        to="/feature-request/create"
        class="btn btn-primary px-4 py-2 create-button"
      >
        + Ajukan Form Baru
      </router-link>
      <div v-else class="text-muted small fw-semibold">
        Hanya pemohon yang dapat membuat ticket baru.
      </div>
    </div>

    <div v-if="roleNotice" class="alert alert-info border-0 shadow-sm mb-4">
      {{ roleNotice }}
    </div>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status"></div>
      <p class="text-muted mt-3">Memuat daftar ticket...</p>
    </div>

    <div v-else>
      <div class="workflow-tabs d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="stage-switcher d-flex flex-wrap gap-2">
          <button
            v-for="option in stageOptions"
            :key="`stage-${option.value}`"
            type="button"
            class="btn"
            :class="stage === option.value ? 'btn-primary' : 'btn-outline-primary'"
            @click="setStage(option.value)"
          >
            {{ option.label }}
          </button>
        </div>

        <div class="ms-auto d-flex align-items-center gap-2 pagination-size-control">
          <label class="form-label mb-0 text-muted small fw-semibold" for="ticket-per-page">
            Per halaman
          </label>
          <select
            id="ticket-per-page"
            v-model.number="perPage"
            class="form-select form-select-sm"
          >
            <option v-for="option in perPageOptions" :key="option" :value="option">
              {{ option }}
            </option>
          </select>
        </div>
      </div>

      <p class="text-muted small mb-4">{{ stageDescription }}</p>

      <div v-if="!hasStageItems" class="alert alert-info border-0 shadow-sm d-flex flex-column flex-lg-row gap-3 align-items-lg-center">
        <div class="flex-grow-1">
          {{ emptyStageMessage }}
        </div>
        <router-link
          v-if="stage === 'submission' && canCreate"
          to="/feature-request/create"
          class="btn btn-outline-primary"
        >
          Buat Ticket Pertama
        </router-link>
      </div>

      <div v-else class="row g-4 row-cols-1 row-cols-xl-2">
        <div v-for="item in requests" :key="item.id" class="col">
          <div class="card ticket-card border-0 shadow-sm">
            <div class="card-body p-4">
              <div class="ticket-card-layout">
                <div class="ticket-card-main">
                  <header class="ticket-card-header d-flex justify-content-between align-items-start gap-3">
                    <div class="ticket-title flex-grow-1">
                      <h4 class="fw-semibold mb-1">{{ item.request_types_label }}</h4>                      
                      <div class="description-wrapper">
                        <div
                          class="description-content"
                          v-html="
                            isExpanded(item.id)
                              ? formatDescription(item.description)
                              : formatDescription(truncateText(item.description, DESCRIPTION_LIMIT))
                          "
                        ></div>

                        <button
                          v-if="(item.description?.length ?? 0) > DESCRIPTION_LIMIT"
                          @click="toggleExpand(item.id)"
                          class="btn btn-link btn-sm p-0 text-decoration-none fw-medium"
                        >
                          {{ isExpanded(item.id) ? 'Sembunyikan' : 'Baca Selengkapnya' }}
                        </button>
                      </div>
                    </div>
                  </header>

                  <div class="ticket-status-stack d-flex flex-row flex-wrap gap-2 justify-content-end align-items-start">
                    <span class="badge rounded-pill" :class="statusBadgeClass(item.status)">
                      {{ statusLabel(item) }}
                    </span>

                    <span
                      v-if="item.development_status_label"
                      class="badge rounded-pill dev-status-chip"
                      :class="developmentStatusBadgeClass(item.development_status)"
                    >
                      {{ item.development_status_label }}
                    </span>

                    <span class="badge rounded-pill priority-chip" :class="priorityBadgeClass(item.priority)">
                      {{ item.priority_label }}
                    </span>
                  </div>

                  <div class="ticket-flags d-flex flex-wrap">
                    <span
                      v-if="item.attachment_url"
                      class="badge bg-info-subtle text-info d-inline-flex align-items-center gap-2"
                    >
                      <span class="flag-icon">📎</span>
                      Lampiran tersedia
                    </span>
                    <span
                      v-if="item.comments_count"
                      class="badge bg-primary-subtle text-primary d-inline-flex align-items-center gap-2"
                    >
                      <span class="flag-icon">💬</span>
                      {{ item.comments_count }} komentar admin
                    </span>
                  </div>

                  <div class="progress-tracker mt-4">
                    <div
                      class="tracker-bar"
                      role="progressbar"
                      :aria-valuenow="progressAria(item)"
                      aria-valuemin="0"
                      aria-valuemax="100"
                    >
                      <div class="tracker-fill" :style="{ width: `${progressPercentage(item)}%` }"></div>
                    </div>
                    <div class="tracker-points">
                      <div
                        v-for="(stage, index) in stageDefinitions(item)"
                        :key="stage.role"
                        class="tracker-point"
                        :class="pointStateClass(stageState(item, stage, index))"
                      >
                        <span class="tracker-dot"></span>
                        <span class="tracker-point-label">{{ stage.label }}</span>
                      </div>
                    </div>
                  </div>
                </div>

                <aside class="ticket-card-meta">
                  <div class="ticket-meta-copy">
                    <small class="text-muted d-block">Diajukan oleh</small>
                    <span class="fw-semibold d-block">{{ item.user?.name }}</span>
                    <p class="text-muted small mb-1">
                      {{
                        item.requester_unit?.name ||
                          item.requester_unit_name ||
                          item.user?.unit?.name ||
                          '-'
                      }}
                    </p>
                    <p class="text-muted small mb-3">{{ instansiLabel(item.requester_instansi || item.user?.instansi) }}</p>
                    <p class="text-muted mb-0">{{ formatDate(item.created_at) }}</p>

                    <!-- Validation Link Section -->
                    <div v-if="stage === 'submission' && item.status === 'pending'" class="mt-3 pt-3 border-top">
                      <div v-if="item.validation_link_info && !isLinkExpired(item.validation_link_info.expires_at)" class="mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                          <small class="text-success fw-semibold d-flex align-items-center gap-1">
                            <i class="bi bi-check-circle-fill"></i> Link Approval Manager Aktif
                          </small>
                        </div>
                        <div class="input-group input-group-sm">
                          <input 
                            type="text" 
                            class="form-control" 
                            :value="item.validation_link_info.link" 
                            readonly 
                            style="background-color: #f8f9fa;"
                          >
                          
                          <button 
                            @click="copyLinkToClipboard(item.validation_link_info.link)"
                            class="btn btn-success btn-sm" 
                            type="button"
                            title="Salin link"
                          >
                            <i class="bi bi-clipboard me-1"></i>Salin
                          </button>
                        </div>
                      </div>

                      <div v-else-if="item.validation_link_info && isLinkExpired(item.validation_link_info.expires_at)" class="alert alert-warning py-2 mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <small>Link sudah kadaluarsa, buat yang baru</small>
                      </div>

                      <div v-else class="alert alert-info py-2 mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-info-circle"></i>
                        <small>Link tidak ada / sudah kadaluarsa</small>
                      </div>

                      <button 
                        v-if="!item.validation_link_info || isLinkExpired(item.validation_link_info.expires_at)"
                        @click="regenerateValidationLink(item.id)"
                        :disabled="regeneratingLinks[item.id]"
                        class="btn btn-outline-secondary btn-sm w-100"
                        type="button"
                      >
                        <span v-if="regeneratingLinks[item.id]" class="spinner-border spinner-border-sm me-1"></span>
                        <i v-else class="bi bi-arrow-repeat me-1"></i>
                          Regenerate Link
                      </button>
                    </div>

                    <!-- Action Buttons -->
                    <div class="mt-3 pt-3 border-top">
                      <router-link :to="`/feature-request/${item.id}`" class="btn btn-outline-primary btn-sm w-100">
                        <i class="bi bi-eye me-1"></i>Lihat Detail
                      </router-link>
                    </div>
                  </div>
                </aside>
              </div>

              
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-4">
        <p class="text-muted small mb-0">
          {{ displaySummary }}
        </p>

        <nav v-if="hasPagination" aria-label="Navigasi halaman ticket">
          <ul class="pagination pagination-sm mb-0">
            <li class="page-item" :class="{ disabled: pageMeta.current <= 1 }">
              <button
                class="page-link"
                type="button"
                @click="previousPage"
                :disabled="pageMeta.current <= 1"
              >
                Sebelumnya
              </button>
            </li>
            <li
              v-for="page in pageNumbers"
              :key="page"
              class="page-item"
              :class="{ active: pageMeta.current === page }"
            >
              <button class="page-link" type="button" @click="goToPage(page)">
                {{ page }}
              </button>
            </li>
            <li class="page-item" :class="{ disabled: pageMeta.current >= pageMeta.last }">
              <button
                class="page-link"
                type="button"
                @click="nextPage"
                :disabled="pageMeta.current >= pageMeta.last"
              >
                Selanjutnya
              </button>
            </li>
          </ul>
        </nav>
      </div>
    </div>

    <LinkModal 
      :show="showRegenerateModal" 
      :link="regeneratedLinkData.link"
      :expires-at="regeneratedLinkData.expiresAt"
      message="Link approval manager berhasil dibuat ulang!"
      @close="closeRegenerateModal"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useFeatureRequestIndex } from '@/ticketing/composables'
import { ROLE, ROLE_LABELS } from '@/constants/roles'
import { ref } from 'vue'
import LinkModal from '@/components/LinkModal.vue'
import { useToast } from "primevue/usetoast";

const toast = useToast();
const DESCRIPTION_LIMIT = 150
const expandTickets = ref([])
const truncateText = (text, length = DESCRIPTION_LIMIT) => {
  if (!text) return ''
  return text.length > length ? text.substring(0, length) + '...' : text
}

const toggleExpand = (id) => {
  const index = expandTickets.value.indexOf(id)
  if (index > -1) {
    expandTickets.value.splice(index, 1)
  } else {
    expandTickets.value.push(id)
  }
}

const isExpanded = (id) => expandTickets.value.includes(id)

const stageDefinitions = (item = null) => {
  const requiresDirectorA = item?.requires_director_a_approval !== false

  const stages = [
    { role: ROLE.USER, label: ROLE_LABELS[ROLE.USER] },
    { role: ROLE.MANAGER, label: ROLE_LABELS[ROLE.MANAGER] }
  ]

  if (requiresDirectorA) {
    stages.push({ role: ROLE.DIRECTOR_A, label: ROLE_LABELS[ROLE.DIRECTOR_A] })
  }

  stages.push({ role: ROLE.DIRECTOR_B, label: ROLE_LABELS[ROLE.DIRECTOR_B] })

  return stages
}

const {
  requests,
  loading,
  perPage,
  perPageOptions,
  statusLabel,
  statusBadgeClass,
  priorityBadgeClass,
  developmentStatusBadgeClass,
  progressPercentage,
  progressSteps,
  formatDate,
  formatDescription,
  instansiLabel,
  canCreate,
  isAdmin,
  pageMeta,
  pageNumbers,
  hasPagination,
  pageSummary,
  goToPage,
  nextPage,
  previousPage,
  stage,
  stageOptions,
  stageDescription,
  setStage,
  regenerateValidationLink,
  regeneratingLinks,
  showRegenerateModal,
  regeneratedLinkData,
  closeRegenerateModal
} = useFeatureRequestIndex()

const hasStageItems = computed(() => (requests.value ?? []).length > 0)

const emptyStageMessage = computed(() => {
  if (stage.value === 'development') {
    return 'Belum ada ticket yang memasuki tahap pengerjaan tim IT.'
  }

  return 'Belum ada ticket yang menunggu persetujuan.'
})

const roleNotice = computed(() => {
  if (isAdmin.value) {
    return 'Sebagai admin Anda dapat memantau seluruh ticket dan menambahkan komentar pada setiap pengajuan.'
  }

  if (!canCreate.value) {
    return 'Anda memiliki akses pantau dan persetujuan. Untuk membuat ticket baru silahkan minta bantuan pemohon.'
  }

  return ''
})

const displaySummary = computed(() => pageSummary.value)

const stageState = (item, stage, index) => {
  const completedSteps = progressSteps(item)

  if (completedSteps >= index + 1) {
    return 'completed'
  }

  if (item.current_stage_role && item.current_stage_role === stage.role) {
    return 'current'
  }

  return 'upcoming'
}

const pointStateClass = (state) => ({
  completed: state === 'completed',
  current: state === 'current'
})

const progressAria = (item) => progressPercentage(item)

const isLinkExpired = (expiresAt) => {
  if (!expiresAt) return true
  const expireTime = new Date(expiresAt).getTime()
  const now = new Date().getTime()
  return expireTime < now
}

const copyLinkToClipboard = (link) => {
  navigator.clipboard.writeText(link).then(() => {
    toast.add({ severity: 'info', summary: 'Info', detail: 'Link berhasil disalin!', life: 3000 });
  }).catch(() => {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Gagal menyalin link', life: 3000 });
  })
}
</script>
