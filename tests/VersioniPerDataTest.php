<?php
/**
 * Prove delle versioni riconosciute anche dalla data: riga T-37.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\CatalogoSchemi;
use TrasparenzaPa\MotoreSchemi;
use WP_UnitTestCase;

/**
 * Due "1.0" dello stesso obbligo, con lo stesso identificativo e regole diverse.
 *
 * La 1.0 ripubblicata cambia una regola nel suo file (la quantita ammette
 * valori negativi fino a -10 e non oltre 10) e una nel file comune, anch'esso
 * ripubblicato con lo stesso identificativo (il titolo non oltre 5 caratteri).
 */
class VersioniPerDataTest extends WP_UnitTestCase {

	use SchemiFinti;

	/**
	 * Codice della prima pubblicazione.
	 *
	 * @var string
	 */
	private static $prima = '1.0@2019-12-01';

	/**
	 * Codice della ripubblicazione.
	 *
	 * @var string
	 */
	private static $ripubblicata = '1.0@2026-06-10';

	/**
	 * Motore sul catalogo con la versione ripubblicata.
	 *
	 * @var MotoreSchemi
	 */
	private $motore;

	/**
	 * Motore pronto.
	 */
	public function set_up(): void {
		parent::set_up();

		$catalogo = CatalogoSchemi::carica( __DIR__ . '/dati/schemi/catalogo-ripubblicato.json' );

		$this->assertInstanceOf( CatalogoSchemi::class, $catalogo, 'Le due 1.0 devono poter stare nello stesso catalogo.' );

		$this->motore = new MotoreSchemi( $catalogo );
	}

	/**
	 * I percorsi e le regole dei difetti di un esito.
	 *
	 * @param true|\WP_Error $esito Esito della validazione.
	 * @return array<int, string>
	 */
	private static function difetti( $esito ): array {
		if ( ! is_wp_error( $esito ) ) {
			return array();
		}

		return array_map(
			static function ( array $d ): string {
				return $d['percorso'] . ' ' . $d['regola'];
			},
			(array) $esito->get_error_data()
		);
	}

	/**
	 * T-37: la versione si riconosce da numero e data, e si sceglie per data.
	 */
	public function test_t37_codice_con_la_data(): void {
		$this->assertSame( array( self::$prima, self::$ripubblicata ), array_column( $this->motore->catalogo()->versioni( 'finto' ), 'codice' ) );
		$this->assertSame( self::$ripubblicata, $this->motore->versione_in_vigore( 'finto' ) );
		$this->assertSame( self::$prima, $this->motore->versione_in_vigore( 'finto', '2025-01-01' ) );

		$esito = $this->motore->valida( 'finto', '1.0', '{"zafferanoTitolo":"x","zafferanoQuantita":1}' );

		$this->assertWPError( $esito );
		$this->assertSame( 'trasparenza_schema_sconosciuto', $esito->get_error_code(), 'Il solo numero non basta a dire quale versione.' );
	}

	/**
	 * T-37: ogni versione si controlla solo con i suoi file.
	 */
	public function test_t37_ogni_versione_con_i_suoi_file(): void {
		// Valido per la prima, non per la ripubblicata: regola del file comune e regola del file dello schema.
		$vecchio = '{"zafferanoTitolo":"Titolo lungo","zafferanoQuantita":50}';

		$this->assertTrue( $this->motore->valida( 'finto', self::$prima, $vecchio ) );

		$difetti = self::difetti( $this->motore->valida( 'finto', self::$ripubblicata, $vecchio ) );

		$this->assertContains( '/zafferanoTitolo maxLength', $difetti, 'La ripubblicata deve usare il suo file comune.' );
		$this->assertContains( '/zafferanoQuantita maximum', $difetti, 'La ripubblicata deve usare il suo file.' );

		// E viceversa.
		$nuovo = '{"zafferanoTitolo":"Corto","zafferanoQuantita":-3}';

		$this->assertTrue( $this->motore->valida( 'finto', self::$ripubblicata, $nuovo ) );
		$this->assertContains( '/zafferanoQuantita minimum', self::difetti( $this->motore->valida( 'finto', self::$prima, $nuovo ) ) );

		// Il risultato non dipende dall'ordine in cui le versioni si usano.
		$this->assertTrue( $this->motore->valida( 'finto', self::$prima, $vecchio ) );
	}

	/**
	 * T-37: ogni versione si dice a parole con la sua data.
	 */
	public function test_t37_versione_a_parole(): void {
		update_option( 'date_format', 'd/m/Y' );

		$this->assertSame( 'versione 1.0 del 10/06/2026', $this->motore->descrivi( 'finto', self::$ripubblicata ) );
		$this->assertSame( 'versione 1.0 del 01/12/2019', $this->motore->descrivi( 'finto', self::$prima ) );
		$this->assertNull( $this->motore->descrivi( 'finto', '1.0' ) );
	}

	/**
	 * T-37: senza l'elenco dei file comuni, i due file comuni gemelli si scontrano.
	 */
	public function test_t37_senza_elenco_comuni_identificativo_doppio(): void {
		$catalogo = json_decode( (string) file_get_contents( __DIR__ . '/dati/schemi/catalogo-ripubblicato.json' ), true );

		unset( $catalogo['schemi'][0]['comuni'] );

		$esito = CatalogoSchemi::carica( $this->catalogo_guasto( (string) wp_json_encode( $catalogo ) ) );

		$this->assertWPError( $esito );
		$this->assertSame( 'trasparenza_catalogo_id_doppio', $esito->get_error_code() );
	}
}
