<?php
/**
 * Testo scritto dagli addetti, preparato per la pagina.
 *
 * Righe di collaudo T-110 e T-115..T-117.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

defined( 'ABSPATH' ) || exit;

/**
 * Escaping che il browser rilegge identico al testo conservato.
 */
final class Testo {

	/**
	 * Il testo con ogni carattere speciale ricodificato, anche dentro un'entita gia scritta.
	 *
	 * Al posto di esc_attr() ed esc_html(), che lasciano com'e un'entita gia scritta: "&amp;"
	 * scritto come testo tornerebbe dal browser come "&", e la partenza di un modulo non
	 * sarebbe piu uguale al dato conservato. Vale per l'attributo value e per il testo fra i tag.
	 *
	 * @param string $testo Testo.
	 * @return string
	 */
	public static function esatto( string $testo ): string {
		return htmlspecialchars( $testo, ENT_QUOTES, 'UTF-8', true );
	}
}
