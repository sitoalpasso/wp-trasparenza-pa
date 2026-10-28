<?php
/**
 * Prove del responsabile della trasparenza: righe T-56..T-58.
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
	 * Senza valori di partenza indicati, porta quelli che il modulo mostrerebbe
	 * se fosse aperto adesso.
	 *
	 * @param mixed                     $dati     Campi del responsabile.
	 * @param array<string, mixed>|null $partenze Valori mostrati all'apertura del modulo.
	 * @return array<string, mixed>
	 */
	private function richiesta( $dati, ?array $partenze = null ): array {
		return array(
			'_wpnonce'                            => wp_create_nonce( SchermataResponsabile::AZIONE ),
			SchermataResponsabile::CAMPO          => $dati,
			SchermataResponsabile::CAMPO_PARTENZA => $partenze ?? ( Responsabile::dati() ?? array_fill_keys( Responsabile::CAMPI, '' ) ),
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
	 * T-56: i dati validi passano, ripuliti dagli spazi ai bordi; telefono e nomina sono facoltativi.
	 */
	public function test_t56_dati_validi(): void {
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
	 * T-56: [attacco] ogni campo sbagliato si rifiuta, invece di salvarlo cambiato.
	 */
	public function test_t56_dati_sbagliati_rifiutati(): void {
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
	 * T-56: dati conservati che non superano il controllo valgono come non indicati.
	 */
	public function test_t56_dati_conservati_estranei_non_indicati(): void {
		update_option( Responsabile::OPZIONE, array_merge( $this->validi(), array( 'nome' => '<b>Maria</b>' ) ) );
		$this->assertNull( Responsabile::dati() );

		update_option( Responsabile::OPZIONE, 'Maria Rossi' );
		$this->assertNull( Responsabile::dati() );
	}

	/**
	 * T-57: sottovoce di menu con il permesso della sezione; schermata rifiutata senza.
	 */
	public function test_t57_menu_e_schermata_solo_con_permesso(): void {
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
	 * T-57: [attacco] salvataggio senza permesso, senza nonce, con il nonce di un'altra azione o di un altro utente.
	 */
	public function test_t57_salvataggio_rifiutato_senza_permesso_o_nonce(): void {
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
	 * T-57: un salvataggio valido scrive i dati e una voce di registro con i soli nomi dei campi.
	 */
	public function test_t57_salvataggio_e_registro_senza_valori(): void {
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
	 * T-57: rimandare gli stessi dati non e un cambio e non va nel registro.
	 */
	public function test_t57_dati_uguali_non_registrati(): void {
		wp_set_current_user( $this->responsabile );

		SchermataResponsabile::salva( $this->richiesta( $this->validi() ) );
		$esito = SchermataResponsabile::salva( $this->richiesta( $this->validi() ) );

		$this->assertSame( 'salvato', $esito['codice'] );
		$this->assertSame( array(), $esito['campi'] );
		$this->assertCount( 1, $this->tracce() );
	}

	/**
	 * T-57: con un campo sbagliato non si scrive niente, e il modulo rimostra quello che si era scritto.
	 */
	public function test_t57_dati_sbagliati_niente_scritto(): void {
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
	 * T-57: se il registro rifiuta, i dati non si scrivono.
	 */
	public function test_t57_registro_che_rifiuta_niente_scritto(): void {
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
	 * T-57: se la lettura dei dati attuali fallisce, non si scrive niente e il registro non dice cambiato quello che non lo è.
	 */
	public function test_t57_lettura_fallita_niente_scritto(): void {
		global $wpdb;

		wp_set_current_user( $this->responsabile );
		SchermataResponsabile::salva( $this->richiesta( $this->validi() ) );

		$nuovi          = $this->validi();
		$nuovi['email'] = 'nuovo@example.org';
		$richiesta      = $this->richiesta( $nuovi );

		$wpdb->suppress_errors( true );
		add_filter(
			'query',
			static function ( $sql ) use ( $wpdb ) {
				$sql = (string) $sql;

				return 0 === strpos( $sql, "SELECT option_value FROM {$wpdb->options} WHERE option_name = '" . Responsabile::OPZIONE . "'" )
					? 'SELECT option_value FROM ' . $wpdb->options . '_inesistente'
					: $sql;
			}
		);

		$esito = SchermataResponsabile::salva( $richiesta );

		remove_all_filters( 'query' );
		$wpdb->suppress_errors( false );

		$this->assertSame( 'lettura', $esito['codice'] );
		$this->assertSame( $nuovi, $esito['inviati'], 'Il modulo rimostra quanto inviato.' );
		$this->assertSame( $this->validi(), Responsabile::conservati(), 'I dati di prima restano.' );
		$this->assertCount( 1, $this->tracce(), 'Nessuna voce di registro per il salvataggio non fatto.' );
	}

	/**
	 * T-57: se i dati non risultano scritti dopo la voce di registro, l'esito lo dice.
	 */
	public function test_t57_dati_non_scritti_esito_di_errore(): void {
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
	 * T-57: [attacco] un altro salvataggio tiene il blocco: dopo l'attesa si rinuncia senza scrivere.
	 */
	public function test_t57_blocco_occupato_niente_scritto(): void {
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
	 * T-57: il modulo porta i valori della banca dati all'apertura, anche quando rimostra quanto inviato.
	 */
	public function test_t57_modulo_porta_i_valori_di_partenza(): void {
		wp_set_current_user( $this->responsabile );
		SchermataResponsabile::salva( $this->richiesta( $this->validi() ) );

		$html = $this->schermata();

		foreach ( $this->validi() as $campo => $valore ) {
			$this->assertStringContainsString( 'name="' . SchermataResponsabile::CAMPO_PARTENZA . '[' . $campo . ']" value="' . esc_attr( $valore ) . '"', $html, $campo );
		}

		$sbagliati         = $this->validi();
		$sbagliati['nome'] = '<b>Maria</b>';
		// L'esito arriva alla schermata come lo porta la risposta al modulo.
		set_transient( SchermataResponsabile::ESITO . get_current_user_id(), SchermataResponsabile::salva( $this->richiesta( $sbagliati ) ), MINUTE_IN_SECONDS );
		$html = $this->schermata();

		$this->assertStringContainsString( esc_attr( '<b>Maria</b>' ), $html, 'Il modulo rimostra quanto inviato.' );
		$this->assertStringContainsString( 'name="' . SchermataResponsabile::CAMPO_PARTENZA . '[nome]" value="Maria Rossi"', $html, 'Dopo un rifiuto la partenza resta quella della banca dati.' );
	}

	/**
	 * T-57: [attacco] una richiesta senza valori di partenza, o con valori non testuali, non scrive niente.
	 */
	public function test_t57_partenza_mancante_o_sbagliata(): void {
		wp_set_current_user( $this->responsabile );

		$senza = $this->richiesta( $this->validi() );
		unset( $senza[ SchermataResponsabile::CAMPO_PARTENZA ] );
		$this->assertSame( 'dati', SchermataResponsabile::salva( $senza )['codice'] );

		$monca = $this->richiesta( $this->validi(), array( 'nome' => '' ) );
		$this->assertSame( 'dati', SchermataResponsabile::salva( $monca )['codice'] );

		$non_testo = $this->richiesta( $this->validi(), array_fill_keys( Responsabile::CAMPI, array() ) );
		$this->assertSame( 'dati', SchermataResponsabile::salva( $non_testo )['codice'] );

		$this->assertNull( get_option( Responsabile::OPZIONE, null ) );
		$this->assertCount( 0, $this->tracce() );
	}

	/**
	 * T-57: [attacco] due moduli completi aperti insieme non si cancellano a vicenda.
	 *
	 * A cambia l'email, B poi cambia solo il telefono: l'email di A resta. Se
	 * anche B cambia l'email, e un conflitto e non si salva niente.
	 */
	public function test_t57_due_moduli_completi(): void {
		wp_set_current_user( $this->responsabile );
		SchermataResponsabile::salva( $this->richiesta( $this->validi() ) );

		$aperto = Responsabile::dati();

		$di_a             = $this->validi();
		$di_a['email']    = 'nuova@example.org';
		$di_b             = $this->validi();
		$di_b['telefono'] = '06 7654321';

		$this->assertSame( 'salvato', SchermataResponsabile::salva( $this->richiesta( $di_a, $aperto ) )['codice'] );
		$esito = SchermataResponsabile::salva( $this->richiesta( $di_b, $aperto ) );

		$this->assertSame( 'salvato', $esito['codice'] );
		$this->assertSame( array( 'telefono' ), $esito['campi'], 'Il registro di B dice solo il campo cambiato da B.' );
		$this->assertSame( 'nuova@example.org', Responsabile::dati()['email'], 'L\'email di A resta.' );
		$this->assertSame( '06 7654321', Responsabile::dati()['telefono'] );

		$di_c          = $this->validi();
		$di_c['email'] = 'altra@example.org';
		$tracce        = count( $this->tracce() );
		$esito         = SchermataResponsabile::salva( $this->richiesta( $di_c, $aperto ) );

		$this->assertSame( 'conflitto', $esito['codice'] );
		$this->assertSame( 'nuova@example.org', Responsabile::dati()['email'], 'Niente scritto.' );
		$this->assertCount( $tracce, $this->tracce(), 'Niente nel registro.' );
	}

	/**
	 * T-57: [attacco] un rifiuto rimostrato non perde i valori di partenza del modulo.
	 *
	 * A e B aprono insieme; A cambia l'email; B invia un telefono sbagliato, e
	 * il rifiuto gli rimostra il modulo; B corregge il telefono e reinvia il
	 * modulo com'e. L'email di A resta e passa solo il telefono di B.
	 */
	public function test_t57_rifiuto_rimostrato_conserva_la_partenza(): void {
		wp_set_current_user( $this->responsabile );
		SchermataResponsabile::salva( $this->richiesta( $this->validi() ) );

		$aperto = Responsabile::dati();

		$di_a          = $this->validi();
		$di_a['email'] = 'nuova@example.org';
		$this->assertSame( 'salvato', SchermataResponsabile::salva( $this->richiesta( $di_a, $aperto ) )['codice'] );

		$di_b             = $this->validi();
		$di_b['telefono'] = 'non un numero';
		$rifiuto          = SchermataResponsabile::salva( $this->richiesta( $di_b, $aperto ) );
		$this->assertSame( 'dati', $rifiuto['codice'] );

		set_transient( SchermataResponsabile::ESITO . get_current_user_id(), $rifiuto, MINUTE_IN_SECONDS );
		$modulo = $this->campi_del_modulo( $this->schermata() );

		$modulo[ SchermataResponsabile::CAMPO ]['telefono'] = '06 7654321';
		$esito = SchermataResponsabile::salva( $this->richiesta( $modulo[ SchermataResponsabile::CAMPO ], $modulo[ SchermataResponsabile::CAMPO_PARTENZA ] ) );

		$this->assertSame( 'nuova@example.org', Responsabile::dati()['email'], 'Il reinvio non cancella l\'email di A.' );
		$this->assertSame( 'salvato', $esito['codice'] );
		$this->assertSame( array( 'telefono' ), $esito['campi'], 'B ha cambiato solo il telefono.' );
	}

	/**
	 * I campi del modulo come li rimanderebbe il browser, letti dalla schermata.
	 *
	 * @param string $html La schermata.
	 * @return array<string, array<string, string>>
	 */
	private function campi_del_modulo( string $html ): array {
		$campi = array(
			SchermataResponsabile::CAMPO          => array(),
			SchermataResponsabile::CAMPO_PARTENZA => array(),
		);

		preg_match_all( '/name="(' . SchermataResponsabile::CAMPO . '|' . SchermataResponsabile::CAMPO_PARTENZA . ')\[([a-z]+)\]" value="([^"]*)"/', $html, $trovati, PREG_SET_ORDER );

		foreach ( $trovati as $campo ) {
			$campi[ $campo[1] ][ $campo[2] ] = html_entity_decode( $campo[3], ENT_QUOTES );
		}

		$this->assertCount( 4, $campi[ SchermataResponsabile::CAMPO ] );
		$this->assertCount( 4, $campi[ SchermataResponsabile::CAMPO_PARTENZA ] );

		return $campi;
	}

	/**
	 * T-57: senza responsabile, avviso a chi ha il permesso, sulla bacheca e non altrove.
	 */
	public function test_t57_avviso_se_non_indicato(): void {
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
	 * T-58: il riquadro compare nella voce indicata dall'albero, con i dati in uscita sicura, e solo lì.
	 */
	public function test_t58_riquadro_nella_voce_indicata(): void {
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
	 * T-58: senza telefono e senza nomina, quelle righe non compaiono vuote.
	 */
	public function test_t58_campi_facoltativi_assenti(): void {
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
	 * T-58: responsabile non indicato, o albero senza collocazione: nessun riquadro.
	 */
	public function test_t58_niente_riquadro_senza_dati_o_collocazione(): void {
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
