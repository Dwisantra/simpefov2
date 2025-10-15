export const ROLE = Object.freeze({
  USER: 1,
  MANAGER: 2,
  DIRECTOR_A: 3,
  DIRECTOR_B: 4,
  ADMIN: 5
})

export const ROLE_LABELS = Object.freeze({
  [ROLE.USER]: 'Pemohon',
  [ROLE.MANAGER]: 'Manager',
  [ROLE.DIRECTOR_A]: 'Direktur RS Raffa Majenang',
  [ROLE.DIRECTOR_B]: 'Direktur RS Wiradadi Husada',
  [ROLE.ADMIN]: 'Administrator'
})

export const ROLE_OPTIONS = Object.freeze([
  { value: ROLE.USER, label: ROLE_LABELS[ROLE.USER] },
  { value: ROLE.MANAGER, label: ROLE_LABELS[ROLE.MANAGER] },
  { value: ROLE.DIRECTOR_A, label: ROLE_LABELS[ROLE.DIRECTOR_A] },
  { value: ROLE.DIRECTOR_B, label: ROLE_LABELS[ROLE.DIRECTOR_B] },
  { value: ROLE.ADMIN, label: ROLE_LABELS[ROLE.ADMIN] }
])

export const isRole = (value, target) => Number(value) === target
