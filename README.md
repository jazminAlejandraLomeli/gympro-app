# 🏋️‍♂️ GymPro App

<p align="center">
  <b>Plataforma SaaS Multi-Tenant White-Label para la gestión integral de gimnasios y experiencia digital de socios.</b>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-8.2%2B-red?style=flat-square&logo=laravel" alt="Laravel">
  <img src="https://img.shields.io/badge/Tailwind_CSS-Responsive-blue?style=flat-square&logo=tailwindcss" alt="Tailwind CSS">
  <img src="https://img.shields.io/badge/Redis-Queues%20%26%20Cache-red?style=flat-square&logo=redis" alt="Redis">
  <img src="https://img.shields.io/badge/License-Proprietary-yellow?style=flat-square" alt="License">
</p>

---

Plataforma web integral ("Todo en Uno") diseñada bajo una arquitectura **SaaS Multi-Tenant de Marca Blanca (White-Label)** para optimizar la operación de los gimnasios y potenciar la experiencia digital personalizada de sus socios.

Cada gimnasio opera bajo su propia identidad visual (logotipo, colores corporativos y nombre propio), compartiendo una infraestructura centralizada de alto rendimiento optimizada para soportar cientos de inquilinos (*tenants*) concurrentes **sin necesidad de descargar aplicaciones nativas**.

---

## ✨ Características Principales

### 🎨 1. Arquitectura Multi-Tenant 100% Marca Blanca (White-Label)
* **Identidad Dinámica:** Cada gimnasio personaliza su logotipo, nombre comercial y paleta de colores (`--color-primary`, `--color-secondary`) desde su panel de configuración (`gym_settings`).
* **Aislamiento Estricto de Datos:** Base de datos compartida con aislamiento lógico garantizado mediante `gym_id`, índices compuestos y Global Scopes automáticos.

### 👑 2. Panel Ejecutivo de Super Administrador (`super_admin`)
* **Gestión de Inquilinos (Tenants):** Alta rápida de nuevos gimnasios, activación/suspensión de licencias y control de programa de referidos.
* **Control Financiero y Ganancias:** Cálculo automático de ingresos mensuales recurrentes (MRR), historial de suscripciones y alertas de vencimiento.
* **Salud del Sistema y Logs:** Monitoreo en tiempo real de servicios (Redis, Base de Datos, Colas de trabajo), almacenamiento multimedia y visor integrado de errores (`failed_jobs` y excepciones).

### 📲 3. Control de Acceso QR y Membresías (Sin Pasarelas Externas)
* **Credencial Digital en Móvil:** Código QR único por socio accesible desde cualquier navegador (*Mobile-First*).
* **Escáner Web en Recepción:** Lectura instantánea mediante cámara (`html5-qrcode`) con validación en microsegundos respaldada por Redis (**Verde:** Acceso Permitido / **Rojo:** Membresía Vencida).
* **Gestión Pasiva de Caja:** Control total de pagos en mostrador (efectivo, transferencia o terminal) mediante renovación manual de fechas de corte y saldos de bonos/créditos.

### 🎥 4. Rutinas con Optimización Multimedia Ligera
* **Demostraciones Visuales Propias:** Los entrenadores suben clips cortos (3–5 seg) ejecutando los ejercicios en las máquinas del propio gimnasio.
* **Procesamiento Asíncrono (FFmpeg + Colas Redis):** Conversión automática en segundo plano a formato `.webp` animado o `.mp4` sin audio (300 KB – 800 KB), garantizando carga instantánea con datos móviles sin saturar el servidor.

### 🔥 5. Gamificación y Evolución Física del Socio
* **Rachas Semanales (Lunes a Domingo):** Calendario visual de asistencia semanal y contador de semanas consecutivas cumpliendo metas.
* **Seguimiento Antropométrico Mensual:** Registro de línea base ("Día 1") con recordatorios automáticos cada 30 días para actualizar peso y medidas, generando comparativas y gráficas de progreso.

### 📢 6. Tablón de Avisos, Promociones e Inventario
* **Comunicación Directa:** Publicación de horarios especiales en días festivos y ofertas comerciales (ej. 2x1 en suplementos) con fecha de expiración automática.
* **Control de Mostrador:** Gestión de stock de productos, suplementos y equipamiento del gimnasio.

---

## 🛠️ Stack Tecnológico

* **Backend:** Laravel (PHP 8.2+)
* **Base de Datos:** MySQL / PostgreSQL (con índices compuestos por `gym_id`)
* **Caché y Colas (Queues):** Redis (validación rápida de accesos QR, sesiones y procesamiento multimedia)
* **Motor de Búsqueda:** Meilisearch + Laravel Scout (búsqueda instantánea de socios filtrada por `gym_id`)
* **Frontend:** Tailwind CSS (100% Responsivo / *Mobile-First*) + Alpine.js / Inertia.js
* **Procesamiento Multimedia & QR:** FFmpeg (backend) + `html5-qrcode` (frontend)

---

## 👥 Roles del Sistema (RBAC)

| Rol | Identificador | Alcance y Responsabilidades |
| :--- | :--- | :--- |
| **Super Admin** | `super_admin` | Administración global del SaaS, alta de gimnasios, finanzas (MRR), salud del servidor y logs de errores. |
| **Dueño / Admin** | `gym_admin` | Configuración de Marca Blanca, gestión de socios, pagos, escáner QR, avisos, entrenadores e inventario. |
| **Entrenador** | `trainer` | Creación de rutinas, carga de clips demostrativos ligeros y registro de valoraciones físicas. |
| **Cliente / Socio** | `member` | Credencial QR, consulta de vigencia, avisos, rutina diaria, rachas semanales y gráficas de avance. |

---

## 🚀 Instalación y Configuración Local

1. **Clonar el repositorio:**

```bash
git clone https://github.com/tu-usuario/tu-repositorio-gym-saas.git
cd tu-repositorio-gym-saas

1. **Instalar dependencias de PHP y Node.js:**
 
