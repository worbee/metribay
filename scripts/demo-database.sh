#!/bin/sh
# Rebuilds the ignored sample database standalone/database/demo.sqlite (docs/DEMO_DATA.md).
# Extra arguments go to metribay:demo, for example --seed=7 or --days=200.
set -eu
cd "$(dirname "$0")/../standalone"
DB_CONNECTION=sqlite DB_DATABASE="$PWD/database/demo.sqlite" METRIBAY_COLLECTION_ENABLED=false \
    php artisan metribay:demo --fresh "$@"
