# Symfony Base Project

API Symfony 7.3 con arquitectura hexagonal (DDD) y cliente Flutter, dockerizada.

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
│   ├── apache/              # Configuración de Apache (sirve la API)
│   ├── db/                  # Datos y scripts de inicialización de BD
│   └── php/                 # Configuración de PHP y Xdebug
├── frontend/                # Cliente Flutter (ver más abajo)
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
- Genera las claves JWT
- Ejecuta las migraciones de base de datos
- Crea un usuario administrador por defecto

## Servicios Docker

| Servicio | Puerto | Descripción |
|----------|--------|-------------|
| Apache | 8011 (HTTP), 4011 (HTTPS) | Servidor web (API backend) |
| PostgreSQL | 5011 | Base de datos |
| PHP-FPM | — | Procesador PHP (sin puerto expuesto) |
| Mailpit | 18011 (Web), 11011 (SMTP) | Servidor de correo para desarrollo |

## Desarrollo frontend (Flutter)

El cliente es una app Flutter en `frontend/` (aún por crear con `flutter create`). Se ejecuta en la máquina local, no en Docker. Convenciones: `.claude/skills/flutter/SKILL.md`.

```bash
cd frontend
flutter pub get
flutter analyze && flutter test
flutter run -d chrome --dart-define=API_BASE_URL=http://localhost:8011/api/v1
```

En el emulador de Android la API local es `http://10.0.2.2:8011/api/v1`.

## Configurar dominio .local

Para acceder a la aplicación usando un dominio personalizado (ej: `agronomy-app.local`) sin especificar puertos:

### 1. Añadir entrada en /etc/hosts

```bash
sudo nano /etc/hosts
```

Añade:

```
172.1.1.100 agronomy-app.local
```

### 2. Acceder a la aplicación

- **Backend API**: http://agronomy-app.local (o https://agronomy-app.local)

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
