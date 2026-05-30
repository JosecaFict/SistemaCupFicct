# Sistema CUP FICCT

Sistema web para gestionar el proceso de admisión del **Curso Preuniversitario CUP** de la Facultad de Ingeniería en Ciencias de la Computación y Telecomunicaciones (FICCT).

## Stack

| Capa     | Tecnología |
|----------|------------|
| Frontend | React 18 + Vite + TypeScript + Tailwind CSS + React Router + Axios |
| Backend  | PHP 8.2 + Laravel 11 + Laravel Sanctum (API REST) |
| BD       | PostgreSQL 17 |

## Estructura del monorepo

```
SistemadeCup/
├── frontend/     # React + Vite + TS + Tailwind
├── backend/      # Laravel 11 (API REST)
├── docs/         # Documentación por ciclo
├── tools/        # composer.phar y utilidades locales
├── .gitignore
└── README.md
```

## Roles

- **Administrador** — gestiona usuarios, gestiones CUP, configuración global.
- **Encargado de inscripción** — verifica requisitos, confirma inscripciones.
- **Docente** — (Ciclo 2) carga de notas de sus grupos asignados.
- **Coordinador / Autoridad** — (Ciclo 2) reportes y cupos.
- **Postulante público (sin login)** — preinscripción, pago, consulta pública de resultados.

## Ciclos de desarrollo

| Ciclo | Estado | Alcance |
|-------|--------|---------|
| 1     | ✅ En curso | Autenticación, usuarios, gestión CUP, postulantes, preinscripción, pago Stripe simulado, requisitos, inscripción, grupos, boleta, bitácora |
| 2     | ⏳ Pendiente | Módulo académico (grupos, docentes, materias, horarios, ambientes, carga de notas, validación de notas) |
| 3+    | ⏳ Pendiente | Resultados finales, cupos, reportes, deploy |

Ver `docs/CICLO-1.md` para detalles, instrucciones de ejecución y flujo de prueba del Ciclo 1.

## Arranque rápido (Ciclo 1)

Requisitos: PHP 8.2+, Node 18+, PostgreSQL 14+.

```powershell
# Backend
cd backend
copy .env.example .env
php ..\tools\composer.phar install
php artisan key:generate
php artisan migrate --seed
php artisan serve  # http://localhost:8000

# Frontend (otra terminal)
cd frontend
copy .env.example .env
npm install
npm run dev        # http://localhost:5173
```

Credenciales del administrador por defecto se documentan en `docs/CICLO-1.md`.
