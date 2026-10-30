<?php
/**
 * Prove dei rinvii dichiarati dall'ente: righe T-94..T-96.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Alberatura;
use TrasparenzaPa\Avvio;
use TrasparenzaPa\Blocco;
use TrasparenzaPa\PaginaPubblica;
use TrasparenzaPa\Permessi;
use TrasparenzaPa\Rinvii;
use TrasparenzaPa\SchermataRinvii;
use WPDieException;
use WP_UnitTestCase;

/**
 * Chi dichiara un rinvio, quali rinvii si accettano, come si salvano.
 */
class RinviiDichiaratiTest extends WP_UnitTestCase {

	use AmbienteTrasparenza;

	/**
	 * Utente con il permesso di amministrare la sezione.
	 *
	 * @var int
	 */
	private $responsabile;

	/**
	 * Amministratore del sito, senza il permesso della sezione.
	 *
	 * @var int
	 */
	private $amministratore;

	/**
	 * Sezione registrata, albero finto con le banche dati, nessun rinvio, permesso a un solo utente.
	 */
	public function set_up(): void {
		parent::set_up();

		\Conformita_Core_Sezioni::azzera();
		Avvio::azzera();
		Avvio::esegui();

		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-rinvii.json' );
		PaginaPubblica::da_init();
		$this->assertInstanceOf( Alberatura::class, PaginaPubblica::albero(), 'Precondizione: l\'albero finto con le banche dati si legge.' );

		delete_option( Rinvii::OPZIONE );
		get_role( 'administrator' )->remove_cap( Permessi::AMMINISTRAZIONE );

		$this->responsabile   = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->amministratore = self::factory()->user->create( array( 'role' => 'administrator' ) );

		get_user_by( 'id', $this->responsabile )->add_cap( Permessi::AMMINISTRAZIONE );
		wp_set_current_user( $this->responsabile );
	}

	/**
	 * Rimette l'albero finto e il permesso all'amministratore, come lo lascia l'avvio della suite.
	 */
	public function tear_down(): void {
		global $wpdb;

		remove_all_filters( 'query' );
		remove_all_filters( 'pre_update_option_' . Rinvii::OPZIONE );
		$wpdb->suppress_errors( false );
		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-finto.json' );
		PaginaPubblica::da_init();
		get_role( 'administrator' )->add_cap( Permessi::AMMINISTRAZIONE );

		parent::tear_down();
	}

	/**
	 * Un rinvio valido, da guastare un campo alla volta.
	 *
	 * @return array<string, string>
	 */
	private function valido(): array {
		return array(
			'banca'     => 'banca_uno',
			'indirizzo' => 'https://example.org/banca/ente?dato=1',
			'etichetta' => 'Dati dell\'ente nella Banca Uno',
			'data'      => '2026-01-15',
		);
	}

	/**
	 * Una richiesta come la manderebbe il modulo, con il nonce dell'utente corrente.
	 *
	 * Senza partenza indicata, porta il rinvio conservato per la voce.
	 *
	 * @param string                    $voce     Voce.
	 * @param mixed                     $rinvio   Campi del rinvio.
	 * @param array<string, mixed>|null $partenza Rinvio mostrato all'apertura del modulo.
	 * @return array<string, mixed>
	 */
	private function richiesta( string $voce, $rinvio, ?array $partenza = null ): array {
		return array(
			'_wpnonce'                      => wp_create_nonce( SchermataRinvii::AZIONE ),
			SchermataRinvii::CAMPO_VOCE     => $voce,
			SchermataRinvii::CAMPO          => $rinvio,
			SchermataRinvii::CAMPO_PARTENZA => $partenza ?? ( Rinvii::dati()[ $voce ] ?? Rinvii::vuoto() ),
		);
	}

	/**
	 * Le voci di registro dei rinvii, in ordine di scrittura.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function tracce(): array {
		return conformita_core_voci_registro(
			array(
				'sezione' => \TrasparenzaPa\SEZIONE,
				'azione'  => SchermataRinvii::AZIONE_REGISTRO,
			)
		);
	}

	/**
	 * I dettagli attesi di una riga di registro.
	 *
	 * @param string                     $voce  Voce.
	 * @param array<string, string>|null $prima Rinvio di prima, nullo se non c'era.
	 * @param array<string, string>|null $dopo  Rinvio di dopo, nullo se tolto.
	 * @return array<string, string|null>
	 */
	private function dettagli( string $voce, ?array $prima, ?array $dopo ): array {
		$attesi = array( 'voce' => $voce );

		foreach ( array(
			'prima' => $prima,
			'dopo'  => $dopo,
		) as $quando => $rinvio ) {
			foreach ( Rinvii::CAMPI as $campo ) {
				$attesi[ $campo . '_' . $quando ] = null === $rinvio ? null : $rinvio[ $campo ];
			}
		}

		return $attesi;
	}

	/**
	 * La schermata come la disegnerebbe WordPress per l'utente corrente.
	 *
	 * @return string
	 */
	private function schermata(): string {
		ob_start();

		try {
			SchermataRinvii::mostra();
		} finally {
			$html = (string) ob_get_clean();
		}

		return $html;
	}

	/**
	 * Fa fallire la lettura dei rinvii dalla banca dati.
	 */
	private function guasta_lettura(): void {
		global $wpdb;

		$wpdb->suppress_errors( true );
		add_filter(
			'query',
			static function ( $sql ) use ( $wpdb ) {
				$sql = (string) $sql;

				return 0 === strpos( $sql, "SELECT option_value FROM {$wpdb->options} WHERE option_name = '" . Rinvii::OPZIONE . "'" )
					? 'SELECT option_value FROM ' . $wpdb->options . '_inesistente'
					: $sql;
			}
		);
	}

	/**
	 * T-94: un rinvio valido torna uguale; tutto vuoto vuol dire nessun rinvio; oggi si accetta.
	 */
	public function test_t94_rinvio_valido(): void {
		$albero = PaginaPubblica::albero();

		$this->assertSame( $this->valido(), Rinvii::controlla( $albero, 'zeta_uno', $this->valido() ) );
		$this->assertSame( Rinvii::vuoto(), Rinvii::controlla( $albero, 'zeta_uno', Rinvii::vuoto() ) );

		$oggi = array_merge( $this->valido(), array( 'data' => Rinvii::oggi() ) );
		$this->assertSame( $oggi, Rinvii::controlla( $albero, 'zeta_uno', $oggi ), 'Oggi nel fuso del sito si accetta.' );

		$this->assertSame( ( new \DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y-m-d' ), Rinvii::oggi() );

		$tre = array_merge( $this->valido(), array( 'banca' => 'banca_tre' ) );
		$this->assertSame( $tre, Rinvii::controlla( $albero, 'alfa', $tre ) );
	}

	/**
	 * T-94: [attacco] rinvii non validi: errore con il motivo.
	 */
	public function test_t94_rinvii_non_validi(): void {
		$albero = PaginaPubblica::albero();
		$domani = ( new \DateTimeImmutable( 'tomorrow', wp_timezone() ) )->format( 'Y-m-d' );
		$casi   = array(
			'voce inesistente'           => array( 'voce_inesistente', $this->valido() ),
			'radice'                     => array( 'radice', $this->valido() ),
			'voce senza banche'          => array( 'zeta_due', $this->valido() ),
			'vuoto su voce senza banche' => array( 'zeta_due', Rinvii::vuoto() ),
			'banca di altra voce'        => array( 'zeta_uno', array_merge( $this->valido(), array( 'banca' => 'banca_tre' ) ) ),
			'banca inesistente'          => array( 'zeta_uno', array_merge( $this->valido(), array( 'banca' => 'banca_zero' ) ) ),
			'banca non testo'            => array( 'zeta_uno', array_merge( $this->valido(), array( 'banca' => 1 ) ) ),
			'indirizzo mancante'         => array( 'zeta_uno', array_diff_key( $this->valido(), array( 'indirizzo' => 0 ) ) ),
			'data non testo'             => array( 'zeta_uno', array_merge( $this->valido(), array( 'data' => array( '2026-01-15' ) ) ) ),
			'indirizzo vuoto'            => array( 'zeta_uno', array_merge( $this->valido(), array( 'indirizzo' => '' ) ) ),
			'indirizzo senza schema'     => array( 'zeta_uno', array_merge( $this->valido(), array( 'indirizzo' => 'example.org/banca' ) ) ),
			'indirizzo altro schema'     => array( 'zeta_uno', array_merge( $this->valido(), array( 'indirizzo' => 'javascript://example.org/%0aalert(1)' ) ) ),
			'indirizzo ftp'              => array( 'zeta_uno', array_merge( $this->valido(), array( 'indirizzo' => 'ftp://example.org/banca' ) ) ),
			'indirizzo con codice'       => array( 'zeta_uno', array_merge( $this->valido(), array( 'indirizzo' => 'https://example.org/"><script>' ) ) ),
			'indirizzo lungo'            => array( 'zeta_uno', array_merge( $this->valido(), array( 'indirizzo' => 'https://example.org/' . str_repeat( 'a', 1981 ) ) ) ),
			'etichetta vuota'            => array( 'zeta_uno', array_merge( $this->valido(), array( 'etichetta' => '' ) ) ),
			'etichetta su due righe'     => array( 'zeta_uno', array_merge( $this->valido(), array( 'etichetta' => "Dati\nnella banca" ) ) ),
			'etichetta con codice'       => array( 'zeta_uno', array_merge( $this->valido(), array( 'etichetta' => 'Dati <b>forti</b>' ) ) ),
			'etichetta lunga'            => array( 'zeta_uno', array_merge( $this->valido(), array( 'etichetta' => str_repeat( 'à', 201 ) ) ) ),
			'data in altro formato'      => array( 'zeta_uno', array_merge( $this->valido(), array( 'data' => '15/01/2026' ) ) ),
			'data con altro intorno'     => array( 'zeta_uno', array_merge( $this->valido(), array( 'data' => '2026-01-15T10:00' ) ) ),
			'data con altro prima'       => array( 'zeta_uno', array_merge( $this->valido(), array( 'data' => 'x2026-01-15' ) ) ),
			'data inesistente'           => array( 'zeta_uno', array_merge( $this->valido(), array( 'data' => '2026-02-30' ) ) ),
			'data vuota'                 => array( 'zeta_uno', array_merge( $this->valido(), array( 'data' => '' ) ) ),
			'data di domani'             => array( 'zeta_uno', array_merge( $this->valido(), array( 'data' => $domani ) ) ),
			'campi senza banca'          => array( 'zeta_uno', array_merge( $this->valido(), array( 'banca' => '' ) ) ),
			'solo data senza banca'      => array( 'zeta_uno', array_merge( Rinvii::vuoto(), array( 'data' => '2026-01-15' ) ) ),
		);

		foreach ( $casi as $nome => list( $voce, $rinvio ) ) {
			$esito = Rinvii::controlla( $albero, $voce, $rinvio );

			$this->assertWPError( $esito, $nome );
			$this->assertSame( 'trasparenza_rinvio_dati', $esito->get_error_code(), $nome );
			$this->assertNotSame( '', $esito->get_error_message(), $nome );
		}

		$this->assertSame( array_merge( $this->valido(), array( 'etichetta' => str_repeat( 'à', 200 ) ) ), Rinvii::controlla( $albero, 'zeta_uno', array_merge( $this->valido(), array( 'etichetta' => str_repeat( 'à', 200 ) ) ) ), 'Duecento caratteri si accettano, anche con lettere accentate.' );

		$lungo = array_merge( $this->valido(), array( 'indirizzo' => 'https://example.org/' . str_repeat( 'a', 1980 ) ) );
		$this->assertSame( $lungo, Rinvii::controlla( $albero, 'zeta_uno', $lungo ), 'Duemila caratteri si accettano.' );
	}

	/**
	 * T-95: dichiarazione, stesso rinvio, cambio e rimozione, ciascuno con la sua riga di registro.
	 */
	public function test_t95_salvataggi_e_registro(): void {
		$esito = SchermataRinvii::salva( $this->richiesta( 'zeta_uno', $this->valido() ) );

		$this->assertSame( 'salvato', $esito['codice'], $esito['messaggio'] );
		$this->assertSame( array( 'zeta_uno' => $this->valido() ), Rinvii::conservati() );
		$this->assertCount( 1, $this->tracce() );
		$this->assertSame( $this->dettagli( 'zeta_uno', null, $this->valido() ), $this->tracce()[0]['dettagli'] );

		$esito = SchermataRinvii::salva( $this->richiesta( 'zeta_uno', $this->valido() ) );
		$this->assertSame( 'salvato', $esito['codice'] );
		$this->assertCount( 1, $this->tracce(), 'Lo stesso rinvio non si registra.' );

		$altra = array_merge( $this->valido(), array( 'banca' => 'banca_tre' ) );
		$this->assertSame( 'salvato', SchermataRinvii::salva( $this->richiesta( 'alfa', $altra ) )['codice'] );

		$cambio = array_merge(
			$this->valido(),
			array(
				'banca'     => 'banca_due',
				'etichetta' => 'Altro dato',
			)
		);
		$this->assertSame( 'salvato', SchermataRinvii::salva( $this->richiesta( 'zeta_uno', $cambio ) )['codice'] );
		$this->assertSame( $this->dettagli( 'zeta_uno', $this->valido(), $cambio ), $this->tracce()[2]['dettagli'] );
		$this->assertSame( $altra, Rinvii::conservati()['alfa'], 'Il rinvio delle altre voci non si tocca.' );

		$esito = SchermataRinvii::salva( $this->richiesta( 'zeta_uno', Rinvii::vuoto() ) );
		$this->assertSame( 'salvato', $esito['codice'] );
		$this->assertSame( 'Rinvio tolto.', $esito['messaggio'] );
		$this->assertSame( array( 'alfa' => $altra ), Rinvii::conservati() );
		$this->assertSame( $this->dettagli( 'zeta_uno', $cambio, null ), $this->tracce()[3]['dettagli'] );
		$this->assertSame( (int) $this->responsabile, (int) $this->tracce()[3]['utente'] );
	}

	/**
	 * T-95: [attacco] permesso, nonce, campi e partenza: niente scritto, niente nel registro.
	 */
	public function test_t95_rifiuti(): void {
		wp_set_current_user( $this->amministratore );
		$this->assertSame( 'permesso', SchermataRinvii::salva( $this->richiesta( 'zeta_uno', $this->valido() ) )['codice'] );

		wp_set_current_user( $this->responsabile );
		$richiesta             = $this->richiesta( 'zeta_uno', $this->valido() );
		$richiesta['_wpnonce'] = 'scaduto';
		$this->assertSame( 'nonce', SchermataRinvii::salva( $richiesta )['codice'] );

		unset( $richiesta['_wpnonce'] );
		$this->assertSame( 'nonce', SchermataRinvii::salva( $richiesta )['codice'] );

		$richiesta = $this->richiesta( 'zeta_uno', $this->valido() );
		unset( $richiesta[ SchermataRinvii::CAMPO_VOCE ] );
		$this->assertSame( 'dati', SchermataRinvii::salva( $richiesta )['codice'] );
		$this->assertSame( 'dati', SchermataRinvii::salva( $this->richiesta( 'zeta_uno', 'banca_uno' ) )['codice'], 'Rinvio che non e un elenco.' );

		$esito = SchermataRinvii::salva( $this->richiesta( 'zeta_uno', array_merge( $this->valido(), array( 'data' => '2026-02-30' ) ) ) );
		$this->assertSame( 'dati', $esito['codice'] );
		$this->assertStringContainsString( 'AAAA-MM-GG', $esito['messaggio'] );
		$this->assertSame( '2026-02-30', $esito['inviati']['data'], 'Il modulo rimostra quanto inviato.' );
		$this->assertSame( Rinvii::vuoto(), $esito['partenza'] );

		foreach ( array(
			'partenza mancante' => null,
			'partenza parziale' => array_diff_key( Rinvii::vuoto(), array( 'data' => 0 ) ),
			'partenza numerica' => array_merge( Rinvii::vuoto(), array( 'data' => 0 ) ),
		) as $nome => $partenza ) {
			$richiesta = $this->richiesta( 'zeta_uno', $this->valido() );

			if ( null === $partenza ) {
				unset( $richiesta[ SchermataRinvii::CAMPO_PARTENZA ] );
			} else {
				$richiesta[ SchermataRinvii::CAMPO_PARTENZA ] = $partenza;
			}

			$this->assertSame( 'dati', SchermataRinvii::salva( $richiesta )['codice'], $nome );
		}

		$this->assertSame( array(), Rinvii::conservati() );
		$this->assertCount( 0, $this->tracce() );
	}

	/**
	 * T-95: [attacco] il rinvio della voce cambiato da altri dopo l'apertura del modulo e un conflitto.
	 */
	public function test_t95_conflitto(): void {
		$aperto = Rinvii::vuoto();

		$this->assertSame( 'salvato', SchermataRinvii::salva( $this->richiesta( 'zeta_uno', $this->valido(), $aperto ) )['codice'] );

		$mio   = array_merge( $this->valido(), array( 'etichetta' => 'Il mio dato' ) );
		$esito = SchermataRinvii::salva( $this->richiesta( 'zeta_uno', $mio, $aperto ) );

		$this->assertSame( 'conflitto', $esito['codice'] );
		$this->assertSame( $aperto, $esito['partenza'], 'Dopo il rifiuto il modulo riporta la partenza di allora.' );
		$this->assertSame( $this->valido(), Rinvii::conservati()['zeta_uno'] );
		$this->assertCount( 1, $this->tracce() );

		$this->assertSame( 'conflitto', SchermataRinvii::salva( $this->richiesta( 'zeta_uno', Rinvii::vuoto(), $aperto ) )['codice'], 'Neanche una rimozione di un rinvio non visto.' );
		$this->assertSame( 'salvato', SchermataRinvii::salva( $this->richiesta( 'zeta_uno', $this->valido(), $aperto ) )['codice'], 'Chi manda il rinvio gia conservato non cambia niente.' );
		$this->assertSame( 'salvato', SchermataRinvii::salva( $this->richiesta( 'alfa', $this->valido(), $aperto ) )['codice'], 'Il conflitto e per voce.' );
	}

	/**
	 * T-95: [attacco] albero assente e salvataggio in corso: niente scritto.
	 */
	public function test_t95_albero_assente_e_blocco(): void {
		global $wpdb;

		PaginaPubblica::fissa_file( __DIR__ . '/dati/inesistente.json' );
		PaginaPubblica::da_init();

		$esito = SchermataRinvii::salva( $this->richiesta( 'zeta_uno', $this->valido() ) );
		$this->assertSame( 'albero', $esito['codice'] );
		$this->assertSame( $this->valido(), $esito['inviati'] );

		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-rinvii.json' );
		PaginaPubblica::da_init();

		list( $host, $porta, $socket ) = $wpdb->parse_db_host( DB_HOST );

		$altra = mysqli_init(); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_init -- serve una seconda connessione, che $wpdb non offre.
		$this->assertTrue( $altra->real_connect( (string) $host, DB_USER, DB_PASSWORD, DB_NAME, $porta ? (int) $porta : null, $socket ? (string) $socket : null ) );

		try {
			$this->assertSame( '1', (string) $altra->query( "SELECT GET_LOCK('" . $altra->real_escape_string( Blocco::nome() ) . "', 0)" )->fetch_row()[0] );
			$this->assertSame( 'occupato', SchermataRinvii::salva( $this->richiesta( 'zeta_uno', $this->valido() ) )['codice'] );
		} finally {
			$altra->close();
		}

		$this->assertSame( array(), Rinvii::conservati() );
		$this->assertCount( 0, $this->tracce() );
	}

	/**
	 * T-95: [guasto] lettura fallita, registro che rifiuta, rinvio non scritto.
	 */
	public function test_t95_guasti(): void {
		global $wpdb;

		$altra = array_merge( $this->valido(), array( 'banca' => 'banca_tre' ) );
		$this->assertSame( 'salvato', SchermataRinvii::salva( $this->richiesta( 'alfa', $altra ) )['codice'] );

		$richiesta = $this->richiesta( 'zeta_uno', $this->valido() );
		$this->guasta_lettura();
		$esito = SchermataRinvii::salva( $richiesta );
		remove_all_filters( 'query' );
		$wpdb->suppress_errors( false );

		$this->assertSame( 'lettura', $esito['codice'] );
		$this->assertSame( array( 'alfa' => $altra ), Rinvii::conservati(), 'Il rinvio delle altre voci resta.' );
		$this->assertCount( 1, $this->tracce() );
		$this->assertWPError(
			( function () {
				$this->guasta_lettura();
				return Rinvii::conservati();
			} )()
		);
		remove_all_filters( 'query' );
		$wpdb->suppress_errors( false );

		$tabella = $wpdb->prefix . 'conformita_core_registro';
		add_filter(
			'query',
			static function ( $sql ) use ( $tabella ) {
				return 0 === strpos( ltrim( (string) $sql ), 'INSERT INTO `' . $tabella . '`' ) ? 'INSERT INTO `' . $tabella . '_inesistente` VALUES (1)' : $sql;
			}
		);
		$wpdb->suppress_errors( true );
		$esito = SchermataRinvii::salva( $this->richiesta( 'zeta_uno', $this->valido() ) );
		$wpdb->suppress_errors( false );
		remove_all_filters( 'query' );

		$this->assertSame( 'registro', $esito['codice'] );
		$this->assertArrayNotHasKey( 'zeta_uno', Rinvii::conservati() );
		$this->assertCount( 1, $this->tracce() );

		add_filter(
			'pre_update_option_' . Rinvii::OPZIONE,
			static function ( $valore, $vecchio ) {
				return $vecchio;
			},
			10,
			2
		);

		$esito = SchermataRinvii::salva( $this->richiesta( 'zeta_uno', $this->valido() ) );

		$this->assertSame( 'scrittura', $esito['codice'] );
		$this->assertStringContainsString( 'cambio che non è avvenuto', $esito['messaggio'] );
		$this->assertArrayNotHasKey( 'zeta_uno', Rinvii::conservati() );
	}

	/**
	 * T-95: righe conservate senza la forma di un rinvio non si restituiscono.
	 */
	public function test_t95_righe_strane_conservate(): void {
		update_option(
			Rinvii::OPZIONE,
			array(
				'zeta_uno' => $this->valido(),
				7          => $this->valido(),
				'alfa'     => array_merge( $this->valido(), array( 'banca' => 1 ) ),
				'zeta_due' => 'banca_uno',
			)
		);

		$this->assertSame( array( 'zeta_uno' => $this->valido() ), Rinvii::conservati() );
		$this->assertSame( array( 'zeta_uno' => $this->valido() ), Rinvii::dati() );

		update_option( Rinvii::OPZIONE, 'non un elenco' );
		$this->assertSame( array(), Rinvii::conservati() );
	}

	/**
	 * T-96: la schermata la apre solo chi ha il permesso della sezione, ed elenca le sole voci con banche dati.
	 */
	public function test_t96_schermata(): void {
		SchermataRinvii::salva( $this->richiesta( 'zeta_uno', $this->valido() ) );

		$html = $this->schermata();

		$this->assertStringContainsString( '<h2>Voce Zeta &gt; Voce Zeta Uno</h2>', $html );
		$this->assertStringContainsString( '<h2>Voce Alfa</h2>', $html );
		$this->assertStringNotContainsString( 'Voce Zeta Due', $html, 'Senza banche dati la voce non si elenca.' );
		$this->assertStringContainsString( '<option value="banca_uno" selected>Banca Uno (tenuta da Titolare Uno)</option>', $html );
		$this->assertStringContainsString( '<option value="banca_due">Banca Due (tenuta da Titolare Due), solo enti finti</option>', $html );
		$this->assertStringContainsString( '<option value="banca_tre">Banca Tre &lt;b&gt;</option>', $html, 'Senza titolare, e con escaping.' );
		$this->assertSame( 1, substr_count( $html, 'value="banca_tre"' ), 'Ogni voce offre le sole sue banche dati.' );
		$this->assertSame( 2, substr_count( $html, '<option value="" ' ) + substr_count( $html, '<option value="">' ), 'Ogni modulo offre di non rinviare.' );
		$this->assertStringContainsString( 'name="partenza[indirizzo]" value="' . esc_attr( $this->valido()['indirizzo'] ) . '"', $html, 'Il rinvio conservato e la partenza del modulo.' );
		$this->assertStringContainsString( 'name="rinvio[etichetta]" value="Dati dell&#039;ente nella Banca Uno"', $html );
		$this->assertStringContainsString( 'name="voce" value="alfa"', $html );
		$this->assertStringContainsString( 'solo per i dati che si trovano davvero nella banca dati', $html );
		$this->assertStringNotContainsString( 'non vale più', $html );

		wp_set_current_user( $this->amministratore );
		$this->expectException( WPDieException::class );
		$this->schermata();
	}

	/**
	 * T-96: il rinvio conservato che non vale piu con l'albero in vigore e segnalato.
	 */
	public function test_t96_rinvio_non_piu_valido(): void {
		update_option(
			Rinvii::OPZIONE,
			array(
				'zeta_uno' => array_merge( $this->valido(), array( 'banca' => 'banca_tre' ) ),
				'alfa'     => array_merge( $this->valido(), array( 'etichetta' => '<script>x</script>' ) ),
			)
		);

		$html = $this->schermata();

		$this->assertSame( 2, substr_count( $html, 'non vale più con l&#039;albero in vigore' ) );
		$this->assertStringNotContainsString( '<script>x</script>', $html, 'Ogni valore con escaping.' );
		$this->assertStringContainsString( 'value="&lt;script&gt;x&lt;/script&gt;"', $html );
	}

	/**
	 * T-96: dopo un rifiuto il modulo della voce rimostra quanto inviato, con la partenza di allora.
	 */
	public function test_t96_rifiuto_rimostrato(): void {
		$aperto = Rinvii::vuoto();

		SchermataRinvii::salva( $this->richiesta( 'zeta_uno', $this->valido(), $aperto ) );

		$mio   = array_merge( $this->valido(), array( 'etichetta' => 'Il mio "dato"' ) );
		$esito = SchermataRinvii::salva( $this->richiesta( 'zeta_uno', $mio, $aperto ) );

		$this->assertSame( 'conflitto', $esito['codice'] );

		set_transient( SchermataRinvii::ESITO . $this->responsabile, $esito, MINUTE_IN_SECONDS );
		$html = $this->schermata();

		$this->assertStringContainsString( 'qualcun altro ha cambiato il rinvio', $html );
		$this->assertStringContainsString( 'name="rinvio[etichetta]" value="Il mio &quot;dato&quot;"', $html );
		$this->assertStringNotContainsString( 'name="partenza[indirizzo]" value="' . esc_attr( $this->valido()['indirizzo'] ) . '"', $html, 'La partenza di allora, non quella riletta.' );
		$this->assertFalse( get_transient( SchermataRinvii::ESITO . $this->responsabile ), 'L\'esito si mostra una volta.' );
		$this->assertSame( 1, substr_count( $html, 'Il mio &quot;dato&quot;' ), 'Solo nel modulo della voce rifiutata.' );
	}

	/**
	 * T-96: senza albero la schermata lo dice e non offre moduli.
	 */
	public function test_t96_albero_assente(): void {
		PaginaPubblica::fissa_file( __DIR__ . '/dati/inesistente.json' );
		PaginaPubblica::da_init();

		$html = $this->schermata();

		$this->assertStringContainsString( 'non è disponibile', $html );
		$this->assertStringNotContainsString( '<form', $html );
	}
}
