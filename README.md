# Elegant Lion Barbershop · Reservas

Aplicación web mobile-first para reservas de barbería. Laravel 13 (PHP 8.3+), Vue 3 / Vite y MySQL 8+ con InnoDB.

> **Estado de entrega:** código fuente preparado para instalación. El proyecto no está desplegado ni conectado a una base de datos real. No incluye una cuenta administradora predefinida ni contraseñas públicas. Las tarifas y barberos del seeder son **ejemplos editables**, no datos oficiales.

## Instalación local

Requisitos: PHP 8.3+, Composer, Node.js 20+, MySQL 8+ (motor InnoDB), npm y extensiones PHP habituales de Laravel (`pdo_mysql`, `mbstring`, `openssl`, `xml`, etc.).

```bash
# Descomprimir este proyecto y entrar a su carpeta.
composer install
cp .env.example .env
# Configurar DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD y APP_URL en .env
php artisan key:generate
php artisan migrate --seed
php artisan admin:create admin@tudominio.com
npm install
npm run build
php artisan serve
```

- Cliente: `http://localhost:8000/`
- Administrador: `http://localhost:8000/admin`

`admin:create` pide una contraseña de mínimo 12 caracteres de manera interactiva. No se publica ningún usuario/contraseña por defecto.

## Funciones

**Clientes**
- Elegir servicio, barbero, día y horario que el servidor confirma libre.
- Identificarse con nombre y celular peruano de 9 dígitos.
- Confirmación inmediata y número de reserva, sin crear cuenta.
- Interfaz adaptable a móviles.

**Administrador** (sesión Laravel y autorización `is_admin`)
- Iniciar/cerrar sesión, ver agenda por día y crear citas manualmente.
- Marcar citas como completadas, canceladas o `no_show` (inasistencias).
- Registrar y archivar clientes sin borrar su historial.
- Crear/desactivar barberos, administrar servicios y precios.
- Configurar horarios semanales por barbero y bloquear/desbloquear fechas.
- Configurar varios bloques por día (por ejemplo, mañana y tarde) y el horizonte de reserva de cada barbero.
- Bloquear rangos completos de vacaciones o ausencias.
- Eliminar servicios sin historial; los servicios usados se desactivan para conservar las reservas.
- Los días bloqueados no pueden tener reservas confirmadas previamente; se devuelve HTTP 409.

**Reglas actuales**
- Cada barbero define cuántos días calendario se pueden reservar, incluyendo hoy (3 por defecto), con mínimo 60 minutos de anticipación.
- Inicio de citas cada 60 minutos (en horas exactas); duración de servicio múltiplo de 15 minutos. La duración real del servicio y los intervalos de ocupación de 15 minutos no cambian.
- Horarios del negocio por barbero, 0 = domingo a 6 = sábado.
- Horario de MySQL y aplicación en `America/Lima`; mantener coherencia de zona horaria.
- Las modificaciones del horario no cancelan una cita existente. Se debe gestionar expresamente.
- Desactivar un barbero o servicio afecta reservas nuevas, conserva historial.
- La reserva pública es instalable como aplicación móvil (PWA) desde navegadores compatibles.
- No-show/completada solo si la hora de inicio ya pasó; no se pueden marcar citas futuras.

## Anti doble reserva: 3 capas

1. **Disponibilidad de lectura:** `GET /api/public/availability` presenta los horarios disponibles de manera informativa.
2. **Revalidación con bloqueo:** `BookingService::book()` usa `DB::transaction(...)` y `SELECT ... FOR UPDATE` sobre el barbero antes de validar agenda, horarios, bloqueos y crear la reserva. Serializa las confirmaciones que compiten por el mismo barbero.
3. **Constraint único MySQL:** `appointment_slots` tiene `UNIQUE(barber_id, day, slot_time)`. Se insertan todos los intervalos de 15 minutos ocupados para cubrir servicios de 30/45/60 minutos. Si otra operación intenta ocupar un segmento, la base rechaza el conflicto y se devuelve **HTTP 409**.

Cada solicitud incluye un UUID `idempotency_key`, único en `appointments`: volver a enviar la **misma** solicitud no genera otra cita. Si la clave se reutiliza con otros datos, se rechaza con HTTP 409. Cancelar o marcar como no asistido libera los slots, pero mantiene el registro de la cita y un evento auditable.

**Importante:** el candado transaccional requiere un motor que lo respete (InnoDB en MySQL). No sustituir por tablas MyISAM. No basta con deshabilitar el botón en Vue.

## Seguridad

- Administración protegida por sesión (`auth`) y middleware `admin` de Laravel.
- Protección CSRF incluida en todas las acciones de escritura. Las APIs usan cookies **same-origin**.
- Se limita la tasa de intentos de acceso, confirmaciones y consultas.
- Datos de clientes disponibles solo en endpoints de administrador.
- No hay panel de registro público de administradores.
- En producción, `APP_DEBUG=false`, `APP_ENV=production`, HTTPS, copias de seguridad, usuario SQL con privilegios mínimos y permisos correctos para `storage` y `bootstrap/cache`.

## Pruebas

```bash
php artisan test
php artisan route:list
npm run build
```

Las pruebas en `tests/Feature/BookingTest.php` cubren idempotencia, rechazo de horarios duplicados, solapamiento de servicios, protección UNIQUE, cancelación, no-show, bloqueo de días y autorización. **No sustituyen una prueba de carga con requests paralelos contra un MySQL real**. Ejecutarla antes de producción (p. ej. con 2 conexiones MySQL disparando requests concurrentes a la misma franja).

## Instalación servidor (Nginx + PHP-FPM)

- `root` del virtualhost debe ser `<proyecto>/public`, nunca el directorio raíz del proyecto.
- Apuntar `fastcgi_pass` al pool PHP correspondiente.
- Instalar dependencias con `composer install --no-dev --optimize-autoloader`; `npm ci` solamente si generas lockfile en tu entorno, si no `npm install && npm run build`.
- Ejecutar `php artisan migrate --force` en despliegue y `php artisan config:cache`.
- **No usar `migrate:fresh` ni `--seed` en una base con reservas reales.** El seeder es para datos iniciales.
- Fijar claves, dominio, `SESSION_SECURE_COOKIE=true` y realizar backups automáticos de MySQL.

## Estructura

```text
app/Services/BookingService.php                Reglas, bloqueos e idempotencia
app/Http/Controllers/PublicBookingController.php Reservas de clientes
app/Http/Controllers/AdminController.php        Gestión de la barbería
app/Http/Middleware/EnsureAdmin.php             Acceso privado
app/Models/                                     Modelos Eloquent
routes/web.php                                  Rutas vistas/API con sesión y CSRF
database/migrations/                            Modelo MySQL e índices únicos
database/seeders/DatabaseSeeder.php             Datos de demostración
resources/js/App.vue                            App Vue 3 responsive
resources/css/app.css                           Tema oscuro
public/logo.png                                 Logo compartido por el cliente
tests/Feature/BookingTest.php                   Tests de negocio
```
