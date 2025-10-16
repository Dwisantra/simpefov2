<template>
  <div class="page-wrapper jangmed-priorities py-2">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
      <div>
        <h2 class="fw-semibold mb-1">Daftar Prioritas Ticket</h2>
        <p class="text-muted mb-0">
          Kelola prioritas seluruh ticket yang telah melewati persetujuan manajemen sebelum diteruskan ke tim IT.
        </p>
      </div>
      <div class="text-muted small">Terakhir diperbarui {{ new Date().toLocaleString('id-ID') }}</div>
    </div>

    <div v-if="message" class="alert py-2" :class="`alert-${messageType}`">
      {{ message }}
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <div v-if="loading" class="text-center py-5">
          <div class="spinner-border text-primary" role="status"></div>
          <p class="text-muted mt-3 mb-0">Memuat daftar ticket selesai...</p>
        </div>

        <template v-else>
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div class="d-flex flex-wrap gap-2">
              <button
                v-for="option in scopeOptions"
                :key="`scope-${option.value}`"
                type="button"
                class="btn btn-sm"
                :class="scope === option.value ? 'btn-primary' : 'btn-outline-primary'"
                @click="setScope(option.value)"
              >
                {{ option.label }}
              </button>
            </div>
            <div class="d-flex align-items-center gap-2">
              <label class="text-muted small mb-0" for="jangmed-per-page">Baris per halaman</label>
              <select id="jangmed-per-page" v-model.number="perPage" class="form-select form-select-sm w-auto">
                <option :value="5">5</option>
                <option :value="10">10</option>
                <option :value="20">20</option>
                <option :value="50">50</option>
              </select>
            </div>
          </div>

          <div class="text-muted small mb-3">
            {{ pageSummary }}
          </div>

          <div class="table-responsive rounded-4 border bg-white">
            <table class="table align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Ticket</th>
                  <!-- <th>Deskripsi</th> -->
                  <th>Pemohon</th>
                  <th>Status</th>
                  <th>Prioritas</th>
                  <th class="text-center">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="items.length === 0">
                  <td colspan="5" class="text-center text-muted py-4">
                    {{ emptyMessage }}
                  </td>
                </tr>
                <tr v-for="item in items" :key="item.id">
                  <td>
                    <div class="fw-semibold">{{ item.request_types_label || item.title }}</div>
                    <div class="text-muted small">Diperbarui {{ formatDate(item.updated_at) }}</div>
                  </td>
                  <!-- <td class="text-muted small">{{ item.description || '-' }}</td> -->
                  <td>
                    <div class="fw-semibold">{{ item.user?.name || '-' }}</div>
                    <div class="text-muted small">{{ item.user?.unit?.name || '-' }}</div>
                    <div class="text-muted small">{{ instansiLabel(item.user?.instansi) }}</div>
                  </td>
                  <td>
                    <span class="badge bg-info text-white">{{ item.development_status_label }}</span>
                  </td>
                  <td>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                      <select
                        class="form-select form-select-sm"
                        :value="localPriorities[item.id]"
                        :disabled="rowSaving[item.id] || isPriorityLocked(item)"
                        @change="(event) => (localPriorities[item.id] = event.target.value)"
                      >
                        <option v-for="option in priorityOptions" :key="option.value" :value="option.value">
                          {{ option.label }}
                        </option>
                      </select>
                      <span class="badge" :class="priorityBadgeClass(localPriorities[item.id])">
                        {{ priorityLabel(localPriorities[item.id]) }}
                      </span>
                    </div>
                  </td>
                  <td class="text-center">
                    <button
                      type="button"
                      class="btn btn-sm rounded-pill btn-primary px-3"
                      :disabled="rowSaving[item.id] || isPriorityLocked(item)"
                      @click="updatePriority(item)"
                    >
                      <span v-if="rowSaving[item.id]" class="spinner-border spinner-border-sm me-2"></span>
                      Simpan
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <nav v-if="pagination.total > 0 && pagination.lastPage > 1" class="mt-3" aria-label="Navigasi halaman">
            <ul class="pagination pagination-sm mb-0">
              <li class="page-item" :class="{ disabled: pagination.currentPage === 1 }">
                <button
                  class="page-link"
                  type="button"
                  :disabled="pagination.currentPage === 1"
                  @click="previousPage"
                >
                  Sebelumnya
                </button>
              </li>
              <li v-for="page in pageNumbers" :key="`jangmed-page-${page}`" class="page-item" :class="{ active: pagination.currentPage === page }">
                <button class="page-link" type="button" @click="changePage(page)">{{ page }}</button>
              </li>
              <li class="page-item" :class="{ disabled: pagination.currentPage >= pagination.lastPage }">
                <button
                  class="page-link"
                  type="button"
                  :disabled="pagination.currentPage >= pagination.lastPage"
                  @click="nextPage"
                >
                  Berikutnya
                </button>
              </li>
            </ul>
          </nav>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useJangmedPriorities } from '@/ticketing/composables'

const {
  items,
  loading,
  perPage,
  scope,
  pagination,
  message,
  messageType,
  priorityOptions,
  priorityBadgeClass,
  scopeOptions,
  localPriorities,
  rowSaving,
  isPriorityLocked,
  setScope,
  updatePriority,
  pageNumbers,
  changePage,
  nextPage,
  previousPage
} = useJangmedPriorities()

const priorityLabel = (value) => {
  const option = priorityOptions.find((item) => item.value === value)
  return option ? option.label : '-'
}

const instansiLabel = (value) => {
  if (!value) return '-'
  const map = {
    wiradadi: 'RS Wiradadi Husada',
    raffa: 'RS Raffa Majenang'
  }
  return map[value] ?? value
}

const formatDate = (value) => {
  if (!value) return '-'
  return new Date(value).toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const scopeSummarySuffix = computed(() =>
  scope.value === 'completed' ? 'ticket selesai' : 'ticket yang siap diteruskan ke tim IT'
)

const emptyMessage = computed(() =>
  scope.value === 'completed'
    ? 'Tidak ada ticket yang sudah selesai.'
    : 'Tidak ada ticket aktif yang perlu diatur prioritasnya.'
)

const pageSummary = computed(() => {
  if (!pagination.total) {
    return emptyMessage.value
  }

  return `Menampilkan ${pagination.from}-${pagination.to} dari ${pagination.total} ${scopeSummarySuffix.value}`
})
</script>

