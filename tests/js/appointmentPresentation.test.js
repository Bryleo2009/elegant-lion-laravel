import test from 'node:test'
import assert from 'node:assert/strict'
import { appointmentDisplayState, sortAppointmentsForDisplay, timeKeyInZone, whatsappContactUrl } from '../../resources/js/appointmentPresentation.js'

const nowKey = '2026-10-29 19:00'

test('timeKeyInZone creates a sortable local key for the business timezone', () => {
  assert.equal(timeKeyInZone(new Date('2026-10-30T00:15:00Z'), 'America/Lima'), '2026-10-29 19:15')
})

test('appointmentDisplayState marks an upcoming confirmed appointment as active', () => {
  assert.equal(appointmentDisplayState({ status: 'confirmed', day: '2026-10-29', time: '20:00' }, nowKey), 'upcoming')
})

test('appointmentDisplayState marks a confirmed appointment whose time passed as past', () => {
  assert.equal(appointmentDisplayState({ status: 'confirmed', day: '2026-10-29', time: '18:30' }, nowKey), 'past')
})

test('appointmentDisplayState treats closed statuses as terminal regardless of time', () => {
  for (const status of ['completed', 'cancelled', 'no_show']) {
    assert.equal(appointmentDisplayState({ status, day: '2026-10-29', time: '20:30' }, nowKey), 'terminal')
  }
})

test('sortAppointmentsForDisplay puts upcoming first, elapsed confirmations second and closed appointments last', () => {
  const appointments = [
    { id: 1, status: 'cancelled', day: '2026-10-29', time: '20:30' },
    { id: 2, status: 'confirmed', day: '2026-10-29', time: '18:30' },
    { id: 3, status: 'completed', day: '2026-10-29', time: '17:30' },
    { id: 4, status: 'confirmed', day: '2026-10-29', time: '21:00' },
    { id: 5, status: 'confirmed', day: '2026-10-29', time: '20:00' },
  ]

  assert.deepEqual(sortAppointmentsForDisplay(appointments, nowKey).map(item => item.id), [5, 4, 2, 1, 3])
})

test('sortAppointmentsForDisplay does not mutate the API response array', () => {
  const appointments = [
    { id: 2, status: 'confirmed', day: '2026-10-29', time: '20:30' },
    { id: 1, status: 'confirmed', day: '2026-10-29', time: '20:00' },
  ]

  sortAppointmentsForDisplay(appointments, nowKey)
  assert.deepEqual(appointments.map(item => item.id), [2, 1])
})

test('whatsappContactUrl opens a peruvian mobile number with a booking message', () => {
  assert.equal(
    whatsappContactUrl('999 999 999', 'Alencasder'),
    'https://wa.me/51999999999?text=Hola%20Alencasder%2C%20te%20contactamos%20de%20Elegant%20Lion%20por%20tu%20reserva.',
  )
})
