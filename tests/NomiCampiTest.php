<?php
/**
 * Prova statica sui nomi dei campi degli schemi: riga T-32.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use WP_UnitTestCase;

/**
 * I campi vivono nei file di dati, mai nel PHP del componente.
 */
class NomiCampiTest extends WP_UnitTestCase {

	/**
	 * Nomi delle proprieta dichiarate in uno schema, a qualunque profondita.
	 *
	 * @param mixed $nodo Parte dello schema.
	 * @return array<int, string>
	 */
	private function nomi( $nodo ): array {
		$nomi = array();

		if ( ! is_array( $nodo ) ) {
			return $nomi;
		}

		foreach ( $nodo as $chiave => $valore ) {
			if ( 'properties' === $chiave && is_array( $valore ) ) {
				$nomi = array_merge( $nomi, array_map( 'strval', array_keys( $valore ) ) );
			}

			$nomi = array_merge( $nomi, $this->nomi( $valore ) );
		}

		return $nomi;
	}

	/**
	 * T-32: nessun nome di campo, nemmeno in un commento, nei sorgenti PHP.
	 */
	public function test_t32_nomi_dei_campi_fuori_dal_php(): void {
		$radice = dirname( __DIR__ );
		$schemi = array_merge(
			(array) glob( $radice . '/schemas/*.json' ),
			(array) glob( __DIR__ . '/dati/schemi/*.schema.json' )
		);
		$nomi   = array();

		foreach ( $schemi as $file ) {
			$testo  = (string) file_get_contents( $file );
			$testo  = str_starts_with( $testo, "\xEF\xBB\xBF" ) ? substr( $testo, 3 ) : $testo;
			$propri = $this->nomi( json_decode( $testo, true ) );

			// Un file che non da nomi renderebbe la prova vuota senza farsi notare.
			if ( str_starts_with( $file, $radice . '/schemas/' ) && str_ends_with( $file, '.schema.json' ) ) {
				$this->assertNotEmpty( $propri, 'Nessun nome raccolto da ' . basename( $file ) );
			}

			$nomi = array_merge( $nomi, $propri );
		}

		$nomi = array_unique( $nomi );

		$this->assertContains( 'zafferanoTitolo', $nomi, 'La raccolta dei nomi deve trovare almeno quelli degli schemi finti.' );

		$ufficiali = array_filter( (array) glob( $radice . '/schemas/*.schema.json' ) );

		$this->assertGreaterThanOrEqual( 4, count( $ufficiali ), 'La raccolta deve leggere gli schemi ufficiali.' );
		$this->assertContains( 'orgPubblicheAmministrazioni', $nomi, 'La raccolta dei nomi deve trovare anche quelli degli schemi ufficiali.' );

		$sorgenti = array_merge( array( $radice . '/trasparenza-pa.php' ), (array) glob( $radice . '/src/*.php' ) );

		foreach ( $sorgenti as $sorgente ) {
			$testo = (string) file_get_contents( $sorgente );

			foreach ( $nomi as $nome ) {
				$this->assertDoesNotMatchRegularExpression(
					'/\b' . preg_quote( $nome, '/' ) . '\b/',
					$testo,
					"Il campo {$nome} compare in " . basename( $sorgente )
				);
			}
		}
	}
}
