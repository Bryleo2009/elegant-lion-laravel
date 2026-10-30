const terminalStatuses = new Set(['completed', 'cancelled', 'no_show'])

function appointmentTimeKey(appointment) {
  const day = appointment.day || String(appointment.starts_at || '').slice(0, 10)
  const time = appointment.time || String(appointment.starts_at || '').slice(11, 16)
  return `${day} ${time}`
}

export function timeKeyInZone(date = new Date(), timeZone = 'America/Lima') {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  }).formatToParts(date)
  const values = Object.fromEntries(parts.map(part => [part.type, part.value]))
  return `${values.year}-${values.month}-${values.day} ${values.hour}:${values.minute}`
}

export function appointmentDisplayState(appointment, nowKey) {
  if (terminalStatuses.has(appointment.status)) return 'terminal'
  return appointmentTimeKey(appointment) < nowKey ? 'past' : 'upcoming'
}

export function sortAppointmentsForDisplay(appointments, nowKey) {
  const priority = { upcoming: 0, past: 1, terminal: 2 }
  return [...appointments].sort((left, right) => {
    const leftState = appointmentDisplayState(left, nowKey)
    const rightState = appointmentDisplayState(right, nowKey)
    const stateDifference = priority[leftState] - priority[rightState]
    if (stateDifference) return stateDifference

    const timeDifference = appointmentTimeKey(left).localeCompare(appointmentTimeKey(right))
    return leftState === 'upcoming' ? timeDifference : -timeDifference
  })
}

export function whatsappContactUrl(phone, clientName = '') {
  const digits = String(phone || '').replace(/\D/g, '')
  const destination = digits.length === 9 ? `51${digits}` : digits
  const greeting = String(clientName || '').trim() ? `Hola ${String(clientName).trim()}` : 'Hola'
  const message = `${greeting}, te contactamos de Elegant Lion por tu reserva.`
  return `https://wa.me/${destination}?text=${encodeURIComponent(message)}`
}
