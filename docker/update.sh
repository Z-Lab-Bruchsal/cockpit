#!/bin/sh
# Rebuilds the image from the current working copy and restarts the stack
# from docker-compose.yml. Run it on the host, not inside the container:
#
#   docker/update.sh          # rebuild + restart
#   docker/update.sh --pull   # git pull first, then rebuild + restart
#
# The image is built before the old containers are stopped, so a failed
# build leaves the running installation untouched.
set -e

cd "$(dirname "$0")/.."

if [ "$1" = "--pull" ]; then
    echo "==> git pull"
    git pull --ff-only
fi

echo "==> Image bauen"
docker compose build

echo "==> Container stoppen"
docker compose down --remove-orphans

echo "==> Container starten (wartet, bis die App gesund ist)"
if ! docker compose up -d --wait; then
    echo "!! Start fehlgeschlagen – letzte Log-Zeilen der App:" >&2
    docker compose logs --tail=50 app >&2
    exit 1
fi

docker compose ps
echo "==> Fertig"
