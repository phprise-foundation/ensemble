#!/usr/bin/env bash
set -euo pipefail

target=/home/esdras/src/phrise

if [ "${EUID}" -ne 0 ]; then
    echo "run with sudo: sudo $0" >&2
    exit 1
fi

if [ -e "${target}" ] && [ -n "$(find "${target}" -mindepth 1 -maxdepth 1 -print -quit 2>/dev/null)" ]; then
    echo "${target} is not empty; remove its contents deliberately before locking" >&2
    exit 1
fi

install -d -o root -g root -m 555 "${target}"
printf '%s\n' \
    'Caminho inválido.' \
    '' \
    'O projeto é PHPRise (PHP + Rise, dois P):' \
    '/home/esdras/src/phprise/multi-agent' \
    '' \
    'Não crie, clone, mova nem grave nada aqui.' \
    > "${target}/LEIA-ME.md"
chown root:root "${target}/LEIA-ME.md"
chmod 444 "${target}/LEIA-ME.md"

if chattr +i "${target}" 2>/dev/null; then
    echo "locked: ${target} (root:root, 555, immutable)"
    exit 0
fi

echo "locked: ${target} (root:root, 555; immutable flag unsupported by this filesystem)"
