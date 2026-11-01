<?php
/**
 * La mappa del sito delle voci della sezione.
 *
 * Riga di collaudo T-153.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

defined( 'ABSPATH' ) || exit;

/**
 * Un elenco solo, con gli indirizzi di `IndicizzazioneVoci::indirizzi()`.
 * Con circa novanta voci per pagina sta largamente nel limite di un elenco.
 */
final class MappaVoci extends \WP_Sitemaps_Provider {

	/**
	 * Nome e tipo.
	 */
	public function __construct() {
		$this->name        = IndicizzazioneVoci::FORNITORE;
		$this->object_type = IndicizzazioneVoci::FORNITORE;
	}

	/**
	 * Gli indirizzi di una pagina dell'elenco.
	 *
	 * @param int    $numero  Numero della pagina dell'elenco.
	 * @param string $sottotipo Sottotipo, non usato.
	 * @return array<int, array{loc: string}>
	 */
	public function get_url_list( $numero, $sottotipo = '' ) {
		unset( $sottotipo );

		if ( 1 !== (int) $numero ) {
			return array();
		}

		return array_map(
			static function ( string $indirizzo ): array {
				return array( 'loc' => $indirizzo );
			},
			IndicizzazioneVoci::indirizzi()
		);
	}

	/**
	 * Quante pagine ha l'elenco: una, o nessuna se non c'e niente da elencare.
	 *
	 * @param string $sottotipo Sottotipo, non usato.
	 * @return int
	 */
	public function get_max_num_pages( $sottotipo = '' ) {
		unset( $sottotipo );

		return array() === IndicizzazioneVoci::indirizzi() ? 0 : 1;
	}
}
