#!/usr/bin/env bash
# Preleva conformita-core nella cartella dei plugin dell'installazione di prova.
#
# La revisione e' fissata e non segue un ramo: cosi' un cambiamento del componente
# comune arriva qui con una modifica deliberata di questo file, leggibile in un diff,
# e non da solo fra due esecuzioni della verifica continua.
#
# Non e' fissata a una release perche' non ce n'e' una utilizzabile: l'unica
# pubblicata precede la costante che espone la versione dell'interfaccia, quindi a
# quella revisione il componente comune non dichiara nessuna versione e la guardia di
# compatibilita' non avrebbe niente da leggere.
#
# La cartella di destinazione si chiama come lo slug e non come il repository:
# l'intestazione Requires Plugins di WordPress cerca lo slug, e con un nome diverso
# la dipendenza non verrebbe riconosciuta.

set -euo pipefail

CORE_REVISIONE="${CORE_REVISIONE:-a80d053a05e272ca96f7a8f4c200ab09722bdd5a}"
CORE_ORIGINE="${CORE_ORIGINE:-https://github.com/sitoalpasso/wp-conformita-core.git}"
WP_CORE_DIR="${WP_CORE_DIR:-/tmp/wordpress}"
DESTINAZIONE="${WP_CORE_DIR}/wp-content/plugins/conformita-core"

if [ -d "${DESTINAZIONE}/.git" ]; then
	echo "Componente comune gia' presente in ${DESTINAZIONE}."
else
	mkdir -p "$( dirname "${DESTINAZIONE}" )"
	git clone --quiet "${CORE_ORIGINE}" "${DESTINAZIONE}"
fi

git -C "${DESTINAZIONE}" fetch --quiet origin "${CORE_REVISIONE}" 2>/dev/null || git -C "${DESTINAZIONE}" fetch --quiet origin
git -C "${DESTINAZIONE}" checkout --quiet "${CORE_REVISIONE}"

VERSIONE_API="$( grep -oE "CONFORMITA_CORE_VERSIONE_API', '[0-9.]+'" "${DESTINAZIONE}/conformita-core.php" | grep -oE "[0-9]+\.[0-9]+\.[0-9]+" )"

if [ -z "${VERSIONE_API}" ]; then
	echo "La revisione ${CORE_REVISIONE} non espone nessuna versione di interfaccia." >&2
	exit 1
fi

echo "Componente comune alla revisione ${CORE_REVISIONE}, interfaccia ${VERSIONE_API}, in ${DESTINAZIONE}."
