# Symfony Base Project

Proyecto base para aplicaciones Symfony 7.3 con arquitectura hexagonal (DDD) y Vue 3, completamente dockerizado con CI/CD para GitLab.

## Requisitos

- Docker y Docker Compose
- Make
- OpenSSL (para generación de claves JWT)

## Estructura del Proyecto

```
├── backend/                 # Aplicación Symfony
│   ├── src/
│   │   ├── Application/     # Casos de uso (Commands, Queries, Handlers, DTOs)
│   │   ├── Domain/          # Entidades, repositorios, servicios de dominio
│   │   └── Infrastructure/  # Controladores, persistencia, servicios externos
│   ├── config/              # Configuración de Symfony
│   ├── migrations/          # Migraciones de Doctrine
│   └── tests/               # Tests unitarios y funcionales
├── docker/                  # Configuración de Docker
│   ├── apache/              # Configuración de Apache (sirve backend + frontend en /app/)
│   ├── db/                  # Datos y scripts de inicialización de BD
│   ├── frontend/            # Dockerfile para el contenedor frontend (Bun)
│   └── php/                 # Configuración de PHP y Xdebug
├── frontend/                # Aplicación Vue 3 (git subtree de vue-base-project)
├── scripts/                 # Scripts de utilidad
├── docker-compose.yaml      # Definición de servicios Docker
├── Makefile                 # Comandos de automatización
└── .gitlab-ci.yml           # Pipeline de CI/CD
```

## Instalación

### 1. Configurar el proyecto (opcional)

Si deseas personalizar los nombres de contenedores, puertos y red:

```bash
make setup-project
```

### 2. Instalar el proyecto

```bash
make install
```

Este comando:
- Construye e inicia todos los contenedores Docker
- Configura los git hooks
- Instala las dependencias de Composer (backend)
- Instala las dependencias de Bun (frontend)
- Genera las claves JWT
- Ejecuta las migraciones de base de datos
- Crea un usuario administrador por defecto

## Servicios Docker

| Servicio | Puerto | Descripción |
|----------|--------|-------------|
| Apache | 8011 (HTTP), 4011 (HTTPS) | Servidor web (backend API + frontend en producción) |
| PostgreSQL | 5011 | Base de datos |
| PHP-FPM | — | Procesador PHP (sin puerto expuesto) |
| Frontend | 3011 | Servidor de desarrollo Vite (Vue 3 + Bun) |
| Mailpit | 18011 (Web), 11011 (SMTP) | Servidor de correo para desarrollo |

## Desarrollo frontend

El frontend se ejecuta en un contenedor Bun dedicado. El servidor de desarrollo Vite escucha en el puerto `3011` del host.

### Iniciar el servidor de desarrollo

```bash
make frontend-dev
```

Esto inicia Vite dentro del contenedor con `--host` para que sea accesible desde el navegador. Pulsa `Ctrl+C` para detenerlo (el contenedor sigue activo).

### Modo offline (mock server)

```bash
make frontend-offline
```

Inicia Vite con `START_MOCK_SERVER=true` para activar MirageJS.

### Build de producción

```bash
make frontend-build
```

Genera `frontend/dist/`, que Apache sirve automáticamente en la ruta `/app/`.

## Configurar dominio .local

Para acceder a la aplicación usando un dominio personalizado (ej: `symfony-base.local`) sin especificar puertos:

### 1. Añadir entrada en /etc/hosts

```bash
sudo nano /etc/hosts
```

Añade:

```
172.1.1.100 symfony-base.local
```

### 2. Acceder a la aplicación

- **Backend API**: http://symfony-base.local (o https://symfony-base.local)
- **Frontend (dev)**: http://localhost:3011/app/
- **Frontend (producción)**: http://symfony-base.local/app/

> El certificado SSL es autofirmado; el navegador mostrará una advertencia de seguridad.

## Comandos Make

### Gestión de contenedores

```bash
make start          # Iniciar todos los contenedores
make stop           # Detener contenedores
make restart        # Reiniciar contenedores
make destroy        # Eliminar contenedores
make rebuild        # Reconstruir desde cero
```

### Backend

```bash
make bash                        # Shell en el contenedor PHP
make composer-install            # Instalar dependencias
make composer-update             # Actualizar dependencias
make composer-require <paquete>  # Añadir dependencia
```

### Frontend

```bash
make frontend-install   # Instalar dependencias (bun install)
make frontend-dev       # Iniciar servidor de desarrollo Vite en el contenedor
make frontend-offline   # Servidor de desarrollo con mock server activo
make frontend-build     # Build de producción → frontend/dist/
make frontend-sync      # Sincronizar con el repositorio upstream de vue-base-project
make frontend-bash      # Shell en el contenedor frontend
```

### Base de datos

```bash
make migration-migrate   # Ejecutar migraciones
make migration-status    # Ver estado de migraciones
```

### Testing

```bash
make test           # Ejecutar tests (para al primer fallo)
make test-all       # Ejecutar todos los tests
make test-parallel  # Ejecutar tests en paralelo
```

### Calidad de código

```bash
make fix-code-project   # Formatear todo el código PHP
make fix-code-staged    # Formatear solo archivos staged
make phpstan            # Análisis estático
```

### JWT

```bash
make jwt-generate   # Generar nuevas claves JWT
```

### Despliegue

```bash
make deploy-install   # Instalar dependencias del script de despliegue (npm ci)
make deploy           # Lanzar el script interactivo de despliegue
```

`make deploy` ejecuta `scripts/deploy/deploy.js` **en la máquina local**, no dentro de un contenedor: necesita Node.js, `rsync`, `sshpass` y acceso al socket de Docker para construir el frontend.

Antes del primer despliegue hay que crear `scripts/deploy/deploy.config.json` con los datos de cada entorno. Configuración, acciones disponibles y modo no interactivo: [`scripts/deploy/README.md`](scripts/deploy/README.md).

## Sincronización del frontend con upstream

El directorio `frontend/` está gestionado mediante **git subtree** vinculado al repositorio [vue-base-project](https://gitlab.com/ManagementDrives/developers/base-projects/vue-base-project).

Para incorporar actualizaciones del proyecto base de Vue:

```bash
make frontend-sync
# equivalente a: git subtree pull --prefix=frontend vue-upstream main --squash
```

Los conflictos se resuelven con el flujo normal de git. El remote `vue-upstream` ya está configurado en el repositorio.

> **Nota**: Si clonas el repositorio en una máquina nueva y necesitas hacer `frontend-sync`, primero debes añadir el remote:
> ```bash
> git remote add vue-upstream git@gitlab.com:ManagementDrives/developers/base-projects/vue-base-project.git
> ```

## CI/CD

El proyecto incluye un pipeline de GitLab CI que ejecuta:

1. **Caché de Composer**: Se actualiza cuando cambian los archivos de lock
2. **Lint PHP**: Análisis estático con PHPStan
3. **Tests unitarios**: PHPUnit con base de datos PostgreSQL

El pipeline se ejecuta en merge requests (excepto drafts).

## Arquitectura

### Backend

El proyecto sigue una arquitectura hexagonal con las siguientes capas:

- **Domain**: Entidades, value objects, repositorios (interfaces), servicios de dominio
- **Application**: Casos de uso implementados con Command/Query Bus (Tactician)
- **Infrastructure**: Implementaciones concretas, controladores, persistencia

### Frontend

- **Vue 3** con Composition API
- **Pinia** para gestión de estado
- **Vue Router** en modo history
- **Vue i18n** para internacionalización
- **Vite** como bundler y servidor de desarrollo
- **Bun** como gestor de paquetes y runtime
- Sistema de **clientes** para despliegues multi-tenant (ver `src/clients/`)

## Tecnologías

| Capa | Tecnología |
|------|------------|
| Framework backend | Symfony 7.3 |
| Base de datos | PostgreSQL 17 con Doctrine ORM |
| Autenticación | JWT (lexik/jwt-authentication-bundle) |
| Command Bus | Tactician |
| Testing | PHPUnit 11, Paratest |
| Análisis estático | PHPStan |
| Formateo PHP | PHP-CS-Fixer |
| Framework frontend | Vue 3.5 |
| Build tool | Vite 7 |
| Package manager | Bun |
| Estado | Pinia |
| Routing | Vue Router |
