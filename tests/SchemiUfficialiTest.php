<?php
/**
 * Prove sugli schemi ufficiali ANAC: righe T-34..T-36.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\CatalogoSchemi;
use TrasparenzaPa\MotoreSchemi;
use WP_UnitTestCase;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- i nomi dei campi sono quelli dei file ufficiali.

/**
 * I file dell'Autorita come dati, e i suoi esempi compilati come banco di prova.
 *
 * Qui i nomi dei campi ufficiali compaiono per forza: le prove rompono campi
 * precisi. La prova statica T-32 guarda solo i sorgenti del componente.
 */
class SchemiUfficialiTest extends WP_UnitTestCase {

	/**
	 * Indirizzo da cui si scaricano gli schemi ufficiali.
	 *
	 * @var string
	 */
	private static $origine = 'https://guida-servizi.anticorruzione.it/help/trasparenza/schemi/json/';

	/**
	 * Motore sul catalogo ufficiale.
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

		$catalogo = CatalogoSchemi::carica( self::catalogo() );

		$this->assertInstanceOf( CatalogoSchemi::class, $catalogo, 'Il catalogo ufficiale deve caricarsi.' );

		$this->motore = new MotoreSchemi( $catalogo );

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
	 * Percorso del catalogo ufficiale.
	 *
	 * @return string
	 */
	private static function catalogo(): string {
		return dirname( __DIR__ ) . '/schemas/catalogo.json';
	}

	/**
	 * Testo di un esempio ufficiale, cosi come scaricato.
	 *
	 * @param string $nome Prefisso del file, per esempio `art.13-pa`.
	 * @return string
	 */
	private static function esempio( string $nome ): string {
		$file = __DIR__ . '/dati/esempi-anac/' . $nome . '-YYYYMMDD-YYYYMMDD.v1.0.json';

		return (string) file_get_contents( $file );
	}

	/**
	 * Un esempio ufficiale decodificato, da rompere.
	 *
	 * @param string $nome Prefisso del file.
	 * @return object
	 */
	private static function esempio_da_rompere( string $nome ): object {
		$testo = self::esempio( $nome );

		return json_decode( str_starts_with( $testo, "\xEF\xBB\xBF" ) ? substr( $testo, 3 ) : $testo, false );
	}

	/**
	 * Gli esempi ufficiali e l'obbligo a cui appartengono.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function esempi(): array {
		return array(
			'art. 13, pubbliche amministrazioni' => array( 'art.13-pa', 'art_13' ),
			'art. 13, ordini e collegi'          => array( 'art.13-oc', 'art_13' ),
			'art. 13, societa ed enti'           => array( 'art.13-se', 'art_13' ),
			'art. 31, valutazione'               => array( 'art.31-oiv', 'art_31' ),
			'art. 31, revisione'                 => array( 'art.31-or', 'art_31' ),
			'art. 31, Corte dei conti'           => array( 'art.31-cc', 'art_31' ),
			'art. 4-bis, pagamenti'              => array( 'art.4-bis', 'art_4_bis' ),
		);
	}

	/**
	 * T-34: il catalogo ufficiale, con i file identici a quelli scaricati.
	 */
	public function test_t34_catalogo_ufficiale(): void {
		$catalogo = $this->motore->catalogo();
		$obblighi = $catalogo->obblighi();

		sort( $obblighi );

		$this->assertSame( array( 'art_13', 'art_31', 'art_4_bis' ), $obblighi );

		$grezzo = json_decode( (string) file_get_contents( self::catalogo() ), true );
		$voci   = array_merge( $grezzo['comuni'], $grezzo['schemi'] );

		$this->assertCount( 4, $voci );

		foreach ( $voci as $voce ) {
			$file = dirname( self::catalogo() ) . '/' . $voce['file'];

			$this->assertSame( self::$origine . $voce['file'], $voce['fonte']['indirizzo'], 'La fonte e il file ufficiale con il suo nome.' );
			$this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $voce['fonte']['sha256'] ?? '', 'Ogni voce porta l\'impronta del file scaricato.' );
			$this->assertSame( $voce['fonte']['sha256'], hash_file( 'sha256', $file ), 'Il file ' . $voce['file'] . ' non e piu quello scaricato.' );
		}

		foreach ( $obblighi as $obbligo ) {
			$versione = $this->motore->versione_in_vigore( $obbligo );

			$this->assertNotNull( $versione, "Nessuna versione in vigore oggi per {$obbligo}." );

			$voce = $catalogo->versione( $obbligo, $versione );

			$this->assertSame( self::$origine . $voce['file'], $voce['id'], 'Il nome del file coincide con la fine del suo identificativo.' );
			$this->assertStringStartsWith( 'https://guida-servizi.anticorruzione.it/', $voce['fonte']['pagina'] );
		}
	}

	/**
	 * T-35: gli esempi ufficiali passano cosi come sono, senza rete.
	 *
	 * @dataProvider esempi
	 *
	 * @param string $nome    Prefisso del file dell'esempio.
	 * @param string $obbligo Obbligo.
	 */
	public function test_t35_esempi_ufficiali( string $nome, string $obbligo ): void {
		$testo = self::esempio( $nome );

		$this->assertStringStartsWith( "\xEF\xBB\xBF", $testo, 'Gli esempi ufficiali cominciano con il segno BOM: la prova deve usarli cosi.' );

		$esito = $this->motore->valida( $obbligo, (string) $this->motore->versione_in_vigore( $obbligo ), $testo );

		$this->assertTrue( $esito, is_wp_error( $esito ) ? (string) wp_json_encode( $esito->get_error_data() ) : '' );
		$this->assertSame( array(), $this->richieste, 'Nessuna richiesta di rete deve partire.' );
	}

	/**
	 * Le rotture volute: esempio, obbligo, modifica, percorso e regola attesi.
	 *
	 * @return array<string, array{0: string, 1: string, 2: callable, 3: string, 4: string}>
	 */
	public static function rotture(): array {
		return array(
			'campo obbligatorio tolto'         => array(
				'art.13-pa',
				'art_13',
				static function ( object $d ): void {
					unset( $d->intestazione->amministrazione->codiceFiscale );
				},
				'/intestazione/amministrazione',
				'required',
			),
			'data non GG/MM/AAAA'              => array(
				'art.13-pa',
				'art_13',
				static function ( object $d ): void {
					$d->intestazione->dataPrimaPubblicazione = '2026-05-22';
				},
				'/intestazione/dataPrimaPubblicazione',
				'pattern',
			),
			'campo non previsto'               => array(
				'art.31-oiv',
				'art_31',
				static function ( object $d ): void {
					$d->intestazione->campoInventato = 'x';
				},
				'/intestazione',
				'additionalProperties',
			),
			'valore fuori elenco'              => array(
				'art.13-pa',
				'art_13',
				static function ( object $d ): void {
					$d->orgPubblicheAmministrazioni->organi[0]->uffici[0]->tipologia = 'Ufficio qualunque';
				},
				'/orgPubblicheAmministrazioni/organi/0/uffici/0/tipologia',
				'enum',
			),
			'recapito senza posta elettronica' => array(
				'art.13-pa',
				'art_13',
				static function ( object $d ): void {
					$contatti = $d->orgPubblicheAmministrazioni->organi[0]->uffici[0]->contatti;

					unset( $contatti->postaElettronicaOrdinaria, $contatti->postaElettronicaCertificata );
				},
				'/orgPubblicheAmministrazioni/organi/0/uffici/0/contatti',
				'required',
			),
			'importo senza decimali'           => array(
				'art.4-bis',
				'art_4_bis',
				static function ( object $d ): void {
					$d->datiSuiPagamenti[0]->importo = '1.255';
				},
				'/datiSuiPagamenti/0/importo',
				'pattern',
			),
			'trimestre fuori intervallo'       => array(
				'art.4-bis',
				'art_4_bis',
				static function ( object $d ): void {
					$d->datiSuiPagamenti[1]->trimestre = 5;
				},
				'/datiSuiPagamenti/1/trimestre',
				'maximum',
			),
			'indirizzo senza protocollo'       => array(
				'art.31-oiv',
				'art_31',
				static function ( object $d ): void {
					$d->attiOrganiDiValutazione->relazioneSistemaDiValutazione->documento = 'www.example.org/RelazioneVal.pdf';
				},
				'/attiOrganiDiValutazione/relazioneSistemaDiValutazione/documento',
				'format',
			),
		);
	}

	/**
	 * T-36: ogni rottura voluta e respinta, con il campo e la regola.
	 *
	 * @dataProvider rotture
	 *
	 * @param string   $nome     Prefisso del file dell'esempio.
	 * @param string   $obbligo  Obbligo.
	 * @param callable $rompi    Modifica che introduce il difetto.
	 * @param string   $percorso Percorso atteso del difetto.
	 * @param string   $regola   Regola attesa.
	 */
	public function test_t36_esempi_rotti_apposta( string $nome, string $obbligo, callable $rompi, string $percorso, string $regola ): void {
		$dato = self::esempio_da_rompere( $nome );

		$rompi( $dato );

		$esito = $this->motore->valida( $obbligo, (string) $this->motore->versione_in_vigore( $obbligo ), $dato );

		$this->assertWPError( $esito );
		$this->assertSame( 'trasparenza_dato_difforme', $esito->get_error_code() );

		$difetti = array_map(
			static function ( array $d ): string {
				return $d['percorso'] . ' ' . $d['regola'];
			},
			$esito->get_error_data()
		);

		$this->assertContains( $percorso . ' ' . $regola, $difetti, 'Difetti trovati: ' . implode( '; ', $difetti ) );
	}
}
