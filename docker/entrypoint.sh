#!/bin/sh
# Container roles for the Metribay image: web, worker, scheduler, demo, key, healthcheck.
# Anything else runs as a command, for example: php artisan metribay:install. See docs/DOCKER.md.
set -eu
cd /app/standalone
data=/var/lib/metribay

prepare() {
    # The healthcheck probes /up only for web roles; the image's start period covers migrations.
    echo "$1" > /tmp/metribay-role
    # A restarted container keeps its writable layer; never reuse an old configuration cache.
    rm -f bootstrap/cache/*.php
    for path in app/private app/public framework/cache/data framework/sessions framework/views logs; do
        mkdir -p "$data/storage/$path"
    done
    if [ -z "${APP_KEY:-}" ]; then
        echo 'APP_KEY is not set. Create one with "docker run --rm ghcr.io/worbee/metribay key" and keep it with your backups.' >&2
        exit 1
    fi
    if [ "${DB_CONNECTION:-sqlite}" = sqlite ] && [ ! -e "$DB_DATABASE" ]; then
        (umask 077 && touch "$DB_DATABASE")
    fi
    php artisan optimize --quiet
}

serve() {
    if [ "${METRIBAY_MIGRATE:-true}" = true ]; then
        php artisan migrate --force --no-interaction
    fi
    exec frankenphp run --config /etc/frankenphp/Caddyfile
}

case "${1:-web}" in
    web)
        prepare web
        serve
        ;;
    worker)
        prepare worker
        exec php artisan queue:work --sleep=3 --timeout=900 --max-time=3600 --memory=256
        ;;
    scheduler)
        prepare scheduler
        exec php artisan schedule:work
        ;;
    demo)
        # Fictional sample portfolio (docs/DEMO_DATA.md), rebuilt on every start. Not for real data.
        export DB_CONNECTION=sqlite DB_DATABASE="$data/demo.sqlite" METRIBAY_COLLECTION_ENABLED=false METRIBAY_ANALYTICS_ENABLED=false
        rm -f "$DB_DATABASE" "$DB_DATABASE-wal" "$DB_DATABASE-shm"
        prepare demo
        php artisan config:clear --quiet
        APP_ENV=local php artisan metribay:demo --fresh
        php artisan optimize --quiet
        echo 'Sign in with demo@metribay.example / sample-portfolio-only'
        serve
        ;;
    key)
        php -r 'echo "base64:", base64_encode(random_bytes(32)), PHP_EOL;'
        ;;
    healthcheck)
        role=$(cat /tmp/metribay-role 2>/dev/null || true)
        if [ "$role" = web ] || [ "$role" = demo ]; then
            exec curl -fsS -o /dev/null http://127.0.0.1:8080/up
        fi
        ;;
    *)
        exec "$@"
        ;;
esac
