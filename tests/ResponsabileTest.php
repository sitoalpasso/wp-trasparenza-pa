<?php
/**
 * Prove del responsabile della trasparenza: righe T-36..T-38.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Avvio;
use TrasparenzaPa\Blocco;
use TrasparenzaPa\PaginaPubblica;
use TrasparenzaPa\Permessi;
use TrasparenzaPa\Responsabile;
use TrasparenzaPa\SchermataResponsabile;
use TrasparenzaPa\SchermataStati;
use WPDieException;
use WP_UnitTestCase;

/**
 * Chi indica il responsabile, quali dati si accettano, dove si vedono.
 */
class ResponsabileTest extends WP_UnitTestCase {

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
	 * Sezione registrata, albero finto, nessun responsabile, permesso a un solo utente.
	 */
	public function set_up(): void {
		parent::set_up();

		\Conformita_Core_Sezioni::azzera();
		Avvio::azzera();
		Avvio::esegui();

		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-finto.json' );
		PaginaPubblica::da_init();

		delete_option( Responsabile::OPZIONE );
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
		remove_all_filters( 'pre_update_option_' . Responsabile::OPZIONE );
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
			'nome'     => 'Maria Rossi',
			'email'    => 'responsabile@example.org',
			'telefono' => '+39 06 1234567',
			'nomina'   => 'https://example.org/atti/nomina.pdf',
		);
	}

	/**
	 * Una richiesta come la manderebbe il modulo, con il nonce dell'utente corrente.
	 *
	 * @param mixed $dati Campi del responsabile.
	 * @return array<string, mixed>
	 */
	private function richiesta( $dati ): array {
		return array(
			'_wpnonce'                   => wp_create_nonce( SchermataResponsabile::AZIONE ),
			SchermataResponsabile::CAMPO => $dati,
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
				'azione'  => SchermataResponsabile::AZIONE_REGISTRO,
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
			SchermataResponsabile::mostra();
		} finally {
			$html = (string) ob_get_clean();
		}

		return $html;
	}

	/**
	 * La voce dell'albero finto in cui sta il responsabile, disegnata.
	 *
	 * @param string $voce Voce da disegnare.
	 * @return string
	 */
	private function pagina( string $voce = 'zeta_uno' ): string {
		return PaginaPubblica::disegna( PaginaPubblica::albero(), $voce, 'https://example.org/sezione/' );
	}

	/**
	 * T-36: i dati validi passano, ripuliti dagli spazi ai bordi; telefono e nomina sono facoltativi.
	 */
	public function test_t36_dati_validi(): void {
		$this->assertSame( $this->validi(), Responsabile::controlla( $this->validi() ) );

		$minimi = array(
			'nome'  => '  Maria Rossi ',
			'email' => 'responsabile@example.org',
		);

		$this->assertSame(
			array(
				'nome'     => 'Maria Rossi',
				'email'    => 'responsabile@example.org',
				'telefono' => '',
				'nomina'   => '',
			),
			Responsabile::controlla( $minimi )
		);
	}

	/**
	 * T-36: [attacco] ogni campo sbagliato si rifiuta, invece di salvarlo cambiato.
	 */
	public function test_t36_dati_sbagliati_rifiutati(): void {
		$guasti = array(
			'nome mancante'           => array( 'nome' => '' ),
			'nome con codice'         => array( 'nome' => 'Maria <script>alert(1)</script>' ),
			'nome su due righe'       => array( 'nome' => "Maria\nRossi" ),
			'nome troppo lungo'       => array( 'nome' => str_repeat( 'a', 201 ) ),
			'nome non testo'          => array( 'nome' => array( 'Maria' ) ),
			'email mancante'          => array( 'email' => '' ),
			'email non valida'        => array( 'email' => 'responsabile' ),
			'email con codice'        => array( 'email' => 'a<b>@example.org' ),
			'telefono con lettere'    => array( 'telefono' => '06 CHIAMAMI' ),
			'telefono con codice'     => array( 'telefono' => '06<b>123</b>' ),
			'nomina javascript'       => array( 'nomina' => 'javascript:alert(1)' ),
			'nomina senza schema'     => array( 'nomina' => 'example.org/nomina.pdf' ),
			'nomina relativa'         => array( 'nomina' => '/atti/nomina.pdf' ),
			'nomina senza protocollo' => array( 'nomina' => '//example.org/nomina.pdf' ),
			'nomina con virgolette'   => array( 'nomina' => 'https://example.org/"onmouseover="x' ),
			'nomina con spazi'        => array( 'nomina' => 'https://example.org/atto di nomina.pdf' ),
		);

		foreach ( $guasti as $caso => $guasto ) {
			$esito = Responsabile::controlla( array_merge( $this->validi(), $guasto ) );

			$this->assertWPError( $esito, $caso );
			$this->assertSame( 'trasparenza_responsabile_dati', $esito->get_error_code(), $caso );
		}
	}

	/**
	 * T-36: dati conservati che non superano il controllo valgono come non indicati.
	 */
	public function test_t36_dati_conservati_estranei_non_indicati(): void {
		update_option( Responsabile::OPZIONE, array_merge( $this->validi(), array( 'nome' => '<b>Maria</b>' ) ) );
		$this->assertNull( Responsabile::dati() );

		update_option( Responsabile::OPZIONE, 'Maria Rossi' );
		$this->assertNull( Responsabile::dati() );
	}

	/**
	 * T-37: sottovoce di menu con il permesso della sezione; schermata rifiutata senza.
	 */
	public function test_t37_menu_e_schermata_solo_con_permesso(): void {
		global $submenu;

		set_current_screen( 'dashboard' );

		$submenu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- la prova ricostruisce il menu da zero.
		wp_set_current_user( $this->responsabile );
		SchermataStati::da_admin_menu();
		SchermataResponsabile::da_admin_menu();

		$voce = array_values( wp_list_filter( $submenu[ SchermataStati::PAGINA ] ?? array(), array( 2 => SchermataResponsabile::PAGINA ) ) );

		$this->assertCount( 1, $voce, 'Sottovoce della voce della sezione.' );
		$this->assertSame( Permessi::AMMINISTRAZIONE, $voce[0][1], 'La sottovoce chiede il permesso della sezione, non uno generico.' );

		wp_set_current_user( $this->amministratore );

		$this->expectException( WPDieException::class );
		$this->schermata();
	}

	/**
	 * T-37: [attacco] salvataggio senza permesso, senza nonce, con il nonce di un'altra azione o di un altro utente.
	 */
	public function test_t37_salvataggio_rifiutato_senza_permesso_o_nonce(): void {
		wp_set_current_user( $this->amministratore );
		$this->assertSame( 'permesso', SchermataResponsabile::salva( $this->richiesta( $this->validi() ) )['codice'] );

		wp_set_current_user( $this->responsabile );

		$senza = $this->richiesta( $this->validi() );
		unset( $senza['_wpnonce'] );
		$this->assertSame( 'nonce', SchermataResponsabile::salva( $senza )['codice'] );

		$altra             = $this->richiesta( $this->validi() );
		$altra['_wpnonce'] = wp_create_nonce( SchermataStati::AZIONE );
		$this->assertSame( 'nonce', SchermataResponsabile::salva( $altra )['codice'] );

		$non_testo             = $this->richiesta( $this->validi() );
		$non_testo['_wpnonce'] = array( wp_create_nonce( SchermataResponsabile::AZIONE ) );
		$this->assertSame( 'nonce', SchermataResponsabile::salva( $non_testo )['codice'] );

		wp_set_current_user( $this->amministratore );
		$di_altri = wp_create_nonce( SchermataResponsabile::AZIONE );
		wp_set_current_user( $this->responsabile );

		$rubato             = $this->richiesta( $this->validi() );
		$rubato['_wpnonce'] = $di_altri;
		$this->assertSame( 'nonce', SchermataResponsabile::salva( $rubato )['codice'] );

		$this->assertFalse( get_option( Responsabile::OPZIONE ), 'Niente scritto.' );
		$this->assertCount( 0, $this->tracce(), 'Niente nel registro.' );
	}

	/**
	 * T-37: un salvataggio valido scrive i dati e una voce di registro con i soli nomi dei campi.
	 */
	public function test_t37_salvataggio_e_registro_senza_valori(): void {
		wp_set_current_user( $this->responsabile );

		$esito = SchermataResponsabile::salva( $this->richiesta( $this->validi() ) );

		$this->assertSame( 'salvato', $esito['codice'] );
		$this->assertSame( Responsabile::CAMPI, $esito['campi'] );
		$this->assertSame( $this->validi(), Responsabile::dati() );

		$nuovi          = $this->validi();
		$nuovi['email'] = 'nuovo@example.org';
		SchermataResponsabile::salva( $this->richiesta( $nuovi ) );

		$tracce = $this->tracce();

		$this->assertCount( 2, $tracce );
		$this->assertSame( array( array( 'campi' => Responsabile::CAMPI ), array( 'campi' => array( 'email' ) ) ), wp_list_pluck( $tracce, 'dettagli' ) );
		$this->assertSame( array( $this->responsabile, $this->responsabile ), array_map( 'intval', wp_list_pluck( $tracce, 'utente' ) ) );

		$scritto = wp_json_encode( $tracce );

		foreach ( array( 'Maria', 'responsabile@example.org', 'nuovo@example.org', '1234567', 'nomina.pdf' ) as $valore ) {
			$this->assertStringNotContainsString( $valore, (string) $scritto, 'Il registro non conserva i dati personali.' );
		}
	}

	/**
	 * T-37: rimandare gli stessi dati non e un cambio e non va nel registro.
	 */
	public function test_t37_dati_uguali_non_registrati(): void {
		wp_set_current_user( $this->responsabile );

		SchermataResponsabile::salva( $this->richiesta( $this->validi() ) );
		$esito = SchermataResponsabile::salva( $this->richiesta( $this->validi() ) );

		$this->assertSame( 'salvato', $esito['codice'] );
		$this->assertSame( array(), $esito['campi'] );
		$this->assertCount( 1, $this->tracce() );
	}

	/**
	 * T-37: con un campo sbagliato non si scrive niente, e il modulo rimostra quello che si era scritto.
	 */
	public function test_t37_dati_sbagliati_niente_scritto(): void {
		wp_set_current_user( $this->responsabile );
		SchermataResponsabile::salva( $this->richiesta( $this->validi() ) );

		$sbagliati          = $this->validi();
		$sbagliati['nome']  = 'Giulia Bianchi';
		$sbagliati['email'] = 'non-valida';

		$esito = SchermataResponsabile::salva( $this->richiesta( $sbagliati ) );

		$this->assertSame( 'dati', $esito['codice'] );
		$this->assertSame( $this->validi(), Responsabile::dati(), 'Nemmeno il nome, che era buono.' );
		$this->assertCount( 1, $this->tracce() );
		$this->assertSame( 'dati', SchermataResponsabile::salva( $this->richiesta( 'Maria Rossi' ) )['codice'], 'Campo che non e un elenco.' );

		set_transient( SchermataResponsabile::ESITO . $this->responsabile, $esito, MINUTE_IN_SECONDS );
		$html = $this->schermata();

		$this->assertStringContainsString( 'value="Giulia Bianchi"', $html );
		$this->assertStringContainsString( 'value="non-valida"', $html );
	}

	/**
	 * T-37: se il registro rifiuta, i dati non si scrivono.
	 */
	public function test_t37_registro_che_rifiuta_niente_scritto(): void {
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
		$esito = SchermataResponsabile::salva( $this->richiesta( $this->validi() ) );
		$wpdb->suppress_errors( false );
		remove_all_filters( 'query' );

		$this->assertSame( 'registro', $esito['codice'] );
		$this->assertNull( Responsabile::dati() );
		$this->assertCount( 0, $this->tracce() );
	}

	/**
	 * T-37: se i dati non risultano scritti dopo la voce di registro, l'esito lo dice.
	 */
	public function test_t37_dati_non_scritti_esito_di_errore(): void {
		wp_set_current_user( $this->responsabile );

		add_filter(
			'pre_update_option_' . Responsabile::OPZIONE,
			static function ( $valore, $vecchio ) {
				return $vecchio;
			},
			10,
			2
		);

		$esito = SchermataResponsabile::salva( $this->richiesta( $this->validi() ) );

		$this->assertSame( 'scrittura', $esito['codice'] );
		$this->assertStringContainsString( 'cambio che non è avvenuto', $esito['messaggio'] );
		$this->assertNull( Responsabile::dati() );
	}

	/**
	 * T-37: [attacco] un altro salvataggio tiene il blocco: dopo l'attesa si rinuncia senza scrivere.
	 */
	public function test_t37_blocco_occupato_niente_scritto(): void {
		global $wpdb;

		wp_set_current_user( $this->responsabile );

		list( $host, $porta, $socket ) = $wpdb->parse_db_host( DB_HOST );

		$altra = mysqli_init(); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_init -- serve una seconda connessione, che $wpdb non offre.
		$this->assertTrue( $altra->real_connect( (string) $host, DB_USER, DB_PASSWORD, DB_NAME, $porta ? (int) $porta : null, $socket ? (string) $socket : null ) );

		try {
			$this->assertSame( '1', (string) $altra->query( "SELECT GET_LOCK('" . $altra->real_escape_string( Blocco::nome() ) . "', 0)" )->fetch_row()[0] );

			$esito = SchermataResponsabile::salva( $this->richiesta( $this->validi() ) );
		} finally {
			$altra->close();
		}

		$this->assertSame( 'occupato', $esito['codice'] );
		$this->assertNull( Responsabile::dati() );
		$this->assertCount( 0, $this->tracce() );
	}

	/**
	 * T-37: senza responsabile, avviso a chi ha il permesso, sulla bacheca e non altrove.
	 */
	public function test_t37_avviso_se_non_indicato(): void {
		$avviso = 'responsabile della prevenzione della corruzione e della trasparenza non è indicato';

		set_current_screen( 'dashboard' );
		wp_set_current_user( $this->responsabile );
		$this->assertStringContainsString( $avviso, $this->avvisi_in_bacheca() );

		wp_set_current_user( $this->amministratore );
		$this->assertStringNotContainsString( $avviso, $this->avvisi_in_bacheca(), 'Solo a chi puo rimediare.' );

		wp_set_current_user( $this->responsabile );
		set_current_screen( 'edit-post' );
		$this->assertStringNotContainsString( $avviso, $this->avvisi_in_bacheca(), 'Non su ogni schermata del pannello.' );

		set_current_screen( 'dashboard' );
		SchermataResponsabile::salva( $this->richiesta( $this->validi() ) );
		$this->assertStringNotContainsString( $avviso, $this->avvisi_in_bacheca() );
	}

	/**
	 * T-38: il riquadro compare nella voce indicata dall'albero, con i dati in uscita sicura, e solo lì.
	 */
	public function test_t38_riquadro_nella_voce_indicata(): void {
		update_option(
			Responsabile::OPZIONE,
			array(
				'nome'     => 'Maria & Rossi',
				'email'    => 'responsabile@example.org',
				'telefono' => '06 1234567',
				'nomina'   => 'https://example.org/atti/nomina.pdf?a=1&b=2',
			)
		);

		$html = $this->pagina();

		$this->assertStringContainsString( 'Responsabile della prevenzione della corruzione e della trasparenza', $html );
		$this->assertStringContainsString( '<p>Maria &amp; Rossi</p>', $html );
		$this->assertStringContainsString( 'href="mailto:responsabile@example.org"', $html );
		$this->assertStringContainsString( '06 1234567', $html );
		$this->assertStringContainsString( 'href="https://example.org/atti/nomina.pdf?a=1&#038;b=2"', $html );

		$this->assertStringNotContainsString( 'trasparenza-pa-responsabile', $this->pagina( 'zeta' ) );
		$this->assertStringNotContainsString( 'trasparenza-pa-responsabile', $this->pagina( 'radice' ) );
	}

	/**
	 * T-38: senza telefono e senza nomina, quelle righe non compaiono vuote.
	 */
	public function test_t38_campi_facoltativi_assenti(): void {
		update_option(
			Responsabile::OPZIONE,
			array(
				'nome'     => 'Maria Rossi',
				'email'    => 'responsabile@example.org',
				'telefono' => '',
				'nomina'   => '',
			)
		);

		$html = $this->pagina();

		$this->assertStringContainsString( 'Maria Rossi', $html );
		$this->assertStringNotContainsString( 'Telefono', $html );
		$this->assertStringNotContainsString( 'Atto di nomina', $html );
	}

	/**
	 * T-38: responsabile non indicato, o albero senza collocazione: nessun riquadro.
	 */
	public function test_t38_niente_riquadro_senza_dati_o_collocazione(): void {
		$this->assertStringNotContainsString( 'trasparenza-pa-responsabile', $this->pagina() );

		update_option( Responsabile::OPZIONE, $this->validi() );

		$senza = json_decode( (string) file_get_contents( __DIR__ . '/dati/albero-finto.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale delle prove.
		unset( $senza['collocazioni'] );
		$albero = \TrasparenzaPa\Alberatura::da_dati( $senza );

		foreach ( $albero->identificativi() as $voce ) {
			$this->assertStringNotContainsString( 'trasparenza-pa-responsabile', PaginaPubblica::disegna( $albero, $voce, 'https://example.org/sezione/' ), $voce );
		}
	}
}
