#!/usr/bin/env bash
set -euo pipefail

# Project directory
cd "$(dirname "${BASH_SOURCE[0]}")/.."
PROJECT_DIR="$PWD"

# Check prerequisites
if ! docker compose version >/dev/null 2>&1; then
    echo "ERROR: Docker Compose is unavailable."
    exit 1
fi

if [[ -f artisan || -f composer.json || -f .env ]]; then
    echo "ERROR: Project is already initialized."
    exit 1
fi

# Match Docker user with WSL user
export APP_UID="$(id -u)"
export APP_GID="$(id -g)"

echo "Building PHP image..."
docker compose --env-file .env.example build app

# Install Laravel into temporary directory
TEMP_DIR="$(mktemp -d "$PROJECT_DIR/.laravel-setup.XXXXXX")"
TEMP_NAME="${TEMP_DIR##*/}"

trap 'rm -rf -- "$TEMP_DIR"' EXIT

echo "Installing Laravel 13..."
docker compose --env-file .env.example run --rm --no-deps app \
    composer create-project laravel/laravel "$TEMP_NAME" "^13.0" \
    --no-interaction --no-scripts

# Merge Laravel and Docker environment variables
echo "Preparing environment configuration..."

awk -F= '
FNR == NR {
    if ($0 ~ /^[A-Za-z_][A-Za-z0-9_]*=/) {
        key = $1
        values[key] = substr($0, index($0, "=") + 1)
        order[++count] = key
    }
    next
}
{
    if ($0 ~ /^[A-Za-z_][A-Za-z0-9_]*=/) {
        key = $1
        if (key in values) {
            print key "=" values[key]
            seen[key] = 1
            next
        }
    }
    print
}
END {
    print ""
    print "# Docker / local development"

    for (i = 1; i <= count; i++) {
        key = order[i]
        if (!(key in seen)) {
            print key "=" values[key]
        }
    }
}
' .env.example "$TEMP_DIR/.env.example" > "$TEMP_DIR/.combined.env"

# Move Laravel files without overwriting template files
shopt -s dotglob nullglob

for item in "$TEMP_DIR"/*; do
    name="${item##*/}"

    case "$name" in
        .env.example|.combined.env|.git)
            continue
            ;;
    esac

    if [[ -e "$PROJECT_DIR/$name" ]]; then
        case "$name" in
            README.md|.gitignore)
                continue
                ;;
            *)
                echo "ERROR: File already exists: $name"
                exit 1
                ;;
        esac
    fi

    mv "$item" "$PROJECT_DIR/"
done

shopt -u dotglob nullglob

mv "$TEMP_DIR/.combined.env" .env.example

# Create local environment
cp .env.example .env

sed -i "s/^APP_UID=.*/APP_UID=${APP_UID}/" .env
sed -i "s/^APP_GID=.*/APP_GID=${APP_GID}/" .env

# Ensure local files are ignored by Git
touch .gitignore

for pattern in "/.env" "/vendor/" "/node_modules/" "/.idea/"; do
    grep -qxF "$pattern" .gitignore || echo "$pattern" >> .gitignore
done

# Start containers
echo "Starting Docker containers..."
docker compose up -d

# Initialize Laravel
docker compose exec -T app php artisan package:discover
docker compose exec -T app php artisan key:generate
docker compose exec -T app php artisan migrate --force

echo ""
echo "Laravel project initialized successfully!"
echo "Application: http://localhost:8080"
