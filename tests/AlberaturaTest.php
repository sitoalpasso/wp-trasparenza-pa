<?php
/**
 * Prove del caricamento dell'albero: righe T-11, T-12 e T-54.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Alberatura;
use WP_UnitTestCase;

/**
 * Un albero si accetta tutto o niente.
 */
class AlberaturaTest extends WP_UnitTestCase {

	/**
	 * File temporanei da togliere a fine prova.
	 *
	 * @var array<int, string>
	 */
	private $temporanei = array();

	/**
	 * Toglie i file temporanei.
	 */
	public function tear_down(): void {
		foreach ( $this->temporanei as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file );
			}
		}

		parent::tear_down();
	}

	/**
	 * Un albero minimo valido, da guastare un difetto alla volta.
	 *
	 * @return array<string, mixed>
	 */
	private function valido(): array {
		return array(
			'formato'  => 1,
			'versione' => 'prova',
			'diciture' => array(
				'non_applicabile' => 'Dicitura uno',
				'sospeso'         => 'Dicitura due',
			),
			'nodi'     => array(
				array(
					'id'          => 'r',
					'nome'        => 'Amministrazione trasparente',
					'padre'       => null,
					'riferimento' => '',
				),
				array(
					'id'          => 'a',
					'nome'        => 'A',
					'padre'       => 'r',
					'riferimento' => '',
				),
				array(
					'id'          => 'b',
					'nome'        => 'B',
					'padre'       => 'a',
					'riferimento' => '',
				),
			),
		);
	}

	/**
	 * Scrive un file temporaneo e ne restituisce il percorso.
	 *
	 * @param string $contenuto Contenuto.
	 * @return string
	 */
	private function file_temporaneo( string $contenuto ): string {
		$file = wp_tempnam( 'albero' );

		file_put_contents( $file, $contenuto );

		$this->temporanei[] = $file;

		return $file;
	}

	/**
	 * T-11: il file valido si carica, con la radice e l'ordine del file.
	 */
	public function test_t11_file_valido(): void {
		$albero = Alberatura::carica( __DIR__ . '/dati/albero-finto.json' );

		$this->assertInstanceOf( Alberatura::class, $albero );
		$this->assertSame( 'Amministrazione trasparente', $albero->voce( $albero->radice() )['nome'] );
		$this->assertSame( 'prova-1', $albero->versione() );

		// Ordine del file, non alfabetico: Zeta viene prima di Alfa.
		$this->assertSame( array( 'zeta', 'alfa' ), $albero->figli( 'radice' ) );
		$this->assertSame( array( 'zeta_uno', 'zeta_due' ), $albero->figli( 'zeta' ) );
		$this->assertSame( array( 'radice', 'zeta', 'zeta_uno' ), $albero->percorso( 'zeta_uno' ) );
	}

	/**
	 * T-12: ogni difetto ha il suo codice, e nessuno produce un albero.
	 */
	public function test_t12_difetti_rifiutati_con_codici_distinti(): void {
		$casi = array(
			'trasparenza_albero_assente'     => Alberatura::carica( '/percorso/che/non/esiste.json' ),
			'trasparenza_albero_illeggibile' => Alberatura::carica( $this->file_temporaneo( '{ non e json' ) ),
		);

		$guasti = array(
			'trasparenza_albero_formato'        => static function ( array $d ): array {
				$d['formato'] = 2;
				return $d;
			},
			'trasparenza_albero_incompleto'     => static function ( array $d ): array {
				unset( $d['versione'] );
				return $d;
			},
			'trasparenza_albero_dicitura'       => static function ( array $d ): array {
				$d['diciture']['sospeso'] = ' ';
				return $d;
			},
			'trasparenza_albero_identificativo' => static function ( array $d ): array {
				$d['nodi'][1]['id'] = 'a-b';
				return $d;
			},
			'trasparenza_albero_duplicato'      => static function ( array $d ): array {
				$d['nodi'][2]['id'] = 'a';
				return $d;
			},
			'trasparenza_albero_voce'           => static function ( array $d ): array {
				$d['nodi'][1]['nome'] = '';
				return $d;
			},
			'trasparenza_albero_radice'         => static function ( array $d ): array {
				$d['nodi'][1]['padre'] = null;
				return $d;
			},
			'trasparenza_albero_nome_sezione'   => static function ( array $d ): array {
				$d['nodi'][0]['nome'] = 'Amministrazione Trasparente';
				return $d;
			},
			'trasparenza_albero_padre'          => static function ( array $d ): array {
				$d['nodi'][2]['padre'] = 'manca';
				return $d;
			},
			'trasparenza_albero_ciclo'          => static function ( array $d ): array {
				$d['nodi'][1]['padre'] = 'b';
				return $d;
			},
		);

		foreach ( $guasti as $codice => $guasta ) {
			$casi[ $codice ] = Alberatura::da_dati( $guasta( $this->valido() ) );
		}

		// Senza radice: due voci che si fanno da padre a vicenda.
		$senza_radice                     = $this->valido();
		$senza_radice['nodi'][0]['padre'] = 'a';
		$casi['senza radice']             = Alberatura::da_dati( $senza_radice );

		$this->assertInstanceOf( Alberatura::class, Alberatura::da_dati( $this->valido() ), 'La base dei guasti deve essere valida.' );

		foreach ( $casi as $atteso => $esito ) {
			$this->assertWPError( $esito, "Il caso {$atteso} deve essere rifiutato." );

			if ( 'senza radice' === $atteso ) {
				$this->assertSame( 'trasparenza_albero_radice', $esito->get_error_code() );
				continue;
			}

			$this->assertSame( $atteso, $esito->get_error_code() );
		}
	}

	/**
	 * T-54: le collocazioni del file si leggono; senza, nessuna collocazione.
	 */
	public function test_t54_collocazioni_lette(): void {
		$dati                 = $this->valido();
		$dati['collocazioni'] = array( 'responsabile_trasparenza' => 'b' );

		$albero = Alberatura::da_dati( $dati );

		$this->assertInstanceOf( Alberatura::class, $albero );
		$this->assertSame( 'b', $albero->collocazione( 'responsabile_trasparenza' ) );
		$this->assertNull( $albero->collocazione( 'altro' ) );
		$this->assertNull( Alberatura::da_dati( $this->valido() )->collocazione( 'responsabile_trasparenza' ), 'Senza collocazioni nel file non se ne inventa una.' );
	}

	/**
	 * T-54: una collocazione sbagliata e un difetto del file, come un padre inesistente.
	 */
	public function test_t54_collocazioni_sbagliate_rifiutate(): void {
		$guasti = array(
			'non elenco'       => 'b',
			'voce inesistente' => array( 'responsabile_trasparenza' => 'manca' ),
			'radice'           => array( 'responsabile_trasparenza' => 'r' ),
			'dato non valido'  => array( 'Responsabile' => 'b' ),
			'dato numerico'    => array( 'b' ),
			'voce non testo'   => array( 'responsabile_trasparenza' => array( 'b' ) ),
		);

		foreach ( $guasti as $caso => $collocazioni ) {
			$dati                 = $this->valido();
			$dati['collocazioni'] = $collocazioni;
			$esito                = Alberatura::da_dati( $dati );

			$this->assertWPError( $esito, $caso );
			$this->assertSame( 'trasparenza_albero_collocazione', $esito->get_error_code(), $caso );
		}
	}
}
