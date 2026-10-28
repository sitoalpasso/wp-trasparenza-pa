<?php
/**
 * Plugin Name:       Trasparenza PA
 * Plugin URI:        https://github.com/sitoalpasso/wp-trasparenza-pa
 * Description:       Obblighi di pubblicazione della sezione Amministrazione trasparente come dati strutturati conformi agli schemi standard.
 * Version:           0.1.0-alpha
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  conformita-core
 * Author:            sitoalpasso
 * Author URI:        https://github.com/sitoalpasso
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       trasparenza-pa
 * Domain Path:       /languages
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

defined( 'ABSPATH' ) || exit;

/**
 * Versione del componente.
 */
const VERSIONE = '0.1.0-alpha';

/**
 * Versione minima di WordPress dichiarata.
 */
const WORDPRESS_MINIMO = '6.5';

/**
 * Versione minima di PHP dichiarata.
 */
const PHP_MINIMO = '8.1';

/**
 * Versione dell'interfaccia di conformita-core richiesta.
 *
 * L'intestazione `Requires Plugins` garantisce la presenza del componente
 * comune e non la sua versione, perche accetta slug e non vincoli di versione:
 * il vincolo di versione vive qui e si verifica a ogni avvio.
 */
const CORE_API_RICHIESTA = '1.7.0';

/**
 * Nome visibile del componente, come compare negli avvisi in amministrazione.
 */
const NOME = 'Trasparenza PA';

/**
 * Nome visibile del componente comune da cui questo dipende.
 *
 * E un dato e non una frase: gli avvisi lo ricevono come parametro, e le prove
 * verificano che l'avviso nomini il componente da installare senza dipendere da
 * come la frase e scritta.
 */
const CORE_NOME = 'Conformita Core';

/**
 * Identificativo della sezione dichiarata al meccanismo comune.
 *
 * Lettere minuscole, cifre e trattino basso: e l'insieme di caratteri che il
 * registro delle sezioni ammette.
 */
const SEZIONE = 'amministrazione_trasparente';

/**
 * Politica di indicizzazione della sezione.
 *
 * Consentita, e non configurabile. L'art. 9 del d.lgs. 33/2013 vieta filtri e
 * soluzioni tecniche che impediscano ai motori di ricerca di indicizzare la
 * sezione: un'opzione che permettesse di spegnere l'indicizzazione sarebbe uno
 * strumento per violare la norma. E l'opposto della politica della pubblicita
 * legale, ed e il motivo per cui il meccanismo comune non ha un valore
 * predefinito.
 */
const INDICIZZAZIONE = 'consentita';

/**
 * Politica di scadenza della sezione.
 *
 * Archivio: alla scadenza dell'obbligo il dato esce dalla sezione ma non si
 * cancella, e resta ottenibile con l'accesso civico (art. 8 c. 3 del d.lgs.
 * 33/2013).
 */
const SCADENZA = 'archivio';

/**
 * Percorso del file principale del plugin.
 */
const FILE_PRINCIPALE = __FILE__;

/**
 * File dell'albero delle voci della sezione.
 *
 * E un dato normativo versionato, non codice: si sostituisce con una nuova
 * versione quando cambia l'Allegato A del d.lgs. 33/2013. Finche manca, la
 * sezione non si mostra e chi amministra il sito vede un avviso.
 */
const FILE_ALBERATURA = __DIR__ . '/dati/alberatura/corrente.json';

require_once __DIR__ . '/src/class-avvio.php';
require_once __DIR__ . '/src/class-alberatura.php';
require_once __DIR__ . '/src/class-stati-obbligo.php';
require_once __DIR__ . '/src/class-pagina-pubblica.php';
require_once __DIR__ . '/src/class-permessi.php';
require_once __DIR__ . '/src/class-schermata-stati.php';

add_action( 'plugins_loaded', array( Avvio::class, 'da_plugins_loaded' ) );

PaginaPubblica::aggancia();
SchermataStati::aggancia();

add_action( 'init', array( Permessi::class, 'da_init' ) );
add_action( 'admin_notices', array( Permessi::class, 'mostra_avviso' ) );
