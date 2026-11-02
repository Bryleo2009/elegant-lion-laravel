import test from 'node:test'
import assert from 'node:assert/strict'
import { normalizePhone, validateBookingContact } from '../../resources/js/bookingValidation.js'

test('normalizePhone removes formatting without changing digits', () => {
  assert.equal(normalizePhone('987 654-321'), '987654321')
})

test('validateBookingContact requires a complete name', () => {
  assert.deepEqual(validateBookingContact({ name: ' Jo ', phone: '987654321' }), {
    name: 'Ingresa tu nombre completo (mínimo 3 caracteres).',
    phone: '',
  })
})

test('validateBookingContact rejects names without letters', () => {
  assert.equal(validateBookingContact({ name: '12345', phone: '987654321' }).name, 'Usa letras para ingresar tu nombre.')
})

test('validateBookingContact explains the peruvian mobile format', () => {
  assert.deepEqual(validateBookingContact({ name: 'Juan Pérez', phone: '123 456' }), {
    name: '',
    phone: 'Ingresa un celular peruano de 9 dígitos que empiece con 9.',
  })
})

test('validateBookingContact accepts accents and a formatted mobile number', () => {
  assert.deepEqual(validateBookingContact({ name: 'Álvaro Núñez', phone: '987 654 321' }), { name: '', phone: '' })
})

test('validateBookingContact does not request a name for a returning customer', () => {
  assert.deepEqual(validateBookingContact({ name: '', phone: '987654321', existingClient: true }), { name: '', phone: '' })
})
