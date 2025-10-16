<template>
  <div class="page-wrapper ticket-monitoring py-2">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 section-heading">
      <div class="heading-copy">
        <h2 class="fw-semibold mb-1">Monitoring Progres Ticket</h2>
        <p class="text-muted mb-0">
          Lihat perkembangan pengerjaan ticket oleh tim IT tanpa perlu akses persetujuan.
        </p>
      </div>
    </div>

    <div class="monitoring-controls d-flex flex-wrap align-items-center gap-3 mb-4">
      <div class="tab-switcher d-flex flex-wrap gap-2">
        <button
          v-for="option in tabOptions"
          :key="`tab-${option.value}`"
          type="button"
          class="btn"
          :class="activeTab === option.value ? 'btn-primary' : 'btn-outline-primary'"
          @click="setTab(option.value)"
        >
          {{ option.label }}
        </button>
      </div>

      <div class="ms-auto d-flex align-items-center gap-2 pagination-size-control">
        <label class="form-label mb-0 text-muted small fw-semibold" for="monitoring-per-page">
          Per halaman
        </label>
        <select
          id="monitoring-per-page"
          v-model.number="perPage"
          class="form-select form-select-sm"
        >
          <option v-for="option in perPageOptions" :key="option" :value="option">
            {{ option }}
          </option>
        </select>
      </div>
    </div>

    <p class="text-muted small mb-4">{{ tabDescription }}</p>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status"></div>
      <p class="text-muted mt-3">Memuat daftar ticket...</p>
    </div>

    <div v-else>
      <div v-if="!tickets.length" class="alert alert-info border-0 shadow-sm">
        {{ emptyMessage }}
      </div>

      <div v-else class="row g-4 row-cols-1 row-cols-xl-2">
        <div v-for="item in tickets" :key="item.id" class="col">
          <div class="card monitoring-card border-0 shadow-sm h-100">
            <div class="card-body p-4 d-flex flex-column gap-3">
              <header class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div class="flex-grow-1">
                  <h4 class="fw-semibold mb-1">{{ item.request_types_label }}</h4>
                  <p class="text-muted mb-0">
                    {{ item.description || 'Tidak ada deskripsi tambahan.' }}
                  </p>
                </div>
                <div class="status-stack d-flex flex-column align-items-end gap-2 text-end">
                  <span
                    class="badge rounded-pill"
                    :class="developmentStatusBadgeClass(item.development_status)"
                  >
                    {{ developmentStatusLabel(item) }}
                  </span>
                  <!-- <span class="badge rounded-pill" :class="statusBadgeClass(item.status)">
                    {{ statusLabel(item) }}
                  </span> -->
                  <span class="badge rounded-pill" :class="priorityBadgeClass(item.priority)">
                    {{ item.priority_label }}
                  </span>
                </div>
              </header>

              <section class="progress-section">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <h6 class="text-muted text-uppercase small fw-semibold mb-0">
                    Progress Pengerjaan
                  </h6>
                  <span class="badge bg-primary-subtle text-primary">
                    {{ developmentProgress(item) }}%
                  </span>
                </div>
                <div class="progress progress-thin" role="progressbar" aria-valuemin="0" aria-valuemax="100">
                  <div
                    class="progress-bar"
                    :style="{ width: `${developmentProgress(item)}%` }"
                    :aria-valuenow="developmentProgress(item)"
                  ></div>
                </div>
                <div class="development-steps text-muted small mt-3">
                  <span>Analisis</span>
                  <span>Pengerjaan</span>
                  <span>Testing</span>
                  <span>Ready Release</span>
                </div>
              </section>

              <section class="ticket-meta text-muted small mt-auto">
                <p class="mb-1">
                  <strong class="text-dark">Diajukan oleh:</strong>
                  {{ item.user?.name || '-' }}
                </p>
                <p class="mb-1">
                  <strong class="text-dark">Unit:</strong>
                  {{
                    item.requester_unit?.name ||
                      item.requester_unit_name ||
                      item.user?.unit?.name ||
                      '-'
                  }}
                </p>
                <p class="mb-1">
                  <strong class="text-dark">Instansi:</strong>
                  {{ instansiLabel(item.requester_instansi || item.user?.instansi) }}
                </p>
                <p class="mb-0">
                  <strong class="text-dark">Pembaruan terakhir:</strong>
                  {{ formatDate(item.updated_at) }}
                </p>
              </section>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-4">
        <p class="text-muted small mb-0">
          {{ pageSummary }}
        </p>

        <nav v-if="hasPagination" aria-label="Navigasi halaman monitoring ticket">
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
import { useTicketMonitoring } from '@/ticketing/composables'

const {
  tickets,
  loading,
  perPage,
  perPageOptions,
  activeTab,
  tabOptions,
  setTab,
  tabDescription,
  emptyMessage,
  statusLabel,
  statusBadgeClass,
  priorityBadgeClass,
  developmentStatusBadgeClass,
  developmentStatusLabel,
  developmentProgress,
  pageMeta,
  pageNumbers,
  hasPagination,
  pageSummary,
  goToPage,
  nextPage,
  previousPage,
  formatDate,
  instansiLabel
} = useTicketMonitoring()
</script>

<style scoped>
.ticket-monitoring .progress-section {
  border: 1px solid #eef2f7;
  border-radius: 12px;
  padding: 1.25rem;
  background-color: #f8fafc;
}

.progress-thin {
  height: 10px;
  border-radius: 999px;
  background-color: rgba(0, 82, 204, 0.1);
}

.progress-bar {
  background-image: linear-gradient(90deg, #0d6efd, #6610f2);
  transition: width 0.3s ease;
}

.development-steps {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.5rem;
  text-align: center;
}

.monitoring-card .status-stack .badge {
  min-width: 140px;
}

.pagination-size-control select {
  min-width: 80px;
}
</style>
