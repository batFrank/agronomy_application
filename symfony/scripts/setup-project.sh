#!/bin/bash

# Script interactivo para configurar el nombre del proyecto en docker-compose.yaml y Makefile
# Reemplaza los nombres de containers, redes, IPs y puertos con los valores del nuevo proyecto

set -e

# Colores para output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# Obtener el directorio del script
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Archivos a modificar
DOCKER_COMPOSE_FILE="$PROJECT_ROOT/docker-compose.yaml"
MAKEFILE="$PROJECT_ROOT/Makefile"
INITDB_FILE="$PROJECT_ROOT/docker/db/initdb/initdb.sql"
ENV_FILE="$PROJECT_ROOT/backend/.env"
ENV_DEV_FILE="$PROJECT_ROOT/backend/.env.dev"
ENV_TEST_FILE="$PROJECT_ROOT/backend/.env.test"
GITLAB_CI_FILE="$PROJECT_ROOT/.gitlab-ci.yml"
VIRTUALHOST_FILE="$PROJECT_ROOT/docker/apache/virtualhost.conf"
VIRTUALHOST_SSL_FILE="$PROJECT_ROOT/docker/apache/virtualhost-ssl.conf"
README_FILE="$PROJECT_ROOT/README.md"
VITE_CONFIG_FILE="$PROJECT_ROOT/frontend/vite.config.js"

# Valores actuales por defecto
OLD_BASE_NAME="symfony-base"
OLD_NETWORK_PREFIX="172.20.1"
OLD_DB_NAME="symfony_base"

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }
log_step() { echo -e "${BLUE}${BOLD}==>${NC} $1"; }

read_with_default() {
    local prompt="$1"
    local default="$2"
    local result
    echo -en "${prompt} ${YELLOW}[${default}]${NC}: " >&2
    read -r result
    echo "${result:-$default}"
}

validate_network_prefix() {
    local prefix="$1"
    if [[ $prefix =~ ^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$ ]]; then
        IFS='.' read -ra octets <<< "$prefix"
        for octet in "${octets[@]}"; do
            if [ "$octet" -gt 255 ]; then return 1; fi
        done
        return 0
    fi
    return 1
}

check_network_conflict() {
    local prefix="$1"
    local subnet="${prefix}.0/24"
    local existing_networks
    existing_networks=$(docker network ls --format '{{.Name}}' 2>/dev/null)
    if [ -z "$existing_networks" ]; then return 0; fi
    while IFS= read -r network_name; do
        local network_subnet
        network_subnet=$(docker network inspect "$network_name" --format '{{range .IPAM.Config}}{{.Subnet}}{{end}}' 2>/dev/null)
        if [ "$network_subnet" == "$subnet" ]; then
            echo "$network_name"
            return 1
        fi
    done <<< "$existing_networks"
    return 0
}

normalize_project_name() {
    echo "$1" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9-]/-/g' | sed 's/--*/-/g' | sed 's/^-//;s/-$//'
}

# Convertir nombre a formato válido para base de datos (guiones bajos en lugar de guiones)
to_db_name() {
    echo "$1" | tr '-' '_'
}

# Generar un secret aleatorio (32 bytes en hexadecimal = 64 caracteres)
generate_secret() {
    openssl rand -hex 32
}

# Calcular sufijo de puertos: 3er octeto + 2º octeto (con padding)
calculate_port_suffix() {
    local prefix="$1"
    IFS='.' read -ra octets <<< "$prefix"
    printf "%d%02d" "${octets[2]}" "${octets[1]}"
}

check_port_in_use() {
    local port="$1"
    local container
    container=$(docker ps --format '{{.Names}}' --filter "publish=$port" 2>/dev/null | head -1)
    if [ -n "$container" ]; then
        echo "$container"
        return 1
    fi
    return 0
}

check_all_ports() {
    local suffix="$1"
    local conflicts=()
    # PostgreSQL y servicios web
    local ports=("5${suffix}:5432" "8${suffix}:80" "4${suffix}:443" "9${suffix}:9001" "11${suffix}:1025" "18${suffix}:8025" "7${suffix}:7${suffix}")
    for port_mapping in "${ports[@]}"; do
        local host_port="${port_mapping%%:*}"
        local container
        container=$(check_port_in_use "$host_port" 2>/dev/null || true)
        if [ -n "$container" ]; then
            conflicts+=("Puerto $host_port usado por: $container")
        fi
    done
    if [ ${#conflicts[@]} -gt 0 ]; then
        printf '%s\n' "${conflicts[@]}"
        return 1
    fi
    return 0
}

# Banner
echo ""
echo -e "${BOLD}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║        ${BLUE}Configuración del Proyecto Symfony Base${NC}${BOLD}             ║${NC}"
echo -e "${BOLD}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Verificar archivos
if [ ! -f "$DOCKER_COMPOSE_FILE" ]; then log_error "No se encontró docker-compose.yaml"; exit 1; fi
if [ ! -f "$MAKEFILE" ]; then log_error "No se encontró Makefile"; exit 1; fi
if [ ! -f "$INITDB_FILE" ]; then log_warning "No se encontró $INITDB_FILE"; fi
if [ ! -f "$ENV_FILE" ]; then log_warning "No se encontró $ENV_FILE"; fi
if [ ! -f "$ENV_DEV_FILE" ]; then log_warning "No se encontró $ENV_DEV_FILE"; fi
if [ ! -f "$ENV_TEST_FILE" ]; then log_warning "No se encontró $ENV_TEST_FILE"; fi
if [ ! -f "$GITLAB_CI_FILE" ]; then log_warning "No se encontró $GITLAB_CI_FILE"; fi
if [ ! -f "$VIRTUALHOST_FILE" ]; then log_warning "No se encontró $VIRTUALHOST_FILE"; fi
if [ ! -f "$VIRTUALHOST_SSL_FILE" ]; then log_warning "No se encontró $VIRTUALHOST_SSL_FILE"; fi
if [ ! -f "$README_FILE" ]; then log_warning "No se encontró $README_FILE"; fi
if [ ! -f "$VITE_CONFIG_FILE" ]; then log_warning "No se encontró $VITE_CONFIG_FILE"; fi

# Detectar valores actuales
DETECTED_NAME=$(grep -oP 'container_name: \K[a-z0-9-]+(?=-database)' "$DOCKER_COMPOSE_FILE" 2>/dev/null | head -1 || echo "$OLD_BASE_NAME")
DETECTED_NETWORK=$(grep -oP 'ipv4_address: \K[0-9]+\.[0-9]+\.[0-9]+' "$DOCKER_COMPOSE_FILE" 2>/dev/null | head -1 || echo "$OLD_NETWORK_PREFIX")
# Detectar sufijo de puerto desde PostgreSQL (5432)
DETECTED_PORT_SUFFIX=$(grep -oP '"5\K[0-9]+(?=:5432")' "$DOCKER_COMPOSE_FILE" 2>/dev/null | head -1 || echo "011")
DETECTED_PHP_CONTAINER=$(grep -oP '^PHP_CONTAINER=\K[a-z0-9-]+' "$MAKEFILE" 2>/dev/null | head -1 || echo "${OLD_BASE_NAME}-php")
DETECTED_FRONTEND_CONTAINER=$(grep -oP '^FRONTEND_CONTAINER=\K[a-z0-9-]+' "$MAKEFILE" 2>/dev/null | head -1 || echo "${OLD_BASE_NAME}-frontend")
# Detectar nombre de BD del initdb.sql (sin el sufijo _test)
if [ -f "$INITDB_FILE" ]; then
    # Detectar desde PostgreSQL: SELECT 'CREATE DATABASE nombre'
    DETECTED_DB_NAME=$(grep -oP "CREATE DATABASE \K[a-z0-9_]+(?=')" "$INITDB_FILE" 2>/dev/null | grep -v '_test$' | head -1)
    # Si no se encuentra, usar el valor por defecto
    if [ -z "$DETECTED_DB_NAME" ]; then
        DETECTED_DB_NAME="$OLD_DB_NAME"
    fi
else
    DETECTED_DB_NAME=$(to_db_name "$DETECTED_NAME")
fi
# Detectar valores de .env
if [ -f "$ENV_FILE" ]; then
    DETECTED_ENV_DB_HOST=$(grep -oP '^DB_HOST=\K[a-z0-9-]+' "$ENV_FILE" 2>/dev/null | head -1 || echo "")
    DETECTED_ENV_DB_NAME=$(grep -oP '^DB_NAME=\K[a-z0-9_]+' "$ENV_FILE" 2>/dev/null | head -1 || echo "")
    # Detectar el nombre del container de mailer en MAILER_DSN (formato: smtp://nombre-mailer:puerto)
    DETECTED_ENV_MAILER_HOST=$(grep -oP '^MAILER_DSN=smtp://\K[a-z0-9-]+' "$ENV_FILE" 2>/dev/null | head -1 || echo "")
fi
# Detectar valores de virtualhost
if [ -f "$VIRTUALHOST_FILE" ]; then
    DETECTED_SERVER_ALIAS=$(grep -oP 'ServerAlias\s+\K[a-z0-9.-]+' "$VIRTUALHOST_FILE" 2>/dev/null | head -1 || echo "")
    DETECTED_PHP_HANDLER=$(grep -oP 'SetHandler\s+"proxy:fcgi://\K[a-z0-9-]+' "$VIRTUALHOST_FILE" 2>/dev/null | head -1 || echo "")
fi

echo -e "${BOLD}Valores actuales detectados:${NC}"
echo -e "  - Nombre base: ${YELLOW}${DETECTED_NAME}${NC}"
echo -e "  - Prefijo de red: ${YELLOW}${DETECTED_NETWORK}${NC}"
echo -e "  - Sufijo de puertos: ${YELLOW}${DETECTED_PORT_SUFFIX}${NC}"
echo -e "  - Nombre BD: ${YELLOW}${DETECTED_DB_NAME}${NC}"
echo -e "  - PHP Container (Makefile): ${YELLOW}${DETECTED_PHP_CONTAINER}${NC}"
echo -e "  - Frontend Container (Makefile): ${YELLOW}${DETECTED_FRONTEND_CONTAINER}${NC}"
if [ -n "$DETECTED_ENV_DB_HOST" ]; then
    echo -e "  - DB_HOST (.env): ${YELLOW}${DETECTED_ENV_DB_HOST}${NC}"
fi
if [ -n "$DETECTED_ENV_DB_NAME" ]; then
    echo -e "  - DB_NAME (.env): ${YELLOW}${DETECTED_ENV_DB_NAME}${NC}"
fi
if [ -n "$DETECTED_ENV_MAILER_HOST" ]; then
    echo -e "  - MAILER_DSN host (.env): ${YELLOW}${DETECTED_ENV_MAILER_HOST}${NC}"
fi
if [ -n "$DETECTED_SERVER_ALIAS" ]; then
    echo -e "  - ServerAlias (Apache): ${YELLOW}${DETECTED_SERVER_ALIAS}${NC}"
fi
if [ -n "$DETECTED_PHP_HANDLER" ]; then
    echo -e "  - PHP Handler (Apache): ${YELLOW}${DETECTED_PHP_HANDLER}${NC}"
fi
echo ""

# PASO 1: Nombre del proyecto
log_step "Paso 1/2: Nombre del proyecto"
echo ""
echo "El nombre se usará para containers, redes y base de datos."
echo ""

DEFAULT_NAME=$(normalize_project_name "$(basename "$PROJECT_ROOT")")
PROJECT_NAME=$(read_with_default "Nombre del proyecto" "$DEFAULT_NAME")
PROJECT_NAME=$(normalize_project_name "$PROJECT_NAME")
DB_NAME=$(to_db_name "$PROJECT_NAME")

echo ""
log_info "Nombre normalizado: ${BOLD}$PROJECT_NAME${NC}"
log_info "Nombre de BD: ${BOLD}$DB_NAME${NC}"
echo ""

# PASO 2: IP de la red
log_step "Paso 2/2: Configuración de red"
echo ""
echo "Introduce los primeros 3 octetos de la IP de red (ej: 172.20.1)"
echo "Los puertos se calcularán automáticamente: 3er octeto + 2º octeto"
echo "  Ejemplo: 172.20.1 -> puertos 3120, 8120, 4120, 9120, 11120, 18120"
echo ""

while true; do
    NETWORK_PREFIX=$(read_with_default "Prefijo de red (3 octetos)" "$DETECTED_NETWORK")

    if ! validate_network_prefix "$NETWORK_PREFIX"; then
        log_error "Formato inválido. Usa: X.X.X (ej: 172.20.1)"
        continue
    fi

    PORT_SUFFIX=$(calculate_port_suffix "$NETWORK_PREFIX")

    echo ""
    log_info "Puertos calculados (sufijo: ${PORT_SUFFIX}):"
    echo "    PostgreSQL: 5${PORT_SUFFIX}:5432"
    echo "    Apache HTTP: 8${PORT_SUFFIX}:80"
    echo "    Apache HTTPS: 4${PORT_SUFFIX}:443"
    echo "    Supervisord: 9${PORT_SUFFIX}:9001"
    echo "    Mailpit SMTP: 11${PORT_SUFFIX}:1025"
    echo "    Mailpit Web: 18${PORT_SUFFIX}:8025"
    echo "    Frontend/Vite: 7${PORT_SUFFIX}:7${PORT_SUFFIX}"
    echo ""

    CONFLICTING_NETWORK=$(check_network_conflict "$NETWORK_PREFIX" 2>/dev/null || true)
    if [ -n "$CONFLICTING_NETWORK" ]; then
        log_error "Ya existe una red Docker con la subred ${NETWORK_PREFIX}.0/24"
        log_warning "Red existente: ${CONFLICTING_NETWORK}"
        echo ""
        read -p "¿Deseas usar esta IP de todas formas? (s/N): " -n 1 -r
        echo ""
        if [[ ! $REPLY =~ ^[Ss]$ ]]; then continue; fi
    fi

    PORT_CONFLICTS=$(check_all_ports "$PORT_SUFFIX" 2>/dev/null || true)
    if [ -n "$PORT_CONFLICTS" ]; then
        log_error "Algunos puertos están en uso:"
        echo "$PORT_CONFLICTS" | while read -r line; do echo "    - $line"; done
        echo ""
        read -p "¿Deseas usar estos puertos de todas formas? (s/N): " -n 1 -r
        echo ""
        if [[ ! $REPLY =~ ^[Ss]$ ]]; then continue; fi
    fi

    break
done
echo ""

# Generar nuevos secrets
NEW_APP_SECRET=$(generate_secret)
NEW_APP_SECRET_DEV=$(generate_secret)
NEW_JWT_PASSPHRASE=$(generate_secret)

# Confirmación
echo -e "${BOLD}═══ Resumen de cambios ═══${NC}"
echo "  Proyecto: $PROJECT_NAME"
echo "  Containers: ${PROJECT_NAME}-{database,apache,php,mailer,frontend}"
echo "  Red: ${PROJECT_NAME}-network (${NETWORK_PREFIX}.0/24)"
echo "  IPs: ${NETWORK_PREFIX}.{200,100,101,102,103}"
echo "  Puertos: 5${PORT_SUFFIX} (PostgreSQL), 8${PORT_SUFFIX} (HTTP), 4${PORT_SUFFIX} (HTTPS), 9${PORT_SUFFIX}, 11${PORT_SUFFIX}, 18${PORT_SUFFIX}, 7${PORT_SUFFIX} (Vite)"
echo "  Base de datos: ${DB_NAME}, ${DB_NAME}_test"
echo ""
echo -e "${BOLD}Archivos .env:${NC}"
echo "  DB_HOST: ${PROJECT_NAME}-database"
echo "  DB_PORT: 5432 (PostgreSQL)"
echo "  DB_NAME: ${DB_NAME} / ${DB_NAME}_test"
echo "  MAILER_DSN: smtp://${PROJECT_NAME}-mailer:1025"
echo "  APP_SECRET: (nuevo generado)"
echo "  JWT_PASSPHRASE: (nuevo generado)"
echo ""
echo -e "${BOLD}Apache VirtualHost:${NC}"
echo "  ServerAlias: ${PROJECT_NAME}.local"
echo "  SetHandler: proxy:fcgi://${PROJECT_NAME}-php:9000"
echo ""

read -p "¿Confirmar cambios? (s/N): " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Ss]$ ]]; then log_info "Operación cancelada"; exit 0; fi
echo ""

# Aplicar cambios a docker-compose.yaml
log_info "Modificando docker-compose.yaml..."

# Nombres de containers y servicios
sed -i "s/${DETECTED_NAME}-database/${PROJECT_NAME}-database/g" "$DOCKER_COMPOSE_FILE"
sed -i "s/${DETECTED_NAME}-apache/${PROJECT_NAME}-apache/g" "$DOCKER_COMPOSE_FILE"
sed -i "s/${DETECTED_NAME}-php/${PROJECT_NAME}-php/g" "$DOCKER_COMPOSE_FILE"
sed -i "s/${DETECTED_NAME}-mailer/${PROJECT_NAME}-mailer/g" "$DOCKER_COMPOSE_FILE"
sed -i "s/${DETECTED_NAME}-frontend/${PROJECT_NAME}-frontend/g" "$DOCKER_COMPOSE_FILE"
sed -i "s/${DETECTED_NAME}-network/${PROJECT_NAME}-network/g" "$DOCKER_COMPOSE_FILE"
sed -i "s/mapeo-network/${PROJECT_NAME}-network/g" "$DOCKER_COMPOSE_FILE"

# Actualizar nombre de base de datos PostgreSQL
if [ -n "$DETECTED_DB_NAME" ] && [ "$DETECTED_DB_NAME" != "$DB_NAME" ]; then
    sed -i "s/POSTGRES_DB: ${DETECTED_DB_NAME}/POSTGRES_DB: ${DB_NAME}/g" "$DOCKER_COMPOSE_FILE"
fi

# IPs de red
sed -i "s/${DETECTED_NETWORK}\./${NETWORK_PREFIX}./g" "$DOCKER_COMPOSE_FILE"

# Puertos
if [ "$DETECTED_PORT_SUFFIX" != "$PORT_SUFFIX" ]; then
    log_info "Actualizando puertos: ${DETECTED_PORT_SUFFIX} -> ${PORT_SUFFIX}"
    # PostgreSQL
    sed -i "s/\"5${DETECTED_PORT_SUFFIX}:5432\"/\"5${PORT_SUFFIX}:5432\"/g" "$DOCKER_COMPOSE_FILE"
    # Apache y otros servicios
    sed -i "s/\"8${DETECTED_PORT_SUFFIX}:80\"/\"8${PORT_SUFFIX}:80\"/g" "$DOCKER_COMPOSE_FILE"
    sed -i "s/\"4${DETECTED_PORT_SUFFIX}:443\"/\"4${PORT_SUFFIX}:443\"/g" "$DOCKER_COMPOSE_FILE"
    sed -i "s/\"9${DETECTED_PORT_SUFFIX}:9001\"/\"9${PORT_SUFFIX}:9001\"/g" "$DOCKER_COMPOSE_FILE"
    sed -i "s/\"11${DETECTED_PORT_SUFFIX}:1025\"/\"11${PORT_SUFFIX}:1025\"/g" "$DOCKER_COMPOSE_FILE"
    sed -i "s/\"18${DETECTED_PORT_SUFFIX}:8025\"/\"18${PORT_SUFFIX}:8025\"/g" "$DOCKER_COMPOSE_FILE"
    # Frontend/Vite
    sed -i "s/\"7${DETECTED_PORT_SUFFIX}:7${DETECTED_PORT_SUFFIX}\"/\"7${PORT_SUFFIX}:7${PORT_SUFFIX}\"/g" "$DOCKER_COMPOSE_FILE"
fi

# Aplicar cambios a initdb.sql
if [ -f "$INITDB_FILE" ]; then
    log_info "Modificando initdb.sql..."
    # Solo aplicar cambios si DETECTED_DB_NAME no está vacío
    if [ -n "$DETECTED_DB_NAME" ] && [ "$DETECTED_DB_NAME" != "$DB_NAME" ]; then
        sed -i "s/${DETECTED_DB_NAME}/${DB_NAME}/g" "$INITDB_FILE"
    else
        log_warning "No se detectó nombre de BD en initdb.sql o ya está actualizado"
    fi
fi

# Aplicar cambios a Makefile
log_info "Modificando Makefile..."
# Detectar el nombre actual del container PHP en el Makefile
CURRENT_PHP_CONTAINER=$(grep -oP '^PHP_CONTAINER=\K[a-z0-9-]+' "$MAKEFILE" 2>/dev/null | head -1)
if [ -n "$CURRENT_PHP_CONTAINER" ]; then
    sed -i "s/PHP_CONTAINER=${CURRENT_PHP_CONTAINER}/PHP_CONTAINER=${PROJECT_NAME}-php/g" "$MAKEFILE"
else
    # Fallback a los patrones conocidos
    sed -i "s/PHP_CONTAINER=mapeo-php/PHP_CONTAINER=${PROJECT_NAME}-php/g" "$MAKEFILE"
    sed -i "s/PHP_CONTAINER=${DETECTED_NAME}-php/PHP_CONTAINER=${PROJECT_NAME}-php/g" "$MAKEFILE"
fi
# Detectar el nombre actual del container Frontend en el Makefile
CURRENT_FRONTEND_CONTAINER=$(grep -oP '^FRONTEND_CONTAINER=\K[a-z0-9-]+' "$MAKEFILE" 2>/dev/null | head -1)
if [ -n "$CURRENT_FRONTEND_CONTAINER" ]; then
    sed -i "s/FRONTEND_CONTAINER=${CURRENT_FRONTEND_CONTAINER}/FRONTEND_CONTAINER=${PROJECT_NAME}-frontend/g" "$MAKEFILE"
else
    sed -i "s/FRONTEND_CONTAINER=${DETECTED_NAME}-frontend/FRONTEND_CONTAINER=${PROJECT_NAME}-frontend/g" "$MAKEFILE"
fi

# Aplicar cambios a backend/.env
if [ -f "$ENV_FILE" ]; then
    log_info "Modificando backend/.env..."
    # Actualizar DB_HOST
    if [ -n "$DETECTED_ENV_DB_HOST" ]; then
        sed -i "s/^DB_HOST=${DETECTED_ENV_DB_HOST}/DB_HOST=${PROJECT_NAME}-database/g" "$ENV_FILE"
    else
        sed -i "s/^DB_HOST=.*/DB_HOST=${PROJECT_NAME}-database/g" "$ENV_FILE"
    fi
    # Actualizar DB_PORT (PostgreSQL usa puerto interno 5432)
    sed -i "s/^DB_PORT=.*/DB_PORT=5432/g" "$ENV_FILE"
    # Actualizar DB_NAME
    if [ -n "$DETECTED_ENV_DB_NAME" ]; then
        sed -i "s/^DB_NAME=${DETECTED_ENV_DB_NAME}/DB_NAME=${DB_NAME}/g" "$ENV_FILE"
    else
        sed -i "s/^DB_NAME=.*/DB_NAME=${DB_NAME}/g" "$ENV_FILE"
    fi
    # Actualizar MAILER_DSN (reemplazar el host del mailer)
    if [ -n "$DETECTED_ENV_MAILER_HOST" ]; then
        sed -i "s/^MAILER_DSN=smtp:\/\/${DETECTED_ENV_MAILER_HOST}:/MAILER_DSN=smtp:\/\/${PROJECT_NAME}-mailer:/g" "$ENV_FILE"
    else
        # Fallback: reemplazar cualquier host en MAILER_DSN con formato smtp://host:puerto
        sed -i "s/^MAILER_DSN=smtp:\/\/[a-z0-9-]*:/MAILER_DSN=smtp:\/\/${PROJECT_NAME}-mailer:/g" "$ENV_FILE"
    fi
    # Actualizar APP_SECRET
    sed -i "s/^APP_SECRET=.*/APP_SECRET=${NEW_APP_SECRET}/g" "$ENV_FILE"
    # Actualizar JWT_PASSPHRASE
    sed -i "s/^JWT_PASSPHRASE=.*/JWT_PASSPHRASE=${NEW_JWT_PASSPHRASE}/g" "$ENV_FILE"
fi

# Aplicar cambios a backend/.env.dev
if [ -f "$ENV_DEV_FILE" ]; then
    log_info "Modificando backend/.env.dev..."
    sed -i "s/^APP_SECRET=.*/APP_SECRET=${NEW_APP_SECRET_DEV}/g" "$ENV_DEV_FILE"
fi

# Aplicar cambios a backend/.env.test
if [ -f "$ENV_TEST_FILE" ]; then
    log_info "Modificando backend/.env.test..."
    # Actualizar DB_HOST (añadir si no existe)
    if grep -q "^DB_HOST=" "$ENV_TEST_FILE"; then
        sed -i "s/^DB_HOST=.*/DB_HOST=${PROJECT_NAME}-database/g" "$ENV_TEST_FILE"
    else
        # Añadir DB_HOST después de USE_SQLITE si no existe
        sed -i "/^USE_SQLITE=/a DB_HOST=${PROJECT_NAME}-database" "$ENV_TEST_FILE"
    fi
    # Actualizar DB_NAME (con sufijo _test)
    sed -i "s/^DB_NAME=.*/DB_NAME=${DB_NAME}_test/g" "$ENV_TEST_FILE"
fi

# Aplicar cambios a .gitlab-ci.yml
if [ -f "$GITLAB_CI_FILE" ]; then
    log_info "Modificando .gitlab-ci.yml..."
    # Actualizar POSTGRES_DB (nombre de BD para el servicio PostgreSQL)
    sed -i "s/POSTGRES_DB: ${DETECTED_DB_NAME}_test/POSTGRES_DB: ${DB_NAME}_test/g" "$GITLAB_CI_FILE"
    # Actualizar DB_NAME (variable de entorno para los tests)
    sed -i "s/DB_NAME: ${DETECTED_DB_NAME}_test/DB_NAME: ${DB_NAME}_test/g" "$GITLAB_CI_FILE"
fi

# Aplicar cambios a virtualhost.conf
if [ -f "$VIRTUALHOST_FILE" ]; then
    log_info "Modificando docker/apache/virtualhost.conf..."
    # Actualizar ServerAlias (buscar línea que contiene ServerAlias y reemplazar todo el valor)
    if [ -n "$DETECTED_SERVER_ALIAS" ] && [ "$DETECTED_SERVER_ALIAS" != "${PROJECT_NAME}.local" ]; then
        sed -i "s/ServerAlias\s\+${DETECTED_SERVER_ALIAS}/ServerAlias ${PROJECT_NAME}.local/g" "$VIRTUALHOST_FILE"
    else
        # Reemplazar cualquier ServerAlias existente (con espacios flexibles)
        sed -i "s/^\(\s*ServerAlias\s\+\)[a-z0-9.-]\+/\1${PROJECT_NAME}.local/g" "$VIRTUALHOST_FILE"
    fi
    # Actualizar SetHandler (buscar línea que contiene SetHandler y reemplazar el nombre del container)
    if [ -n "$DETECTED_PHP_HANDLER" ] && [ "$DETECTED_PHP_HANDLER" != "${PROJECT_NAME}-php" ]; then
        sed -i "s|SetHandler\s\+\"proxy:fcgi://${DETECTED_PHP_HANDLER}:9000\"|SetHandler \"proxy:fcgi://${PROJECT_NAME}-php:9000\"|g" "$VIRTUALHOST_FILE"
    else
        # Reemplazar cualquier nombre de container en SetHandler
        sed -i "s|SetHandler\s\+\"proxy:fcgi://[a-z0-9-]\+:9000\"|SetHandler \"proxy:fcgi://${PROJECT_NAME}-php:9000\"|g" "$VIRTUALHOST_FILE"
    fi
fi

# Aplicar cambios a virtualhost-ssl.conf
if [ -f "$VIRTUALHOST_SSL_FILE" ]; then
    log_info "Modificando docker/apache/virtualhost-ssl.conf..."
    # Actualizar ServerAlias (buscar línea que contiene ServerAlias y reemplazar todo el valor)
    if [ -n "$DETECTED_SERVER_ALIAS" ] && [ "$DETECTED_SERVER_ALIAS" != "${PROJECT_NAME}.local" ]; then
        sed -i "s/ServerAlias\s\+${DETECTED_SERVER_ALIAS}/ServerAlias ${PROJECT_NAME}.local/g" "$VIRTUALHOST_SSL_FILE"
    else
        # Reemplazar cualquier ServerAlias existente (con espacios flexibles)
        sed -i "s/^\(\s*ServerAlias\s\+\)[a-z0-9.-]\+/\1${PROJECT_NAME}.local/g" "$VIRTUALHOST_SSL_FILE"
    fi
    # Actualizar SetHandler (buscar línea que contiene SetHandler y reemplazar el nombre del container)
    if [ -n "$DETECTED_PHP_HANDLER" ] && [ "$DETECTED_PHP_HANDLER" != "${PROJECT_NAME}-php" ]; then
        sed -i "s|SetHandler\s\+\"proxy:fcgi://${DETECTED_PHP_HANDLER}:9000\"|SetHandler \"proxy:fcgi://${PROJECT_NAME}-php:9000\"|g" "$VIRTUALHOST_SSL_FILE"
    else
        # Reemplazar cualquier nombre de container en SetHandler
        sed -i "s|SetHandler\s\+\"proxy:fcgi://[a-z0-9-]\+:9000\"|SetHandler \"proxy:fcgi://${PROJECT_NAME}-php:9000\"|g" "$VIRTUALHOST_SSL_FILE"
    fi
fi

# Aplicar cambios a la carpeta .opencode (reemplazar nombre del container PHP)
OPENCODE_DIR="$PROJECT_ROOT/.opencode"
if [ -d "$OPENCODE_DIR" ]; then
    log_info "Modificando archivos en .opencode..."
    OPENCODE_FILES_CHANGED=0
    while IFS= read -r -d '' file; do
        if grep -qF "${DETECTED_NAME}-php" "$file" 2>/dev/null; then
            sed -i "s/${DETECTED_NAME}-php/${PROJECT_NAME}-php/g" "$file"
            OPENCODE_FILES_CHANGED=$((OPENCODE_FILES_CHANGED + 1))
        fi
    done < <(find "$OPENCODE_DIR" -type f -not -path "*/node_modules/*" -not -path "*/.git/*" -print0)
    if [ "$OPENCODE_FILES_CHANGED" -gt 0 ]; then
        log_info "  ${OPENCODE_FILES_CHANGED} archivo(s) actualizado(s) en .opencode"
    else
        log_warning "  No se encontraron ocurrencias de '${DETECTED_NAME}-php' en .opencode"
    fi
fi

# Aplicar cambios al README.md (actualizar puertos e IP)
if [ -f "$README_FILE" ]; then
    log_info "Modificando README.md..."
    # Actualizar puertos en la tabla de servicios
    sed -i "s/| Apache | [0-9]* (HTTP), [0-9]* (HTTPS)/| Apache | 8${PORT_SUFFIX} (HTTP), 4${PORT_SUFFIX} (HTTPS)/g" "$README_FILE"
    sed -i "s/| PostgreSQL | [0-9]*/| PostgreSQL | 5${PORT_SUFFIX}/g" "$README_FILE"
    sed -i "s/| Mailpit | [0-9]* (Web), [0-9]* (SMTP)/| Mailpit | 18${PORT_SUFFIX} (Web), 11${PORT_SUFFIX} (SMTP)/g" "$README_FILE"
    sed -i "s/| Frontend | [0-9]* |/| Frontend | 7${PORT_SUFFIX} |/g" "$README_FILE"
    sed -i "s/puerto \`[0-9]*\` del host/puerto \`7${PORT_SUFFIX}\` del host/g" "$README_FILE"
    sed -i "s|http://localhost:[0-9]*/app/|http://localhost:7${PORT_SUFFIX}/app/|g" "$README_FILE"
    # Actualizar IP y dominio en la sección de /etc/hosts
    sed -i "s/[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.100 [a-z0-9-]*\.local/${NETWORK_PREFIX}.100 ${PROJECT_NAME}.local/g" "$README_FILE"
    # Actualizar URLs de ejemplo
    sed -i "s|http://[a-z0-9-]*\.local|http://${PROJECT_NAME}.local|g" "$README_FILE"
    sed -i "s|https://[a-z0-9-]*\.local|https://${PROJECT_NAME}.local|g" "$README_FILE"
fi

# Aplicar cambios a frontend/vite.config.js
if [ -f "$VITE_CONFIG_FILE" ]; then
    log_info "Modificando frontend/vite.config.js..."
    # Actualizar proxy target con el nombre del nuevo container Apache
    sed -i "s/${DETECTED_NAME}-apache/${PROJECT_NAME}-apache/g" "$VITE_CONFIG_FILE"
    # Actualizar puerto de Vite si el sufijo cambió
    if [ "$DETECTED_PORT_SUFFIX" != "$PORT_SUFFIX" ]; then
        sed -i "s/port: 7${DETECTED_PORT_SUFFIX}/port: 7${PORT_SUFFIX}/g" "$VITE_CONFIG_FILE"
    fi
fi

echo ""
echo -e "${GREEN}${BOLD}✓ Configuración completada exitosamente${NC}"
echo ""
log_warning "Revisa los archivos antes de ejecutar 'make start'"
log_info "Para usar el dominio ${PROJECT_NAME}.local, añade a /etc/hosts:"
echo "    ${NETWORK_PREFIX}.100 ${PROJECT_NAME}.local"
