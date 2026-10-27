<?php
/**
 * Prove del catalogo degli schemi: righe T-27 e T-28.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\CatalogoSchemi;
use WP_UnitTestCase;

/**
 * Un catalogo si accetta tutto o niente.
 */
class CatalogoSchemiTest extends WP_UnitTestCase {

	use SchemiFinti;

	/**
	 * T-27: il catalogo valido si carica, con le versioni in ordine di entrata in vigore.
	 */
	public function test_t27_catalogo_valido(): void {
		$catalogo = CatalogoSchemi::carica( $this->catalogo_finto() );

		$this->assertInstanceOf( CatalogoSchemi::class, $catalogo );
		$this->assertSame( array( 'finto' ), $catalogo->obblighi() );

		// Nel file la 1.1 viene prima: l'ordine e quello dell'entrata in vigore.
		$versioni = $catalogo->versioni( 'finto' );

		$this->assertSame( array( '1.0', '1.1' ), array_column( $versioni, 'versione' ) );
		$this->assertSame( '2019-12-01', $versioni[0]['approvato_il'] );
		$this->assertSame( '2020-01-01', $versioni[0]['in_vigore_dal'] );
		$this->assertSame( 'finto-v1.0.schema.json', $versioni[0]['file'] );
		$this->assertSame( 'Schema finto, versione 1.0', $versioni[0]['fonte']['titolo'] );

		// Il file comune comincia con il segno BOM, come i file ufficiali.
		$this->assertStringStartsWith( "\xEF\xBB\xBF", (string) file_get_contents( dirname( $this->catalogo_finto() ) . '/comuni-finto-v1.0.schema.json' ) );
		$this->assertArrayHasKey( self::$base_finta . '/comuni-finto-v1.0.schema.json', $catalogo->contenuti() );
	}

	/**
	 * T-28: ogni difetto ha il suo codice, e nessuno produce un catalogo.
	 */
	public function test_t28_difetti_rifiutati_con_codici_distinti(): void {
		$casi = array(
			'trasparenza_catalogo_assente'            => CatalogoSchemi::carica( '/percorso/che/non/esiste.json' ),
			'trasparenza_catalogo_illeggibile'        => CatalogoSchemi::carica( $this->catalogo_guasto( '{ non e json' ) ),
			'trasparenza_catalogo_formato'            => $this->guasta(
				static function ( array $c ): array {
					$c['formato'] = 2;
					return $c;
				}
			),
			'trasparenza_catalogo_incompleto'         => $this->guasta(
				static function ( array $c ): array {
					$c['schemi'] = array();
					return $c;
				}
			),
			'trasparenza_catalogo_versione_doppia'    => $this->guasta(
				static function ( array $c ): array {
					$c['schemi'][1]['versione'] = '1.1';
					return $c;
				}
			),
			'trasparenza_catalogo_id_doppio'          => $this->guasta(
				static function ( array $c ): array {
					$c['comuni'][] = array(
						'file'  => 'finto-v1.0.schema.json',
						'fonte' => $c['comuni'][0]['fonte'],
					);
					return $c;
				}
			),
			'trasparenza_catalogo_data'               => $this->guasta(
				static function ( array $c ): array {
					$c['schemi'][0]['in_vigore_dal'] = '2099-02-30';
					return $c;
				}
			),
			'trasparenza_catalogo_fonte'              => $this->guasta(
				static function ( array $c ): array {
					unset( $c['schemi'][0]['fonte']['indirizzo'] );
					return $c;
				}
			),
			'trasparenza_catalogo_file_assente'       => $this->guasta(
				static function ( array $c ): array {
					$c['schemi'][0]['file'] = 'manca.schema.json';
					return $c;
				}
			),
			'trasparenza_catalogo_schema_illeggibile' => $this->guasta(
				static function ( array $c ): array {
					$c['schemi'][0]['file'] = 'rotto.schema.json';
					return $c;
				},
				array( 'rotto.schema.json' => 'non e uno schema' )
			),
			'trasparenza_catalogo_rimando'            => $this->guasta(
				static function ( array $c ): array {
					$c['comuni'] = array();
					return $c;
				}
			),
		);

		$this->assertInstanceOf( CatalogoSchemi::class, $this->guasta( null ), 'La base dei guasti deve essere valida.' );

		foreach ( $casi as $atteso => $esito ) {
			$this->assertWPError( $esito, "Il caso {$atteso} deve essere rifiutato." );
			$this->assertSame( $atteso, $esito->get_error_code() );
		}
	}
}
