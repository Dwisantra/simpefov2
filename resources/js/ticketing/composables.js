import { ref, reactive, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import { Modal } from 'bootstrap'
import { useRouter, useRoute } from 'vue-router'
import axios from '@/lib/axios'
import { useAuthStore } from '@/stores/auth'
import { ROLE, ROLE_LABELS, ROLE_OPTIONS, isRole } from '@/constants/roles'

const roleLabels = ROLE_LABELS

const statusLabels = {
  pending: 'Menunggu ACC Manager',
  approved_manager: 'Menunggu Direktur RS Raffa Majenang',
  approved_a: 'Menunggu Direktur RS Wiradadi Husada',
  approved_b: 'Selesai',
  done: 'Selesai'
}

const resolveStatusLabel = (status, instansi = null) => {
  if (!status) {
    return '-'
  }

  if (instansi === 'wiradadi' && status === 'approved_manager') {
    return statusLabels.approved_a
  }

  return statusLabels[status] ?? status
}

const developmentStatusRegistry = Object.freeze({
  1: { label: 'Analisis', badgeClass: 'bg-secondary-subtle text-secondary' },
  2: { label: 'Pengerjaan', badgeClass: 'bg-info-subtle text-info' },
  3: { label: 'Testing', badgeClass: 'bg-warning-subtle text-warning' },
  4: { label: 'Ready Release', badgeClass: 'bg-success-subtle text-success' }
})

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

  const email = ref('')
  const password = ref('')
  const showPassword = ref(false)
  const loading = ref(false)
  const error = ref('')

  const togglePasswordVisibility = () => {
    showPassword.value = !showPassword.value
  }

  const submit = async () => {
    error.value = ''

    if (!email.value || !password.value) {
      error.value = 'Masukkan email dan password Anda.'
      return
    }

    try {
      loading.value = true
      await auth.login(email.value, password.value)
      router.push('/feature-request')
    } catch (err) {
      error.value = err?.response?.data?.message ?? 'Login gagal. Periksa kembali data Anda.'
    } finally {
      loading.value = false
    }
  }

  return {
    email,
    password,
    showPassword,
    loading,
    error,
    togglePasswordVisibility,
    submit
  }
}

export function useRegisterForm() {
  const router = useRouter()

  const name = ref('')
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

  const statusLabel = (value) => {
    if (!value && value !== 0) {
      return '-'
    }

    if (typeof value === 'string') {
      return resolveStatusLabel(value)
    }

    if (typeof value === 'object') {
      return resolveStatusLabel(value.status, value.requester_instansi)
    }

    return '-'
  }

  const statusBadgeClass = (status) => {
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

  const priorityBadgeClass = (priority) => {
    switch (priority) {
      case 'cito':
        return 'bg-danger-subtle text-danger'
      case 'sedang':
        return 'bg-warning-subtle text-warning'
      default:
        return 'bg-secondary-subtle text-secondary'
    }
  }

  const baseTotalSteps = 4

  const totalSteps = (item) => (item?.requester_instansi === 'wiradadi' ? 3 : baseTotalSteps)

  const instansiLabel = (value) => {
    const map = {
      wiradadi: 'RS Wiradadi Husada',
      raffa: 'RS Raffa Majenang',
    }

    return map[value] ?? value ?? '-'
  }

  const progressFromStatus = (item) => {
    const status = typeof item === 'object' ? item?.status : item
    const instansi =
      (typeof item === 'object' && (item?.requester_instansi || item?.user?.instansi)) || null

    if (instansi === 'wiradadi') {
      const wiradadiMap = {
        pending: 1,
        approved_manager: 2,
        approved_a: 2,
        approved_b: 3,
        done: 3
      }

      return wiradadiMap[status] ?? 0
    }

    const defaultMap = {
      pending: 1,
      approved_manager: 2,
      approved_a: 3,
      approved_b: 4,
      done: 4
    }

    return defaultMap[status] ?? 0
  }

  const progressSteps = (item) => {
    const count = totalSteps(item)
    const rawProgress = item?.status_progress
    const parsedProgress =
      rawProgress === null || rawProgress === undefined || rawProgress === ''
        ? NaN
        : Number(rawProgress)
    const baseProgress = Number.isFinite(parsedProgress)
      ? parsedProgress
      : progressFromStatus(item)
    const numeric = Number.isFinite(baseProgress) ? baseProgress : 0
    return Math.max(0, Math.min(count, numeric))
  }

  const progressPercentage = (item) => {
    const stepsCount = totalSteps(item)

    if (stepsCount <= 1) {
      return 100
    }

    const value = progressSteps(item)

    if (value <= 1) {
      return 5
    }

    return Math.min(100, Math.round(((value - 1) / (stepsCount - 1)) * 100))
  }

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
      return 'Tidak ada tiket yang ditampilkan.'
    }

    return `Menampilkan ${pageMeta.value.from}-${pageMeta.value.to} dari ${pageMeta.value.total} tiket`
  })

  const loadRequests = async (page = 1) => {
    loading.value = true
    try {
      const { data } = await axios.get('/feature-requests', {
        params: {
          page,
          per_page: perPage.value
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

  const canCreate = computed(() => isRole(userRole.value, ROLE.USER))
  const isAdmin = computed(() => isRole(userRole.value, ROLE.ADMIN))
  const developmentStatusBadgeClass = (statusId) => resolveDevelopmentStatusBadgeClass(statusId)

  const perPageOptions = [5, 10, 20, 50]

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
    formatDate: formatDateTime,
    instansiLabel,
    canCreate,
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
      message.value = 'Hanya pemohon yang dapat mengajukan tiket baru.'
      messageType.value = 'error'
      return
    }

    if (!auth.user?.is_verified) {
      message.value = 'Akun Anda belum diverifikasi admin. Tunggu verifikasi sebelum mengajukan.'
      messageType.value = 'error'
      return
    }

    if (requestTypes.value.length === 0) {
      message.value = 'Pilih minimal satu jenis permintaan.'
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

  const userRole = computed(() => Number(auth.user?.level ?? auth.user?.role ?? 0))
  const isAdmin = computed(() => isRole(userRole.value, ROLE.ADMIN))
  const typeLabels = {
    new_feature: 'Pembuatan Fitur Baru',
    new_report: 'Pembuatan Report/Cetakan',
    bug_fix: 'Lapor Bug/Error',
    gitlab_issue: 'Issue dari GitLab',
  }
  const developmentStatusOptions = developmentStatusChoices

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
      selectedDevelopmentStatus.value =
        data?.development_status ?? (developmentStatusChoices[0]?.value ?? 1)
      closeAttachmentViewer()
    } catch (error) {
      feature.value = null
      closeAttachmentViewer()
    } finally {
      loading.value = false
    }
  }

  onMounted(fetchFeature)
  onBeforeUnmount(closeAttachmentViewer)

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
    resolveStatusLabel(feature.value?.status, feature.value?.requester_instansi)
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
    const isWiradadi = feature.value?.requester_instansi === 'wiradadi'

    const definitions = [
      {
        role: ROLE.USER,
        title: 'Pengajuan Pemohon',
        description: 'Pemohon mengisi detail permintaan dan mengesahkan dengan kode ACC.'
      },
      {
        role: ROLE.MANAGER,
        title: 'Persetujuan Manager',
        description: 'Manager memvalidasi kebutuhan dan kesesuaian form.'
      }
    ]

    if (!isWiradadi) {
      definitions.push({
        role: ROLE.DIRECTOR_A,
        title: 'Direktur RS Raffa Majenang',
        description: 'Direktur RS Raffa Majenang melakukan verifikasi lanjutan.'
      })
    }

    definitions.push({
      role: ROLE.DIRECTOR_B,
      title: 'Direktur RS Wiradadi Husada',
      description: 'Direktur RS Wiradadi Husada menyetujui final sebelum implementasi.'
    })

    let previousCompleted = true

    return definitions.map((definition) => {
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

  const priorityOptions = [
    { value: 'biasa', label: 'Prioritas Biasa' },
    { value: 'sedang', label: 'Prioritas Sedang' },
    { value: 'cito', label: 'Prioritas Cito' }
  ]

  const priorityBadgeClass = (priority) => {
    switch (priority) {
      case 'cito':
        return 'bg-danger-subtle text-danger'
      case 'sedang':
        return 'bg-warning-subtle text-warning'
      default:
        return 'bg-secondary-subtle text-secondary'
    }
  }

  watch(
    () => feature.value?.priority,
    (newPriority) => {
      selectedPriority.value = newPriority ?? 'biasa'
    }
  )

  watch(
    () => feature.value?.development_status,
    (newStatus) => {
      if (typeof newStatus === 'number') {
        selectedDevelopmentStatus.value = newStatus
      } else {
        selectedDevelopmentStatus.value = developmentStatusChoices[0]?.value ?? 1
      }
    }
  )

  const currentStageRole = computed(() => {
    const status = feature.value?.status
    const instansi = feature.value?.requester_instansi

    if (status === 'approved_manager') {
      return instansi === 'wiradadi' ? ROLE.DIRECTOR_B : ROLE.DIRECTOR_A
    }

    const map = {
      pending: ROLE.MANAGER,
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
      return 'Data tiket tidak ditemukan.'
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
      attachmentError.value = 'Data tiket tidak tersedia untuk diunduh.'
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
      link.download = feature.value.attachment_name || 'lampiran-permintaan'
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
      const message = 'Data tiket tidak ditemukan untuk mengunduh lampiran komentar.'
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
      attachmentError.value = 'Data tiket tidak tersedia untuk dilihat.'
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
      commentError.value = 'Data tiket tidak ditemukan.'
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
      priorityError.value = 'Data tiket tidak ditemukan.'
      return
    }

    try {
      prioritySaving.value = true
      const { data } = await axios.put(`/feature-requests/${feature.value.id}`, {
        priority: selectedPriority.value
      })
      feature.value = data
      prioritySuccess.value = 'Prioritas tiket berhasil diperbarui.'
    } catch (error) {
      priorityError.value = error?.response?.data?.message ?? 'Gagal memperbarui prioritas.'
    } finally {
      prioritySaving.value = false
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
      developmentStatusError.value = 'Data tiket tidak ditemukan.'
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
      attachmentError.value = 'Lampiran tidak tersedia untuk tiket ini.'
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
      link.download = feature.value.attachment_name || 'lampiran-tiket'
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

  const syncGitlabIssue = async () => {
    gitlabError.value = ''
    gitlabSuccess.value = ''

    if (!isAdmin.value) {
      gitlabError.value = 'Hanya admin yang dapat melakukan sinkronisasi ke GitLab.'
      return
    }

    if (!feature.value) {
      gitlabError.value = 'Data tiket tidak ditemukan.'
      return
    }

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

  const deleteFeature = async () => {
    deleteError.value = ''

    if (!isAdmin.value) {
      deleteError.value = 'Hanya admin yang dapat menghapus tiket.'
      return
    }

    if (!feature.value) {
      deleteError.value = 'Data tiket tidak ditemukan.'
      return
    }

    const confirmed = window.confirm('Yakin ingin menghapus tiket ini? Tindakan tidak dapat dibatalkan.')
    if (!confirmed) {
      return
    }

    try {
      deleteLoading.value = true
      await axios.delete(`/feature-requests/${feature.value.id}`)
      router.push('/feature-request')
    } catch (error) {
      deleteError.value = error?.response?.data?.message ?? 'Gagal menghapus tiket.'
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
    syncGitlabIssue,
    formatGitlabState,
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

  const units = ref([])
  const users = ref([])
  const unitPagination = reactive({
    currentPage: 1,
    perPage: 10,
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
    is_active: true
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
  }

  const startEditUnit = (unit) => {
    unitForm.id = unit.id
    unitForm.name = unit.name
    unitForm.instansi = unit.instansi
    unitForm.is_active = !!unit.is_active
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
      is_active: unitForm.is_active
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

    updateUser(user, { level: normalized }, 'Role pengguna diperbarui.')
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
    toggleUserVerification,
    verifiedBadgeClass,
    changeUnitPage,
    changeUserPage,
    updateUnitPerPage,
    updateUserPerPage
  }
}
