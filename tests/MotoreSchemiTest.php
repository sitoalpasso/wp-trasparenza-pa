<?php
/**
 * Prove del motore degli schemi: righe T-26, T-29..T-31 e T-33.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use DateTimeImmutable;
use DateTimeZone;
use TrasparenzaPa\CatalogoSchemi;
use TrasparenzaPa\MotoreSchemi;
use WP_UnitTestCase;

/**
 * Il dato contro la versione giusta del suo schema.
 */
class MotoreSchemiTest extends WP_UnitTestCase {

	use SchemiFinti;

	/**
	 * Motore sul catalogo finto.
	 *
	 * @var MotoreSchemi
	 */
	private $motore;

	/**
	 * Richieste di rete tentate durante la prova.
	 *
	 * @var array<int, string>
	 */
	private $richieste = array();

	/**
	 * Motore pronto e ogni richiesta di rete annotata e bloccata.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->motore = new MotoreSchemi( CatalogoSchemi::carica( $this->catalogo_finto() ) );

		add_filter(
			'pre_http_request',
			function ( $risposta, $argomenti, $indirizzo ) {
				$this->richieste[] = $indirizzo;

				return new \WP_Error( 'rete_vietata', 'La prova non ammette richieste di rete.' );
			},
			10,
			3
		);
	}

	/**
	 * Un dato conforme alla versione 1.0.
	 *
	 * @return array<string, mixed>
	 */
	private function dato_conforme(): array {
		return array(
			'zafferanoTitolo'   => 'Titolo',
			'zafferanoQuantita' => 3,
			'zafferanoVoci'     => array( array( 'zafferanoNome' => 'Uno' ) ),
		);
	}

	/**
	 * T-26: la libreria c'e solo con i nomi prefissati, e convive con un'altra copia.
	 */
	public function test_t26_libreria_prefissata(): void {
		$radice = dirname( __DIR__ );

		$this->assertFileExists( $radice . '/vendor-prefixed/autoload.php', 'Eseguire composer install: la libreria si genera.' );

		foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $radice . '/vendor-prefixed/opis', \FilesystemIterator::SKIP_DOTS ) ) as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}

			$this->assertDoesNotMatchRegularExpression(
				'/^\s*(namespace|use)\s+\\\\?Opis\\\\/m',
				(string) file_get_contents( $file->getPathname() ),
				'Nome non prefissato in ' . $file->getPathname()
			);
		}

		foreach ( glob( $radice . '/src/*.php' ) as $sorgente ) {
			$this->assertDoesNotMatchRegularExpression(
				'/(?<!Vendor\\\\)Opis\\\\/',
				(string) file_get_contents( $sorgente ),
				'Il componente deve usare solo la copia prefissata: ' . basename( $sorgente )
			);
		}

		// La copia senza prefisso, caricata dagli strumenti di sviluppo, fa la parte dell'altro componente.
		$this->assertTrue( class_exists( 'Opis\\JsonSchema\\Validator' ), 'Serve una seconda copia della libreria per provare la convivenza.' );

		$this->assertTrue( $this->motore->valida( 'finto', '1.0@2019-12-01', json_decode( (string) wp_json_encode( $this->dato_conforme() ) ) ) );

		$proprieta = new \ReflectionProperty( MotoreSchemi::class, 'validatori' );
		$proprieta->setAccessible( true );

		$validatori = $proprieta->getValue( $this->motore );

		$this->assertCount( 1, $validatori );
		$this->assertStringStartsWith( 'TrasparenzaPa\\Vendor\\', get_class( reset( $validatori ) ) );
	}

	/**
	 * T-29: il dato conforme passa, quello difforme dice dove e perche.
	 */
	public function test_t29_dato_conforme_e_difforme(): void {
		$this->assertTrue( $this->motore->valida( 'finto', '1.0@2019-12-01', (string) wp_json_encode( $this->dato_conforme() ) ) );

		$difforme                     = $this->dato_conforme();
		$difforme['zafferanoTitolo']  = '';
		$difforme['zafferanoVoci'][1] = new \stdClass();
		$esito                        = $this->motore->valida( 'finto', '1.0@2019-12-01', (string) wp_json_encode( $difforme ) );

		$this->assertWPError( $esito );
		$this->assertSame( 'trasparenza_dato_difforme', $esito->get_error_code() );

		$difetti = array_map(
			static function ( array $d ): string {
				return $d['percorso'] . ' ' . $d['regola'];
			},
			$esito->get_error_data()
		);

		$this->assertContains( '/zafferanoTitolo minLength', $difetti );
		$this->assertContains( '/zafferanoVoci/1 required', $difetti );

		foreach ( $esito->get_error_data() as $difetto ) {
			$this->assertNotSame( '', $difetto['messaggio'] );
			$this->assertStringNotContainsString( '{', $difetto['messaggio'], 'Il messaggio deve avere i valori al posto dei segnaposto.' );
		}
	}

	/**
	 * T-29: dato in forma ambigua, obbligo o versione sconosciuti.
	 */
	public function test_t29_rifiuti_prima_della_validazione(): void {
		$this->assertSame( 'trasparenza_dato_forma', $this->motore->valida( 'finto', '1.0@2019-12-01', $this->dato_conforme() )->get_error_code() );
		$this->assertSame( 'trasparenza_dato_forma', $this->motore->valida( 'finto', '1.0@2019-12-01', '{ non e json' )->get_error_code() );
		$this->assertSame( 'trasparenza_schema_sconosciuto', $this->motore->valida( 'finto', '9.9', '{}' )->get_error_code() );
		$this->assertSame( 'trasparenza_schema_sconosciuto', $this->motore->valida( 'inesistente', '1.0', '{}' )->get_error_code() );
	}

	/**
	 * T-30: i rimandi esterni si risolvono sui file locali, senza rete.
	 */
	public function test_t30_validazione_senza_rete(): void {
		// Il titolo rimanda al file comune con un indirizzo assoluto: se il rimando non si risolvesse, il controllo non scatterebbe.
		$esito = $this->motore->valida( 'finto', '1.0@2019-12-01', '{"zafferanoTitolo":"","zafferanoQuantita":1}' );

		$this->assertWPError( $esito );
		$this->assertSame( '/zafferanoTitolo', $esito->get_error_data()[0]['percorso'] );
		$this->assertSame( 'minLength', $esito->get_error_data()[0]['regola'] );

		$this->assertTrue( $this->motore->valida( 'finto', '1.0@2019-12-01', json_decode( (string) wp_json_encode( $this->dato_conforme() ) ) ) );
		$this->assertSame( array(), $this->richieste, 'Nessuna richiesta di rete deve partire.' );
	}

	/**
	 * T-31: la versione si sceglie per data, e il dato vecchio resta valido con la sua.
	 */
	public function test_t31_versioni_affiancate(): void {
		$this->assertSame( '1.0@2019-12-01', $this->motore->versione_in_vigore( 'finto' ), 'La 1.1 non e ancora in vigore.' );
		$this->assertNull( $this->motore->versione_in_vigore( 'finto', '2019-12-31' ) );
		$this->assertSame( '1.0@2019-12-01', $this->motore->versione_in_vigore( 'finto', '2098-12-31' ) );
		$this->assertSame( '1.1@2098-12-01', $this->motore->versione_in_vigore( 'finto', '2099-01-01' ) );

		// Quantita 500: valida per la 1.0, oltre il massimo della 1.1.
		$vecchio = '{"zafferanoTitolo":"Titolo","zafferanoQuantita":500}';

		$this->assertTrue( $this->motore->valida( 'finto', '1.0@2019-12-01', $vecchio ) );
		$this->assertWPError( $this->motore->valida( 'finto', '1.1@2098-12-01', $vecchio ) );

		// Il campo nuovo della 1.1 non esiste nella 1.0.
		$nuovo = '{"zafferanoTitolo":"Titolo","zafferanoQuantita":5,"zafferanoNota":"n"}';

		$this->assertTrue( $this->motore->valida( 'finto', '1.1@2098-12-01', $nuovo ) );
		$this->assertWPError( $this->motore->valida( 'finto', '1.0@2019-12-01', $nuovo ) );
	}

	/**
	 * T-31: "oggi" e la data nel fuso del sito, non quella del server.
	 *
	 * Due fusi agli estremi: in ogni momento, almeno in uno dei due la data
	 * locale e diversa da quella universale, quindi un calcolo sulla data
	 * universale sbaglierebbe sempre almeno uno dei due casi.
	 */
	public function test_t31_oggi_nel_fuso_del_sito(): void {
		foreach ( array( 'Pacific/Kiritimati', 'Pacific/Pago_Pago' ) as $fuso ) {
			update_option( 'timezone_string', $fuso );

			$oggi   = new DateTimeImmutable( 'now', new DateTimeZone( $fuso ) );
			$domani = $oggi->modify( '+1 day' );

			foreach ( array(
				'1.1@2098-12-01' => $oggi,
				'1.0@2019-12-01' => $domani,
			) as $attesa => $vigore ) {
				$motore = new MotoreSchemi(
					$this->guasta(
						static function ( array $c ) use ( $vigore ): array {
							$c['schemi'][0]['in_vigore_dal'] = $vigore->format( 'Y-m-d' );
							return $c;
						}
					)
				);

				$this->assertSame( (string) $attesa, $motore->versione_in_vigore( 'finto' ), "Fuso {$fuso}, 1.1 in vigore dal " . $vigore->format( 'Y-m-d' ) );
			}
		}
	}

	/**
	 * T-33: uno schema in piu, aggiunto solo come file, funziona.
	 */
	public function test_t33_schema_aggiunto_come_solo_file(): void {
		$catalogo = CatalogoSchemi::carica( __DIR__ . '/dati/schemi/catalogo-con-terzo.json' );

		$this->assertInstanceOf( CatalogoSchemi::class, $catalogo );

		$motore = new MotoreSchemi( $catalogo );

		$this->assertSame( '1.0@2019-12-01', $motore->versione_in_vigore( 'altro_finto' ) );
		$this->assertTrue( $motore->valida( 'altro_finto', '1.0@2019-12-01', '{"cardamomoValore":"x"}' ) );
		$this->assertWPError( $motore->valida( 'altro_finto', '1.0@2019-12-01', '{"cardamomoValore":""}' ) );
	}
}
