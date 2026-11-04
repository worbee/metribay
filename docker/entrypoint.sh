#!/bin/sh
# Container roles for the Metribay image: web, worker, scheduler, demo, demo-refresh, key, healthcheck.
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

# Builds the fictional portfolio into a fresh SQLite file that the demo then only reads.
build_demo() {
    rm -f "$1" "$1-wal" "$1-shm"
    (umask 022 && touch "$1")
    # A separate, uncached configuration: the served demo's cache points at the read-only file.
    env APP_CONFIG_CACHE=/tmp/metribay-demo-build-config.php APP_ENV=local DB_CONNECTION=sqlite DB_DATABASE="$1" DB_READ_ONLY=false DB_JOURNAL_MODE=delete \
        METRIBAY_COLLECTION_ENABLED=false METRIBAY_DEMO=false CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync \
        php artisan metribay:demo --fresh --no-interaction
    chmod 0444 "$1"
}

demo_environment() {
    export DB_CONNECTION=sqlite DB_DATABASE="$data/demo.sqlite" DB_READ_ONLY=true DB_JOURNAL_MODE=delete \
        METRIBAY_DEMO=true METRIBAY_COLLECTION_ENABLED=false METRIBAY_ANALYTICS_ENABLED="${METRIBAY_ANALYTICS_ENABLED:-false}" \
        SESSION_DRIVER=file CACHE_STORE=file QUEUE_CONNECTION=sync MAIL_MAILER=log
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
        # Read-only sample portfolio (docs/DEMO.md): automatic sign-in, every change refused, rebuilt on start.
        demo_environment
        prepare demo
        build_demo "$data/demo.sqlite"
        php artisan optimize --quiet
        echo 'Demo ready: open the server address and you are signed in as the sample owner.'
        METRIBAY_MIGRATE=false serve
        ;;
    demo-refresh)
        # Rebuild the demo database next to the served one, then swap it in. For a nightly cron in a demo container.
        build_demo "$data/next-demo.sqlite"
        mv -f "$data/next-demo.sqlite" "$data/demo.sqlite"
        echo 'Demo data rebuilt.'
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
