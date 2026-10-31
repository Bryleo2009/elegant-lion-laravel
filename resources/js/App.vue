<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { ArrowRight, ArrowLeft, ArrowUpRight, Check, CheckCircle2, CalendarDays, CalendarOff, Clock3, UserRound, UsersRound, Scissors, ShieldCheck, Menu, X, LogOut, Plus, ChevronLeft, ChevronRight, Ban, ListFilter, Settings2, Phone, MessageCircle, Download, Trash2, Crown, AlertCircle, Pencil, Save, ClipboardCheck, Wifi } from 'lucide-vue-next'
import { normalizePhone, validateBookingContact } from './bookingValidation.js'
import { appointmentDisplayState, sortAppointmentsForDisplay, timeKeyInZone, whatsappContactUrl } from './appointmentPresentation.js'

// URL pública: Vue no debe convertir la imagen en una importación de Vite.
const logoUrl = '/logo.png'
const isAdmin = window.location.pathname.startsWith('/admin')
const user = ref(null)
const loading = ref(false)
const busy = ref(false)
const message = ref('')
const error = ref('')
const setup = ref({ barbers: [], services: [], today: '', days_ahead: 60 })
const step = ref(1)
const barberId = ref(null)
const serviceId = ref(null)
const date = ref('')
const time = ref('')
const times = ref([])
const loadingTimes = ref(false)
const name = ref('')
const phone = ref('')
const contactTouched = ref({ name: false, phone: false })
const contactSubmitted = ref(false)
const bookingId = ref(crypto.randomUUID())
const booked = ref(null)
const auth = ref({ email: '', password: '' })
const activeTab = ref('agenda')
const adminDate = ref('')
const agendaBarberId = ref('')
const appointments = ref([])
const adminNowKey = ref(timeKeyInZone())
const stats = ref({total: 0, confirmed: 0, completed: 0, no_show: 0})
const barbers = ref([])
const services = ref([])
const clients = ref([])
const clientSearch = ref('')
const newClient = ref({name: '', phone: '', notes: ''})
const newBarber = ref({name: '', subtitle: ''})
const newService = ref({name: '', duration_minutes: 30, price: ''})
const scheduleBarber = ref(null)
const rules = ref([])
const scheduleBookingDays = ref(3)
const timeOffs = ref([])
const offDay = ref('')
const offEnd = ref('')
const offReason = ref('')
const installPrompt = ref(null)
const canInstallApp = ref(false)
const isIosDevice = /iphone|ipad|ipod/i.test(window.navigator.userAgent)
const showManual = ref(false)
const manual = ref({barber_id: null, service_id: null, date: '', time: '', client_name: '', phone: '', notes: ''})
const manualSlots = ref([])
const weekNames = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado']
const tabs = [
  {id:'agenda', label:'Agenda', icon:CalendarDays},
  {id:'horarios', label:'Horarios', icon:Clock3},
  {id:'equipo', label:'Barberos', icon:Scissors},
  {id:'clientes', label:'Clientes', icon:UsersRound},
  {id:'servicios', label:'Servicios', icon:Settings2},
]

function statusLabel(s) { return ({confirmed:'Confirmada',completed:'Atendida',cancelled:'Cancelada',no_show:'No asistió'})[s] || s }
function money(cents) { return 'S/ ' + (Number(cents || 0) / 100).toFixed(2) }
function localDateAdd(iso, days) {
  if (!iso) return ''
  const d = new Date(`${iso}T12:00:00Z`)
  d.setUTCDate(d.getUTCDate() + days)
  return d.toISOString().slice(0,10)
}
function dateLabel(d) {
  if (!d) return ''
  const parts = new Date(`${d}T12:00:00Z`).toLocaleDateString('es-PE', { day: 'numeric', month: 'long', timeZone:'UTC' })
  return parts
}
function weekDay(d) { return new Date(`${d}T12:00:00Z`).toLocaleDateString('es-PE', {weekday:'short', timeZone:'UTC'}).replace('.','') }
const selectedBarber = computed(() => setup.value.barbers.find(b => b.id === barberId.value))
const selectedService = computed(() => setup.value.services.find(s => s.id === serviceId.value))
const selectedBookingDays = computed(() => Math.max(1, Number(selectedBarber.value?.booking_days_ahead || setup.value.days_ahead || 3)))
const bookingMaxDate = computed(() => localDateAdd(setup.value.today, selectedBookingDays.value - 1))
const nextDays = computed(() => Array.from({length:selectedBookingDays.value}, (_,i)=>localDateAdd(setup.value.today,i)))
const contactErrors = computed(() => validateBookingContact({ name: name.value, phone: phone.value }))
const visibleContactErrors = computed(() => ({
  name: (contactTouched.value.name || contactSubmitted.value) ? contactErrors.value.name : '',
  phone: (contactTouched.value.phone || contactSubmitted.value) ? contactErrors.value.phone : '',
}))
const canBook = computed(() => barberId.value && serviceId.value && date.value && time.value && !contactErrors.value.name && !contactErrors.value.phone)
const displayedAppointments = computed(() => sortAppointmentsForDisplay(appointments.value, adminNowKey.value))
const manualBookingMaxDate = computed(() => {
  const barber = barbers.value.find(item => item.id === manual.value.barber_id)
  return localDateAdd(setup.value.today, Math.max(1, Number(barber?.booking_days_ahead || 3)) - 1)
})

async function api(path, options = {}) {
  const token = document.querySelector('meta[name="csrf-token"]')?.content || ''
  const response = await fetch(path, {
    credentials: 'same-origin',
    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
    ...options,
    body: options.body ? JSON.stringify(options.body) : undefined,
  })
  let data = {}
  try { data = await response.json() } catch (_) {}
  if (!response.ok) {
    const violations = data.errors ? Object.values(data.errors).flat().join(' ') : null
    const err = new Error(violations || data.message || `Error HTTP ${response.status}`)
    err.status = response.status
    throw err
  }
  return data
}
function notify(msg) { message.value = msg; error.value = ''; setTimeout(() => { if (message.value === msg) message.value = '' }, 5000) }
function fail(e) { error.value = e.message || 'No se pudo procesar la solicitud.'; message.value = '' }
async function initSetup() {
  try {
    setup.value = await api('/api/public/setup')
    barberId.value = setup.value.barbers[0]?.id ?? null
    serviceId.value = setup.value.services[0]?.id ?? null
    date.value = setup.value.today
    adminDate.value = setup.value.today
    manual.value.date = setup.value.today
  } catch(e) { fail(e) }
}
let lastRequest = 0
async function loadTimes() {
  const id = ++lastRequest
  time.value = ''
  times.value = []
  if (!barberId.value || !serviceId.value || !date.value) return
  loadingTimes.value = true
  try {
    const params = new URLSearchParams({barber_id:barberId.value, service_id:serviceId.value, date:date.value})
    const result = await api('/api/public/availability?' + params)
    if (id === lastRequest) times.value = result.slots
  } catch(e) { if (id === lastRequest) fail(e) }
  finally { if (id === lastRequest) loadingTimes.value = false }
}
watch([barberId, serviceId, date], () => {
  if (date.value && bookingMaxDate.value && date.value > bookingMaxDate.value) { date.value = setup.value.today; return }
  bookingId.value = crypto.randomUUID(); booked.value = null; loadTimes()
})
watch([time, name, phone], () => { if (!busy.value) bookingId.value = crypto.randomUUID() })
async function confirmBooking() {
  contactSubmitted.value = true
  if (!canBook.value || busy.value) return
  busy.value = true; error.value = ''
  try {
    const result = await api('/api/bookings', { method:'POST', body: {
      barber_id:barberId.value, service_id:serviceId.value, date:date.value, time:time.value,
      client_name:name.value.trim(), phone:normalizePhone(phone.value), idempotency_key:bookingId.value,
    }})
    booked.value = result.appointment
    step.value = 3
  } catch(e) {
    fail(e)
    if (e.status === 409) { bookingId.value = crypto.randomUUID(); await loadTimes(); step.value = 2 }
  } finally { busy.value = false }
}
function resetBooking() {
  booked.value = null; step.value = 1; name.value = ''; phone.value = ''; time.value = ''
  contactTouched.value = { name: false, phone: false }; contactSubmitted.value = false
  bookingId.value = crypto.randomUUID(); loadTimes()
}
async function checkSession() {
  try { const res = await api('/api/admin/session'); user.value = res.user }
  catch(e) { fail(e) }
  if (user.value) loadAdmin()
}
async function login() {
  busy.value = true; error.value = ''
  try {
    const res = await api('/api/admin/login', {method:'POST', body: auth.value})
    user.value = res.user; auth.value.password = ''; notify('Sesión iniciada'); loadAdmin()
  } catch(e) { fail(e) } finally { busy.value = false }
}
async function logout() {
  try {
    const res = await api('/api/admin/logout', {method:'POST'})
    if (res.csrf_token) document.querySelector('meta[name="csrf-token"]').content = res.csrf_token
    user.value = null; activeTab.value = 'agenda'; notify('Sesión cerrada')
  } catch(e) { fail(e) }
}
async function loadAdmin() {
  if (!user.value) return
  try {
    const [b,s] = await Promise.all([api('/api/admin/barbers'),api('/api/admin/services')])
    barbers.value = b.barbers; services.value = s.services
    if (!barbers.value.some(b => b.id === scheduleBarber.value)) scheduleBarber.value = barbers.value[0]?.id ?? null
    if (!barbers.value.some(b => b.id === manual.value.barber_id && b.active)) manual.value.barber_id = barbers.value.find(b=>b.active)?.id ?? null
    manual.value.service_id ||= services.value.filter(s=>s.active)[0]?.id
    await loadTab()
  } catch(e) { fail(e) }
}
async function loadTab() {
  if (activeTab.value === 'agenda') await loadAgenda()
  if (activeTab.value === 'horarios') await loadSchedules()
  if (activeTab.value === 'clientes') await loadClients()
}
async function switchTab(tab) {
  activeTab.value = tab; error.value = ''; await loadTab()
}
let agendaRequest = 0
async function loadAgenda() {
  if (!adminDate.value) return
  const requestId = ++agendaRequest
  const params = new URLSearchParams({ date: adminDate.value })
  if (agendaBarberId.value) params.set('barber_id', agendaBarberId.value)
  try {
    adminNowKey.value = timeKeyInZone()
    const r = await api('/api/admin/appointments?' + params)
    if (requestId !== agendaRequest) return
    appointments.value = r.appointments; stats.value = r.stats
  } catch(e) { if (requestId === agendaRequest) fail(e) }
}
async function changeStatus(a, status) {
  const text = {completed:'marcar como atendida',no_show:'registrar como inasistencia',cancelled:'cancelar'}[status]
  if (!window.confirm(`¿Seguro que deseas ${text} la reserva de ${a.client?.name}?`)) return
  busy.value = true
  try {
    await api(`/api/admin/appointments/${a.id}/status`, {method:'PATCH', body:{status}})
    notify('Estado actualizado'); await loadAgenda()
  } catch(e) { fail(e) } finally { busy.value = false }
}
async function loadClients() {
  try { clients.value = (await api('/api/admin/clients?search=' + encodeURIComponent(clientSearch.value))).clients }
  catch(e) { fail(e) }
}
async function saveClient() {
  busy.value = true
  try {
    await api('/api/admin/clients', {method:'POST',body:newClient.value})
    newClient.value = {name:'',phone:'',notes:''}; notify('Cliente registrado'); await loadClients()
  } catch(e) { fail(e) } finally { busy.value = false }
}
async function editClient(c) {
  const next = window.prompt('Nombre del cliente', c.name)
  if (!next || next === c.name) return
  try { await api(`/api/admin/clients/${c.id}`, {method:'PATCH',body:{name:next}}); notify('Cliente actualizado'); await loadClients() }
  catch(e) { fail(e) }
}
async function toggleClient(c) {
  if (c.active && !window.confirm(`¿Archivar a ${c.name}? Su historial permanecerá guardado.`)) return
  try { await api(`/api/admin/clients/${c.id}`, {method:'PATCH',body:{active:!c.active}}); notify('Cliente actualizado'); await loadClients() }
  catch(e) { fail(e) }
}
async function saveBarber() {
  busy.value = true
  try {
    await api('/api/admin/barbers', {method:'POST',body:newBarber.value})
    newBarber.value = {name:'',subtitle:''}; notify('Barbero agregado'); await loadAdmin()
  } catch(e) { fail(e) } finally { busy.value = false }
}
async function editBarber(b) {
  const name = window.prompt('Nombre del barbero', b.name)
  if (!name || name === b.name) return
  try { await api(`/api/admin/barbers/${b.id}`, {method:'PATCH',body:{name}}); notify('Nombre actualizado'); await loadAdmin() }
  catch(e) { fail(e) }
}
async function toggleBarber(b) {
  if (b.active && !window.confirm('Al desactivarlo no aparecerá para nuevas reservas. Las citas actuales seguirán registradas. ¿Continuar?')) return
  try { await api(`/api/admin/barbers/${b.id}`, {method:'PATCH',body:{active:!b.active}}); notify('Disponibilidad de barbero actualizada'); await loadAdmin() }
  catch(e) { fail(e) }
}
async function deleteBarber(b) {
  if (busy.value || !window.confirm(`¿Eliminar definitivamente a "${b.name}"? Esta acción no se puede deshacer. Si tiene reservas registradas, no se eliminará.`)) return
  busy.value = true
  try {
    await api(`/api/admin/barbers/${b.id}`, {method:'DELETE'})
    if (String(agendaBarberId.value) === String(b.id)) agendaBarberId.value = ''
    if (Number(scheduleBarber.value) === Number(b.id)) scheduleBarber.value = null
    if (Number(manual.value.barber_id) === Number(b.id)) manual.value.barber_id = null
    setup.value.barbers = setup.value.barbers.filter(barber => barber.id !== b.id)
    await loadAdmin()
    notify('Barbero eliminado correctamente')
  } catch(e) { fail(e) } finally { busy.value = false }
}
async function loadSchedules() {
  if (!scheduleBarber.value) return
  try {
    const r = await api('/api/admin/schedules?barber_id=' + scheduleBarber.value)
    scheduleBookingDays.value = Number(r.barber?.booking_days_ahead || 3)
    rules.value = r.rules.map(x => ({weekday:x.weekday,blocks:(x.blocks || []).map(block => ({opens_at:block.opens_at.slice(0,5),closes_at:block.closes_at.slice(0,5)}))}))
    timeOffs.value = r.time_offs
  } catch(e) { fail(e) }
}
async function saveSchedule() {
  busy.value = true
  try {
    const r = await api('/api/admin/schedules', {method:'PUT',body:{barber_id:scheduleBarber.value,booking_days_ahead:Number(scheduleBookingDays.value),rules:rules.value}})
    notify(r.message); await loadSchedules()
  } catch(e) { fail(e) } finally { busy.value = false }
}
async function blockDay() {
  if (!offDay.value) return
  busy.value = true
  try {
    await api('/api/admin/time-offs', {method:'POST',body:{barber_id:scheduleBarber.value,day:offDay.value,ends_on:offEnd.value || offDay.value,reason:offReason.value}})
    offDay.value = ''; offEnd.value = ''; offReason.value = ''; notify('Rango bloqueado'); await loadSchedules()
  } catch(e) { fail(e) } finally { busy.value = false }
}
async function unblockDay(off) {
  try { await api(`/api/admin/time-offs/${off.id}`,{method:'DELETE'}); notify('Día habilitado'); await loadSchedules() }
  catch(e) { fail(e) }
}
async function saveService() {
  busy.value = true
  try {
    await api('/api/admin/services', {method:'POST',body:{name:newService.value.name,duration_minutes:Number(newService.value.duration_minutes),price_cents:Math.round(Number(newService.value.price || 0)*100),active:true}})
    newService.value = {name:'',duration_minutes:30,price:''}; notify('Servicio creado'); await loadAdmin()
  } catch(e) { fail(e) } finally { busy.value = false }
}
async function updateService(s) {
  busy.value = true
  try {
    await api(`/api/admin/services/${s.id}`, {method:'PATCH',body:{name:s.name,duration_minutes:Number(s.duration_minutes),price_cents:Number(s.price_cents),active:s.active}})
    notify('Servicio actualizado'); await loadAdmin()
  } catch(e) { fail(e) } finally { busy.value = false }
}
async function deleteService(s) {
  if (!window.confirm(`¿Eliminar el servicio “${s.name}”? Esta acción solo será posible si no tiene reservas registradas.`)) return
  busy.value = true
  try { await api(`/api/admin/services/${s.id}`, {method:'DELETE'}); notify('Servicio eliminado'); await loadAdmin() }
  catch(e) { fail(e) } finally { busy.value = false }
}
function addScheduleBlock(rule) {
  if (rule.blocks.length >= 4) return
  const previous = rule.blocks[rule.blocks.length - 1]
  if (!previous) { rule.blocks.push({opens_at:'10:00',closes_at:'19:00'}); return }
  if (previous.closes_at >= '23:30') return
  const [hours, minutes] = previous.closes_at.split(':').map(Number)
  const closingMinutes = Math.min(hours * 60 + minutes + 60, 23 * 60 + 30)
  const closesAt = `${String(Math.floor(closingMinutes / 60)).padStart(2,'0')}:${String(closingMinutes % 60).padStart(2,'0')}`
  rule.blocks.push({opens_at:previous.closes_at,closes_at:closesAt})
}
function removeScheduleBlock(rule, index) { rule.blocks.splice(index, 1) }
async function installApp() {
  if (!installPrompt.value) {
    window.alert('En iPhone o iPad: abre Compartir y elige “Agregar a pantalla de inicio”.')
    return
  }
  await installPrompt.value.prompt()
  await installPrompt.value.userChoice
  installPrompt.value = null; canInstallApp.value = false
}
async function loadManualSlots() {
  manual.value.time = ''; manualSlots.value = []
  if (!manual.value.barber_id || !manual.value.service_id || !manual.value.date) return
  try {
    const p = new URLSearchParams({barber_id:manual.value.barber_id,service_id:manual.value.service_id,date:manual.value.date})
    manualSlots.value = (await api('/api/public/availability?' + p)).slots
  } catch(e) { fail(e) }
}
async function saveManual() {
  busy.value = true
  try {
    await api('/api/admin/appointments', {method:'POST',body:{...manual.value,idempotency_key:bookingId.value}})
    showManual.value = false; bookingId.value = crypto.randomUUID()
    adminDate.value = manual.value.date; notify('Cita registrada'); await loadAgenda()
  } catch(e) {
    fail(e)
    if (e.status === 409) { bookingId.value = crypto.randomUUID(); await loadManualSlots() }
  } finally { busy.value = false }
}
function openManual() {
  manual.value = {barber_id:barbers.value.filter(b=>b.active)[0]?.id,service_id:services.value.filter(s=>s.active)[0]?.id,date:adminDate.value,time:'',client_name:'',phone:'',notes:''}
  bookingId.value = crypto.randomUUID(); showManual.value = true; loadManualSlots()
}
let adminClock = null
onMounted(async () => {
  window.addEventListener('beforeinstallprompt', captureInstallPrompt)
  window.addEventListener('appinstalled', clearInstallPrompt)
  await initSetup()
  if (isAdmin) {
    adminClock = window.setInterval(() => {
      adminNowKey.value = timeKeyInZone()
      if (user.value && activeTab.value === 'agenda') loadAgenda()
    }, 60000)
    await checkSession()
  } else await loadTimes()
})
function captureInstallPrompt(event) { event.preventDefault(); installPrompt.value = event; canInstallApp.value = true }
function clearInstallPrompt() { installPrompt.value = null; canInstallApp.value = false }
onBeforeUnmount(() => {
  if (adminClock) window.clearInterval(adminClock)
  window.removeEventListener('beforeinstallprompt', captureInstallPrompt)
  window.removeEventListener('appinstalled', clearInstallPrompt)
})
</script>

<template>
  <div class="site">
    <header class="site-header">
      <div class="header-inner wrap">
        <a href="/" class="brand" aria-label="Elegant Lion, inicio">
          <img :src="logoUrl" alt="Logo Elegant Lion" class="brand-logo" />
          <span class="brand-title">ELEGANT LION <small>BARBERSHOP</small></span>
        </a>
        <div class="header-actions"><button v-if="!isAdmin && (canInstallApp || isIosDevice)" class="install-button" @click="installApp"><Download :size="15"/> Instalar app</button><a :href="isAdmin ? '/' : '/admin'" class="header-link">
          <template v-if="isAdmin"><ArrowLeft :size="15" /> Reservas</template>
          <template v-else><ShieldCheck :size="15" /> Acceso</template>
        </a></div>
      </div>
    </header>

    <div v-if="error || message" class="notice-bar" :class="{bad:!!error}" role="status">
      <AlertCircle v-if="error" :size="17" /><CheckCircle2 v-else :size="17" />
      <span>{{ error || message }}</span><button @click="error='';message=''" aria-label="Cerrar aviso"><X :size="16" /></button>
    </div>

    <main v-if="!isAdmin" class="booking-portal">
      <aside class="booking-story" aria-label="Resumen de tu reserva">
  <div class="story-inner">
    <div class="story-intro">
      <p class="story-kicker">Reservas online</p>
      <h1>Reserva tu cita</h1>
      <p class="story-description">Elige servicio, barbero y horario. Recibes la confirmación al instante y pagas en el local.</p>
    </div>

    <ul class="story-points">
      <li><Check :size="16" /> Sin crear cuenta</li>
      <li><Check :size="16" /> Horarios actualizados en tiempo real</li>
      <li><Check :size="16" /> Confirmación inmediata</li>
    </ul>

    <section v-if="!booked" class="story-summary" aria-live="polite">
      <h2>Tu reserva</h2>
      <dl>
        <div>
          <dt>Servicio</dt>
          <dd :class="{ pending: !selectedService }">
            {{ selectedService?.name || 'Por elegir' }}
            <small v-if="selectedService">{{ selectedService.duration_minutes }} min</small>
          </dd>
        </div>
        <div>
          <dt>Barbero</dt>
          <dd :class="{ pending: !selectedBarber }">{{ selectedBarber?.name || 'Por elegir' }}</dd>
        </div>
        <div>
          <dt>Fecha y hora</dt>
          <dd :class="{ pending: !time }">{{ time ? `${dateLabel(date)} · ${time}` : 'Por elegir' }}</dd>
        </div>
        <div class="total">
          <dt>Total en el local</dt>
          <dd>{{ money(selectedService?.price_cents) }}</dd>
        </div>
      </dl>
    </section>
  </div>
</aside>

      <section class="booking-workspace" aria-label="Reservar cita">
        <div class="booking-card" v-if="!booked">
          <div class="booking-status">
            <span><i></i> Reservas online</span>
            <strong>Paso {{step}} de 3</strong>
          </div>
          <div class="booking-progress" aria-label="Progreso de reserva"><span :style="{width:(step/3*100)+'%'}"></span></div>
          <ol class="booking-steps" aria-label="Pasos de la reserva">
            <li :class="{active:step===1,done:step>1}"><span>1</span> Servicio</li>
            <li :class="{active:step===2,done:step>2}"><span>2</span> Horario</li>
            <li :class="{active:step===3}"><span>3</span> Confirmación</li>
          </ol>

          <template v-if="step === 1">
            <header class="booking-heading">
              <p>COMENCEMOS</p>
              <h2>¿Qué hacemos por ti?</h2>
              <span>Selecciona un servicio y el profesional que prefieras.</span>
            </header>
            <fieldset class="booking-fieldset">
              <legend>Servicio</legend>
              <div class="service-choice-grid">
                <button v-for="s in setup.services" :key="s.id" type="button" class="service-choice" :class="{selected:serviceId===s.id}" @click="serviceId=s.id" :aria-pressed="serviceId===s.id">
                  <span class="choice-icon"><Scissors :size="18" /></span>
                  <span class="choice-copy"><strong>{{s.name}}</strong><small>{{s.duration_minutes}} min</small></span>
                  <strong class="choice-price">{{money(s.price_cents)}}</strong>
                  <span class="choice-check"><Check :size="15" /></span>
                </button>
              </div>
              <div v-if="!setup.services.length" class="booking-empty">No hay servicios disponibles por el momento.</div>
            </fieldset>
            <fieldset class="booking-fieldset">
              <legend>Profesional</legend>
              <div class="barber-choice-grid">
                <button v-for="b in setup.barbers" :key="b.id" type="button" class="barber-choice" :class="{selected:barberId===b.id}" @click="barberId=b.id" :aria-pressed="barberId===b.id">
                  <span class="barber-avatar">{{b.name.substring(0,1).toUpperCase()}}</span>
                  <span><strong>{{b.name}}</strong><small>{{b.subtitle || 'Barbero profesional'}}</small></span>
                  <span class="choice-check"><Check :size="15" /></span>
                </button>
              </div>
              <div v-if="!setup.barbers.length" class="booking-empty">No hay profesionales disponibles por el momento.</div>
            </fieldset>
            <button class="booking-primary" :disabled="!barberId||!serviceId" @click="step=2">Ver horarios disponibles <ArrowRight :size="19"/></button>
          </template>

          <template v-else-if="step === 2">
            <button class="booking-back" @click="step=1"><ArrowLeft :size="16"/> Cambiar servicio</button>
            <header class="booking-heading compact">
              <p>ELIGE TU MOMENTO</p>
              <h2>¿Cuándo vienes?</h2>
              <span>Los horarios se actualizan en tiempo real.</span>
            </header>
            <fieldset class="booking-fieldset">
              <legend>Fecha</legend>
              <div class="date-choice-row" role="group" aria-label="Fechas disponibles">
                <button v-for="d in nextDays" :key="d" type="button" class="date-choice" :class="{selected:date===d}" :aria-pressed="date===d" @click="date=d">
                  <small>{{weekDay(d)}}</small><strong>{{d.slice(-2)}}</strong><span>{{new Date(`${d}T12:00:00Z`).toLocaleDateString('es-PE',{month:'short',timeZone:'UTC'}).replace('.','')}}</span>
                </button>
              </div>
              <label class="date-picker"><span>¿Buscas otra fecha?</span><input aria-label="Seleccionar otra fecha" type="date" v-model="date" :min="setup.today" :max="bookingMaxDate" /></label>
            </fieldset>
            <fieldset class="booking-fieldset">
              <legend>Hora disponible</legend>
              <div v-if="loadingTimes" class="booking-empty loading"><span></span> Consultando la agenda…</div>
              <div v-else-if="!times.length" class="booking-empty"><CalendarOff :size="21"/><div><strong>No quedan horarios para este día.</strong><small>Elige otra fecha para continuar.</small></div></div>
              <div v-else class="time-choice-grid"><button v-for="t in times" :key="t" type="button" :aria-pressed="time===t" class="time-choice" :class="{selected:time===t}" @click="time=t"><Clock3 :size="15"/> {{t}}</button></div>
            </fieldset>
            <div class="mobile-selection"><span>{{selectedService?.name}} · {{selectedBarber?.name}}</span><strong>{{money(selectedService?.price_cents)}}</strong></div>
            <button class="booking-primary" :disabled="!time" @click="step=3">Revisar y confirmar <ArrowRight :size="19"/></button>
          </template>

          <template v-else-if="step === 3">
            <button class="booking-back" @click="step=2"><ArrowLeft :size="16"/> Cambiar horario</button>
            <header class="booking-heading compact">
              <p>ÚLTIMO PASO</p>
              <h2>Confirma tu reserva</h2>
              <span>Revisa los detalles y dinos cómo podemos identificarte.</span>
            </header>
            <div class="booking-review">
              <div><span><Scissors :size="16"/> Servicio</span><strong>{{selectedService?.name}}</strong></div>
              <div><span><UserRound :size="16"/> Profesional</span><strong>{{selectedBarber?.name}}</strong></div>
              <div><span><CalendarDays :size="16"/> Fecha y hora</span><strong>{{dateLabel(date)}} · {{time}}</strong></div>
              <div class="review-total"><span>Total a pagar en el local</span><strong>{{money(selectedService?.price_cents)}}</strong></div>
            </div>
            <form class="booking-contact" @submit.prevent="confirmBooking" novalidate>
              <div class="contact-grid">
                <label :class="{invalid:visibleContactErrors.name}">
                  <span>Nombre completo</span>
                  <input v-model="name" @blur="contactTouched.name=true" :aria-invalid="!!visibleContactErrors.name" aria-describedby="name-error" required autocomplete="name" maxlength="120" placeholder="Ej. Juan Pérez" />
                  <small id="name-error" class="field-error" aria-live="polite">{{visibleContactErrors.name}}</small>
                </label>
                <label :class="{invalid:visibleContactErrors.phone}">
                  <span>Celular</span>
                  <input v-model="phone" @blur="contactTouched.phone=true" :aria-invalid="!!visibleContactErrors.phone" aria-describedby="phone-help phone-error" required autocomplete="tel" type="tel" inputmode="numeric" maxlength="15" placeholder="987 654 321" />
                  <small v-if="!visibleContactErrors.phone" id="phone-help" class="field-help">9 dígitos, sin código de país.</small>
                  <small id="phone-error" class="field-error" aria-live="polite">{{visibleContactErrors.phone}}</small>
                </label>
              </div>
              <button class="booking-primary" :disabled="busy" type="submit">{{busy ? 'Confirmando tu reserva…' : 'Confirmar reserva'}} <ArrowRight :size="19"/></button>
            </form>
            <p class="booking-reassurance"><ShieldCheck :size="15"/> Tus datos se usan únicamente para gestionar esta cita.</p>
          </template>
        </div>

        <div v-else class="booking-card booking-success" role="status">
          <div class="success-mark"><Check :size="28" /></div>
          <p class="success-kicker">RESERVA CONFIRMADA</p>
          <h2>Listo, {{booked.name.split(' ')[0]}}.</h2>
          <p>Tu cita quedó registrada. Te esperamos en Elegant Lion.</p>
          <div class="booking-review success-review">
            <div><span>Número de reserva</span><strong>#{{booked.id}}</strong></div>
            <div><span>Servicio</span><strong>{{booked.service}}</strong></div>
            <div><span>Profesional</span><strong>{{booked.barber}}</strong></div>
            <div><span>Fecha y hora</span><strong>{{dateLabel(booked.date)}} · {{booked.time}}</strong></div>
          </div>
          <button class="booking-primary" @click="resetBooking">Hacer otra reserva <ArrowRight :size="19"/></button>
        </div>
      </section>
    </main>

    <main v-else class="wrap admin-page">
      <template v-if="!user">
        <section class="login-panel">
          <img :src="logoUrl" alt="Elegant Lion" class="login-logo" />
          <div class="eyebrow small-eyebrow centered">ACCESO PRIVADO</div>
          <h1>Panel de control</h1>
          <p>Tu barbería, en un solo lugar.</p>
          <form @submit.prevent="login" class="login-form">
            <label class="form-label">Correo electrónico<input v-model="auth.email" type="email" required autocomplete="username" placeholder="admin@barberia.com" /></label>
            <label class="form-label">Contraseña<input v-model="auth.password" type="password" required autocomplete="current-password" placeholder="Tu contraseña" /></label>
            <button class="btn-main" :disabled="busy" type="submit">{{busy ? 'Ingresando…' : 'Ingresar al panel'}}<ArrowRight :size="18"/></button>
          </form>
          <small class="login-foot">Acceso exclusivo para personal autorizado.</small>
        </section>
      </template>
      <template v-else>
        <div class="admin-heading"><div><div class="eyebrow small-eyebrow">ELEGANT LION / BACK OFFICE</div><h1>Buen día, {{user.name.split(' ')[0]}}<span class="gold-dot">.</span></h1></div><button class="logout" @click="logout"><LogOut :size="18"/><span>Salir</span></button></div>
        <div class="admin-shell">
          <nav class="admin-nav" aria-label="Secciones de administración">
            <span class="admin-nav-label">Navegación</span>
            <button v-for="tab in tabs" :key="tab.id" :class="{active:activeTab===tab.id}" @click="switchTab(tab.id)"><component :is="tab.icon" :size="18"/><span>{{tab.label}}</span></button>
          </nav>
          <section class="admin-content">
            <template v-if="activeTab==='agenda'">
              <div class="panel-head"><div><span class="overline">GESTIÓN DE CITAS</span><h2>Agenda</h2></div><button class="gold-button" @click="openManual"><Plus :size="17"/> Nueva cita</button></div>
              <div class="agenda-toolbar">
                <div class="date-toolbar"><button class="icon-button" @click="adminDate=localDateAdd(adminDate,-1);loadAgenda()" aria-label="Día anterior"><ChevronLeft :size="20"/></button><input aria-label="Fecha de agenda" type="date" v-model="adminDate" @change="loadAgenda"/><button class="icon-button" @click="adminDate=localDateAdd(adminDate,1);loadAgenda()" aria-label="Día siguiente"><ChevronRight :size="20"/></button><button class="today-button" @click="adminDate=setup.today;loadAgenda()">Hoy</button></div>
                <label class="form-label agenda-barber-filter"><span>Barbero</span><select v-model="agendaBarberId" @change="loadAgenda" aria-label="Filtrar agenda por barbero"><option value="">Todos los barberos</option><option v-for="barber in barbers" :key="barber.id" :value="String(barber.id)">{{barber.name}}</option></select></label>
              </div>
              <div class="metric-grid"><div class="metric"><small>Reservas</small><strong>{{stats.total}}</strong><span>del día</span></div><div class="metric"><small>Por atender</small><strong>{{stats.confirmed}}</strong><span>confirmadas</span></div><div class="metric"><small>Atendidas</small><strong>{{stats.completed}}</strong><span>completadas</span></div><div class="metric"><small>No asistieron</small><strong>{{stats.no_show}}</strong><span>ausencias</span></div></div>
              <div class="list-header"><h3>Reservas del día</h3><span>{{dateLabel(adminDate)}}</span></div>
              <div v-if="!appointments.length" class="admin-empty"><CalendarDays :size="30"/> {{agendaBarberId ? 'Este barbero no tiene reservas para esta fecha.' : 'Aún no hay reservas para esta fecha.'}}</div>
              <div v-else class="appointment-list">
                <template v-for="(a,index) in displayedAppointments" :key="a.id">
                  <div v-if="appointmentDisplayState(a,adminNowKey)!=='upcoming' && (index===0 || appointmentDisplayState(displayedAppointments[index-1],adminNowKey)==='upcoming')" class="appointment-history-divider"><span>Historial del día</span><small>Finalizadas o con hora vencida</small></div>
                  <article class="appointment-card" :class="[`is-${appointmentDisplayState(a,adminNowKey)}`,{'is-muted':appointmentDisplayState(a,adminNowKey)!=='upcoming'}]">
                    <div class="appt-time"><strong>{{a.time || a.starts_at?.slice(11,16)}}</strong><small>{{a.service?.duration_minutes}} MIN</small></div>
                    <div class="appt-main"><div class="appt-top"><strong>{{a.client?.name}}</strong><span v-if="appointmentDisplayState(a,adminNowKey)==='past'" class="status-pill past">Hora pasada</span><span v-else class="status-pill" :class="a.status">{{a.status==='completed' && a.auto_completed ? 'Atendida (automática)' : statusLabel(a.status)}}</span></div><p>{{a.service?.name}} · {{a.barber?.name}}</p><div class="appointment-contact"><a :href="'tel:'+a.client?.phone" class="phone-link"><Phone :size="12"/> {{a.client?.phone}}</a><a :href="whatsappContactUrl(a.client?.phone,a.client?.name)" target="_blank" rel="noopener noreferrer" class="whatsapp-link" aria-label="Contactar por WhatsApp"><MessageCircle :size="14"/> WhatsApp</a></div><div v-if="a.status==='confirmed' || (a.status==='completed' && a.auto_completed)" class="appt-actions"><button v-if="a.status==='confirmed'" @click="changeStatus(a,'completed')" :disabled="busy"><CheckCircle2 :size="14"/> Atendió</button><button @click="changeStatus(a,'no_show')" :disabled="busy"><Ban :size="14"/> {{a.status==='completed' ? 'Corregir: no asistió' : 'No asistió'}}</button><button v-if="a.status==='confirmed'" @click="changeStatus(a,'cancelled')" :disabled="busy" class="danger-text">Cancelar</button></div></div>
                  </article>
                </template>
              </div>
            </template>

            <template v-else-if="activeTab==='horarios'">
              <div class="panel-head"><div><span class="overline">DISPONIBILIDAD</span><h2>Horarios</h2></div></div>
              <p class="section-description">Organiza los turnos de cada barbero y bloquea días sin atención.</p>
              <div class="schedule-settings"><label class="form-label select-barber">Barbero<select v-model="scheduleBarber" @change="loadSchedules"><option v-for="b in barbers" :key="b.id" :value="b.id">{{b.name}}</option></select></label><label class="form-label booking-window">Días disponibles para reservar<input type="number" v-model.number="scheduleBookingDays" min="1" max="90"/><small>Incluye el día de hoy.</small></label></div>
              <h3 class="subheading">Horario semanal</h3>
              <div class="rules schedule-rules"><section v-for="r in rules" :key="r.weekday" class="schedule-day"><div class="schedule-day-head"><strong>{{weekNames[r.weekday]}}</strong><button class="text-action" @click="addScheduleBlock(r)" :disabled="r.blocks.length>=4"><Plus :size="14"/> Agregar bloque</button></div><div v-if="!r.blocks.length" class="closed-day">Sin atención</div><div v-for="(block,index) in r.blocks" :key="index" class="schedule-block"><span class="block-number">{{index+1}}</span><input type="time" v-model="block.opens_at" step="1800" :aria-label="`Inicio ${weekNames[r.weekday]} bloque ${index+1}`"/><span>a</span><input type="time" v-model="block.closes_at" step="1800" :aria-label="`Fin ${weekNames[r.weekday]} bloque ${index+1}`"/><button class="icon-button block-remove" @click="removeScheduleBlock(r,index)" aria-label="Eliminar bloque"><X :size="16"/></button></div></section></div>
              <p class="field-hint">Los cambios no cancelan las reservas que ya fueron confirmadas.</p>
              <button class="gold-button save-button" @click="saveSchedule" :disabled="busy||!rules.length"><Save :size="17"/> Guardar horarios</button>
              <div class="divider"></div>
              <h3 class="subheading">Días bloqueados</h3><p class="section-description">Vacaciones, feriados o ausencias. El rango incluye ambas fechas y solo afecta al barbero seleccionado.</p>
              <form class="inline-form time-off-form" @submit.prevent="blockDay"><label class="form-label">Desde<input type="date" v-model="offDay" :min="setup.today" @change="!offEnd||offEnd<offDay ? offEnd=offDay : null" required/></label><label class="form-label">Hasta<input type="date" v-model="offEnd" :min="offDay||setup.today" required/></label><label class="form-label">Motivo (opcional)<input v-model="offReason" placeholder="Descanso, viaje…"/></label><button class="secondary-button" type="submit" :disabled="busy"><CalendarOff :size="16"/> Bloquear rango</button></form>
              <div class="simple-list"><div v-for="off in timeOffs" :key="off.id" class="simple-row time-off-row"><span><CalendarOff :size="17"/><span><strong>{{dateLabel(off.day.slice(0,10))}}<template v-if="off.ends_on && off.ends_on.slice(0,10)!==off.day.slice(0,10)"> — {{dateLabel(off.ends_on.slice(0,10))}}</template></strong><small>{{off.reason || 'Sin atención'}}</small></span></span><button @click="unblockDay(off)" class="text-action">Habilitar</button></div><div v-if="!timeOffs.length" class="quiet-empty">No hay rangos bloqueados para este barbero.</div></div>
            </template>

            <template v-else-if="activeTab==='equipo'">
              <div class="panel-head"><div><span class="overline">PERSONAL</span><h2>Barberos</h2></div><span class="counter">{{barbers.length}} en total</span></div>
              <div class="simple-list"><div v-for="b in barbers" :key="b.id" class="simple-row staff-row"><span class="staff-info"><span class="staff-avatar">{{b.name[0]}}</span><span><strong>{{b.name}}</strong><small>{{b.subtitle || 'Barbero'}}</small></span></span><span class="row-controls"><button class="icon-button" @click="editBarber(b)" aria-label="Editar nombre"><Pencil :size="16"/></button><button class="text-action" :class="{'danger-text':b.active}" @click="toggleBarber(b)" :disabled="busy">{{b.active?'Desactivar':'Activar'}}</button><button class="icon-button delete-button" type="button" @click="deleteBarber(b)" :disabled="busy" :aria-label="'Eliminar a '+b.name" :title="'Eliminar a '+b.name"><Trash2 :size="16"/></button></span></div></div>
              <div class="divider"></div><h3 class="subheading">Agregar barbero</h3>
              <form class="inline-form" @submit.prevent="saveBarber"><label class="form-label">Nombre<input v-model="newBarber.name" required minlength="2" placeholder="Nombre completo"/></label><label class="form-label">Descripción<input v-model="newBarber.subtitle" placeholder="Especialista en degradados"/></label><button class="secondary-button" type="submit" :disabled="busy"><Plus :size="16"/> Agregar</button></form>
              <p class="field-hint">Puedes eliminar definitivamente a un barbero sin reservas registradas. Si ya tiene citas en su historial, desactívalo para conservarlas.</p>
            </template>

            <template v-else-if="activeTab==='clientes'">
              <div class="panel-head"><div><span class="overline">DIRECTORIO</span><h2>Clientes</h2></div><span class="counter">{{clients.length}} visibles</span></div>
              <form class="search-row" @submit.prevent="loadClients"><input v-model="clientSearch" placeholder="Buscar nombre o celular…"/><button type="submit" class="secondary-button">Buscar</button></form>
              <div class="client-directory"><article v-for="c in clients" :key="c.id" class="client-card" :class="{archived:!c.active}"><div class="client-avatar">{{c.name.substring(0,1).toUpperCase()}}</div><div class="client-data"><div><strong>{{c.name}}</strong><span v-if="!c.active" class="archived-badge">Archivado</span></div><a :href="'tel:'+c.phone"><Phone :size="13"/> {{c.phone}}</a><small>{{c.visits}} {{c.visits===1?'visita completada':'visitas completadas'}}</small><p v-if="c.notes">{{c.notes}}</p></div><div class="client-actions"><a :href="whatsappContactUrl(c.phone,c.name)" target="_blank" rel="noopener noreferrer" class="whatsapp-link"><MessageCircle :size="15"/> WhatsApp</a><button class="secondary-button" @click="editClient(c)"><Pencil :size="15"/> Editar</button><button class="text-action" @click="toggleClient(c)">{{c.active?'Archivar':'Reactivar'}}</button></div></article><div v-if="!clients.length" class="quiet-empty">No hay clientes registrados.</div></div>
              <div class="divider"></div><h3 class="subheading">Registrar cliente</h3><form class="inline-form" @submit.prevent="saveClient"><label class="form-label">Nombre<input v-model="newClient.name" required minlength="3" placeholder="Nombre y apellido"/></label><label class="form-label">Celular<input v-model="newClient.phone" required inputmode="numeric" placeholder="987654321"/></label><button class="secondary-button" type="submit" :disabled="busy"><Plus :size="16"/> Registrar</button></form><p class="field-hint">Las inasistencias se registran en Agenda. Archivar un cliente no elimina su historial.</p>
            </template>

            <template v-else-if="activeTab==='servicios'">
              <div class="panel-head"><div><span class="overline">TU CARTA</span><h2>Servicios</h2></div></div>
              <div class="service-admin-list"><div v-for="s in services" :key="s.id" class="service-edit"><label class="form-label">Servicio<input v-model="s.name" /></label><label class="form-label">Minutos<input type="number" v-model.number="s.duration_minutes" min="15" max="180" step="15"/></label><label class="form-label">Precio (S/)<input type="number" min="0" step="0.01" :value="(Number(s.price_cents)/100).toFixed(2)" @change="s.price_cents=Math.round(Number($event.target.value||0)*100)"/></label><label class="service-toggle"><input type="checkbox" v-model="s.active"/> Visible</label><div class="service-actions"><button class="icon-button" @click="updateService(s)" aria-label="Guardar servicio"><Save :size="17"/></button><button class="icon-button delete-button" @click="deleteService(s)" aria-label="Eliminar servicio"><Trash2 :size="17"/></button></div></div></div>
              <div class="divider"></div><h3 class="subheading">Nuevo servicio</h3><form class="inline-form" @submit.prevent="saveService"><label class="form-label">Nombre<input v-model="newService.name" required placeholder="Corte premium"/></label><label class="form-label">Minutos<input type="number" v-model.number="newService.duration_minutes" required min="15" max="180" step="15"/></label><label class="form-label">Precio (S/)<input type="number" v-model="newService.price" required min="0" step="0.01" placeholder="35.00"/></label><button class="secondary-button" type="submit" :disabled="busy"><Plus :size="16"/> Crear</button></form><p class="field-hint">Los servicios se muestran al cliente con el precio en soles y la duración estimada.</p>
            </template>
          </section>
        </div>
      </template>
    </main>
    <footer class="footer wrap">
      <span class="footer-copy">© {{new Date().getFullYear()}} Elegant Lion Barbershop</span>
      <div class="footer-branding">
        <span class="footer-motto">ESTILO · PRECISIÓN · ELEGANCIA</span>
        <a class="footer-developer" href="https://www.ofsystem.com.pe/" target="_blank" rel="noopener noreferrer" aria-label="OfSystem, desarrollador de Elegant Lion">
          <span>DESARROLLADO POR</span>
          <img :src="'/ofsystem-logo-blanco.png'" alt="OfSystem" width="118" height="35" loading="lazy" decoding="async"/>
        </a>
      </div>
    </footer>
    <div v-if="showManual" class="modal-backdrop" @click.self="showManual=false" role="presentation"><section class="modal" role="dialog" aria-modal="true" aria-labelledby="manual-title"><div class="modal-head"><h2 id="manual-title">Nueva reserva manual</h2><button class="icon-button" @click="showManual=false" aria-label="Cerrar"><X :size="19"/></button></div><form @submit.prevent="saveManual" class="manual-form"><label class="form-label">Barbero<select v-model="manual.barber_id" @change="loadManualSlots"><option v-for="b in barbers.filter(b=>b.active)" :key="b.id" :value="b.id">{{b.name}}</option></select></label><label class="form-label">Servicio<select v-model="manual.service_id" @change="loadManualSlots"><option v-for="s in services.filter(s=>s.active)" :key="s.id" :value="s.id">{{s.name}}</option></select></label><label class="form-label">Fecha<input type="date" :min="setup.today" :max="manualBookingMaxDate" v-model="manual.date" @change="loadManualSlots" required/></label><label class="form-label">Hora<select v-model="manual.time" required><option disabled value="">Selecciona horario</option><option v-for="t in manualSlots" :key="t">{{t}}</option></select></label><label class="form-label">Nombre del cliente<input v-model="manual.client_name" minlength="3" required placeholder="Nombre completo"/></label><label class="form-label">Celular<input v-model="manual.phone" required inputmode="numeric" placeholder="987654321"/></label><label class="form-label full">Notas (opcional)<input v-model="manual.notes" maxlength="1000" placeholder="Observaciones internas"/></label><button class="btn-main full" :disabled="busy||!manual.time">{{busy?'Guardando…':'Crear cita'}} <ArrowRight :size="18"/></button></form></section></div>
  </div>
</template>
