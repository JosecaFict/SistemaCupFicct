# Sistema CUP FICCT

Sistema web para gestionar el proceso de admisión del **Curso Preuniversitario (CUP)** de la
Facultad de Ingeniería en Ciencias de la Computación y Telecomunicaciones (**FICCT** – UAGRM).

Aplicación full-stack en producción: preinscripción pública, pago en línea, gestión de
inscripciones, módulo académico (notas y resultados) y reportes.

## Demo en vivo
- **Frontend:** https://sistema-cup-ficct.vercel.app
- **API:** https://sistemacupficct-production.up.railway.app

## Pila tecnológica
| Capa | Tecnología |
|---|---|
| Interfaz | React 18 + Vite + TypeScript + Tailwind CSS + React Router + Axios |
| Backend | PHP 8.2 + Laravel 11 + Laravel Sanctum (API REST, auth SPA por cookies) |
| Base de datos | PostgreSQL 14+ |
| Pagos | Stripe Checkout (modo test) |
| Correo | API HTTP de Brevo (OTP de recuperación de contraseña) |
| Despliegue | Vercel (frontend) · Railway (backend + PostgreSQL) |

## Estructura del monorepo
```
SistemadeCup/
├── frontend/   # React + Vite + TypeScript + Tailwind
├── backend/    # Laravel 11 (API REST)
├── tools/      # Utilidades locales (ignorado por Git)
├── .gitignore
└── README.md
```

## Roles
- **Administrador** — usuarios, gestiones CUP, configuración global y supervisión.
- **Encargado de inscripción** — verifica requisitos y confirma inscripciones de la gestión activa (rol rotativo).
- **Docente** — carga de notas de sus grupos asignados.
- **Coordinador / Autoridad** — cálculo de resultados, asignación de cupos y reportes.
- **Postulante público** (sin iniciar sesión) — preinscripción, pago en línea y consulta de resultados.

## Funcionalidades
**Gestión y administración**
- Autenticación con Sanctum (SPA por cookies), recuperación de contraseña por OTP y política de contraseña.
- Gestión de usuarios y roles.
- Configuración de gestiones CUP: fechas, exámenes, ponderaciones, cupos por carrera, turnos y **costo de inscripción configurable**.
- Generación automática de grupos por turno.

**Preinscripción y pago (público)**
- Preinscripción en línea y generación de formulario.
- **Pago con Stripe Checkout** (modo test), con el monto definido por la gestión; modo simulado como respaldo.
- Reimpresión de formulario.

**Inscripción**
- Verificación documental (checklist de requisitos).
- Confirmación de inscripción con asignación de grupo y código de postulante.
- **Boleta de inscripción** con horario semanal del grupo (materia, días, horario y aula).
- Bitácora de auditoría.

**Módulo académico y resultados**
- Materias, horarios, ambientes y asignación de docentes a grupos.
- Carga y validación de notas.
- Cálculo de resultados finales (aceptados, reprobados, sin cupo), asignación de cupos por carrera y ranking.
- Publicación y consulta pública de resultados.
- Reportes e indicadores (KPIs).

**Experiencia**
- Diseño responsive (móvil y escritorio).

## Arranque rápido (desarrollo local)
**Requisitos:** PHP 8.2+, Composer, Node 18+, PostgreSQL 14+.

```bash
# Backend
cd backend
cp .env.example .env          # configurar conexión a PostgreSQL
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve             # http://localhost:8000
```

```bash
# Frontend (otra terminal)
cd frontend
cp .env.example .env          # VITE_API_URL=http://localhost:8000
npm install
npm run dev                   # http://localhost:5173
```

> En Windows usá `copy` en lugar de `cp`. Si no tenés Composer global, podés usar `php tools/composer.phar install`.

## Variables de entorno (backend)
| Variable | Descripción |
|---|---|
| `APP_URL`, `FRONTEND_URL` | URLs base de la API y del SPA |
| `DB_*` | Conexión a PostgreSQL |
| `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN` | Dominios para autenticación por cookies |
| `STRIPE_MODE` | `simulated` o `test` |
| `STRIPE_KEY`, `STRIPE_SECRET` | Claves de Stripe (modo test) |
| `BREVO_API_KEY` | Envío de correo (OTP) por API HTTP |

| `STRIPE_KEY`, `STRIPE_SECRET` | Claves de Stripe (modo test) |
| `BREVO_API_KEY` | Envío de correo (OTP) por API HTTP |

## Notas
- **Pagos:** Stripe opera en **modo test** (no procesa dinero real). Stripe no soporta BOB, por lo que la sesión de prueba se cobra en USD mientras el sistema registra el monto en bolivianos. Tarjeta de prueba: `4242 4242 4242 4242`.
- **Correo:** Railway bloquea SMTP saliente, por eso el envío de correo (OTP) se hace por la **API HTTP de Brevo**.
- **Credenciales de demo:** el usuario administrador inicial se crea con el seeder (`AdminUserSeeder`).

