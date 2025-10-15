<template>
  <div class="page-wrapper ticket-index py-2">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 section-heading">
      <div class="heading-copy">
        <h2 class="fw-semibold mb-1">Ticketing Permintaan Form</h2>
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
        Hanya pemohon yang dapat membuat tiket baru.
      </div>
    </div>

    <div v-if="roleNotice" class="alert alert-info border-0 shadow-sm mb-4">
      {{ roleNotice }}
    </div>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status"></div>
      <p class="text-muted mt-3">Memuat daftar tiket...</p>
    </div>

    <div v-else-if="!hasRequests" class="empty-state text-center py-5">
      <h4 class="fw-semibold">Belum ada tiket pengajuan</h4>
      <p class="text-muted mb-4">
        Mulai ajukan permintaan form untuk mengaktifkan alur persetujuan digital dengan manager dan direktur.
      </p>
      <router-link
        v-if="canCreate"
        to="/feature-request/create"
        class="btn btn-outline-primary px-4"
      >
        Buat Tiket Pertama
      </router-link>
      <p v-else class="text-muted mb-0">
        Hubungi pemohon untuk mengirimkan tiket pertama.
      </p>
    </div>

    <div v-else>
      <div class="ticket-filter-bar d-flex flex-wrap align-items-center gap-3">
        <div class="d-flex align-items-center gap-2">
          <label class="form-label mb-0 text-muted small fw-semibold" for="ticket-filter-select">
            Tampilkan
          </label>
          <select
            id="ticket-filter-select"
            v-model="completionFilter"
            class="form-select form-select-sm"
          >
            <option v-for="option in completionOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
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

      <div v-if="!hasFilteredRequests" class="alert alert-info border-0 shadow-sm">
        {{ emptyFilteredMessage }}
      </div>

      <div v-else class="row g-4 row-cols-1 row-cols-xl-2">
        <div v-for="item in filteredRequests" :key="item.id" class="col">
          <div class="card ticket-card border-0 shadow-sm h-100">
            <div class="card-body p-4">
              <div class="ticket-card-layout">
                <div class="ticket-card-main">
                  <header class="ticket-card-header">
                    <div class="ticket-title">
                      <h4 class="fw-semibold mb-1">{{ item.request_types_label }}</h4>
                      <p class="text-muted mb-0">
                        {{ item.description || 'Tidak ada deskripsi tambahan.' }}
                      </p>
                    </div>
                    <div class="ticket-status-stack">
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
                  </header>

                  <div class="ticket-flags d-flex flex-wrap gap-2 mt-3 mt-lg-4">
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
                    <p class="text-muted small mb-1">{{ item.requester_unit || item.user?.unit?.name || '-' }}</p>
                    <p class="text-muted small mb-3">{{ instansiLabel(item.requester_instansi || item.user?.instansi) }}</p>
                    <p class="text-muted mb-0">{{ formatDate(item.created_at) }}</p>
                  </div>
                  <router-link :to="`/feature-request/${item.id}`" class="btn btn-outline-primary w-100">
                    Lihat Detail
                  </router-link>
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

        <nav v-if="hasPagination" aria-label="Navigasi halaman tiket">
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
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useFeatureRequestIndex } from '@/ticketing/composables'
import { ROLE, ROLE_LABELS } from '@/constants/roles'

const stageDefinitions = (item) => {
  const instansi = item?.requester_instansi || item?.user?.instansi

  const stages = [
    { role: ROLE.USER, label: ROLE_LABELS[ROLE.USER] },
    { role: ROLE.MANAGER, label: ROLE_LABELS[ROLE.MANAGER] }
  ]

  if (instansi !== 'wiradadi') {
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
  instansiLabel,
  canCreate,
  isAdmin,
  pageMeta,
  pageNumbers,
  hasPagination,
  pageSummary,
  goToPage,
  nextPage,
  previousPage
} = useFeatureRequestIndex()

const completionFilter = ref('active')
const completionOptions = [
  { value: 'all', label: 'Semua tiket' },
  { value: 'active', label: 'Sedang diproses' },
  { value: 'completed', label: 'Sudah selesai' }
]
const completedStatuses = ['approved_b', 'done']

const hasRequests = computed(() => {
  if ((requests.value ?? []).length > 0) {
    return true
  }

  return (pageMeta.value?.total ?? 0) > 0
})

const filteredRequests = computed(() => {
  const list = requests.value ?? []

  if (completionFilter.value === 'active') {
    return list.filter((item) => !completedStatuses.includes(item.status))
  }

  if (completionFilter.value === 'completed') {
    return list.filter((item) => completedStatuses.includes(item.status))
  }

  return list
})

const hasFilteredRequests = computed(() => filteredRequests.value.length > 0)

const emptyFilteredMessage = computed(() => {
  if (completionFilter.value === 'active') {
    return 'Tidak ada tiket aktif saat ini.'
  }

  if (completionFilter.value === 'completed') {
    return 'Belum ada tiket yang ditandai selesai.'
  }

  return 'Belum ada tiket pengajuan.'
})

const roleNotice = computed(() => {
  if (isAdmin.value) {
    return 'Sebagai admin Anda dapat memantau seluruh tiket dan menambahkan komentar pada setiap pengajuan.'
  }

  if (!canCreate.value) {
    return 'Anda memiliki akses pantau dan persetujuan. Untuk membuat tiket baru silakan minta bantuan pemohon.'
  }

  return ''
})

const displaySummary = computed(() => {
  if (completionFilter.value === 'all') {
    return pageSummary.value
  }

  const count = filteredRequests.value.length

  if (!count) {
    return 'Tidak ada tiket yang ditampilkan.'
  }

  return `Menampilkan 1-${count} dari ${count} tiket`
})

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
</script>
