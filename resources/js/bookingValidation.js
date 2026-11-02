export function normalizePhone(value = '') {
  return String(value).replace(/\D/g, '')
}

export function validateBookingContact({ name = '', phone = '', existingClient = false } = {}) {
  const cleanName = String(name).trim()
  const cleanPhone = normalizePhone(phone)

  let nameError = ''
  if (!existingClient && cleanName.length < 3) {
    nameError = 'Ingresa tu nombre completo (mínimo 3 caracteres).'
  } else if (!existingClient && !/\p{L}/u.test(cleanName)) {
    nameError = 'Usa letras para ingresar tu nombre.'
  }

  return {
    name: nameError,
    phone: /^9\d{8}$/.test(cleanPhone) ? '' : 'Ingresa un celular peruano de 9 dígitos que empiece con 9.',
  }
}
