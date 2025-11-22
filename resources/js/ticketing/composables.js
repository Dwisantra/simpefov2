import { ref, reactive, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import { Modal } from 'bootstrap'
import { useRouter, useRoute } from 'vue-router'
import axios from '@/lib/axios'
import { useAuthStore } from '@/stores/auth'
import { ROLE, ROLE_LABELS, ROLE_OPTIONS, isRole } from '@/constants/roles'
import { MANAGER_CATEGORY, MANAGER_CATEGORY_OPTIONS } from '@/constants/managerCategories'

const roleLabels = ROLE_LABELS

const statusLabels = {
  pending: 'Menunggu ACC Manager',
  approved_manager: 'Menunggu Direktur RS Raffa Majenang',
  approved_a: 'Menunggu Direktur RS Wiradadi Husada',
  approved_b: 'Selesai',
  done: 'Selesai'
}

const resolveStatusLabel = (status, _instansi = null, requiresDirectorA = true) => {
  if (!status) {
    return '-'
  }

  if (status === 'approved_manager' && !requiresDirectorA) {
    return 'Menunggu Direktur RS Wiradadi Husada'
  }

  return statusLabels[status] ?? status
}

const developmentStatusRegistry = Object.freeze({
  1: { label: 'Analisis', badgeClass: 'bg-secondary-subtle text-secondary' },
  2: { label: 'Pengerjaan', badgeClass: 'bg-info-subtle text-info' },
  3: { label: 'Testing', badgeClass: 'bg-warning-subtle text-warning' },
  4: { label: 'Ready Release', badgeClass: 'bg-success-subtle text-success' }
})

const DEVELOPMENT_STAGE_PENGERJAAN = 2

const developmentStatusChoices = Object.freeze(
  Object.entries(developmentStatusRegistry).map(([value, meta]) => ({
    value: Number(value),
    label: meta.label
  }))
)

const resolveDevelopmentStatusBadgeClass = (statusId) =>
  developmentStatusRegistry[statusId]?.badgeClass ?? 'bg-secondary-subtle text-secondary'

const resolveDevelopmentStatusLabel = (statusId) =>
  developmentStatusRegistry[statusId]?.label ?? ''

const instansiNameMap = Object.freeze({
  wiradadi: 'RS Wiradadi Husada',
  raffa: 'RS Raffa Majenang'
})

const releaseStatusRegistry = Object.freeze({
  1: { label: 'Sudah release (belum dipakai)', badgeClass: 'bg-info-subtle text-info' },
  2: { label: 'Sudah release dan dipakai', badgeClass: 'bg-success-subtle text-success' }
})

const releaseStatusChoices = Object.freeze(
  Object.entries(releaseStatusRegistry).map(([value, meta]) => ({
    value: Number(value),
    label: meta.label
  }))
)

const formatInstansiLabel = (value) => instansiNameMap[value] ?? value ?? '-'

const baseApprovalSteps = 4

const requiresDirectorAApproval = (item) => {
  if (!item || typeof item !== 'object') {
    return true
  }

  return item.requires_director_a_approval !== false
}

const approvalProgressFromStatus = (item) => {
  const status = typeof item === 'object' ? item?.status : item
  const requiresDirectorA = requiresDirectorAApproval(item)

  const map = requiresDirectorA
    ? {
      pending: 1,
      approved_manager: 2,
      approved_a: 3,
      approved_b: 4,
      done: 4
    }
    : {
      pending: 1,
      approved_manager: 2,
      approved_a: 2,
      approved_b: 3,
      done: 3
    }

  return map[status] ?? 0
}

const totalApprovalSteps = (item = null) =>
  requiresDirectorAApproval(item) ? baseApprovalSteps : baseApprovalSteps - 1

const approvalProgressSteps = (item) => {
  const count = totalApprovalSteps(item)
  const rawProgress = item?.status_progress
  const parsedProgress =
    rawProgress === null || rawProgress === undefined || rawProgress === ''
      ? NaN
      : Number(rawProgress)
  const baseProgress = Number.isFinite(parsedProgress)
    ? parsedProgress
    : approvalProgressFromStatus(item)
  const numeric = Number.isFinite(baseProgress) ? baseProgress : 0
  return Math.max(0, Math.min(count, numeric))
}

const approvalProgressPercentage = (item) => {
  const stepsCount = totalApprovalSteps(item)

  if (stepsCount <= 1) {
    return 100
  }

  const value = approvalProgressSteps(item)

  if (value <= 1) {
    return 5
  }

  return Math.min(100, Math.round(((value - 1) / (stepsCount - 1)) * 100))
}

const statusBadgeClassFor = (status) => {
  switch (status) {
    case 'approved_b':
    case 'done':
      return 'bg-success-subtle text-success'
    case 'approved_a':
      return 'bg-info-subtle text-info'
    case 'approved_manager':
      return 'bg-warning-subtle text-warning'
    default:
      return 'bg-secondary-subtle text-secondary'
  }
}

const priorityBadgeClassFor = (priority) => resolvePriorityBadgeClass(priority)

const describeTicketStatus = (value) => {
  if (!value && value !== 0) {
    return '-'
  }

  if (typeof value === 'string') {
    return resolveStatusLabel(value, null, true)
  }

  if (typeof value === 'object') {
    const requiresDirectorA = requiresDirectorAApproval(value)
    return resolveStatusLabel(value.status, value.requester_instansi, requiresDirectorA)
  }

  return '-'
}

const developmentStepsTotal = developmentStatusChoices.length || 4

const normalizeDevelopmentStep = (item) => {
  const raw = item?.development_status
  const parsed = Number(raw)

  if (Number.isFinite(parsed) && parsed >= 1) {
    return Math.max(1, Math.min(developmentStepsTotal, parsed))
  }

  if (item?.status === 'done') {
    return developmentStepsTotal
  }

  return 1
}

const developmentProgressPercentageFor = (item) => {
  if (developmentStepsTotal <= 0) {
    return 0
  }

  const step = normalizeDevelopmentStep(item)
  return Math.round((step / developmentStepsTotal) * 100)
}

const PRIORITY_OPTIONS = Object.freeze([
  { value: 'biasa', label: 'Prioritas Biasa' },
  { value: 'sedang', label: 'Prioritas Sedang' },
  { value: 'cito', label: 'Prioritas Cito' }
])

const resolvePriorityBadgeClass = (priority) => {
  switch (priority) {
    case 'cito':
      return 'bg-danger-subtle text-danger'
    case 'sedang':
      return 'bg-warning-subtle text-warning'
    default:
      return 'bg-secondary-subtle text-secondary'
  }
}

const formatDateTime = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const formatDateOnly = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}

const toDateInputValue = (value) => {
  if (!value) return ''

  const d = new Date(value)
  if (Number.isNaN(d.getTime())) {
    return ''
  }

  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

export function useAppShell() {
  const auth = useAuthStore()
  const router = useRouter()

  const kodeSign = ref('')
  const kodeSignError = ref('')
  const kodeSignSuccess = ref('')
  const kodeSignModal = ref(null)
  const accountMenuRef = ref(null)
  const accountMenuOpen = ref(false)
  const savingKode = ref(false)
  let modalInstance = null

  const isLoggedIn = computed(() => !!auth.user)
  const userRole = computed(() => Number(auth.user?.level ?? auth.user?.role ?? 0))

  const roleLabel = computed(() => roleLabels[userRole.value] ?? 'Pengguna')
  const canRequest = computed(() => isRole(userRole.value, ROLE.USER))
  const isAdmin = computed(() => isRole(userRole.value, ROLE.ADMIN))
  const isManager = computed(() => isRole(userRole.value, ROLE.MANAGER))
  const managerCategoryId = computed(() => Number(auth.user?.manager_category_id ?? 0))
  const isJangmedManager = computed(
    () => isManager.value && managerCategoryId.value === MANAGER_CATEGORY.JANGMED
  )

  const ensureModalInstance = () => {
    if (kodeSignModal.value && !modalInstance) {
      modalInstance = new Modal(kodeSignModal.value, { backdrop: 'static' })
    }
  }

  const closeAccountMenu = () => {
    accountMenuOpen.value = false
  }

  const toggleAccountMenu = () => {
    accountMenuOpen.value = !accountMenuOpen.value
  }

  const handleDocumentClick = (event) => {
    if (!accountMenuRef.value) return
    if (!accountMenuRef.value.contains(event.target)) {
      closeAccountMenu()
    }
  }

  const handleEscape = (event) => {
    if (event.key === 'Escape') {
      closeAccountMenu()
    }
  }

  const openKodeSignModal = () => {
    nextTick(() => {
      ensureModalInstance()
      closeAccountMenu()
      kodeSign.value = ''
      kodeSignError.value = ''
      kodeSignSuccess.value = ''
      modalInstance?.show()
    })
  }

  const closeKodeSignModal = () => {
    modalInstance?.hide()
  }

  watch(
    () => auth.user,
    (newVal) => {
      if (newVal && !newVal.has_kode_sign) {
        openKodeSignModal()
      }
    },
    { immediate: true }
  )

  onMounted(() => {
    ensureModalInstance()
    document.addEventListener('click', handleDocumentClick)
    document.addEventListener('keydown', handleEscape)
  })

  onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick)
    document.removeEventListener('keydown', handleEscape)
  })

  watch(
    () => router.currentRoute.value.fullPath,
    () => {
      closeAccountMenu()
    }
  )

  const logout = async () => {
    try {
      closeAccountMenu()
      await axios.post('/logout')
    } catch (error) {
      // Abaikan kesalahan logout dari server tetapi tetap hapus sesi lokal
    } finally {
      auth.logout()
      router.push('/login')
    }
  }

  const submitKodeSign = async () => {
    kodeSignError.value = ''
    kodeSignSuccess.value = ''

    if (!kodeSign.value || kodeSign.value.length < 4) {
      kodeSignError.value = 'Kode ACC minimal terdiri dari 4 karakter.'
      return
    }

    try {
      savingKode.value = true
      await auth.updateKodeSign(kodeSign.value)
      kodeSignSuccess.value = 'Kode ACC berhasil disimpan.'
      setTimeout(() => {
        closeKodeSignModal()
      }, 800)
    } catch (error) {
      kodeSignError.value = error?.response?.data?.message ?? 'Gagal menyimpan kode ACC.'
    } finally {
      savingKode.value = false
    }
  }

  return {
    auth,
    isLoggedIn,
    roleLabel,
    canRequest,
    isAdmin,
    isJangmedManager,
    kodeSign,
    kodeSignError,
    kodeSignSuccess,
    kodeSignModal,
    accountMenuRef,
    accountMenuOpen,
    toggleAccountMenu,
    savingKode,
    openKodeSignModal,
    closeKodeSignModal,
    submitKodeSign,
    logout
  }
}

export function useLoginForm() {
  const auth = useAuthStore()
  const router = useRouter()

  const username = ref('')
  const password = ref('')
  const showPassword = ref(false)
  const loading = ref(false)
  const error = ref('')
  const sessionMessage = computed(() => auth.logoutMessage)

  const togglePasswordVisibility = () => {
    showPassword.value = !showPassword.value
  }

  const dismissSessionMessage = () => {
    auth.clearLogoutMessage()
  }

  const submit = async () => {
    error.value = ''

    const normalizedUsername = username.value.trim().toLowerCase()

    if (!normalizedUsername || !password.value) {
      error.value = 'Masukkan username dan password Anda.'
      return
    }

    try {
      loading.value = true
      username.value = normalizedUsername
      await auth.login(username.value, password.value)
      router.push('/feature-request')
    } catch (err) {
      error.value = err?.response?.data?.message ?? 'Login gagal. Periksa kembali data Anda.'
    } finally {
      loading.value = false
    }
  }

  return {
    username,
    password,
    showPassword,
    loading,
    error,
    sessionMessage,
    dismissSessionMessage,
    togglePasswordVisibility,
    submit
  }
}

export function useRegisterForm() {
  const router = useRouter()

  const name = ref('')
  const username = ref('')
  const email = ref('')
  const password = ref('')
  const passwordConfirmation = ref('')
  const phone = ref('')
  const instansi = ref('wiradadi')
  const unitId = ref('')
  const units = ref([])
  const loadingUnits = ref(false)
  const loading = ref(false)
  const error = ref('')
  const success = ref('')

  const availableUnits = computed(() =>
    units.value.filter((item) => item.instansi === instansi.value && item.is_active)
  )

  const ensureUnitSelection = () => {
    if (!availableUnits.value.some((item) => String(item.id) === String(unitId.value))) {
      unitId.value = availableUnits.value[0]?.id ?? ''
    }
  }

  const fetchUnits = async () => {
    loadingUnits.value = true
    try {
      const { data } = await axios.get('/public/units')
      units.value = Array.isArray(data) ? data : []
      ensureUnitSelection()
    } catch (err) {
      error.value = err?.response?.data?.message ?? 'Gagal memuat daftar unit.'
    } finally {
      loadingUnits.value = false
    }
  }

  watch(instansi, () => {
    ensureUnitSelection()
  })

  onMounted(() => {
    fetchUnits()
  })

  const submit = async () => {
    error.value = ''
    success.value = ''

    if (
      !name.value ||
      !username.value ||
      !email.value ||
      !password.value ||
      !passwordConfirmation.value ||
      !phone.value ||
      !unitId.value
    ) {
      error.value = 'Mohon lengkapi seluruh data yang dibutuhkan.'
      return
    }

    const passwordCombination = /^(?=.*[A-Za-z])(?=.*\d).{8,}$/
    const usernamePattern = /^[A-Za-z0-9_-]+$/

    const normalizedUsername = username.value.trim().toLowerCase()

    if (!normalizedUsername) {
      error.value = 'Username wajib diisi.'
      return
    }

    if (!usernamePattern.test(normalizedUsername)) {
      error.value = 'Username hanya boleh berisi huruf, angka, garis bawah, dan tanda hubung.'
      return
    }

    username.value = normalizedUsername

    if (!passwordCombination.test(password.value)) {
      error.value = 'Password harus minimal 8 karakter dan mengandung kombinasi huruf serta angka.'
      return
    }

    if (password.value !== passwordConfirmation.value) {
      error.value = 'Konfirmasi password tidak sesuai.'
      return
    }

    try {
      loading.value = true
      await axios.post('/register', {
        name: name.value,
        username: username.value,
        email: email.value,
        password: password.value,
        password_confirmation: passwordConfirmation.value,
        phone: phone.value,
        instansi: instansi.value,
        unit_id: unitId.value
      })
      success.value =
        'Registrasi berhasil! Menunggu verifikasi admin sebelum Anda dapat masuk ke sistem.'
      password.value = ''
      passwordConfirmation.value = ''
      setTimeout(() => router.push('/login'), 1500)
    } catch (err) {
      error.value = err?.response?.data?.message ?? 'Registrasi gagal. Coba lagi.'
    } finally {
      loading.value = false
    }
  }

  return {
    name,
    username,
    email,
    password,
    phone,
    instansi,
    unitId,
    units: availableUnits,
    loadingUnits,
    loading,
    error,
    success,
    passwordConfirmation,
    submit,
  }
}

export function useFeatureRequestIndex() {
  const auth = useAuthStore()
  const loading = ref(false)
  const perPage = ref(10)
  const stage = ref('submission')
  const pagination = ref({
    data: [],
    current_page: 1,
    last_page: 1,
    per_page: perPage.value,
    total: 0,
    from: 0,
    to: 0
  })

  const userRole = computed(() => Number(auth.user?.level ?? auth.user?.role ?? 0))

  const requests = computed(() => pagination.value.data ?? [])

  const stageOptions = [
    { value: 'submission', label: 'Tahap Pengajuan' },
    { value: 'development', label: 'Tahap Pengerjaan' }
  ]

  const stageCopy = {
    submission:
      'Pantau ticket yang sedang melalui proses pengajuan dan membutuhkan tindak lanjut.',
    development:
      'Lihat ticket yang sudah selesai tahap pengajuan dan sedang ditangani oleh tim IT.'
  }

  const stageDescription = computed(() => stageCopy[stage.value] ?? '')

  const statusLabel = (value) => describeTicketStatus(value)

  const statusBadgeClass = (status) => statusBadgeClassFor(status)

  const priorityBadgeClass = (priority) => priorityBadgeClassFor(priority)

  const totalSteps = (item = null) => totalApprovalSteps(item)

  const instansiLabel = (value) => formatInstansiLabel(value)

  const progressSteps = (item) => approvalProgressSteps(item)

  const progressPercentage = (item) => approvalProgressPercentage(item)

  const pageMeta = computed(() => ({
    current: pagination.value.current_page ?? 1,
    last: pagination.value.last_page ?? 1,
    perPage: pagination.value.per_page ?? perPage.value,
    from: pagination.value.from ?? 0,
    to: pagination.value.to ?? 0,
    total: pagination.value.total ?? requests.value.length
  }))

  const hasPagination = computed(() => pageMeta.value.last > 1)

  const pageNumbers = computed(() => {
    const total = pageMeta.value.last
    const current = pageMeta.value.current
    const delta = 2

    const start = Math.max(1, current - delta)
    const end = Math.min(total, current + delta)

    return Array.from({ length: Math.max(0, end - start + 1) }, (_, index) => start + index)
  })

  const pageSummary = computed(() => {
    if (!pageMeta.value.total) {
      return 'Tidak ada ticket yang ditampilkan.'
    }

    return `Menampilkan ${pageMeta.value.from}-${pageMeta.value.to} dari ${pageMeta.value.total} ticket`
  })

  const loadRequests = async (page = 1) => {
    loading.value = true
    try {
      const { data } = await axios.get('/feature-requests', {
        params: {
          page,
          per_page: perPage.value,
          stage: stage.value
        }
      })

      pagination.value = {
        ...data,
        data: data?.data ?? []
      }
    } finally {
      loading.value = false
    }
  }

  const goToPage = (page) => {
    if (page < 1 || page > pageMeta.value.last || page === pageMeta.value.current) {
      return
    }

    loadRequests(page)
  }

  const nextPage = () => {
    goToPage(pageMeta.value.current + 1)
  }

  const previousPage = () => {
    goToPage(pageMeta.value.current - 1)
  }

  onMounted(() => {
    loadRequests(pageMeta.value.current)
  })

  watch(
    perPage,
    (value, oldValue) => {
      if (value !== oldValue) {
        loadRequests(1)
      }
    }
  )

  watch(stage, () => {
    loadRequests(1)
  })

  const canCreate = computed(() => isRole(userRole.value, ROLE.USER))
  const isAdmin = computed(() => isRole(userRole.value, ROLE.ADMIN))
  const developmentStatusBadgeClass = (statusId) => resolveDevelopmentStatusBadgeClass(statusId)

  const perPageOptions = [5, 10, 20, 50]

  const setStage = (value) => {
    if (stage.value === value) {
      return
    }

    const option = stageOptions.find((item) => item.value === value)
    if (option) {
      stage.value = option.value
    }
  }

  return {
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
    totalSteps,
    pageMeta,
    pageNumbers,
    hasPagination,
    pageSummary,
    goToPage,
    nextPage,
    previousPage,
    loadRequests,
    stage,
    stageOptions,
    stageDescription,
    setStage,
    formatDate: formatDateTime,
    formatDateOnly,
    instansiLabel,
    canCreate,
    isAdmin
  }
}

export function useTicketMonitoring() {
  const auth = useAuthStore()
  const loading = ref(false)
  const perPage = ref(10)
  const activeTab = ref('pengerjaan')
  const pagination = ref({
    data: [],
    current_page: 1,
    last_page: 1,
    per_page: perPage.value,
    total: 0,
    from: 0,
    to: 0
  })

  const tickets = computed(() => pagination.value.data ?? [])

  const tabOptions = [
    { value: 'pengerjaan', label: 'Pengerjaan' },
    { value: 'selesai', label: 'Selesai' }
  ]

  const tabCopy = {
    pengerjaan: 'Pantau progres ticket yang sedang dikerjakan tim IT.',
    selesai: 'Lihat ticket yang sudah selesai dikerjakan dan siap digunakan unit terkait.'
  }

  const emptyCopy = {
    pengerjaan: 'Belum ada ticket dalam pengerjaan pada periode ini.',
    selesai: 'Belum ada ticket selesai pada periode ini.'
  }

  const tabDescription = computed(() => tabCopy[activeTab.value] ?? '')
  const emptyMessage = computed(() => emptyCopy[activeTab.value] ?? 'Belum ada ticket.')

  const loadTickets = async (page = 1) => {
    loading.value = true
    try {
      const { data } = await axios.get('/feature-requests/monitoring', {
        params: {
          page,
          per_page: perPage.value,
          tab: activeTab.value
        }
      })

      pagination.value = {
        ...data,
        data: data?.data ?? []
      }
    } finally {
      loading.value = false
    }
  }

  const perPageOptions = [5, 10, 20, 50]

  const pageMeta = computed(() => ({
    current: pagination.value.current_page ?? 1,
    last: pagination.value.last_page ?? 1,
    perPage: pagination.value.per_page ?? perPage.value,
    from: pagination.value.from ?? 0,
    to: pagination.value.to ?? 0,
    total: pagination.value.total ?? tickets.value.length
  }))

  const hasPagination = computed(() => pageMeta.value.last > 1)

  const pageNumbers = computed(() => {
    const total = pageMeta.value.last
    const current = pageMeta.value.current
    const delta = 2

    const start = Math.max(1, current - delta)
    const end = Math.min(total, current + delta)

    return Array.from({ length: Math.max(0, end - start + 1) }, (_, index) => start + index)
  })

  const pageSummary = computed(() => {
    if (!pageMeta.value.total) {
      return 'Tidak ada ticket yang ditampilkan.'
    }

    return `Menampilkan ${pageMeta.value.from}-${pageMeta.value.to} dari ${pageMeta.value.total} ticket`
  })

  const goToPage = (page) => {
    if (page < 1 || page > pageMeta.value.last || page === pageMeta.value.current) {
      return
    }

    loadTickets(page)
  }

  const nextPage = () => {
    goToPage(pageMeta.value.current + 1)
  }

  const previousPage = () => {
    goToPage(pageMeta.value.current - 1)
  }

  const setTab = (value) => {
    if (activeTab.value === value) {
      return
    }

    const option = tabOptions.find((item) => item.value === value)
    if (option) {
      activeTab.value = option.value
    }
  }

  onMounted(() => {
    loadTickets(pageMeta.value.current)
  })

  watch(
    perPage,
    (value, oldValue) => {
      if (value !== oldValue) {
        loadTickets(1)
      }
    }
  )

  watch(activeTab, () => {
    loadTickets(1)
  })

  const releaseStatusLabel = (status) => releaseStatusRegistry[Number(status)]?.label ?? 'Belum diatur'
  const releaseStatusBadgeClass = (status) => releaseStatusRegistry[Number(status)]?.badgeClass ?? 'bg-secondary-subtle text-secondary'

  const exportMonitoring = async () => {
    const response = await axios.get('/feature-requests/monitoring/export', {
      params: { tab: activeTab.value },
      responseType: 'blob'
    })

    const blob = new Blob([response.data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', 'monitoring-ticket.xlsx')
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  }

  const isAdmin = computed(() => isRole(Number(auth.user?.level ?? auth.user?.role ?? 0), ROLE.ADMIN))

  const statusLabel = (value) => describeTicketStatus(value)
  const statusBadgeClass = (status) => statusBadgeClassFor(status)
  const priorityBadgeClass = (priority) => priorityBadgeClassFor(priority)
  const instansiLabel = (value) => formatInstansiLabel(value)
  const developmentStatusBadgeClass = (statusId) => resolveDevelopmentStatusBadgeClass(statusId)
  const developmentStatusLabel = (item) => {
    if (item?.development_status_label) {
      return item.development_status_label
    }

    if (item?.development_status) {
      const label = resolveDevelopmentStatusLabel(Number(item.development_status))
      if (label) {
        return label
      }
    }

    if (item?.status === 'done') {
      return 'Selesai'
    }

    return 'Belum Ditentukan'
  }

  const developmentProgress = (item) => developmentProgressPercentageFor(item)

  return {
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
    loadTickets,
    formatDate: formatDateTime,
    formatDateOnly,
    instansiLabel,
    releaseStatusLabel,
    releaseStatusBadgeClass,
    exportMonitoring,
    isAdmin
  }
}

export function useFeatureRequestCreate() {
  const router = useRouter()
  const auth = useAuthStore()

  const requestTypes = ref([])
  const description = ref('')
  const signCode = ref('')
  const note = ref('')
  const attachment = ref(null)
  const attachmentName = ref('')
  const attachmentError = ref('')
  const attachmentInput = ref(null)
  const loading = ref(false)
  const message = ref('')
  const messageType = ref('success')

  const userRole = computed(() => Number(auth.user?.level ?? auth.user?.role ?? 0))
  const canSubmit = computed(() => isRole(userRole.value, ROLE.USER))
  const requesterName = computed(() => auth.user?.name ?? '-')
  const requesterUnit = computed(() => auth.user?.unit?.name ?? '-')
  const requesterInstansi = computed(() => {
    const map = {
      wiradadi: 'RS Wiradadi Husada',
      raffa: 'RS Raffa Majenang',
    }

    return map[auth.user?.instansi] ?? auth.user?.instansi ?? '-'
  })

  const requestTypeOptions = [
    { value: 'new_feature', label: 'Pembuatan Fitur Baru' },
    { value: 'new_report', label: 'Pembuatan Report/Cetakan' },
    { value: 'bug_fix', label: 'Lapor Bug/Error' },
  ]

  watch(
    () => userRole.value,
    (role) => {
      if (role && !isRole(role, ROLE.USER)) {
        router.replace('/feature-request')
      }
    },
    { immediate: true }
  )

  const messageTypeClass = computed(() => (messageType.value === 'success' ? 'alert-success' : 'alert-danger'))

  const resetAttachmentField = () => {
    attachment.value = null
    attachmentName.value = ''
    attachmentError.value = ''
    if (attachmentInput.value) {
      attachmentInput.value.value = ''
    }
  }

  const handleAttachmentChange = (event) => {
    const [file] = event.target.files || []
    attachmentError.value = ''

    if (!file) {
      resetAttachmentField()
      return
    }

    const allowedTypes = [
      'application/pdf',
      'application/msword',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'image/jpeg',
      'image/png'
    ]

    if (!allowedTypes.includes(file.type)) {
      attachment.value = null
      attachmentName.value = ''
      if (attachmentInput.value) {
        attachmentInput.value.value = ''
      }
      attachmentError.value = 'Format berkas harus PDF, DOC, DOCX, JPG, atau PNG.'
      return
    }

    const maxSize = 5 * 1024 * 1024
    if (file.size > maxSize) {
      attachment.value = null
      attachmentName.value = ''
      if (attachmentInput.value) {
        attachmentInput.value.value = ''
      }
      attachmentError.value = 'Ukuran berkas maksimal 5MB.'
      return
    }

    attachment.value = file
    attachmentName.value = file.name
  }

  const submit = async () => {
    message.value = ''

    if (!canSubmit.value) {
      message.value = 'Hanya pemohon yang dapat mengajukan ticket baru.'
      messageType.value = 'error'
      return
    }

    if (!auth.user?.is_verified) {
      message.value = 'Akun Anda belum diverifikasi admin. Tunggu verifikasi sebelum mengajukan.'
      messageType.value = 'error'
      return
    }

    if (requestTypes.value.length === 0) {
      message.value = 'Pilih minimal satu jenis pengajuan.'
      messageType.value = 'error'
      return
    }

    if (!auth.user?.has_kode_sign) {
      message.value = 'Anda belum menyimpan kode ACC. Silakan atur kode terlebih dahulu.'
      messageType.value = 'error'
      return
    }

    if (!signCode.value) {
      message.value = 'Masukkan kode ACC Anda untuk mengirim pengajuan.'
      messageType.value = 'error'
      return
    }

    try {
      loading.value = true
      const payload = new FormData()
      requestTypes.value.forEach((value) => payload.append('request_types[]', value))
      payload.append('sign_code', signCode.value)
      if (description.value) {
        payload.append('description', description.value)
      }
      if (note.value) {
        payload.append('note', note.value)
      }
      if (attachment.value) {
        payload.append('attachment', attachment.value)
      }

      await axios.post('/feature-requests', payload, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
      message.value = 'Pengajuan berhasil dikirim. Silakan pantau status persetujuan.'
      messageType.value = 'success'
      note.value = ''
      signCode.value = ''
      requestTypes.value = []
      description.value = ''
      resetAttachmentField()
      setTimeout(() => router.push('/feature-request'), 1000)
    } catch (error) {
      message.value = error?.response?.data?.message ?? 'Pengajuan gagal dikirim.'
      messageType.value = 'error'
    } finally {
      loading.value = false
    }
  }

  return {
    requestTypes,
    description,
    signCode,
    note,
    canSubmit,
    requesterName,
    requesterUnit,
    requesterInstansi,
    requestTypeOptions,
    attachment,
    attachmentName,
    attachmentError,
    attachmentInput,
    loading,
    message,
    messageTypeClass,
    handleAttachmentChange,
    resetAttachmentField,
    submit
  }
}

export function useFeatureRequestDetail() {
  const route = useRoute()
  const router = useRouter()
  const auth = useAuthStore()

  const feature = ref(null)
  const loading = ref(true)
  const signCode = ref('')
  const note = ref('')
  const submitting = ref(false)
  const successMessage = ref('')
  const errorMessage = ref('')
  const adminComment = ref('')
  const commentFile = ref(null)
  const commentFileName = ref('')
  const commentError = ref('')
  const commentSubmitting = ref(false)
  const commentInput = ref(null)
  const commentDownloadings = ref({})
  const commentDownloadErrors = ref({})
  const selectedPriority = ref('biasa')
  const prioritySaving = ref(false)
  const prioritySuccess = ref('')
  const priorityError = ref('')
  const selectedReleaseStatus = ref(null)
  const releaseDate = ref('')
  const releaseSaving = ref(false)
  const releaseSuccess = ref('')
  const releaseError = ref('')
  const selectedDevelopmentStatus = ref(developmentStatusChoices[0]?.value ?? 1)
  const developmentStatusSaving = ref(false)
  const developmentStatusSuccess = ref('')
  const developmentStatusError = ref('')
  const deleteLoading = ref(false)
  const deleteError = ref('')
  const downloadingAttachment = ref(false)
  const attachmentError = ref('')
  const attachmentViewerOpen = ref(false)
  const gitlabSyncing = ref(false)
  const gitlabSuccess = ref('')
  const gitlabError = ref('')
  const gitlabConfirmModal = ref(null)
  let gitlabConfirmInstance = null

  const userRole = computed(() => Number(auth.user?.level ?? auth.user?.role ?? 0))
  const isAdmin = computed(() => isRole(userRole.value, ROLE.ADMIN))
  const typeLabels = {
    new_feature: 'Pembuatan Fitur Baru',
    new_report: 'Pembuatan Report/Cetakan',
    bug_fix: 'Lapor Bug/Error',
    gitlab_issue: 'Issue dari GitLab',
  }
  const developmentStatusOptions = developmentStatusChoices
  const releaseStatusOptions = releaseStatusChoices

  const openAttachmentViewer = () => {
    attachmentViewerOpen.value = true
  }

  const closeAttachmentViewer = () => {
    attachmentViewerOpen.value = false
    attachmentError.value = ''
  }

  const showAttachmentViewer = () => {
    openAttachmentViewer()
  }

  const hideAttachmentViewer = () => {
    closeAttachmentViewer()
  }

  const instansiLabel = (value) => {
    const map = {
      wiradadi: 'RS Wiradadi Husada',
      raffa: 'RS Raffa Majenang',
    }

    return map[value] ?? value ?? '-'
  }

  const fetchFeature = async () => {
    loading.value = true
    gitlabSuccess.value = ''
    gitlabError.value = ''
    try {
      const { data } = await axios.get(`/feature-requests/${route.params.id}`)
      feature.value = data
      commentDownloadings.value = {}
      commentDownloadErrors.value = {}
      selectedPriority.value = data?.priority ?? 'biasa'
      const fetchedDevelopmentStatus = Number(data?.development_status)
      selectedDevelopmentStatus.value = Number.isFinite(fetchedDevelopmentStatus)
        ? fetchedDevelopmentStatus
        : developmentStatusChoices[0]?.value ?? 1
      const fetchedReleaseStatus = Number(data?.release_status)
      selectedReleaseStatus.value = Number.isFinite(fetchedReleaseStatus)
        ? fetchedReleaseStatus
        : null
      releaseDate.value = toDateInputValue(data?.release_date) ?? ''
      closeAttachmentViewer()
    } catch (error) {
      feature.value = null
      closeAttachmentViewer()
    } finally {
      loading.value = false
    }
  }

  const ensureGitlabConfirmInstance = () => {
    if (gitlabConfirmModal.value && !gitlabConfirmInstance) {
      gitlabConfirmInstance = new Modal(gitlabConfirmModal.value, {
        backdrop: 'static',
        keyboard: false
      })
    }
  }

  const openGitlabConfirmModal = () => {
    nextTick(() => {
      ensureGitlabConfirmInstance()
      gitlabConfirmInstance?.show()
    })
  }

  const closeGitlabConfirmModal = () => {
    gitlabConfirmInstance?.hide()
  }

  onMounted(fetchFeature)
  onMounted(() => {
    ensureGitlabConfirmInstance()
  })
  onBeforeUnmount(() => {
    closeAttachmentViewer()
    if (gitlabConfirmInstance) {
      gitlabConfirmInstance.hide()
      if (typeof gitlabConfirmInstance.dispose === 'function') {
        gitlabConfirmInstance.dispose()
      }
    }
    gitlabConfirmInstance = null
  })

  watch(
    () => gitlabConfirmModal.value,
    () => {
      ensureGitlabConfirmInstance()
    }
  )

  watch(showAttachmentViewer, (visible) => {
    if (typeof document === 'undefined') return

    if (visible) {
      document.body.style.overflow = 'hidden'
      document.addEventListener('keydown', handleViewerEscape)
    } else {
      document.body.style.overflow = ''
      document.removeEventListener('keydown', handleViewerEscape)
    }
  })

  const statusLabel = computed(() =>
    resolveStatusLabel(
      feature.value?.status,
      feature.value?.requester_instansi,
      feature.value?.requires_director_a_approval !== false
    )
  )

  const statusClass = computed(() => {
    const status = feature.value?.status
    if (status === 'approved_b' || status === 'done') return 'bg-success-subtle text-success'
    if (status === 'approved_a') return 'bg-info-subtle text-info'
    if (status === 'approved_manager') return 'bg-warning-subtle text-warning'
    return 'bg-secondary-subtle text-secondary'
  })

  const developmentStatusLabel = computed(() => {
    if (!feature.value) {
      return ''
    }

    return (
      feature.value.development_status_label ??
      resolveDevelopmentStatusLabel(feature.value.development_status)
    )
  })

  const developmentStatusClass = computed(() =>
    resolveDevelopmentStatusBadgeClass(feature.value?.development_status)
  )

  const approvalsByRole = computed(() => {
    const map = {}
    feature.value?.approvals?.forEach((approval) => {
      map[approval.role] = approval
    })
    return map
  })

  const requestTypes = computed(() =>
    feature.value?.request_types?.map((type) => ({ value: type, label: typeLabels[type] ?? type })) ?? []
  )

  const steps = computed(() => {
    const requiresDirectorA = feature.value?.requires_director_a_approval !== false

    const definitions = [
      {
        role: ROLE.USER,
        title: 'Pengajuan Pemohon',
        description: 'Pemohon mengisi detail pengajuan dan mengesahkan dengan kode ACC.'
      },
      {
        role: ROLE.MANAGER,
        title: 'Persetujuan Manager',
        description: 'Manager memvalidasi kebutuhan dan kesesuaian form.'
      },
      {
        role: ROLE.DIRECTOR_A,
        title: 'Direktur RS Raffa Majenang',
        description: 'Direktur RS Raffa Majenang melakukan verifikasi lanjutan.'
      },
      {
        role: ROLE.DIRECTOR_B,
        title: 'Direktur RS Wiradadi Husada',
        description: 'Direktur RS Wiradadi Husada menyetujui final sebelum implementasi.'
      }
    ]

    const filteredDefinitions = requiresDirectorA
      ? definitions
      : definitions.filter((definition) => definition.role !== ROLE.DIRECTOR_A)

    let previousCompleted = true

    return filteredDefinitions.map((definition) => {
      let approval = approvalsByRole.value[definition.role]
      let completed = !!approval

      if (definition.role === ROLE.USER) {
        if (!approval && feature.value) {
          approval = {
            id: `requester-${feature.value.id}`,
            role: ROLE.USER,
            approved_at: feature.value.created_at,
            note: null,
            user: feature.value.user ?? null
          }
        }

        completed = Boolean(feature.value?.id || approval)
      }

      const step = {
        ...definition,
        approval,
        completed,
        current: !completed && previousCompleted
      }

      previousCompleted = previousCompleted && completed

      return step
    })
  })

  const approvalHistory = computed(() => feature.value?.approvals ?? [])

  const comments = computed(() => feature.value?.comments ?? [])

  const gitlabIssue = computed(() => {
    if (!feature.value) {
      return null
    }

    return {
      id: feature.value.gitlab_issue_id,
      iid: feature.value.gitlab_issue_iid,
      url: feature.value.gitlab_issue_url,
      state: feature.value.gitlab_issue_state,
      synced_at: feature.value.gitlab_synced_at,
    }
  })

  const gitlabSyncedAtLabel = computed(() => {
    if (!gitlabIssue.value?.synced_at) {
      return ''
    }

    return formatDateTime(gitlabIssue.value.synced_at)
  })

  const isFullyApproved = computed(() => {
    const status = feature.value?.status
    return status === 'approved_b' || status === 'done'
  })

  const shouldShowGitlabSyncPanel = computed(
    () => Boolean(gitlabIssue.value?.iid) || isFullyApproved.value
  )

  const canCreateGitlabIssue = computed(() => !gitlabIssue.value?.iid && isFullyApproved.value)

  const priorityOptions = PRIORITY_OPTIONS

  const priorityBadgeClass = (priority) => resolvePriorityBadgeClass(priority)

  watch(
    () => feature.value?.priority,
    (newPriority) => {
      selectedPriority.value = newPriority ?? 'biasa'
    }
  )

  watch(
    () => feature.value?.development_status,
    (newStatus) => {
      const parsedStatus = Number(newStatus)
      if (Number.isFinite(parsedStatus)) {
        selectedDevelopmentStatus.value = parsedStatus
      } else {
        selectedDevelopmentStatus.value = developmentStatusChoices[0]?.value ?? 1
      }
    }
  )

  watch(
    () => feature.value?.release_status,
    (newStatus) => {
      const parsedStatus = Number(newStatus)
      selectedReleaseStatus.value = Number.isFinite(parsedStatus) ? parsedStatus : null
    }
  )

  watch(
    () => feature.value?.release_date,
    (newDate) => {
      releaseDate.value = toDateInputValue(newDate)
    }
  )

  const currentStageRole = computed(() => {
    const status = feature.value?.status
    const requiresDirectorA = feature.value?.requires_director_a_approval !== false

    const map = requiresDirectorA
      ? {
        pending: ROLE.MANAGER,
        approved_manager: ROLE.DIRECTOR_A,
        approved_a: ROLE.DIRECTOR_B
      }
      : {
        pending: ROLE.MANAGER,
        approved_manager: ROLE.DIRECTOR_B,
        approved_a: ROLE.DIRECTOR_B
      }

    return map[status] ?? null
  })

  const canApprove = computed(() => {
    const requiredRole = currentStageRole.value
    return requiredRole && isRole(userRole.value, requiredRole)
  })

  const approvalHint = computed(() => {
    if (!feature.value) {
      return 'Data ticket tidak ditemukan.'
    }

    const requiredRole = currentStageRole.value

    if (!requiredRole) {
      return 'Seluruh tahap persetujuan telah selesai.'
    }

    if (!isRole(userRole.value, requiredRole)) {
      return 'Menunggu persetujuan dari peran yang berwenang pada tahap ini.'
    }

    if (!auth.user?.has_kode_sign) {
      return 'Anda belum menyimpan kode ACC. Silakan atur kode melalui menu profil.'
    }

    return 'Anda dapat melakukan persetujuan begitu siap.'
  })

  const roleText = (role) => roleLabels[role] ?? role

  const resetCommentFile = () => {
    commentFile.value = null
    commentFileName.value = ''
    commentError.value = ''
    if (commentInput.value) {
      commentInput.value.value = ''
    }
  }

  const resolveDownloadErrorMessage = async (error) => {
    if (!error?.response) {
      return 'Gagal mengunduh lampiran. Periksa koneksi Anda dan coba lagi.'
    }

    const { status, data, headers } = error.response

    if (status === 404) {
      return 'Lampiran tidak ditemukan atau sudah dihapus.'
    }

    if (status === 403) {
      if (typeof data?.message === 'string' && data.message.trim()) {
        return data.message
      }
      return 'Anda tidak memiliki akses untuk mengunduh lampiran ini.'
    }

    if (typeof data === 'string' && data.trim()) {
      if (data.includes('Route [login] not defined')) {
        return 'Sesi login tidak lagi valid. Silakan masuk kembali lalu coba unduh lagi.'
      }
      return data
    }

    if (data instanceof Blob) {
      try {
        const text = await data.text()

        if (text.includes('Route [login] not defined')) {
          return 'Sesi login tidak lagi valid. Silakan masuk kembali lalu coba unduh lagi.'
        }

        try {
          const parsed = JSON.parse(text)
          if (typeof parsed?.message === 'string' && parsed.message.trim()) {
            return parsed.message
          }
        } catch (parseError) {
          // Abaikan kesalahan parsing dan teruskan menggunakan teks mentah
        }

        if (text.trim()) {
          return text
        }
      } catch (blobError) {
        // Abaikan kesalahan pembacaan blob dan teruskan pesan generik
      }
    }

    if (typeof data?.message === 'string' && data.message.trim()) {
      return data.message
    }

    if (headers?.['www-authenticate']) {
      return 'Sesi login Anda mungkin telah kedaluwarsa. Silakan masuk kembali.'
    }

    return 'Gagal mengunduh lampiran. Silakan coba lagi nanti.'
  }

  const downloadAttachment = async () => {
    attachmentError.value = ''

    if (!feature.value?.attachment_url) {
      attachmentError.value = 'Lampiran tidak tersedia.'
      if (typeof window !== 'undefined') {
        window.alert(attachmentError.value)
      }
      return
    }

    if (!feature.value?.id) {
      attachmentError.value = 'Data ticket tidak tersedia untuk diunduh.'
      if (typeof window !== 'undefined') {
        window.alert(attachmentError.value)
      }
      return
    }

    try {
      downloadingAttachment.value = true

      const headers = {
        Accept: 'application/octet-stream, application/json'
      }

      if (auth.token) {
        headers.Authorization = `Bearer ${auth.token}`
      }

      const response = await axios.get(`/feature-requests/${feature.value.id}/attachment`, {
        responseType: 'blob',
        headers
      })

      const blob = response.data instanceof Blob ? response.data : new Blob([response.data])
      const downloadUrl = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = downloadUrl
      link.download = feature.value.attachment_name || 'lampiran-pengajuan'
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(downloadUrl)
    } catch (error) {
      const message = await resolveDownloadErrorMessage(error)
      attachmentError.value = message
      if (typeof window !== 'undefined') {
        window.alert(message)
      }
    } finally {
      downloadingAttachment.value = false
    }
  }

  const setCommentDownloading = (commentId, downloading) => {
    const key = String(commentId)
    commentDownloadings.value = {
      ...commentDownloadings.value,
      [key]: downloading
    }
  }

  const setCommentDownloadError = (commentId, message) => {
    const key = String(commentId)
    commentDownloadErrors.value = {
      ...commentDownloadErrors.value,
      [key]: message
    }
  }

  const isCommentDownloading = (commentId) =>
    Boolean(commentDownloadings.value[String(commentId)])

  const commentDownloadError = (commentId) =>
    commentDownloadErrors.value[String(commentId)] ?? ''

  const downloadCommentAttachment = async (comment) => {
    if (!comment || !comment.id) {
      return
    }

    const commentId = comment.id
    const featureId = feature.value?.id

    setCommentDownloadError(commentId, '')

    if (!comment.attachment_url) {
      const message = 'Lampiran komentar tidak tersedia.'
      setCommentDownloadError(commentId, message)
      if (typeof window !== 'undefined') {
        window.alert(message)
      }
      return
    }

    if (!featureId) {
      const message = 'Data ticket tidak ditemukan untuk mengunduh lampiran komentar.'
      setCommentDownloadError(commentId, message)
      if (typeof window !== 'undefined') {
        window.alert(message)
      }
      return
    }

    try {
      setCommentDownloading(commentId, true)

      const headers = {
        Accept: 'application/octet-stream, application/json'
      }

      const response = await axios.get(
        `/feature-requests/${featureId}/comments/${commentId}/attachment`,
        {
          responseType: 'blob',
          headers
        }
      )

      const blob = response.data instanceof Blob ? response.data : new Blob([response.data])
      const downloadUrl = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = downloadUrl
      link.download = comment.attachment_name || 'lampiran-komentar'
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(downloadUrl)
      setCommentDownloadError(commentId, '')
    } catch (error) {
      const message = await resolveDownloadErrorMessage(error)
      setCommentDownloadError(commentId, message)
      if (typeof window !== 'undefined') {
        window.alert(message)
      }
    } finally {
      setCommentDownloading(commentId, false)
    }
  }

  const viewAttachment = async () => {
    attachmentError.value = ''

    if (!feature.value?.attachment_url) {
      attachmentError.value = 'Lampiran tidak tersedia.'
      if (typeof window !== 'undefined') {
        window.alert(attachmentError.value)
      }
      return
    }

    if (!feature.value?.id) {
      attachmentError.value = 'Data ticket tidak tersedia untuk dilihat.'
      if (typeof window !== 'undefined') {
        window.alert(attachmentError.value)
      }
      return
    }

    try {
      viewingAttachment.value = true

      const headers = {
        Accept: 'application/octet-stream, application/json'
      }

      if (auth.token) {
        headers.Authorization = `Bearer ${auth.token}`
      }

      const response = await axios.get(`/feature-requests/${feature.value.id}/attachment`, {
        responseType: 'blob',
        headers
      })

      const blob = response.data instanceof Blob ? response.data : new Blob([response.data])
      const contentType =
        response.headers?.['content-type'] ||
        response.headers?.['Content-Type'] ||
        blob.type ||
        'application/octet-stream'

      revokeAttachmentPreviewUrl()

      if (typeof window === 'undefined') {
        attachmentError.value = 'Pratinjau tidak tersedia dalam lingkungan saat ini.'
        return
      }

      attachmentPreviewType.value = contentType
      attachmentPreviewUrl.value = window.URL.createObjectURL(blob)
      showAttachmentViewer.value = true
    } catch (error) {
      const message = await resolveDownloadErrorMessage(error)
      attachmentError.value = message
      if (typeof window !== 'undefined') {
        window.alert(message)
      }
    } finally {
      viewingAttachment.value = false
    }
  }

  const handleCommentFileChange = (event) => {
    const [file] = event.target.files || []
    commentError.value = ''

    if (!file) {
      resetCommentFile()
      return
    }

    const allowedTypes = [
      'application/pdf',
      'application/msword',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'image/jpeg',
      'image/png'
    ]

    if (!allowedTypes.includes(file.type)) {
      commentFile.value = null
      commentFileName.value = ''
      if (commentInput.value) {
        commentInput.value.value = ''
      }
      commentError.value = 'Format berkas harus PDF, DOC, DOCX, JPG, atau PNG.'
      return
    }

    const maxSize = 5 * 1024 * 1024
    if (file.size > maxSize) {
      commentFile.value = null
      commentFileName.value = ''
      if (commentInput.value) {
        commentInput.value.value = ''
      }
      commentError.value = 'Ukuran berkas maksimal 5MB.'
      return
    }

    commentFile.value = file
    commentFileName.value = file.name
  }

  const canComment = computed(() => isRole(userRole.value, ROLE.ADMIN))

  const postComment = async () => {
    commentError.value = ''

    if (!canComment.value) {
      commentError.value = 'Anda tidak memiliki akses untuk memberikan komentar.'
      return
    }

    if (!adminComment.value.trim()) {
      commentError.value = 'Tulis komentar terlebih dahulu.'
      return
    }

    if (!feature.value) {
      commentError.value = 'Data ticket tidak ditemukan.'
      return
    }

    try {
      commentSubmitting.value = true
      const payload = new FormData()
      payload.append('comment', adminComment.value.trim())
      if (commentFile.value) {
        payload.append('attachment', commentFile.value)
      }

      const { data } = await axios.post(`/feature-requests/${feature.value.id}/comments`, payload, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })

      adminComment.value = ''
      resetCommentFile()
      const existing = feature.value.comments ?? []
      feature.value = {
        ...feature.value,
        comments: [data, ...existing],
        comments_count: (feature.value.comments_count ?? existing.length) + 1
      }
    } catch (error) {
      commentError.value = error?.response?.data?.message ?? 'Gagal menyimpan komentar.'
    } finally {
      commentSubmitting.value = false
    }
  }

  const updatePriority = async () => {
    priorityError.value = ''
    prioritySuccess.value = ''

    if (!isAdmin.value) {
      priorityError.value = 'Hanya admin yang dapat memperbarui prioritas.'
      return
    }

    if (!feature.value) {
      priorityError.value = 'Data ticket tidak ditemukan.'
      return
    }

    try {
      prioritySaving.value = true
      const { data } = await axios.put(`/feature-requests/${feature.value.id}`, {
        priority: selectedPriority.value
      })
      feature.value = data
      prioritySuccess.value = 'Prioritas ticket berhasil diperbarui.'
    } catch (error) {
      priorityError.value = error?.response?.data?.message ?? 'Gagal memperbarui prioritas.'
    } finally {
      prioritySaving.value = false
    }
  }

  const updateReleaseInfo = async () => {
    releaseError.value = ''
    releaseSuccess.value = ''

    if (!isAdmin.value) {
      releaseError.value = 'Hanya admin yang dapat memperbarui data release.'
      return
    }

    if (!feature.value) {
      releaseError.value = 'Data ticket tidak ditemukan.'
      return
    }

    try {
      releaseSaving.value = true
      const payload = {}

      if (selectedReleaseStatus.value !== null && selectedReleaseStatus.value !== undefined) {
        payload.release_status = selectedReleaseStatus.value
      }

      if (releaseDate.value || releaseDate.value === '') {
        payload.release_date = releaseDate.value || null
      }

      const { data } = await axios.put(`/feature-requests/${feature.value.id}`, payload)
      feature.value = data
      releaseSuccess.value = 'Data release berhasil diperbarui.'
    } catch (error) {
      releaseError.value = error?.response?.data?.message ?? 'Gagal memperbarui data release.'
    } finally {
      releaseSaving.value = false
    }
  }

  const updateDevelopmentStatus = async () => {
    developmentStatusError.value = ''
    developmentStatusSuccess.value = ''

    if (!isAdmin.value) {
      developmentStatusError.value = 'Hanya admin yang dapat memperbarui status pengembangan.'
      return
    }

    if (!feature.value) {
      developmentStatusError.value = 'Data ticket tidak ditemukan.'
      return
    }

    try {
      developmentStatusSaving.value = true
      const { data } = await axios.put(`/feature-requests/${feature.value.id}`, {
        development_status: selectedDevelopmentStatus.value
      })
      feature.value = data
      developmentStatusSuccess.value = 'Status pengembangan berhasil diperbarui.'
    } catch (error) {
      developmentStatusError.value = error?.response?.data?.message ?? 'Gagal memperbarui status pengembangan.'
    } finally {
      developmentStatusSaving.value = false
    }
  }

  const downloadFeatureAttachment = async () => {
    attachmentError.value = ''

    if (!feature.value?.attachment_url) {
      attachmentError.value = 'Lampiran tidak tersedia untuk ticket ini.'
      return
    }

    try {
      downloadingAttachment.value = true
      const response = await axios.get(feature.value.attachment_url, {
        responseType: 'blob',
      })

      const blob = new Blob([response.data], { type: response.headers['content-type'] })
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = feature.value.attachment_name || 'lampiran-ticket'
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      window.URL.revokeObjectURL(url)
    } catch (error) {
      attachmentError.value = error?.response?.data?.message ?? 'Gagal mengunduh lampiran.'
    } finally {
      downloadingAttachment.value = false
      closeAttachmentViewer()
    }
  }

  const formatGitlabState = (state) => {
    if (!state) {
      return ''
    }

    return (
      {
        opened: 'Terbuka',
        closed: 'Ditutup',
        locked: 'Terkunci',
      }[state] ?? state
    )
  }

  const runGitlabSync = async () => {
    try {
      gitlabSyncing.value = true
      const { data } = await axios.post(`/feature-requests/${feature.value.id}/gitlab`)
      feature.value = data.feature
      gitlabSuccess.value = data.message ?? 'Sinkronisasi GitLab berhasil.'
      selectedPriority.value = data.feature?.priority ?? selectedPriority.value
    } catch (error) {
      gitlabError.value = error?.response?.data?.message ?? 'Gagal melakukan sinkronisasi GitLab.'
    } finally {
      gitlabSyncing.value = false
    }
  }

  const syncGitlabIssue = async () => {
    gitlabError.value = ''
    gitlabSuccess.value = ''

    if (!isAdmin.value) {
      gitlabError.value = 'Hanya admin yang dapat melakukan sinkronisasi ke GitLab.'
      return
    }

    if (!feature.value) {
      gitlabError.value = 'Data ticket tidak ditemukan.'
      return
    }

    if (!feature.value.gitlab_issue_iid && !canCreateGitlabIssue.value) {
      gitlabError.value =
        'Issue GitLab hanya dapat dibuat setelah seluruh stakeholder menyetujui dan ticket memasuki tahap pengerjaan.'
      return
    }

    if (!feature.value.gitlab_issue_iid) {
      openGitlabConfirmModal()
      return
    }

    await runGitlabSync()
  }

  const confirmGitlabSync = async () => {
    if (gitlabSyncing.value) {
      return
    }

    if (!feature.value) {
      closeGitlabConfirmModal()
      return
    }

    await runGitlabSync()
    closeGitlabConfirmModal()
  }

  const cancelGitlabSync = () => {
    closeGitlabConfirmModal()
  }

  const deleteFeature = async () => {
    deleteError.value = ''

    if (!isAdmin.value) {
      deleteError.value = 'Hanya admin yang dapat menghapus ticket.'
      return
    }

    if (!feature.value) {
      deleteError.value = 'Data  tidak ditemukan.'
      return
    }

    const confirmed = window.confirm('Yakin ingin menghapus ticket ini? Tindakan tidak dapat dibatalkan.')
    if (!confirmed) {
      return
    }

    try {
      deleteLoading.value = true
      await axios.delete(`/feature-requests/${feature.value.id}`)
      router.push('/feature-request')
    } catch (error) {
      deleteError.value = error?.response?.data?.message ?? 'Gagal menghapus ticket.'
    } finally {
      deleteLoading.value = false
    }
  }

  const approve = async () => {
    errorMessage.value = ''
    successMessage.value = ''

    if (!canApprove.value) {
      errorMessage.value = 'Anda tidak memiliki akses untuk menyetujui tahap ini.'
      return
    }

    if (!auth.user?.has_kode_sign) {
      errorMessage.value = 'Simpan kode ACC Anda terlebih dahulu.'
      return
    }

    try {
      submitting.value = true
      const { data } = await axios.post(`/feature-requests/${feature.value.id}/approve`, {
        sign_code: signCode.value,
        note: note.value
      })
      successMessage.value = data.message
      signCode.value = ''
      note.value = ''
      feature.value = data.feature
    } catch (error) {
      errorMessage.value = error?.response?.data?.message ?? 'Terjadi kesalahan saat menyimpan persetujuan.'
    } finally {
      submitting.value = false
    }
  }

  const releaseStatusLabel = (status) => releaseStatusRegistry[Number(status)]?.label ?? 'Belum diatur'
  const releaseStatusBadgeClass = (status) => releaseStatusRegistry[Number(status)]?.badgeClass ?? 'bg-secondary-subtle text-secondary'

  return {
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
    requestTypes,
    instansiLabel,
    steps,
    approvalHistory,
    canApprove,
    approvalHint,
    roleText,
    formatDate: formatDateTime,
    formatDateOnly,
    releaseStatusLabel,
    releaseStatusBadgeClass,
    approve,
    priorityOptions,
    selectedPriority,
    prioritySaving,
    prioritySuccess,
    priorityError,
    priorityBadgeClass,
    updatePriority,
    releaseStatusOptions,
    selectedReleaseStatus,
    releaseDate,
    releaseSaving,
    releaseSuccess,
    releaseError,
    updateReleaseInfo,
    developmentStatusOptions,
    selectedDevelopmentStatus,
    developmentStatusSaving,
    developmentStatusSuccess,
    developmentStatusError,
    developmentStatusLabel,
    developmentStatusClass,
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
    downloadAttachment: downloadFeatureAttachment,
    downloadingAttachment,
    attachmentError,
    attachmentViewerOpen,
    openAttachmentViewer,
    closeAttachmentViewer,
    showAttachmentViewer,
    hideAttachmentViewer,
    gitlabIssue,
    gitlabSyncedAtLabel,
    gitlabSyncing,
    gitlabSuccess,
    gitlabError,
    gitlabConfirmModal,
    shouldShowGitlabSyncPanel,
    canCreateGitlabIssue,
    syncGitlabIssue,
    confirmGitlabSync,
    cancelGitlabSync,
    formatGitlabState,
  }
}

export function useJangmedPriorities() {
  const loading = ref(false)
  const items = ref([])
  const perPage = ref(10)
  const scope = ref('active')
  const pagination = reactive({
    currentPage: 1,
    lastPage: 1,
    total: 0,
    from: 0,
    to: 0
  })
  const message = ref('')
  const messageType = ref('info')
  const rowSaving = reactive({})
  const localPriorities = reactive({})

  const priorityOptions = PRIORITY_OPTIONS
  const priorityBadgeClass = (priority) => resolvePriorityBadgeClass(priority)
  const scopeOptions = Object.freeze([
    { value: 'active', label: 'Sedang Dikerjakan' },
    { value: 'completed', label: 'Selesai' }
  ])

  const isPriorityLocked = (item) => {
    if (!item) {
      return false
    }

    if (item.status === 'done') {
      return true
    }

    const developmentStatus = Number(item.development_status)
    return (
      item.status === 'approved_b' &&
      Number.isFinite(developmentStatus) &&
      developmentStatus >= 4
    )
  }

  const setMessage = (type, value) => {
    messageType.value = type
    message.value = value
    if (value) {
      setTimeout(() => {
        message.value = ''
      }, 3500)
    }
  }

  const syncLocalPriorities = () => {
    Object.keys(localPriorities).forEach((key) => {
      delete localPriorities[key]
    })

    items.value.forEach((item) => {
      localPriorities[item.id] = item.priority ?? 'biasa'
    })
  }

  const setRowSaving = (id, state) => {
    rowSaving[id] = state
  }

  const mergeItem = (updated) => {
    items.value = items.value.map((item) => (item.id === updated.id ? updated : item))
    localPriorities[updated.id] = updated.priority ?? 'biasa'
  }

  const loadItems = async (page = pagination.currentPage) => {
    loading.value = true
    try {
      const { data } = await axios.get('/manager/jangmed/priorities', {
        params: {
          page,
          per_page: perPage.value,
          scope: scope.value
        }
      })

      const list = data?.data ?? []
      items.value = list
      pagination.currentPage = Number(data?.current_page) || page
      pagination.lastPage = Number(data?.last_page) || 1
      pagination.total = Number(data?.total) || list.length
      pagination.from = Number(data?.from) || (list.length ? 1 : 0)
      pagination.to = Number(data?.to) || list.length
      syncLocalPriorities()
    } catch (error) {
      items.value = []
      pagination.currentPage = 1
      pagination.lastPage = 1
      pagination.total = 0
      pagination.from = 0
      pagination.to = 0
      setMessage('danger', error?.response?.data?.message ?? 'Gagal memuat daftar prioritas.')
    } finally {
      loading.value = false
    }
  }

  const changePage = (page) => {
    if (page < 1 || page > pagination.lastPage || page === pagination.currentPage) {
      return
    }
    loadItems(page)
  }

  const nextPage = () => changePage(pagination.currentPage + 1)
  const previousPage = () => changePage(pagination.currentPage - 1)

  const pageNumbers = computed(() => {
    const pages = []
    for (let i = 1; i <= pagination.lastPage; i += 1) {
      pages.push(i)
    }
    return pages
  })

  const setScope = (value) => {
    if (!value || scope.value === value) {
      return
    }

    const option = scopeOptions.find((item) => item.value === value)

    if (option) {
      scope.value = option.value
    }
  }

  const updatePriority = async (item) => {
    if (isPriorityLocked(item)) {
      setMessage('warning', 'Prioritas tidak dapat diubah karena ticket sudah selesai.')
      return
    }

    const selectedPriority = localPriorities[item.id] ?? item.priority ?? 'biasa'
    setRowSaving(item.id, true)
    setMessage('', '')

    try {
      const { data } = await axios.patch(`/manager/jangmed/priorities/${item.id}`, {
        priority: selectedPriority
      })

      if (data?.feature) {
        mergeItem(data.feature)
      }

      setMessage('success', data?.message ?? 'Prioritas berhasil diperbarui.')
    } catch (error) {
      setMessage('danger', error?.response?.data?.message ?? 'Gagal memperbarui prioritas.')
    } finally {
      setRowSaving(item.id, false)
    }
  }

  watch(perPage, (value, oldValue) => {
    if (value !== oldValue) {
      loadItems(1)
    }
  })

  watch(scope, (value, oldValue) => {
    if (value !== oldValue) {
      pagination.currentPage = 1
      loadItems(1)
    }
  })

  onMounted(() => {
    loadItems(pagination.currentPage)
  })

  return {
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
    loadItems,
    setScope,
    updatePriority,
    pageNumbers,
    changePage,
    nextPage,
    previousPage
  }
}

export function useAdminMaster() {
  const router = useRouter()
  const auth = useAuthStore()

  const instansiOptions = [
    { value: 'wiradadi', label: 'RS Wiradadi Husada' },
    { value: 'raffa', label: 'RS Raffa Majenang' }
  ]

  const roleOptions = ROLE_OPTIONS
  const managerCategoryOptions = MANAGER_CATEGORY_OPTIONS

  const units = ref([])
  const users = ref([])
  const unitPagination = reactive({
    currentPage: 1,
    perPage: 5,
    total: 0,
    lastPage: 1,
    from: 0,
    to: 0
  })
  const userPagination = reactive({
    currentPage: 1,
    perPage: 10,
    total: 0,
    lastPage: 1,
    from: 0,
    to: 0
  })
  const loadingUnits = ref(false)
  const loadingUsers = ref(false)
  const savingUnit = ref(false)
  const deletingUnitId = ref(null)
  const unitMessage = ref('')
  const unitMessageType = ref('')
  const userMessage = ref('')
  const userMessageType = ref('')
  const userSaving = reactive({})
  const allUnits = ref([])

  const unitForm = reactive({
    id: null,
    name: '',
    instansi: instansiOptions[0].value,
    is_active: true,
    manager_category_id: managerCategoryOptions[0]?.value ?? null
  })

  const isEditingUnit = computed(() => unitForm.id !== null)

  const sortUnits = (items) =>
    [...items].sort((a, b) => a.name.localeCompare(b.name, 'id', { sensitivity: 'base' }))

  const ensureAdmin = () => {
    const role = Number(auth.user?.level ?? auth.user?.role ?? 0)
    if (!isRole(role, ROLE.ADMIN)) {
      router.replace('/feature-request')
    }
  }

  watch(
    () => auth.user,
    () => ensureAdmin(),
    { immediate: true }
  )

  const showUnitMessage = (type, message) => {
    unitMessageType.value = type
    unitMessage.value = message
    if (message) {
      setTimeout(() => {
        unitMessage.value = ''
        unitMessageType.value = ''
      }, 3500)
    }
  }

  const showUserMessage = (type, message) => {
    userMessageType.value = type
    userMessage.value = message
    if (message) {
      setTimeout(() => {
        userMessage.value = ''
        userMessageType.value = ''
      }, 3500)
    }
  }

  const sanitizePage = (value, fallback = 1) => {
    const parsed = Number(value)
    if (!Number.isFinite(parsed) || parsed < 1) {
      return fallback
    }

    return parsed
  }

  const sanitizePerPage = (value, fallback = 10) => {
    const parsed = Number(value)
    if (!Number.isFinite(parsed) || parsed < 1) {
      return fallback
    }

    return Math.min(parsed, 50)
  }

  const resetPagination = (state) => {
    state.currentPage = 1
    state.lastPage = 1
    state.total = 0
    state.from = 0
    state.to = 0
  }

  const fetchUnits = async (page = unitPagination.currentPage) => {
    loadingUnits.value = true
    try {
      const targetPage = sanitizePage(page, unitPagination.currentPage)
      const { data } = await axios.get('/units', {
        params: {
          page: targetPage,
          per_page: sanitizePerPage(unitPagination.perPage)
        }
      })

      if (Array.isArray(data?.data)) {
        units.value = sortUnits(data.data)
      } else if (Array.isArray(data)) {
        units.value = sortUnits(data)
      } else {
        units.value = []
      }

      const hasItems = units.value.length > 0
      unitPagination.currentPage = sanitizePage(data?.current_page, targetPage)
      unitPagination.perPage = sanitizePerPage(data?.per_page, unitPagination.perPage)
      unitPagination.total = Number.isFinite(Number(data?.total)) ? Number(data.total) : units.value.length
      unitPagination.lastPage = Math.max(1, sanitizePage(data?.last_page, 1))
      unitPagination.from = Number.isFinite(Number(data?.from)) ? Number(data.from) : hasItems ? 1 : 0
      unitPagination.to = Number.isFinite(Number(data?.to)) ? Number(data.to) : hasItems ? units.value.length : 0
    } catch (error) {
      units.value = []
      resetPagination(unitPagination)
      showUnitMessage('danger', error?.response?.data?.message ?? 'Gagal memuat daftar unit.')
    } finally {
      loadingUnits.value = false
    }
  }

  const fetchAllUnits = async () => {
    try {
      const { data } = await axios.get('/units', {
        params: { all: true }
      })
      allUnits.value = Array.isArray(data) ? sortUnits(data) : []
    } catch (error) {
      allUnits.value = []
    }
  }

  const fetchUsers = async (page = userPagination.currentPage) => {
    loadingUsers.value = true
    try {
      const targetPage = sanitizePage(page, userPagination.currentPage)
      const { data } = await axios.get('/admin/users', {
        params: {
          page: targetPage,
          per_page: sanitizePerPage(userPagination.perPage)
        }
      })

      if (Array.isArray(data?.data)) {
        users.value = data.data
      } else if (Array.isArray(data)) {
        users.value = data
      } else {
        users.value = []
      }

      const hasItems = users.value.length > 0
      userPagination.currentPage = sanitizePage(data?.current_page, targetPage)
      userPagination.perPage = sanitizePerPage(data?.per_page, userPagination.perPage)
      userPagination.total = Number.isFinite(Number(data?.total)) ? Number(data.total) : users.value.length
      userPagination.lastPage = Math.max(1, sanitizePage(data?.last_page, 1))
      userPagination.from = Number.isFinite(Number(data?.from)) ? Number(data.from) : hasItems ? 1 : 0
      userPagination.to = Number.isFinite(Number(data?.to)) ? Number(data.to) : hasItems ? users.value.length : 0
    } catch (error) {
      users.value = []
      resetPagination(userPagination)
      showUserMessage('danger', error?.response?.data?.message ?? 'Gagal memuat daftar pengguna.')
    } finally {
      loadingUsers.value = false
    }
  }

  onMounted(() => {
    fetchUnits()
    fetchAllUnits()
    fetchUsers()
  })

  const resetUnitForm = () => {
    unitForm.id = null
    unitForm.name = ''
    unitForm.instansi = instansiOptions[0].value
    unitForm.is_active = true
    unitForm.manager_category_id = managerCategoryOptions[0]?.value ?? null
  }

  const startEditUnit = (unit) => {
    unitForm.id = unit.id
    unitForm.name = unit.name
    unitForm.instansi = unit.instansi
    unitForm.is_active = !!unit.is_active
    unitForm.manager_category_id =
      unit.manager_category_id ?? (managerCategoryOptions[0]?.value ?? null)
  }

  const submitUnit = async () => {
    if (!unitForm.name) {
      showUnitMessage('danger', 'Nama unit harus diisi.')
      return
    }

    savingUnit.value = true

    const payload = {
      name: unitForm.name,
      instansi: unitForm.instansi,
      is_active: unitForm.is_active,
      manager_category_id: unitForm.manager_category_id
    }

    try {
      if (unitForm.id) {
        const { data } = await axios.put(`/units/${unitForm.id}`, payload)
        showUnitMessage('success', `Unit ${data.name ?? unitForm.name} berhasil diperbarui.`)
      } else {
        const { data } = await axios.post('/units', payload)
        showUnitMessage('success', `Unit ${data.name ?? unitForm.name} berhasil ditambahkan.`)
      }
      resetUnitForm()
      await Promise.all([fetchUnits(unitPagination.currentPage), fetchAllUnits()])
    } catch (error) {
      showUnitMessage('danger', error?.response?.data?.message ?? 'Gagal menyimpan data unit.')
    } finally {
      savingUnit.value = false
    }
  }

  const cancelUnitEdit = () => {
    resetUnitForm()
  }

  const toggleUnitStatus = async (unit) => {
    try {
      const { data } = await axios.put(`/units/${unit.id}`, {
        is_active: !unit.is_active
      })
      showUnitMessage('success', `Status unit ${data.name ?? unit.name} diperbarui.`)
      await Promise.all([fetchAllUnits(), fetchUnits(unitPagination.currentPage)])
    } catch (error) {
      showUnitMessage('danger', error?.response?.data?.message ?? 'Gagal memperbarui status unit.')
    }
  }

  const deleteUnit = async (unit) => {
    deletingUnitId.value = unit.id
    try {
      await axios.delete(`/units/${unit.id}`)
      showUnitMessage('success', 'Unit berhasil dihapus.')
      const targetPage = units.value.length <= 1 && unitPagination.currentPage > 1
        ? unitPagination.currentPage - 1
        : unitPagination.currentPage
      await Promise.all([fetchAllUnits(), fetchUnits(targetPage)])
    } catch (error) {
      showUnitMessage('danger', error?.response?.data?.message ?? 'Unit tidak dapat dihapus.')
    } finally {
      deletingUnitId.value = null
    }
  }

  const unitsForInstansi = (instansi) =>
    allUnits.value.filter((unit) => {
      if (!instansi) {
        return unit.is_active
      }
      return unit.instansi === instansi && unit.is_active
    })

  const changeUnitPage = (page) => {
    if (loadingUnits.value) {
      return
    }

    const target = sanitizePage(page, unitPagination.currentPage)
    if (target === unitPagination.currentPage || target < 1 || target > unitPagination.lastPage) {
      return
    }

    fetchUnits(target)
  }

  const changeUserPage = (page) => {
    if (loadingUsers.value) {
      return
    }

    const target = sanitizePage(page, userPagination.currentPage)
    if (target === userPagination.currentPage || target < 1 || target > userPagination.lastPage) {
      return
    }

    fetchUsers(target)
  }

  const updateUnitPerPage = (value) => {
    const perPage = sanitizePerPage(value, unitPagination.perPage)
    if (perPage === unitPagination.perPage) {
      return
    }

    unitPagination.perPage = perPage
    fetchUnits(1)
  }

  const updateUserPerPage = (value) => {
    const perPage = sanitizePerPage(value, userPagination.perPage)
    if (perPage === userPagination.perPage) {
      return
    }

    userPagination.perPage = perPage
    fetchUsers(1)
  }

  const setUserSaving = (userId, state) => {
    userSaving[userId] = state
  }

  const mergeUser = (updatedUser) => {
    users.value = users.value.map((item) => (item.id === updatedUser.id ? updatedUser : item))
  }

  const updateUser = async (user, payload, successMessage = 'Perubahan tersimpan.') => {
    setUserSaving(user.id, true)
    try {
      const { data } = await axios.put(`/admin/users/${user.id}`, payload)
      if (data?.user) {
        mergeUser(data.user)
      }
      showUserMessage('success', successMessage)
    } catch (error) {
      showUserMessage('danger', error?.response?.data?.message ?? 'Gagal memperbarui data pengguna.')
    } finally {
      setUserSaving(user.id, false)
    }
  }

  const changeUserInstansi = (user, instansi) => {
    updateUser(user, { instansi, unit_id: null }, 'Instansi pengguna diperbarui.')
  }

  const changeUserUnit = (user, unitId) => {
    updateUser(
      user,
      { unit_id: unitId || null },
      unitId ? 'Unit pengguna diperbarui.' : 'Unit pengguna dihapus.'
    )
  }

  const changeUserRole = (user, role) => {
    const normalized = Number(role)
    if (!Number.isFinite(normalized) || normalized <= 0) {
      showUserMessage('danger', 'Role yang dipilih tidak valid.')
      return
    }

    if (normalized === ROLE.MANAGER) {
      const selectedCategory =
        user.manager_category_id || managerCategoryOptions[0]?.value || MANAGER_CATEGORY.YANMUM
      updateUser(
        user,
        { level: normalized, manager_category_id: selectedCategory },
        'Role pengguna diperbarui.'
      )
    } else {
      updateUser(user, { level: normalized, manager_category_id: null }, 'Role pengguna diperbarui.')
    }
  }

  const changeUserManagerCategory = (user, categoryId) => {
    const parsed = Number(categoryId)

    if (!Number.isFinite(parsed)) {
      showUserMessage('danger', 'Kategori manager tidak valid.')
      return
    }

    updateUser(
      user,
      { manager_category_id: parsed },
      'Kategori manager berhasil diperbarui.'
    )
  }

  const toggleUserVerification = (user) => {
    updateUser(
      user,
      { is_verified: !user.is_verified },
      !user.is_verified ? 'Pengguna berhasil diverifikasi.' : 'Status verifikasi pengguna diperbarui.'
    )
  }

  const verifiedBadgeClass = (isVerified) =>
    isVerified ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-danger'

  return {
    instansiOptions,
    roleOptions,
    managerCategoryOptions,
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
    updateUserPerPage
  }
}
