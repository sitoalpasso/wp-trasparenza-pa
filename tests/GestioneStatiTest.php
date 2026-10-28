<?php
/**
 * Prove della schermata di gestione degli stati: righe T-46..T-53.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Avvio;
use TrasparenzaPa\PaginaPubblica;
use TrasparenzaPa\Permessi;
use TrasparenzaPa\SchermataStati;
use TrasparenzaPa\StatiObbligo;
use WPDieException;
use WP_UnitTestCase;

/**
 * Chi puo dichiarare gli stati, come li dichiara, e la traccia che lasciano.
 */
class GestioneStatiTest extends WP_UnitTestCase {

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
	 * Sezione registrata, albero finto, nessuno stato, permesso di nessuno.
	 */
	public function set_up(): void {
		parent::set_up();

		\Conformita_Core_Sezioni::azzera();
		Avvio::azzera();
		Avvio::esegui();

		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-finto.json' );
		PaginaPubblica::da_init();

		delete_option( StatiObbligo::OPZIONE );
		delete_option( Permessi::OPZIONE_ASSEGNATO );
		$this->togli_permesso_a_tutti();

		$this->responsabile   = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->amministratore = self::factory()->user->create( array( 'role' => 'administrator' ) );

		get_user_by( 'id', $this->responsabile )->add_cap( Permessi::AMMINISTRAZIONE );
	}

	/**
	 * Rimette l'albero finto e i ruoli come li lascia l'avvio della suite.
	 *
	 * I ruoli vivono anche in memoria, e la banca dati riportata indietro non
	 * li rimette a posto: senza questo passaggio le prove degli altri file
	 * troverebbero l'avviso "nessun ruolo puo dichiarare gli stati".
	 */
	public function tear_down(): void {
		remove_all_filters( 'query' );
		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-finto.json' );
		PaginaPubblica::da_init();
		$this->togli_permesso_a_tutti();
		get_role( 'administrator' )->add_cap( Permessi::AMMINISTRAZIONE );

		parent::tear_down();
	}

	/**
	 * Toglie il permesso della sezione da ogni ruolo.
	 */
	private function togli_permesso_a_tutti(): void {
		foreach ( wp_roles()->role_objects as $ruolo ) {
			$ruolo->remove_cap( Permessi::AMMINISTRAZIONE );
		}
	}

	/**
	 * Una richiesta come la manderebbe il modulo, con il nonce dell'utente corrente.
	 *
	 * @param array<string, mixed> $stati Coppie voce e stato.
	 * @return array<string, mixed>
	 */
	private function richiesta( array $stati ): array {
		return array(
			'_wpnonce'            => wp_create_nonce( SchermataStati::AZIONE ),
			SchermataStati::CAMPO => $stati,
		);
	}

	/**
	 * Le voci di registro degli stati, in ordine di scrittura.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function tracce(): array {
		return conformita_core_voci_registro(
			array(
				'sezione' => \TrasparenzaPa\SEZIONE,
				'azione'  => SchermataStati::AZIONE_REGISTRO,
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
			SchermataStati::mostra();
		} finally {
			$html = (string) ob_get_clean();
		}

		return $html;
	}

	/**
	 * T-46: alla prima installazione il permesso va all'amministratore.
	 */
	public function test_t46_permesso_assegnato_alla_prima_installazione(): void {
		$this->assertTrue( Permessi::assegna() );
		$this->assertTrue( get_role( 'administrator' )->has_cap( Permessi::AMMINISTRAZIONE ) );
		$this->assertSame( \TrasparenzaPa\VERSIONE, get_option( Permessi::OPZIONE_ASSEGNATO ) );
		$this->assertFalse( get_role( 'editor' )->has_cap( Permessi::AMMINISTRAZIONE ), 'Nessun altro ruolo lo riceve.' );
	}

	/**
	 * T-46: tolto dall'ente, il permesso non torna con la richiesta successiva.
	 */
	public function test_t46_permesso_tolto_non_torna(): void {
		Permessi::assegna();
		get_role( 'administrator' )->remove_cap( Permessi::AMMINISTRAZIONE );

		Permessi::da_init();
		Permessi::assegna();

		$this->assertFalse( get_role( 'administrator' )->has_cap( Permessi::AMMINISTRAZIONE ) );
	}

	/**
	 * T-46: senza il ruolo di amministratore niente si assegna e niente si ricorda.
	 */
	public function test_t46_senza_amministratore_niente_ricordato(): void {
		$copia = get_role( 'administrator' )->capabilities;
		remove_role( 'administrator' );

		$esito = Permessi::assegna();

		add_role( 'administrator', 'Administrator', $copia );

		$this->assertWPError( $esito );
		$this->assertSame( 'trasparenza_permessi_ruolo_assente', $esito->get_error_code() );
		$this->assertFalse( get_option( Permessi::OPZIONE_ASSEGNATO ), 'Senza assegnazione la richiesta dopo deve riprovare.' );
	}

	/**
	 * T-47: se nessun ruolo ha il permesso, chi gestisce i componenti lo sa.
	 */
	public function test_t47_avviso_se_nessuno_ha_il_permesso(): void {
		wp_set_current_user( $this->amministratore );

		$this->assertTrue( Permessi::nessun_ruolo_possiede() );
		$this->assertStringContainsString( Permessi::AMMINISTRAZIONE, $this->avvisi_in_bacheca() );

		wp_set_current_user( $this->responsabile );
		$this->assertStringNotContainsString( Permessi::AMMINISTRAZIONE, $this->avvisi_in_bacheca(), 'Riservato a chi gestisce i componenti.' );

		Permessi::assegna();
		wp_set_current_user( $this->amministratore );

		$this->assertFalse( Permessi::nessun_ruolo_possiede() );
		$this->assertStringNotContainsString( Permessi::AMMINISTRAZIONE, $this->avvisi_in_bacheca() );
	}

	/**
	 * T-48: la voce di menu chiede il permesso della sezione, e la schermata si aggancia solo per chi lo ha.
	 *
	 * WordPress mette la voce nell'elenco per tutti e la toglie poi a chi non
	 * ha il permesso scritto nella voce: si controllano quel permesso e
	 * l'aggancio che disegna la schermata.
	 */
	public function test_t48_menu_solo_con_permesso(): void {
		global $menu;

		set_current_screen( 'dashboard' );
		$aggancio = 'toplevel_page_' . SchermataStati::PAGINA;

		$menu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- la prova ricostruisce il menu da zero.
		wp_set_current_user( $this->amministratore );
		SchermataStati::da_admin_menu();

		$voce = array_values( wp_list_filter( $menu, array( 2 => SchermataStati::PAGINA ) ) );

		$this->assertCount( 1, $voce );
		$this->assertSame( Permessi::AMMINISTRAZIONE, $voce[0][1], 'La voce chiede il permesso della sezione, non uno generico.' );
		$this->assertFalse( has_action( $aggancio ), 'Amministratore del sito senza il permesso della sezione.' );

		$menu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- come sopra.
		wp_set_current_user( $this->responsabile );
		SchermataStati::da_admin_menu();

		$this->assertNotFalse( has_action( $aggancio, array( SchermataStati::class, 'mostra' ) ) );
		remove_all_actions( $aggancio );
	}

	/**
	 * T-48: la schermata aperta senza permesso si rifiuta.
	 */
	public function test_t48_schermata_senza_permesso_rifiutata(): void {
		wp_set_current_user( $this->amministratore );

		$this->expectException( WPDieException::class );
		$this->schermata();
	}

	/**
	 * T-48: tutte le voci tranne la radice, nessuno stato preselezionato.
	 */
	public function test_t48_schermata_elenca_le_voci_senza_preselezione(): void {
		StatiObbligo::imposta( PaginaPubblica::albero(), 'zeta_uno', StatiObbligo::SOSPESO );
		wp_set_current_user( $this->responsabile );

		$html = $this->schermata();

		foreach ( array( 'zeta', 'alfa', 'zeta_uno', 'zeta_due' ) as $voce ) {
			$this->assertStringContainsString( 'name="stati[' . $voce . ']"', $html, "Manca la voce {$voce}." );
		}

		$this->assertStringNotContainsString( 'name="stati[radice]"', $html, 'La radice non porta obblighi propri.' );
		$this->assertMatchesRegularExpression( '#name="stati\[alfa\]"><option value="" selected>Da scegliere</option><option value="applicabile">#', $html, 'Voce non dichiarata: si parte da "Da scegliere".' );
		$this->assertMatchesRegularExpression( '#name="stati\[zeta_uno\]"><option value="applicabile">Applicabile</option><option value="non_applicabile">Non applicabile</option><option value="sospeso" selected=\'selected\'>#', $html, 'Voce dichiarata: niente "Da scegliere", stato attuale selezionato.' );
		$this->assertSame( 3, substr_count( $html, '<option value="" selected>' ), 'Una sola scelta vuota per voce non dichiarata.' );
	}

	/**
	 * T-48: la schermata spiega gli effetti con le frasi dell'albero e conta le non dichiarate.
	 */
	public function test_t48_schermata_spiega_e_conta(): void {
		wp_set_current_user( $this->responsabile );

		$html = $this->schermata();

		$this->assertStringContainsString( esc_html( '"L\'obbligo di pubblicazione non è applicabile all\'amministrazione"' ), $html );
		$this->assertStringContainsString( esc_html( '"Obbligo di pubblicazione sospeso"' ), $html );
		$this->assertStringContainsString( '4 voci ancora senza stato:', $html );

		foreach ( array( 'zeta', 'alfa', 'zeta_uno', 'zeta_due' ) as $voce ) {
			StatiObbligo::imposta( PaginaPubblica::albero(), $voce, StatiObbligo::APPLICABILE );
		}

		$this->assertStringContainsString( 'Tutte le voci hanno uno stato dichiarato.', $this->schermata() );
	}

	/**
	 * T-49: senza permesso, senza nonce o con il nonce di un'altra azione non si scrive niente.
	 */
	public function test_t49_permesso_e_nonce_obbligatori(): void {
		wp_set_current_user( $this->amministratore );
		$this->assertSame( 'permesso', SchermataStati::salva( $this->richiesta( array( 'alfa' => StatiObbligo::APPLICABILE ) ) )['codice'] );

		wp_set_current_user( $this->responsabile );

		$senza_nonce = array( SchermataStati::CAMPO => array( 'alfa' => StatiObbligo::APPLICABILE ) );
		$this->assertSame( 'nonce', SchermataStati::salva( $senza_nonce )['codice'] );

		$altra_azione = array(
			'_wpnonce'            => wp_create_nonce( 'altra_azione' ),
			SchermataStati::CAMPO => array( 'alfa' => StatiObbligo::APPLICABILE ),
		);
		$this->assertSame( 'nonce', SchermataStati::salva( $altra_azione )['codice'] );

		$nonce_non_testuale = array(
			'_wpnonce'            => array( wp_create_nonce( SchermataStati::AZIONE ) ),
			SchermataStati::CAMPO => array( 'alfa' => StatiObbligo::APPLICABILE ),
		);
		$this->assertSame( 'nonce', SchermataStati::salva( $nonce_non_testuale )['codice'] );

		$this->assertFalse( get_option( StatiObbligo::OPZIONE ) );
		$this->assertSame( array(), $this->tracce() );
	}

	/**
	 * T-49: il nonce di un utente non vale per un altro.
	 */
	public function test_t49_nonce_di_un_altro_utente(): void {
		$altro = self::factory()->user->create( array( 'role' => 'editor' ) );
		get_user_by( 'id', $altro )->add_cap( Permessi::AMMINISTRAZIONE );

		wp_set_current_user( $altro );
		$richiesta = $this->richiesta( array( 'alfa' => StatiObbligo::APPLICABILE ) );

		wp_set_current_user( $this->responsabile );

		$this->assertSame( 'nonce', SchermataStati::salva( $richiesta )['codice'] );
		$this->assertNull( StatiObbligo::stato( 'alfa' ) );
	}

	/**
	 * T-50: una richiesta valida salva i cambi e lascia invariati i campi vuoti.
	 */
	public function test_t50_salvataggio_valido(): void {
		wp_set_current_user( $this->responsabile );

		$esito = SchermataStati::salva(
			$this->richiesta(
				array(
					'zeta'     => StatiObbligo::APPLICABILE,
					'alfa'     => StatiObbligo::NON_APPLICABILE,
					'zeta_uno' => '',
				)
			)
		);

		$this->assertSame( 'salvato', $esito['codice'] );
		$this->assertSame( array( 'zeta', 'alfa' ), $esito['salvate'] );
		$this->assertSame( StatiObbligo::APPLICABILE, StatiObbligo::stato( 'zeta' ) );
		$this->assertSame( StatiObbligo::NON_APPLICABILE, StatiObbligo::stato( 'alfa' ) );
		$this->assertNull( StatiObbligo::stato( 'zeta_uno' ), 'Il campo vuoto non dichiara niente.' );
	}

	/**
	 * T-50: uno stato dichiarato si cambia ma non si toglie.
	 */
	public function test_t50_stato_dichiarato_non_si_toglie(): void {
		wp_set_current_user( $this->responsabile );
		SchermataStati::salva( $this->richiesta( array( 'alfa' => StatiObbligo::NON_APPLICABILE ) ) );

		$esito = SchermataStati::salva( $this->richiesta( array( 'alfa' => '' ) ) );

		$this->assertSame( 'salvato', $esito['codice'] );
		$this->assertSame( array(), $esito['salvate'] );
		$this->assertSame( StatiObbligo::NON_APPLICABILE, StatiObbligo::stato( 'alfa' ) );

		SchermataStati::salva( $this->richiesta( array( 'alfa' => StatiObbligo::APPLICABILE ) ) );
		$this->assertSame( StatiObbligo::APPLICABILE, StatiObbligo::stato( 'alfa' ) );
	}

	/**
	 * T-50: un solo difetto e non si scrive niente, nemmeno le voci buone.
	 *
	 * @dataProvider richieste_difettose
	 *
	 * @param mixed $stati Campo degli stati come arriva dal modulo.
	 */
	public function test_t50_tutto_o_niente( $stati ): void {
		wp_set_current_user( $this->responsabile );

		$richiesta                          = $this->richiesta( array() );
		$richiesta[ SchermataStati::CAMPO ] = $stati;

		$esito = SchermataStati::salva( $richiesta );

		$this->assertSame( 'dati', $esito['codice'] );
		$this->assertSame( array(), $esito['salvate'] );
		$this->assertFalse( get_option( StatiObbligo::OPZIONE ), 'Niente deve essere stato scritto.' );
		$this->assertSame( array(), $this->tracce() );
	}

	/**
	 * Richieste con un difetto, ciascuna con almeno una voce buona davanti.
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public function richieste_difettose(): array {
		return array(
			'voce inesistente'        => array(
				array(
					'alfa'        => StatiObbligo::APPLICABILE,
					'inesistente' => StatiObbligo::APPLICABILE,
				),
			),
			'valore fuori dai tre'    => array(
				array(
					'alfa' => StatiObbligo::APPLICABILE,
					'zeta' => 'forse',
				),
			),
			'valore con uno spazio'   => array(
				array(
					'alfa' => StatiObbligo::APPLICABILE,
					'zeta' => 'applicabile ',
				),
			),
			'radice'                  => array(
				array(
					'alfa'   => StatiObbligo::APPLICABILE,
					'radice' => StatiObbligo::APPLICABILE,
				),
			),
			'valore non testuale'     => array(
				array(
					'alfa' => StatiObbligo::APPLICABILE,
					'zeta' => array( StatiObbligo::APPLICABILE ),
				),
			),
			'identificativo numerico' => array(
				array(
					'alfa' => StatiObbligo::APPLICABILE,
					0      => StatiObbligo::APPLICABILE,
				),
			),
			'campo non elenco'        => array( 'alfa=applicabile' ),
		);
	}

	/**
	 * T-51: ogni cambio lascia una voce nel registro, con chi, prima e dopo.
	 */
	public function test_t51_ogni_cambio_nel_registro(): void {
		wp_set_current_user( $this->responsabile );

		SchermataStati::salva( $this->richiesta( array( 'alfa' => StatiObbligo::NON_APPLICABILE ) ) );
		SchermataStati::salva(
			$this->richiesta(
				array(
					'alfa' => StatiObbligo::APPLICABILE,
					'zeta' => StatiObbligo::SOSPESO,
				)
			)
		);

		$tracce = $this->tracce();

		$this->assertCount( 3, $tracce );
		$this->assertSame(
			array(
				array(
					'voce'  => 'alfa',
					'prima' => null,
					'dopo'  => StatiObbligo::NON_APPLICABILE,
				),
				array(
					'voce'  => 'alfa',
					'prima' => StatiObbligo::NON_APPLICABILE,
					'dopo'  => StatiObbligo::APPLICABILE,
				),
				array(
					'voce'  => 'zeta',
					'prima' => null,
					'dopo'  => StatiObbligo::SOSPESO,
				),
			),
			wp_list_pluck( $tracce, 'dettagli' )
		);
		$this->assertSame( array( $this->responsabile, $this->responsabile, $this->responsabile ), array_map( 'intval', wp_list_pluck( $tracce, 'utente' ) ) );
		$this->assertNull( $tracce[0]['contenuto'], 'Lo stato e della sezione, non di un contenuto.' );
	}

	/**
	 * T-51: rimandare lo stato che c'e gia non e un cambio e non va nel registro.
	 */
	public function test_t51_stato_uguale_non_registrato(): void {
		wp_set_current_user( $this->responsabile );

		SchermataStati::salva( $this->richiesta( array( 'alfa' => StatiObbligo::SOSPESO ) ) );
		$esito = SchermataStati::salva( $this->richiesta( array( 'alfa' => StatiObbligo::SOSPESO ) ) );

		$this->assertSame( array(), $esito['salvate'] );
		$this->assertCount( 1, $this->tracce() );
	}

	/**
	 * T-51: se il registro rifiuta un cambio, quello e i successivi non si fanno.
	 */
	public function test_t51_registro_che_rifiuta_ferma_i_cambi(): void {
		global $wpdb;

		wp_set_current_user( $this->responsabile );

		$tabella = $wpdb->prefix . 'conformita_core_registro';
		$scritte = 0;

		/*
		 * La prima scrittura nel registro passa, la seconda diventa una
		 * istruzione che la banca dati rifiuta: e il registro che dice di no,
		 * non la prova che finge.
		 */
		add_filter(
			'query',
			static function ( $sql ) use ( $tabella, &$scritte ) {
				if ( 0 === strpos( ltrim( (string) $sql ), 'INSERT INTO `' . $tabella . '`' ) && ++$scritte > 1 ) {
					return 'INSERT INTO `' . $tabella . '_inesistente` VALUES (1)';
				}

				return $sql;
			}
		);

		$wpdb->suppress_errors( true );
		$esito = SchermataStati::salva(
			$this->richiesta(
				array(
					'zeta'     => StatiObbligo::APPLICABILE,
					'alfa'     => StatiObbligo::NON_APPLICABILE,
					'zeta_uno' => StatiObbligo::SOSPESO,
				)
			)
		);
		$wpdb->suppress_errors( false );
		remove_all_filters( 'query' );

		$this->assertSame( 'registro', $esito['codice'] );
		$this->assertSame( array( 'zeta' ), $esito['salvate'] );
		$this->assertStringContainsString( 'Salvati 1 cambi su 3', $esito['messaggio'] );
		$this->assertStringContainsString( '"Voce Alfa"', $esito['messaggio'] );
		$this->assertSame( StatiObbligo::APPLICABILE, StatiObbligo::stato( 'zeta' ) );
		$this->assertNull( StatiObbligo::stato( 'alfa' ), 'Il cambio rifiutato dal registro non si fa.' );
		$this->assertNull( StatiObbligo::stato( 'zeta_uno' ), 'Nemmeno quelli dopo.' );
		$this->assertCount( 1, $this->tracce() );
	}

	/**
	 * T-51: se la scrittura dello stato non avviene, il salvataggio si ferma e lo dice.
	 *
	 * Il registro accetta, la banca dati no: l'esito non e "salvato", nomina la
	 * voce e dice che per quella il registro contiene un cambio non avvenuto.
	 */
	public function test_t51_stato_non_scritto_ferma_i_cambi(): void {
		wp_set_current_user( $this->responsabile );

		$scritture = 0;

		/*
		 * La prima scrittura dello stato passa; la seconda viene riportata al
		 * valore di prima, come una scrittura andata persa.
		 */
		add_filter(
			'pre_update_option_' . StatiObbligo::OPZIONE,
			static function ( $valore, $vecchio ) use ( &$scritture ) {
				return ++$scritture > 1 ? $vecchio : $valore;
			},
			10,
			2
		);

		$esito = SchermataStati::salva(
			$this->richiesta(
				array(
					'zeta'     => StatiObbligo::APPLICABILE,
					'alfa'     => StatiObbligo::NON_APPLICABILE,
					'zeta_uno' => StatiObbligo::SOSPESO,
				)
			)
		);
		remove_all_filters( 'pre_update_option_' . StatiObbligo::OPZIONE );

		$this->assertSame( 'stato', $esito['codice'] );
		$this->assertSame( array( 'zeta' ), $esito['salvate'] );
		$this->assertStringContainsString( 'Salvati 1 cambi su 3', $esito['messaggio'] );
		$this->assertStringContainsString( '"Voce Alfa"', $esito['messaggio'] );
		$this->assertStringContainsString( 'cambio che non è avvenuto', $esito['messaggio'] );
		$this->assertSame( StatiObbligo::APPLICABILE, StatiObbligo::stato( 'zeta' ) );
		$this->assertNull( StatiObbligo::stato( 'alfa' ) );
		$this->assertNull( StatiObbligo::stato( 'zeta_uno' ), 'Dopo il guasto non si va avanti.' );
		$this->assertSame( array( 'zeta', 'alfa' ), wp_list_pluck( wp_list_pluck( $this->tracce(), 'dettagli' ), 'voce' ), 'Il registro ha la voce del cambio non avvenuto, e nessuna dopo.' );
	}

	/**
	 * T-52: senza albero la schermata lo dice e il salvataggio si rifiuta.
	 */
	public function test_t52_senza_albero(): void {
		PaginaPubblica::fissa_file( __DIR__ . '/dati/non-esiste.json' );
		PaginaPubblica::da_init();
		wp_set_current_user( $this->responsabile );

		$this->assertSame( 'albero', SchermataStati::salva( $this->richiesta( array( 'alfa' => StatiObbligo::APPLICABILE ) ) )['codice'] );

		$html = $this->schermata();

		$this->assertStringContainsString( 'L&#039;albero delle voci non è disponibile', $html );
		$this->assertStringNotContainsString( '<form', $html );
		$this->assertFalse( get_option( StatiObbligo::OPZIONE ) );
	}

	/**
	 * T-52: senza sezione registrata non c'e menu e i ruoli non si toccano.
	 */
	public function test_t52_senza_sezione_niente_menu_ne_permessi(): void {
		global $menu;

		Avvio::azzera();
		set_current_screen( 'dashboard' );
		wp_set_current_user( $this->responsabile );

		$menu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- la prova ricostruisce il menu da zero.
		SchermataStati::da_admin_menu();
		Permessi::da_init();

		$this->assertNotContains( SchermataStati::PAGINA, wp_list_pluck( $menu, 2 ) );
		$this->assertFalse( get_role( 'administrator' )->has_cap( Permessi::AMMINISTRAZIONE ) );
		$this->assertFalse( get_option( Permessi::OPZIONE_ASSEGNATO ) );
	}

	/**
	 * T-53: con un altro salvataggio in corso si aspetta, poi si rinuncia senza scrivere.
	 *
	 * L'altro salvataggio e una seconda connessione alla stessa banca dati che
	 * tiene il blocco: e quello che fa una richiesta contemporanea.
	 */
	public function test_t53_salvataggio_contemporaneo_rifiutato(): void {
		global $wpdb;

		list( $host, $porta, $socket ) = $wpdb->parse_db_host( DB_HOST );

		$altra = mysqli_init(); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_init -- serve una seconda connessione, che $wpdb non offre.
		$this->assertTrue( $altra->real_connect( (string) $host, DB_USER, DB_PASSWORD, DB_NAME, $porta ? (int) $porta : null, $socket ? (string) $socket : null ) );
		$this->assertSame( '1', (string) $altra->query( "SELECT GET_LOCK('" . $altra->real_escape_string( SchermataStati::nome_blocco() ) . "', 0)" )->fetch_row()[0] );

		wp_set_current_user( $this->responsabile );

		try {
			$esito = SchermataStati::salva( $this->richiesta( array( 'alfa' => StatiObbligo::APPLICABILE ) ) );
		} finally {
			$altra->close();
		}

		$this->assertSame( 'occupato', $esito['codice'] );
		$this->assertNull( StatiObbligo::stato( 'alfa' ) );
		$this->assertSame( array(), $this->tracce() );

		$this->assertSame( 'salvato', SchermataStati::salva( $this->richiesta( array( 'alfa' => StatiObbligo::APPLICABILE ) ) )['codice'], 'Chiusa l\'altra connessione il blocco e libero.' );
	}

	/**
	 * T-53: il punto di partenza si rilegge dalla banca dati, non dalla memoria della richiesta.
	 *
	 * Un'altra richiesta ha cambiato uno stato dopo che questa lo aveva letto:
	 * il "prima" del registro e quello nuovo, e lo stato scritto dall'altra
	 * richiesta su un'altra voce non si perde.
	 */
	public function test_t53_partenza_riletta_dalla_banca_dati(): void {
		global $wpdb;

		wp_set_current_user( $this->responsabile );
		SchermataStati::salva( $this->richiesta( array( 'alfa' => StatiObbligo::APPLICABILE ) ) );

		$this->assertSame( StatiObbligo::APPLICABILE, StatiObbligo::stato( 'alfa' ), 'Lo stato e ora in memoria.' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- la prova cambia la banca dati alle spalle della memoria, come farebbe un'altra richiesta.
		$wpdb->update(
			$wpdb->options,
			array(
				'option_value' => maybe_serialize(
					array(
						'alfa' => StatiObbligo::SOSPESO,
						'zeta' => StatiObbligo::NON_APPLICABILE,
					)
				),
			),
			array( 'option_name' => StatiObbligo::OPZIONE )
		);

		SchermataStati::salva( $this->richiesta( array( 'alfa' => StatiObbligo::NON_APPLICABILE ) ) );

		$tracce = $this->tracce();

		$this->assertSame( StatiObbligo::SOSPESO, end( $tracce )['dettagli']['prima'] );
		$this->assertSame( StatiObbligo::NON_APPLICABILE, StatiObbligo::stato( 'zeta' ), 'Il cambio dell\'altra richiesta resta.' );
		$this->assertSame( StatiObbligo::NON_APPLICABILE, StatiObbligo::stato( 'alfa' ) );
	}
}
