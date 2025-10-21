<template>
  <div class="page-wrapper admin-master py-2">
    <div
      class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4"
    >
      <div>
        <h2 class="fw-semibold mb-1">Master Data Sistem</h2>
        <p class="text-muted mb-0">
          Kelola unit layanan, verifikasi akun pengguna, dan tentukan role akses untuk memastikan alur
          ticket berjalan rapi.
        </p>
      </div>
      <div class="text-muted small">
        <span v-if="combinedLoading">Memuat data master...</span>
        <span v-else>Pembaruan terakhir {{ new Date().toLocaleString('id-ID') }}</span>
      </div>
    </div>

    <div class="card master-card shadow-sm border-0 mb-4">
      <div class="card-body p-4">
        <div class="row g-4 align-items-start">
          <div class="col-lg-4">
            <div class="master-section-heading">
              <h5 class="fw-semibold mb-1">
                {{ isEditingUnit ? 'Ubah Data Unit' : 'Tambah Unit Baru' }}
              </h5>
              <p class="text-muted small mb-3">
                Input unit beserta instansi terkait agar pemohon dapat memilih unit yang tepat saat registrasi.
              </p>
            </div>

            <form @submit.prevent="submitUnit" class="unit-form card border-0 shadow-sm">
              <div class="card-body p-4">
                <div class="mb-3">
                  <label for="unitName" class="form-label">Nama Unit</label>
                  <input
                    id="unitName"
                    v-model="unitForm.name"
                    type="text"
                    class="form-control"
                    placeholder="Contoh: Instalasi Radiologi"
                    :disabled="savingUnit"
                  />
                </div>
                <div class="mb-3">
                  <label for="unitInstansi" class="form-label">Instansi</label>
                  <select
                    id="unitInstansi"
                    v-model="unitForm.instansi"
                    class="form-select"
                    :disabled="savingUnit"
                  >
                    <option v-for="option in instansiOptions" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </option>
                  </select>
                </div>
                <div class="mb-3">
                  <label for="unitManagerCategory" class="form-label">Kategori Manager</label>
                  <select
                    id="unitManagerCategory"
                    v-model.number="unitForm.manager_category_id"
                    class="form-select"
                    :disabled="savingUnit"
                  >
                    <option
                      v-for="option in managerCategoryOptions"
                      :key="`unit-manager-cat-${option.value}`"
                      :value="option.value"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                </div>
                <div class="form-check form-switch mb-4">
                  <input
                    id="unitActive"
                    v-model="unitForm.is_active"
                    class="form-check-input"
                    type="checkbox"
                    :disabled="savingUnit"
                  />
                  <label class="form-check-label" for="unitActive">Unit aktif</label>
                </div>
                <div class="d-flex gap-2">
                  <button type="submit" class="btn btn-primary flex-grow-1" :disabled="savingUnit">
                    <span v-if="savingUnit" class="spinner-border spinner-border-sm me-2"></span>
                    Simpan Unit
                  </button>
                  <button
                    v-if="isEditingUnit"
                    type="button"
                    class="btn btn-outline-secondary"
                    @click="cancelUnitEdit"
                    :disabled="savingUnit"
                  >
                    Batal
                  </button>
                </div>
              </div>
            </form>

            <div v-if="unitMessage" class="alert mt-3 py-2" :class="`alert-${unitMessageType || 'info'}`">
              {{ unitMessage }}
            </div>
          </div>

          <div class="col-lg-8">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
              <h5 class="fw-semibold mb-0">Daftar Unit Terdaftar</h5>
              <span v-if="loadingUnits" class="text-muted small">Memuat data unit...</span>
            </div>

            <div class="table-responsive-md rounded-4 border bg-white">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Unit</th>
                    <th>Instansi</th>
                    <th>Kategori Manager</th>
                    <th>Status</th>
                    <th class="text-center">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!loadingUnits && units.length === 0">
                    <td colspan="5" class="text-center text-muted py-4">
                      Belum ada unit terdaftar. Tambahkan unit pertama melalui formulir di samping.
                    </td>
                  </tr>
                  <tr v-for="unit in units" :key="unit.id">
                    <td>
                      <div class="fw-semibold">{{ unit.name }}</div>
                      <div class="text-muted small">Terakhir diperbarui {{ formatDate(unit.updated_at) }}</div>
                    </td>
                    <td>
                      <span class="badge bg-primary-subtle text-primary">{{ instansiLabel(unit.instansi) }}</span>
                    </td>
                    <td>
                      <span class="badge bg-info-subtle text-info">{{ unit.manager_category_label || '-' }}</span>
                    </td>
                    <td>
                      <span
                        class="badge"
                        :class="unit.is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'"
                      >
                        {{ unit.is_active ? 'Aktif' : 'Tidak aktif' }}
                      </span>
                    </td>
                    <td class="text-center text-lg-end">
                      <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary rounded-pill" type="button" @click="startEditUnit(unit)">
                          Ubah
                        </button>
                        <button class="btn btn-outline-secondary ms-1 rounded-pill" type="button" @click="toggleUnitStatus(unit)">
                          {{ unit.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                        </button>
                        <button
                          class="btn btn-outline-danger ms-1"
                          type="button"
                          @click="deleteUnit(unit)"
                          :disabled="deletingUnitId === unit.id"
                        >
                          <span v-if="deletingUnitId === unit.id" class="spinner-border spinner-border-sm"></span>
                          <span v-else>Hapus</span>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div
              v-if="unitPagination.total > 0"
              class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mt-3"
            >
              <div class="text-muted small">
                Menampilkan {{ unitPagination.from || 0 }}-{{ unitPagination.to || 0 }} dari
                {{ unitPagination.total }} unit
              </div>
              <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="d-flex align-items-center gap-2">
                  <label class="text-muted small mb-0">Baris per halaman</label>
                  <select
                    class="form-select form-select-sm w-auto"
                    :value="unitPagination.perPage"
                    @change="updateUnitPerPage($event.target.value)"
                    :disabled="loadingUnits"
                  >
                    <option v-for="option in paginationPerPageOptions" :key="`unit-per-${option}`" :value="option">
                      {{ option }}
                    </option>
                  </select>
                </div>
                <nav aria-label="Navigasi halaman unit">
                  <ul class="pagination pagination-sm mb-0">
                    <li class="page-item" :class="{ disabled: loadingUnits || unitPagination.currentPage === 1 }">
                      <button
                        class="page-link"
                        type="button"
                        @click="changeUnitPage(unitPagination.currentPage - 1)"
                        :disabled="loadingUnits || unitPagination.currentPage === 1"
                      >
                        Sebelumnya
                      </button>
                    </li>
                    <li class="page-item disabled">
                      <span class="page-link">
                        Halaman {{ unitPagination.currentPage }} / {{ unitPagination.lastPage || 1 }}
                      </span>
                    </li>
                    <li
                      class="page-item"
                      :class="{ disabled: loadingUnits || unitPagination.currentPage >= unitPagination.lastPage }"
                    >
                      <button
                        class="page-link"
                        type="button"
                        @click="changeUnitPage(unitPagination.currentPage + 1)"
                        :disabled="loadingUnits || unitPagination.currentPage >= unitPagination.lastPage"
                      >
                        Berikutnya
                      </button>
                    </li>
                  </ul>
                </nav>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card master-card shadow-sm border-0">
      <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <div>
            <h5 class="fw-semibold mb-1">Verifikasi & Role Pengguna</h5>
            <p class="text-muted small mb-0">
              Administrasi akun untuk menentukan akses, memverifikasi registrasi baru, dan mengatur unit pengguna.
            </p>
          </div>
          <span v-if="loadingUsers" class="text-muted small">Memuat daftar pengguna...</span>
        </div>

        <div v-if="userMessage" class="alert py-2" :class="`alert-${userMessageType || 'info'}`">
          {{ userMessage }}
        </div>

        <div class="table-responsive-md rounded-4 border bg-white">
          <table class="table align-middle table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>Pengguna</th>
                <th>Instansi</th>
                <th>Unit</th>
                <th>Role</th>
                <th>Kategori Manager</th>
                <th>Status</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!loadingUsers && users.length === 0">
                <td colspan="6" class="text-center text-muted py-4">
                  Belum ada pengguna terdaftar.
                </td>
              </tr>
              <tr v-for="user in users" :key="user.id">
                <td>
                  <div class="fw-semibold">{{ user.name }}</div>
                  <div class="text-info small">{{ user.username }}</div>
                  <div class="text-muted small">{{ user.email }}</div>
                </td>
                <td style="min-width: 160px;">
                  <select
                    class="form-select form-select-sm"
                    :value="user.instansi || ''"
                    :disabled="userSaving[user.id]"
                    @change="changeUserInstansi(user, $event.target.value)"
                  >
                    <option value="">Pilih instansi...</option>
                    <option v-for="option in instansiOptions" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </option>
                  </select>
                </td>
                <td style="min-width: 200px;">
                  <select
                    class="form-select form-select-sm"
                    :value="user.unit?.id ?? ''"
                    :disabled="userSaving[user.id]"
                    @change="changeUserUnit(user, $event.target.value)"
                  >
                    <option value="">Tidak ada unit</option>
                    <option
                      v-for="unit in unitsForInstansi(user.instansi)"
                      :key="`${user.id}-${unit.id}`"
                      :value="unit.id"
                    >
                      {{ unit.name }}
                    </option>
                  </select>
                </td>
                <td style="min-width: 170px;">
                  <select
                    class="form-select form-select-sm"
                    :value="user.level"
                    :disabled="userSaving[user.id]"
                    @change="changeUserRole(user, Number($event.target.value))"
                  >
                    <option v-for="option in roleOptions" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </option>
                  </select>
                </td>
                <td style="min-width: 200px;">
                  <select
                    v-if="Number(user.level) === ROLE.MANAGER"
                    class="form-select form-select-sm"
                    :value="user.manager_category_id || managerCategoryOptions[0]?.value"
                    :disabled="userSaving[user.id]"
                    @change="changeUserManagerCategory(user, $event.target.value)"
                  >
                    <option
                      v-for="option in managerCategoryOptions"
                      :key="`manager-cat-${option.value}`"
                      :value="option.value"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                  <span v-else class="text-muted small">-</span>
                </td>
                <td>
                  <span class="badge" :class="verifiedBadgeClass(user.is_verified)">
                    {{ user.is_verified ? 'Terverifikasi' : 'Belum diverifikasi' }}
                  </span>
                </td>
                <td class="text-end">
                  <button
                    class="btn btn-sm btn-outline-success"
                    type="button"
                    :disabled="userSaving[user.id]"
                    @click="toggleUserVerification(user)"
                  >
                    <span v-if="userSaving[user.id]" class="spinner-border spinner-border-sm"></span>
                    <span v-else>{{ user.is_verified ? 'Cabut Verifikasi' : 'Verifikasi' }}</span>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div
          v-if="userPagination.total > 0"
          class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mt-3"
        >
          <div class="text-muted small">
            Menampilkan {{ userPagination.from || 0 }}-{{ userPagination.to || 0 }} dari
            {{ userPagination.total }} pengguna
          </div>
          <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="d-flex align-items-center gap-2">
              <label class="text-muted small mb-0">Baris per halaman</label>
              <select
                class="form-select form-select-sm w-auto"
                :value="userPagination.perPage"
                @change="updateUserPerPage($event.target.value)"
                :disabled="loadingUsers"
              >
                <option v-for="option in paginationPerPageOptions" :key="`user-per-${option}`" :value="option">
                  {{ option }}
                </option>
              </select>
            </div>
            <nav aria-label="Navigasi halaman pengguna">
              <ul class="pagination pagination-sm mb-0">
                <li class="page-item" :class="{ disabled: loadingUsers || userPagination.currentPage === 1 }">
                  <button
                    class="page-link"
                    type="button"
                    @click="changeUserPage(userPagination.currentPage - 1)"
                    :disabled="loadingUsers || userPagination.currentPage === 1"
                  >
                    Sebelumnya
                  </button>
                </li>
                <li class="page-item disabled">
                  <span class="page-link">
                    Halaman {{ userPagination.currentPage }} / {{ userPagination.lastPage || 1 }}
                  </span>
                </li>
                <li
                  class="page-item"
                  :class="{ disabled: loadingUsers || userPagination.currentPage >= userPagination.lastPage }"
                >
                  <button
                    class="page-link"
                    type="button"
                    @click="changeUserPage(userPagination.currentPage + 1)"
                    :disabled="loadingUsers || userPagination.currentPage >= userPagination.lastPage"
                  >
                    Berikutnya
                  </button>
                </li>
              </ul>
            </nav>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { ROLE } from '@/constants/roles'
import { useAdminMaster } from '@/ticketing/composables'

const {
  instansiOptions,
  roleOptions,
  units,
  users,
  unitPagination,
  userPagination,
  loadingUnits,
  loadingUsers,
  savingUnit,
  deletingUnitId,
  unitForm,
  isEditingUnit,
  unitMessage,
  unitMessageType,
  userMessage,
  userMessageType,
  userSaving,
  startEditUnit,
  cancelUnitEdit,
  submitUnit,
  toggleUnitStatus,
  deleteUnit,
  unitsForInstansi,
  changeUserInstansi,
  changeUserUnit,
  changeUserRole,
  changeUserManagerCategory,
  toggleUserVerification,
  verifiedBadgeClass,
  changeUnitPage,
  changeUserPage,
  updateUnitPerPage,
  updateUserPerPage,
  managerCategoryOptions
} = useAdminMaster()

const combinedLoading = computed(() => loadingUnits.value || loadingUsers.value)

const paginationPerPageOptions = Object.freeze([5, 10, 25, 50])

const instansiLabel = (value) => {
  const option = instansiOptions.find((item) => item.value === value)
  return option ? option.label : value || '-'
}

const formatDate = (value) => {
  if (!value) {
    return '-'
  }

  return new Date(value).toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>

