<?php
/**
 * Prove dei dati dell'accesso civico: righe T-99..T-102.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Avvio;
use TrasparenzaPa\Blocco;
use TrasparenzaPa\PaginaPubblica;
use TrasparenzaPa\Permessi;
use TrasparenzaPa\AccessoCivico;
use TrasparenzaPa\Alberatura;
use TrasparenzaPa\SchermataAccessoCivico;
use TrasparenzaPa\SchermataStati;
use WPDieException;
use WP_UnitTestCase;

/**
 * Chi indica a chi si chiede l'accesso civico, quali dati si accettano.
 */
class AccessoCivicoTest extends WP_UnitTestCase {

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
	 * Sezione registrata, albero finto con le due collocazioni, nessun dato, permesso a un solo utente.
	 */
	public function set_up(): void {
		parent::set_up();

		\Conformita_Core_Sezioni::azzera();
		Avvio::azzera();
		Avvio::esegui();

		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-accesso-civico.json' );
		PaginaPubblica::da_init();

		delete_option( AccessoCivico::OPZIONE );
		get_role( 'administrator' )->remove_cap( Permessi::AMMINISTRAZIONE );

		$this->responsabile   = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->amministratore = self::factory()->user->create( array( 'role' => 'administrator' ) );

		get_user_by( 'id', $this->responsabile )->add_cap( Permessi::AMMINISTRAZIONE );
	}

	/**
	 * Rimette l'albero finto e il permesso all'amministratore, come lo lascia l'avvio della suite.
	 */
	public function tear_down(): void {
		remove_all_filters( 'query' );
		remove_all_filters( 'pre_update_option_' . AccessoCivico::OPZIONE );
		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-finto.json' );
		PaginaPubblica::da_init();
		get_role( 'administrator' )->add_cap( Permessi::AMMINISTRAZIONE );

		parent::tear_down();
	}

	/**
	 * Dati validi, da guastare un campo alla volta.
	 *
	 * @return array<string, string>
	 */
	private function validi(): array {
		return array(
			'ufficio'            => 'Ufficio relazioni con il pubblico',
			'ufficio_email'      => 'urp@example.org',
			'ufficio_telefono'   => '+39 06 1234567',
			'sostituto'          => 'Paolo Verdi, segretario generale',
			'sostituto_email'    => 'sostituto@example.org',
			'sostituto_telefono' => '06 7654321',
			'modulo'             => 'https://example.org/moduli/accesso-civico.pdf',
		);
	}

	/**
	 * Una richiesta come la manderebbe il modulo, con il nonce dell'utente corrente.
	 *
	 * Senza valori di partenza indicati, porta quelli che il modulo mostrerebbe
	 * se fosse aperto adesso.
	 *
	 * @param mixed                     $dati     Campi del responsabile.
	 * @param array<string, mixed>|null $partenze Valori mostrati all'apertura del modulo.
	 * @return array<string, mixed>
	 */
	private function richiesta( $dati, ?array $partenze = null ): array {
		return array(
			'_wpnonce'                             => wp_create_nonce( SchermataAccessoCivico::AZIONE ),
			SchermataAccessoCivico::CAMPO          => $dati,
			SchermataAccessoCivico::CAMPO_PARTENZA => $partenze ?? ( AccessoCivico::dati() ?? array_fill_keys( AccessoCivico::CAMPI, '' ) ),
		);
	}

	/**
	 * Le voci di registro del responsabile, in ordine di scrittura.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function tracce(): array {
		return conformita_core_voci_registro(
			array(
				'sezione' => \TrasparenzaPa\SEZIONE,
				'azione'  => SchermataAccessoCivico::AZIONE_REGISTRO,
			)
		);
	}

	/**
	 * La schermata come la disegnerebbe WordPress per l'utente corrente.
	 *
	 * @return string
	 */
	private function schermata(): string {
		ob_start();

		try {
			SchermataAccessoCivico::mostra();
		} finally {
			$html = (string) ob_get_clean();
		}

		return $html;
	}

	/**
	 * T-100: i dati validi passano, ripuliti dagli spazi ai bordi; l'indirizzo del modulo e facoltativo.
	 */
	public function test_t100_dati_validi(): void {
		$this->assertSame( $this->validi(), AccessoCivico::controlla( $this->validi() ) );

		$senza = array_merge(
			$this->validi(),
			array(
				'ufficio' => '  Ufficio relazioni con il pubblico ',
				'modulo'  => '',
			)
		);

		$this->assertSame( array_merge( $this->validi(), array( 'modulo' => '' ) ), AccessoCivico::controlla( $senza ) );

		$lungo = array_merge( $this->validi(), array( 'modulo' => 'https://example.org/' . str_repeat( 'a', 1980 ) ) );
		$this->assertSame( $lungo, AccessoCivico::controlla( $lungo ), 'Duemila caratteri si accettano.' );

		$accentato = array_merge( $this->validi(), array( 'sostituto' => str_repeat( 'à', 200 ) ) );
		$this->assertSame( $accentato, AccessoCivico::controlla( $accentato ), 'Duecento caratteri si accettano, anche accentati.' );
	}

	/**
	 * T-100: [attacco] ogni campo sbagliato si rifiuta, invece di salvarlo cambiato.
	 */
	public function test_t100_dati_sbagliati_rifiutati(): void {
		$guasti = array(
			'ufficio mancante'           => array( 'ufficio' => '' ),
			'ufficio con codice'         => array( 'ufficio' => 'Ufficio <script>alert(1)</script>' ),
			'ufficio su due righe'       => array( 'ufficio' => "Ufficio\nrelazioni" ),
			'ufficio troppo lungo'       => array( 'ufficio' => str_repeat( 'a', 201 ) ),
			'ufficio non testo'          => array( 'ufficio' => array( 'Ufficio' ) ),
			'email ufficio mancante'     => array( 'ufficio_email' => '' ),
			'email ufficio non valida'   => array( 'ufficio_email' => 'urp' ),
			'email ufficio con codice'   => array( 'ufficio_email' => 'a<b>@example.org' ),
			'telefono ufficio mancante'  => array( 'ufficio_telefono' => '' ),
			'telefono ufficio lettere'   => array( 'ufficio_telefono' => '06 CHIAMAMI' ),
			'telefono ufficio codice'    => array( 'ufficio_telefono' => '06<b>123</b>' ),
			'sostituto mancante'         => array( 'sostituto' => '' ),
			'sostituto con codice'       => array( 'sostituto' => 'Paolo <b>Verdi</b>' ),
			'sostituto su due righe'     => array( 'sostituto' => "Paolo\nVerdi" ),
			'sostituto troppo lungo'     => array( 'sostituto' => str_repeat( 'à', 201 ) ),
			'sostituto non testo'        => array( 'sostituto' => 1 ),
			'email sostituto mancante'   => array( 'sostituto_email' => '' ),
			'email sostituto non valida' => array( 'sostituto_email' => 'sostituto@' ),
			'telefono sostituto manca'   => array( 'sostituto_telefono' => '' ),
			'telefono sostituto lettere' => array( 'sostituto_telefono' => 'interno 12' ),
			'modulo javascript'          => array( 'modulo' => 'javascript:alert(1)' ),
			'modulo senza schema'        => array( 'modulo' => 'example.org/modulo.pdf' ),
			'modulo relativo'            => array( 'modulo' => '/moduli/modulo.pdf' ),
			'modulo ftp'                 => array( 'modulo' => 'ftp://example.org/modulo.pdf' ),
			'modulo con virgolette'      => array( 'modulo' => 'https://example.org/"onmouseover="x' ),
			'modulo con spazi'           => array( 'modulo' => 'https://example.org/modulo di accesso.pdf' ),
			'modulo lungo'               => array( 'modulo' => 'https://example.org/' . str_repeat( 'a', 1981 ) ),
			'modulo non testo'           => array( 'modulo' => array() ),
		);

		foreach ( $guasti as $caso => $guasto ) {
			$esito = AccessoCivico::controlla( array_merge( $this->validi(), $guasto ) );

			$this->assertWPError( $esito, $caso );
			$this->assertSame( 'trasparenza_accesso_civico_dati', $esito->get_error_code(), $caso );
			$this->assertNotSame( '', $esito->get_error_message(), $caso );
		}

		$monco = $this->validi();
		unset( $monco['sostituto_email'] );
		$this->assertWPError( AccessoCivico::controlla( $monco ), 'Campo obbligatorio assente.' );
	}

	/**
	 * T-100: dati conservati che non superano il controllo valgono come non indicati.
	 */
	public function test_t100_dati_conservati_estranei_non_indicati(): void {
		update_option( AccessoCivico::OPZIONE, array_merge( $this->validi(), array( 'sostituto' => '<b>Paolo</b>' ) ) );
		$this->assertNull( AccessoCivico::dati() );

		update_option( AccessoCivico::OPZIONE, 'Paolo Verdi' );
		$this->assertNull( AccessoCivico::dati() );
	}

	/**
	 * T-102: sottovoce di menu con il permesso della sezione; schermata rifiutata senza.
	 */
	public function test_t102_menu_e_schermata_solo_con_permesso(): void {
		global $submenu;

		set_current_screen( 'dashboard' );

		$submenu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- la prova ricostruisce il menu da zero.
		wp_set_current_user( $this->responsabile );
		SchermataStati::da_admin_menu();
		SchermataAccessoCivico::da_admin_menu();

		$voce = array_values( wp_list_filter( $submenu[ SchermataStati::PAGINA ] ?? array(), array( 2 => SchermataAccessoCivico::PAGINA ) ) );

		$this->assertCount( 1, $voce, 'Sottovoce della voce della sezione.' );
		$this->assertSame( Permessi::AMMINISTRAZIONE, $voce[0][1], 'La sottovoce chiede il permesso della sezione, non uno generico.' );

		wp_set_current_user( $this->amministratore );

		$this->expectException( WPDieException::class );
		$this->schermata();
	}

	/**
	 * T-101: [attacco] salvataggio senza permesso, senza nonce, con il nonce di un'altra azione o di un altro utente.
	 */
	public function test_t101_salvataggio_rifiutato_senza_permesso_o_nonce(): void {
		wp_set_current_user( $this->amministratore );
		$this->assertSame( 'permesso', SchermataAccessoCivico::salva( $this->richiesta( $this->validi() ) )['codice'] );

		wp_set_current_user( $this->responsabile );

		$senza = $this->richiesta( $this->validi() );
		unset( $senza['_wpnonce'] );
		$this->assertSame( 'nonce', SchermataAccessoCivico::salva( $senza )['codice'] );

		$altra             = $this->richiesta( $this->validi() );
		$altra['_wpnonce'] = wp_create_nonce( SchermataStati::AZIONE );
		$this->assertSame( 'nonce', SchermataAccessoCivico::salva( $altra )['codice'] );

		$non_testo             = $this->richiesta( $this->validi() );
		$non_testo['_wpnonce'] = array( wp_create_nonce( SchermataAccessoCivico::AZIONE ) );
		$this->assertSame( 'nonce', SchermataAccessoCivico::salva( $non_testo )['codice'] );

		wp_set_current_user( $this->amministratore );
		$di_altri = wp_create_nonce( SchermataAccessoCivico::AZIONE );
		wp_set_current_user( $this->responsabile );

		$rubato             = $this->richiesta( $this->validi() );
		$rubato['_wpnonce'] = $di_altri;
		$this->assertSame( 'nonce', SchermataAccessoCivico::salva( $rubato )['codice'] );

		$this->assertFalse( get_option( AccessoCivico::OPZIONE ), 'Niente scritto.' );
		$this->assertCount( 0, $this->tracce(), 'Niente nel registro.' );
	}

	/**
	 * T-101: un salvataggio valido scrive i dati e una voce di registro con i soli nomi dei campi.
	 */
	public function test_t101_salvataggio_e_registro_senza_valori(): void {
		wp_set_current_user( $this->responsabile );

		$esito = SchermataAccessoCivico::salva( $this->richiesta( $this->validi() ) );

		$this->assertSame( 'salvato', $esito['codice'] );
		$this->assertSame( AccessoCivico::CAMPI, $esito['campi'] );
		$this->assertSame( $this->validi(), AccessoCivico::dati() );

		$nuovi                  = $this->validi();
		$nuovi['ufficio_email'] = 'nuovo@example.org';
		SchermataAccessoCivico::salva( $this->richiesta( $nuovi ) );

		$tracce = $this->tracce();

		$this->assertCount( 2, $tracce );
		$this->assertSame( array( array( 'campi' => AccessoCivico::CAMPI ), array( 'campi' => array( 'ufficio_email' ) ) ), wp_list_pluck( $tracce, 'dettagli' ) );
		$this->assertSame( array( $this->responsabile, $this->responsabile ), array_map( 'intval', wp_list_pluck( $tracce, 'utente' ) ) );

		$scritto = wp_json_encode( $tracce );

		foreach ( array( 'Paolo', 'Ufficio relazioni', 'urp@example.org', 'sostituto@example.org', 'nuovo@example.org', '1234567', '7654321', 'accesso-civico.pdf' ) as $valore ) {
			$this->assertStringNotContainsString( $valore, (string) $scritto, 'Il registro non conserva i dati personali.' );
		}
	}

	/**
	 * T-101: rimandare gli stessi dati non e un cambio e non va nel registro.
	 */
	public function test_t101_dati_uguali_non_registrati(): void {
		wp_set_current_user( $this->responsabile );

		SchermataAccessoCivico::salva( $this->richiesta( $this->validi() ) );
		$esito = SchermataAccessoCivico::salva( $this->richiesta( $this->validi() ) );

		$this->assertSame( 'salvato', $esito['codice'] );
		$this->assertSame( array(), $esito['campi'] );
		$this->assertCount( 1, $this->tracce() );
	}

	/**
	 * T-101: con un campo sbagliato non si scrive niente, e il modulo rimostra quello che si era scritto.
	 */
	public function test_t101_dati_sbagliati_niente_scritto(): void {
		wp_set_current_user( $this->responsabile );
		SchermataAccessoCivico::salva( $this->richiesta( $this->validi() ) );

		$sbagliati                    = $this->validi();
		$sbagliati['sostituto']       = 'Giulia Bianchi';
		$sbagliati['sostituto_email'] = 'non-valida';

		$esito = SchermataAccessoCivico::salva( $this->richiesta( $sbagliati ) );

		$this->assertSame( 'dati', $esito['codice'] );
		$this->assertSame( $this->validi(), AccessoCivico::dati(), 'Nemmeno il titolare, che era buono.' );
		$this->assertCount( 1, $this->tracce() );
		$this->assertSame( 'dati', SchermataAccessoCivico::salva( $this->richiesta( 'Paolo Verdi' ) )['codice'], 'Campo che non e un elenco.' );

		set_transient( SchermataAccessoCivico::ESITO . $this->responsabile, $esito, MINUTE_IN_SECONDS );
		$html = $this->schermata();

		$this->assertStringContainsString( 'value="Giulia Bianchi"', $html );
		$this->assertStringContainsString( 'value="non-valida"', $html );
	}

	/**
	 * T-101: se il registro rifiuta, i dati non si scrivono.
	 */
	public function test_t101_registro_che_rifiuta_niente_scritto(): void {
		global $wpdb;

		wp_set_current_user( $this->responsabile );

		$tabella = $wpdb->prefix . 'conformita_core_registro';

		add_filter(
			'query',
			static function ( $sql ) use ( $tabella ) {
				return 0 === strpos( ltrim( (string) $sql ), 'INSERT INTO `' . $tabella . '`' ) ? 'INSERT INTO `' . $tabella . '_inesistente` VALUES (1)' : $sql;
			}
		);

		$wpdb->suppress_errors( true );
		$esito = SchermataAccessoCivico::salva( $this->richiesta( $this->validi() ) );
		$wpdb->suppress_errors( false );
		remove_all_filters( 'query' );

		$this->assertSame( 'registro', $esito['codice'] );
		$this->assertNull( AccessoCivico::dati() );
		$this->assertCount( 0, $this->tracce() );
	}

	/**
	 * T-101: se la lettura dei dati attuali fallisce, non si scrive niente e il registro non dice cambiato quello che non lo è.
	 */
	public function test_t101_lettura_fallita_niente_scritto(): void {
		global $wpdb;

		wp_set_current_user( $this->responsabile );
		SchermataAccessoCivico::salva( $this->richiesta( $this->validi() ) );

		$nuovi                  = $this->validi();
		$nuovi['ufficio_email'] = 'nuovo@example.org';
		$richiesta              = $this->richiesta( $nuovi );

		$wpdb->suppress_errors( true );
		add_filter(
			'query',
			static function ( $sql ) use ( $wpdb ) {
				$sql = (string) $sql;

				return 0 === strpos( $sql, "SELECT option_value FROM {$wpdb->options} WHERE option_name = '" . AccessoCivico::OPZIONE . "'" )
					? 'SELECT option_value FROM ' . $wpdb->options . '_inesistente'
					: $sql;
			}
		);

		$esito = SchermataAccessoCivico::salva( $richiesta );

		remove_all_filters( 'query' );
		$wpdb->suppress_errors( false );

		$this->assertSame( 'lettura', $esito['codice'] );
		$this->assertSame( $nuovi, $esito['inviati'], 'Il modulo rimostra quanto inviato.' );
		$this->assertSame( $this->validi(), AccessoCivico::conservati(), 'I dati di prima restano.' );
		$this->assertCount( 1, $this->tracce(), 'Nessuna voce di registro per il salvataggio non fatto.' );
	}

	/**
	 * T-101: se i dati non risultano scritti dopo la voce di registro, l'esito lo dice.
	 */
	public function test_t101_dati_non_scritti_esito_di_errore(): void {
		wp_set_current_user( $this->responsabile );

		add_filter(
			'pre_update_option_' . AccessoCivico::OPZIONE,
			static function ( $valore, $vecchio ) {
				return $vecchio;
			},
			10,
			2
		);

		$esito = SchermataAccessoCivico::salva( $this->richiesta( $this->validi() ) );

		$this->assertSame( 'scrittura', $esito['codice'] );
		$this->assertStringContainsString( 'cambio che non è avvenuto', $esito['messaggio'] );
		$this->assertNull( AccessoCivico::dati() );
	}

	/**
	 * T-101: [attacco] un altro salvataggio tiene il blocco: dopo l'attesa si rinuncia senza scrivere.
	 */
	public function test_t101_blocco_occupato_niente_scritto(): void {
		global $wpdb;

		wp_set_current_user( $this->responsabile );

		list( $host, $porta, $socket ) = $wpdb->parse_db_host( DB_HOST );

		$altra = mysqli_init(); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_init -- serve una seconda connessione, che $wpdb non offre.
		$this->assertTrue( $altra->real_connect( (string) $host, DB_USER, DB_PASSWORD, DB_NAME, $porta ? (int) $porta : null, $socket ? (string) $socket : null ) );

		try {
			$this->assertSame( '1', (string) $altra->query( "SELECT GET_LOCK('" . $altra->real_escape_string( Blocco::nome() ) . "', 0)" )->fetch_row()[0] );

			$esito = SchermataAccessoCivico::salva( $this->richiesta( $this->validi() ) );
		} finally {
			$altra->close();
		}

		$this->assertSame( 'occupato', $esito['codice'] );
		$this->assertNull( AccessoCivico::dati() );
		$this->assertCount( 0, $this->tracce() );
	}

	/**
	 * T-102: il modulo porta i valori della banca dati all'apertura, anche quando rimostra quanto inviato.
	 */
	public function test_t102_modulo_porta_i_valori_di_partenza(): void {
		wp_set_current_user( $this->responsabile );
		SchermataAccessoCivico::salva( $this->richiesta( $this->validi() ) );

		$html = $this->schermata();

		foreach ( $this->validi() as $campo => $valore ) {
			$this->assertStringContainsString( 'name="' . SchermataAccessoCivico::CAMPO_PARTENZA . '[' . $campo . ']" value="' . esc_attr( $valore ) . '"', $html, $campo );
		}

		$sbagliati              = $this->validi();
		$sbagliati['sostituto'] = '<b>Paolo</b>';
		// L'esito arriva alla schermata come lo porta la risposta al modulo.
		set_transient( SchermataAccessoCivico::ESITO . get_current_user_id(), SchermataAccessoCivico::salva( $this->richiesta( $sbagliati ) ), MINUTE_IN_SECONDS );
		$html = $this->schermata();

		$this->assertStringContainsString( esc_attr( '<b>Paolo</b>' ), $html, 'Il modulo rimostra quanto inviato.' );
		$this->assertStringContainsString( 'name="' . SchermataAccessoCivico::CAMPO_PARTENZA . '[sostituto]" value="Paolo Verdi, segretario generale"', $html, 'Dopo un rifiuto la partenza resta quella della banca dati.' );
	}

	/**
	 * T-101: [attacco] una richiesta senza valori di partenza, o con valori non testuali, non scrive niente.
	 */
	public function test_t101_partenza_mancante_o_sbagliata(): void {
		wp_set_current_user( $this->responsabile );

		$senza = $this->richiesta( $this->validi() );
		unset( $senza[ SchermataAccessoCivico::CAMPO_PARTENZA ] );
		$this->assertSame( 'dati', SchermataAccessoCivico::salva( $senza )['codice'] );

		$monca = $this->richiesta( $this->validi(), array( 'sostituto' => '' ) );
		$this->assertSame( 'dati', SchermataAccessoCivico::salva( $monca )['codice'] );

		$non_testo = $this->richiesta( $this->validi(), array_fill_keys( AccessoCivico::CAMPI, array() ) );
		$this->assertSame( 'dati', SchermataAccessoCivico::salva( $non_testo )['codice'] );

		$this->assertNull( get_option( AccessoCivico::OPZIONE, null ) );
		$this->assertCount( 0, $this->tracce() );
	}

	/**
	 * T-101: [attacco] due moduli completi aperti insieme non si cancellano a vicenda.
	 *
	 * A cambia l'email, B poi cambia solo il telefono: l'email di A resta. Se
	 * anche B cambia l'email, e un conflitto e non si salva niente.
	 */
	public function test_t101_due_moduli_completi(): void {
		wp_set_current_user( $this->responsabile );
		SchermataAccessoCivico::salva( $this->richiesta( $this->validi() ) );

		$aperto = AccessoCivico::dati();

		$di_a                     = $this->validi();
		$di_a['ufficio_email']    = 'nuova@example.org';
		$di_b                     = $this->validi();
		$di_b['ufficio_telefono'] = '06 7654321';

		$this->assertSame( 'salvato', SchermataAccessoCivico::salva( $this->richiesta( $di_a, $aperto ) )['codice'] );
		$esito = SchermataAccessoCivico::salva( $this->richiesta( $di_b, $aperto ) );

		$this->assertSame( 'salvato', $esito['codice'] );
		$this->assertSame( array( 'ufficio_telefono' ), $esito['campi'], 'Il registro di B dice solo il campo cambiato da B.' );
		$this->assertSame( 'nuova@example.org', AccessoCivico::dati()['ufficio_email'], 'L\'email di A resta.' );
		$this->assertSame( '06 7654321', AccessoCivico::dati()['ufficio_telefono'] );

		$di_c                  = $this->validi();
		$di_c['ufficio_email'] = 'altra@example.org';
		$tracce                = count( $this->tracce() );
		$esito                 = SchermataAccessoCivico::salva( $this->richiesta( $di_c, $aperto ) );

		$this->assertSame( 'conflitto', $esito['codice'] );
		$this->assertSame( 'nuova@example.org', AccessoCivico::dati()['ufficio_email'], 'Niente scritto.' );
		$this->assertCount( $tracce, $this->tracce(), 'Niente nel registro.' );
	}

	/**
	 * T-102: [attacco] un rifiuto rimostrato non perde i valori di partenza del modulo.
	 *
	 * A e B aprono insieme; A cambia l'email; B invia un telefono sbagliato, e
	 * il rifiuto gli rimostra il modulo; B corregge il telefono e reinvia il
	 * modulo com'e. L'email di A resta e passa solo il telefono di B.
	 */
	public function test_t102_rifiuto_rimostrato_conserva_la_partenza(): void {
		wp_set_current_user( $this->responsabile );
		SchermataAccessoCivico::salva( $this->richiesta( $this->validi() ) );

		$aperto = AccessoCivico::dati();

		$di_a                  = $this->validi();
		$di_a['ufficio_email'] = 'nuova@example.org';
		$this->assertSame( 'salvato', SchermataAccessoCivico::salva( $this->richiesta( $di_a, $aperto ) )['codice'] );

		$di_b                     = $this->validi();
		$di_b['ufficio_telefono'] = 'non un numero';
		$rifiuto                  = SchermataAccessoCivico::salva( $this->richiesta( $di_b, $aperto ) );
		$this->assertSame( 'dati', $rifiuto['codice'] );

		set_transient( SchermataAccessoCivico::ESITO . get_current_user_id(), $rifiuto, MINUTE_IN_SECONDS );
		$modulo = $this->campi_del_modulo( $this->schermata() );

		$modulo[ SchermataAccessoCivico::CAMPO ]['ufficio_telefono'] = '06 7654321';
		$esito = SchermataAccessoCivico::salva( $this->richiesta( $modulo[ SchermataAccessoCivico::CAMPO ], $modulo[ SchermataAccessoCivico::CAMPO_PARTENZA ] ) );

		$this->assertSame( 'nuova@example.org', AccessoCivico::dati()['ufficio_email'], 'Il reinvio non cancella l\'email di A.' );
		$this->assertSame( 'salvato', $esito['codice'] );
		$this->assertSame( array( 'ufficio_telefono' ), $esito['campi'], 'B ha cambiato solo il telefono.' );
	}

	/**
	 * I campi del modulo come li rimanderebbe il browser, letti dalla schermata.
	 *
	 * @param string $html La schermata.
	 * @return array<string, array<string, string>>
	 */
	private function campi_del_modulo( string $html ): array {
		$campi = array(
			SchermataAccessoCivico::CAMPO          => array(),
			SchermataAccessoCivico::CAMPO_PARTENZA => array(),
		);

		preg_match_all( '/name="(' . SchermataAccessoCivico::CAMPO . '|' . SchermataAccessoCivico::CAMPO_PARTENZA . ')\[([a-z_]+)\]" value="([^"]*)"/', $html, $trovati, PREG_SET_ORDER );

		foreach ( $trovati as $campo ) {
			$campi[ $campo[1] ][ $campo[2] ] = html_entity_decode( $campo[3], ENT_QUOTES );
		}

		$this->assertCount( 7, $campi[ SchermataAccessoCivico::CAMPO ] );
		$this->assertCount( 7, $campi[ SchermataAccessoCivico::CAMPO_PARTENZA ] );

		return $campi;
	}

	/**
	 * T-102: senza responsabile, avviso a chi ha il permesso, sulla bacheca e non altrove.
	 */
	public function test_t102_avviso_se_non_indicato(): void {
		$avviso = 'non dice a chi si chiede l&#039;accesso civico';

		set_current_screen( 'dashboard' );
		wp_set_current_user( $this->responsabile );
		$this->assertStringContainsString( $avviso, $this->avvisi_in_bacheca() );

		wp_set_current_user( $this->amministratore );
		$this->assertStringNotContainsString( $avviso, $this->avvisi_in_bacheca(), 'Solo a chi puo rimediare.' );

		wp_set_current_user( $this->responsabile );
		set_current_screen( 'edit-post' );
		$this->assertStringNotContainsString( $avviso, $this->avvisi_in_bacheca(), 'Non su ogni schermata del pannello.' );

		set_current_screen( 'dashboard' );
		SchermataAccessoCivico::salva( $this->richiesta( $this->validi() ) );
		$this->assertStringNotContainsString( $avviso, $this->avvisi_in_bacheca() );
	}

	/**
	 * T-102: [attacco] dopo un rifiuto si rimostrano solo i campi inviati come testo.
	 */
	public function test_t102_rifiuto_con_campi_non_testo(): void {
		wp_set_current_user( $this->responsabile );

		$inviati              = $this->validi();
		$inviati['sostituto'] = array( 'Paolo Verdi' );
		$esito                = SchermataAccessoCivico::salva( $this->richiesta( $inviati ) );

		$this->assertSame( 'dati', $esito['codice'] );

		set_transient( SchermataAccessoCivico::ESITO . $this->responsabile, $esito, MINUTE_IN_SECONDS );
		$html = $this->schermata();

		$this->assertStringContainsString( 'name="' . SchermataAccessoCivico::CAMPO . '[sostituto]" value=""', $html );
		$this->assertStringContainsString( 'name="' . SchermataAccessoCivico::CAMPO . '[ufficio]" value="Ufficio relazioni con il pubblico"', $html );
		$this->assertStringNotContainsString( 'Array', $html );
	}

	/**
	 * T-102: la schermata dice in quale voce compaiono i dati, o che l'albero non lo dice.
	 */
	public function test_t102_collocazione_nella_schermata(): void {
		wp_set_current_user( $this->responsabile );

		$this->assertStringContainsString( 'nella voce &quot;Voce Zeta Due&quot;', $this->schermata() );

		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-finto.json' );
		PaginaPubblica::da_init();

		$html = $this->schermata();

		$this->assertStringContainsString( 'L&#039;albero delle voci non indica dove mostrare i dati dell&#039;accesso civico', $html );
		$this->assertStringNotContainsString( 'nella voce &quot;', $html );
	}

	/**
	 * T-102: l'avviso compare anche sulla schermata della sezione, e sparisce con i dati.
	 */
	public function test_t102_avviso_sulla_schermata_della_sezione(): void {
		$avviso = 'non dice a chi si chiede l&#039;accesso civico';

		wp_set_current_user( $this->responsabile );
		set_current_screen( 'amministrazione-trasparente_page_' . SchermataAccessoCivico::PAGINA );
		$this->assertStringContainsString( $avviso, $this->avvisi_in_bacheca() );

		update_option( AccessoCivico::OPZIONE, array_merge( $this->validi(), array( 'sostituto' => '<b>Paolo</b>' ) ) );
		$this->assertStringContainsString( $avviso, $this->avvisi_in_bacheca(), 'Dati conservati non validi: non indicati.' );
	}

	/**
	 * T-99: la versione 2026-10-30-2 dell'albero e in vigore e porta la collocazione dei dati dell'accesso civico.
	 */
	public function test_t99_versione_con_la_collocazione(): void {
		$cartella = dirname( \TrasparenzaPa\FILE_ALBERATURA );
		$vecchio  = $cartella . '/2026-10-30.json';

		$this->assertSame( '2026-10-30-2.json', basename( \TrasparenzaPa\FILE_ALBERATURA ), 'La versione in vigore e la 2026-10-30-2.' );
		$this->assertSame( '5dda6b36b7768a8dd9b2f99667d83d7d3072f7daeffd54be298c0c5e749451c7', hash_file( 'sha256', $vecchio ), 'La versione 2026-10-30 non si modifica.' );

		$prima = json_decode( (string) file_get_contents( $vecchio ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale del componente.
		$ora   = json_decode( (string) file_get_contents( \TrasparenzaPa\FILE_ALBERATURA ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale del componente.

		$this->assertSame( '2026-10-30-2', $ora['versione'], 'Il nome del file dice la versione.' );
		$this->assertSame( 'accesso_civico', $ora['collocazioni']['accesso_civico'] );
		$this->assertSame( 'anac_1310_2016_all_1', $ora['fonte_collocazioni']['accesso_civico'] );

		$albero = Alberatura::carica( \TrasparenzaPa\FILE_ALBERATURA );

		$this->assertInstanceOf( Alberatura::class, $albero );
		$this->assertSame( 'Accesso civico', $albero->voce( (string) $albero->collocazione( AccessoCivico::DATO ) )['nome'] );
		$this->assertSame( 'prevenzione_della_corruzione', $albero->collocazione( 'responsabile_trasparenza' ), 'La collocazione del responsabile resta.' );
		$this->assertNull( Alberatura::carica( $vecchio )->collocazione( AccessoCivico::DATO ), 'La versione vecchia resta leggibile, senza la collocazione.' );

		unset( $ora['versione'], $ora['collocazioni']['accesso_civico'], $ora['fonte_collocazioni']['accesso_civico'], $prima['versione'] );

		$this->assertSame( $prima, $ora, 'La versione nuova aggiunge la collocazione e la sua fonte e non cambia altro.' );
	}
}
