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
 * Percorso del file principale del plugin.
 */
const FILE_PRINCIPALE = __FILE__;
