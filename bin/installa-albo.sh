#!/usr/bin/env bash
# Preleva l'albo pretorio nella cartella dei plugin dell'installazione di prova,
# per la suite della convivenza (composer test:con-albo).
#
# La revisione e' fissata e non segue un ramo, come per il componente comune: un
# cambiamento dell'albo arriva in queste prove con una modifica deliberata di
# questo file, leggibile in un diff, e non da solo fra due esecuzioni della
# verifica continua. La revisione deve chiedere la stessa versione di interfaccia
# del componente comune procurato da bin/installa-core.sh.
#
# La cartella di destinazione si chiama come lo slug dell'albo.

set -euo pipefail

ALBO_REVISIONE="${ALBO_REVISIONE:-74e9b8f9f3014cdab66f28029c1bf971a03a1822}"
ALBO_ORIGINE="${ALBO_ORIGINE:-https://github.com/sitoalpasso/wp-albo-pretorio.git}"
WP_CORE_DIR="${WP_CORE_DIR:-/tmp/wordpress}"
DESTINAZIONE="${WP_CORE_DIR}/wp-content/plugins/albo-pretorio-pa"

if [ -d "${DESTINAZIONE}/.git" ]; then
	echo "Albo pretorio gia' presente in ${DESTINAZIONE}."
else
	mkdir -p "$( dirname "${DESTINAZIONE}" )"
	git clone --quiet "${ALBO_ORIGINE}" "${DESTINAZIONE}"
fi

git -C "${DESTINAZIONE}" fetch --quiet origin "${ALBO_REVISIONE}" 2>/dev/null || git -C "${DESTINAZIONE}" fetch --quiet origin
git -C "${DESTINAZIONE}" checkout --quiet "${ALBO_REVISIONE}"

echo "Albo pretorio alla revisione ${ALBO_REVISIONE} in ${DESTINAZIONE}."
