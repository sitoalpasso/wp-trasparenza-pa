<?php
/**
 * Prove del contenuto della sezione e della scelta della voce: righe T-59..T-62.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Avvio;
use TrasparenzaPa\Blocco;
use TrasparenzaPa\Installazione;
use TrasparenzaPa\PaginaPubblica;
use TrasparenzaPa\Permessi;
use TrasparenzaPa\SchedaContenuto;
use TrasparenzaPa\TipoContenuto;
use TrasparenzaPa\TipoVoce;
use WP_UnitTestCase;

use const TrasparenzaPa\OPZIONE_VERSIONE;
use const TrasparenzaPa\SEZIONE;
use const TrasparenzaPa\TIPO_CONTENUTO;
use const TrasparenzaPa\TIPO_VOCE;

/**
 * Il tipo, i permessi, la voce scelta con la sua traccia.
 */
class ContenutoTest extends WP_UnitTestCase {

	use AmbienteTrasparenza;

	/**
	 * Amministratore delle prove.
	 *
	 * @var int
	 */
	private $amministratore;

	/**
	 * Sezione e tipi registrati, permessi assegnati, albero finto, amministratore corrente.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! TipoVoce::registrato() || ! TipoContenuto::registrato() ) {
			\Conformita_Core_Tipi::azzera();
			TipoVoce::azzera();
			TipoContenuto::azzera();

			if ( ! Avvio::registrata() ) {
				\Conformita_Core_Sezioni::azzera();
				Avvio::azzera();
				update_option( 'active_plugins', array( plugin_basename( \TrasparenzaPa\FILE_PRINCIPALE ) ) );
				Avvio::esegui();
			}

			$this->assertTrue( TipoVoce::registra() );
			$this->assertTrue( TipoContenuto::registra() );
		}

		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-finto.json' );
		PaginaPubblica::da_init();

		delete_option( OPZIONE_VERSIONE );
		$this->assertTrue( Installazione::aggiorna_se_serve(), 'L\'installazione deve assegnare i permessi.' );

		$this->amministratore = self::factory()->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $this->amministratore );
	}

	/**
	 * Rimette l'albero finto e toglie i guasti.
	 */
	public function tear_down(): void {
		global $wpdb;

		remove_all_filters( 'query' );
		remove_all_filters( 'update_post_metadata' );
		$wpdb->suppress_errors( false );
		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-finto.json' );
		PaginaPubblica::da_init();

		parent::tear_down();
	}

	/**
	 * Un contenuto in bozza.
	 *
	 * @return int
	 */
	private function contenuto(): int {
		return (int) self::factory()->post->create(
			array(
				'post_type'   => TIPO_CONTENUTO,
				'post_status' => 'draft',
				'post_title'  => 'Contenuto di prova',
			)
		);
	}

	/**
	 * Una richiesta come la manderebbe il riquadro.
	 *
	 * @param int        $contenuto Contenuto.
	 * @param mixed      $scelta    Voce scelta.
	 * @param mixed|null $partenza  Voce mostrata all'apertura; se non indicata, quella attuale.
	 * @return array<string, mixed>
	 */
	private function richiesta( int $contenuto, $scelta, $partenza = null ): array {
		$valori = get_post_meta( $contenuto, SchedaContenuto::META_COLLOCAZIONE, false );

		return array(
			SchedaContenuto::CAMPO_NONCE    => wp_create_nonce( SchedaContenuto::azione_nonce( $contenuto ) ),
			SchedaContenuto::CAMPO          => $scelta,
			SchedaContenuto::CAMPO_PARTENZA => $partenza ?? (string) ( $valori[0] ?? '' ),
		);
	}

	/**
	 * Le voci di registro della scelta della voce di un contenuto.
	 *
	 * @param int $contenuto Contenuto.
	 * @return array<int, array<string, mixed>>
	 */
	private function tracce( int $contenuto ): array {
		return conformita_core_voci_registro(
			array(
				'contenuto' => $contenuto,
				'azione'    => SchedaContenuto::AZIONE_REGISTRO,
			)
		);
	}

	/**
	 * Le voci memorizzate, tutte.
	 *
	 * @param int $contenuto Contenuto.
	 * @return array<int, string>
	 */
	private function memorizzate( int $contenuto ): array {
		return array_map( 'strval', get_post_meta( $contenuto, SchedaContenuto::META_COLLOCAZIONE, false ) );
	}

	/**
	 * T-59: il tipo passa dal componente comune, con le scelte dichiarate.
	 */
	public function test_t59_tipo_registrato_attraverso_il_componente_comune(): void {
		$this->assertTrue( conformita_core_tipo_registrato( TIPO_CONTENUTO ) );
		$this->assertSame( SEZIONE, conformita_core_sezione_del_tipo( TIPO_CONTENUTO ) );

		$tipo = get_post_type_object( TIPO_CONTENUTO );

		$this->assertFalse( $tipo->show_in_rest, 'Il contenuto non e esposto all\'interfaccia REST.' );
		$this->assertFalse( $tipo->public );
		$this->assertFalse( $tipo->publicly_queryable );
		$this->assertTrue( $tipo->show_ui );
		$this->assertSame( 'edit.php?post_type=' . TIPO_VOCE, $tipo->show_in_menu, 'Sottovoce del menu Trasparenza.' );
		$this->assertTrue( post_type_supports( TIPO_CONTENUTO, 'editor' ) );
		$this->assertTrue( post_type_supports( TIPO_CONTENUTO, 'revisions' ) );
	}

	/**
	 * T-59: [attacco] una richiesta anonima non trova il contenuto, nemmeno pubblicato e con una fine lontana.
	 */
	public function test_t59_contenuto_chiuso_al_pubblico(): void {
		$contenuto = $this->contenuto();

		wp_publish_post( $contenuto );
		$this->assertSame( 'publish', get_post_status( $contenuto ) );
		$this->assertTrue( conformita_core_imposta_fine_pubblicazione( $contenuto, '2099-12-31' ) );

		wp_set_current_user( 0 );

		foreach ( array(
			array(
				'post_type' => TIPO_CONTENUTO,
				'p'         => (string) $contenuto,
			),
			array( 's' => 'Contenuto di prova' ),
		) as $parametri ) {
			$_GET      = $parametri;
			$richiesta = new \WP();

			$richiesta->public_query_vars  = $GLOBALS['wp']->public_query_vars;
			$richiesta->private_query_vars = $GLOBALS['wp']->private_query_vars;
			$richiesta->parse_request();

			$trovati = ( new \WP_Query( $richiesta->query_vars ) )->posts;

			$this->assertNotContains( $contenuto, wp_list_pluck( $trovati, 'ID' ), 'Richiesta anonima: ' . wp_json_encode( $parametri ) );
		}

		$_GET = array();
	}

	/**
	 * T-59: senza sezione registrata il tipo non si registra.
	 */
	public function test_t59_inerte_senza_sezione(): void {
		\Conformita_Core_Sezioni::azzera();
		\Conformita_Core_Tipi::azzera();
		Avvio::azzera();
		TipoContenuto::azzera();

		$esito = TipoContenuto::registra();

		$this->assertWPError( $esito );
		$this->assertSame( 'trasparenza_sezione_non_registrata', $esito->get_error_code() );
		$this->assertFalse( TipoContenuto::registrato() );
		$this->assertWPError( Permessi::mappa( TIPO_CONTENUTO ), 'Nessun permesso per un tipo non registrato.' );
		$this->assertFalse( Installazione::aggiorna_se_serve(), 'Senza i tipi i permessi non si assegnano e la versione non si memorizza.' );
	}

	/**
	 * T-60: l'amministratore gestisce i contenuti, Autore, Redattore e Collaboratore no.
	 */
	public function test_t60_permessi(): void {
		$contenuto = $this->contenuto();
		$mappa     = Permessi::mappa( TIPO_CONTENUTO );

		$this->assertIsArray( $mappa );

		// Si tolgono e si riassegnano: il ruolo puo averli gia da un'installazione precedente.
		foreach ( array_diff( $mappa, array( 'read' ) ) as $permesso ) {
			get_role( 'administrator' )->remove_cap( $permesso );
		}

		delete_option( OPZIONE_VERSIONE );
		$this->assertTrue( Installazione::aggiorna_se_serve() );

		$this->assertNotSame( Permessi::mappa( TIPO_VOCE )['edit_posts'], $mappa['edit_posts'], 'Permessi propri, non quelli della voce.' );
		$this->assertTrue( get_role( 'administrator' )->has_cap( $mappa['edit_posts'] ) );
		$this->assertTrue( get_role( 'administrator' )->has_cap( $mappa['publish_posts'] ) );
		$this->assertTrue( user_can( $this->amministratore, 'edit_post', $contenuto ) );

		foreach ( array( 'author', 'editor', 'contributor' ) as $ruolo ) {
			$utente = self::factory()->user->create( array( 'role' => $ruolo ) );

			$this->assertFalse( user_can( $utente, 'edit_post', $contenuto ), "Il ruolo {$ruolo} non deve modificare i contenuti." );
			$this->assertFalse( user_can( $utente, $mappa['edit_posts'] ), "Il ruolo {$ruolo} non deve vedere i contenuti." );
		}
	}

	/**
	 * T-61: il riquadro offre solo le voci senza sottovoci, con il percorso, e porta la voce di partenza.
	 */
	public function test_t61_riquadro(): void {
		$contenuto = $this->contenuto();
		$html      = SchedaContenuto::riquadro( get_post( $contenuto ) );

		$this->assertSame( array( 'alfa', 'zeta_uno', 'zeta_due' ), SchedaContenuto::voci_ammesse( PaginaPubblica::albero() ) );
		$this->assertStringContainsString( 'value="zeta_uno">Voce Zeta &gt; Voce Zeta Uno</option>', $html );
		$this->assertStringContainsString( 'value="alfa">Voce Alfa</option>', $html );
		$this->assertStringNotContainsString( 'value="zeta"', $html, 'Una voce con sottovoci non si sceglie.' );
		$this->assertStringNotContainsString( 'value="radice"', $html );
		$this->assertStringContainsString( '<option value="" selected>Da scegliere</option>', $html );
		$this->assertStringContainsString( 'non compare nella sezione', $html );
		$this->assertStringContainsString( 'name="' . SchedaContenuto::CAMPO_PARTENZA . '" value=""', $html );
		$this->assertStringContainsString( 'name="' . SchedaContenuto::CAMPO_NONCE . '"', $html );

		$this->assertSame( 'salvato', SchedaContenuto::applica( $contenuto, $this->richiesta( $contenuto, 'zeta_uno' ) )['codice'] );

		$html = SchedaContenuto::riquadro( get_post( $contenuto ) );

		$this->assertStringContainsString( 'value="zeta_uno" selected=\'selected\'>', $html );
		$this->assertStringNotContainsString( 'Da scegliere', $html );
		$this->assertStringNotContainsString( 'non compare nella sezione', $html );
		$this->assertStringContainsString( 'name="' . SchedaContenuto::CAMPO_PARTENZA . '" value="zeta_uno"', $html );

		// Una voce che l'albero in vigore non ammette piu vale come nessuna voce, ma resta la partenza.
		update_post_meta( $contenuto, SchedaContenuto::META_COLLOCAZIONE, 'zeta' );
		$html = SchedaContenuto::riquadro( get_post( $contenuto ) );

		$this->assertNull( SchedaContenuto::collocazione( $contenuto ) );
		$this->assertStringContainsString( '<option value="" selected>Da scegliere</option>', $html );
		$this->assertStringContainsString( 'non compare nella sezione', $html );
		$this->assertStringContainsString( 'name="' . SchedaContenuto::CAMPO_PARTENZA . '" value="zeta"', $html );
	}

	/**
	 * T-61: la radice non si sceglie nemmeno quando e l'unica voce dell'albero.
	 */
	public function test_t61_radice_mai_ammessa(): void {
		$dati = json_decode( (string) file_get_contents( __DIR__ . '/dati/albero-finto.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale delle prove.

		$dati['nodi'] = array_values( array_filter( $dati['nodi'], static fn( $nodo ) => null === ( $nodo['padre'] ?? null ) ) );
		unset( $dati['collocazioni'], $dati['fonte_collocazioni'] );

		$file = get_temp_dir() . 'albero-una-voce-' . wp_generate_password( 8, false ) . '.json';
		file_put_contents( $file, (string) wp_json_encode( $dati ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- file temporaneo delle prove.

		try {
			$albero = \TrasparenzaPa\Alberatura::carica( $file );
		} finally {
			wp_delete_file( $file );
		}

		$this->assertInstanceOf( \TrasparenzaPa\Alberatura::class, $albero );
		$this->assertSame( array(), $albero->figli( $albero->radice() ) );
		$this->assertSame( array(), SchedaContenuto::voci_ammesse( $albero ) );
	}

	/**
	 * T-61: la voce scelta si salva dopo la sua voce di registro, che dice prima e dopo.
	 */
	public function test_t61_voce_salvata_e_registrata(): void {
		$contenuto = $this->contenuto();

		$this->assertSame( 'salvato', SchedaContenuto::applica( $contenuto, $this->richiesta( $contenuto, 'alfa' ) )['codice'] );
		$this->assertSame( array( 'alfa' ), $this->memorizzate( $contenuto ) );
		$this->assertSame( 'alfa', SchedaContenuto::collocazione( $contenuto ) );

		$this->assertSame( 'salvato', SchedaContenuto::applica( $contenuto, $this->richiesta( $contenuto, 'zeta_due' ) )['codice'] );
		$this->assertSame( array( 'zeta_due' ), $this->memorizzate( $contenuto ), 'Un solo valore anche dopo il cambio.' );

		$tracce = $this->tracce( $contenuto );

		$this->assertCount( 2, $tracce );
		$this->assertSame( SEZIONE, $tracce[0]['sezione'] );
		$this->assertSame( TIPO_CONTENUTO, $tracce[0]['tipo'] );
		$this->assertSame( (int) $this->amministratore, (int) $tracce[0]['utente'] );
		$this->assertSame(
			array(
				array(
					'prima' => '',
					'dopo'  => 'alfa',
				),
				array(
					'prima' => 'alfa',
					'dopo'  => 'zeta_due',
				),
			),
			array_values( wp_list_pluck( $tracce, 'dettagli' ) )
		);

		$this->assertSame( 'invariato', SchedaContenuto::applica( $contenuto, $this->richiesta( $contenuto, 'zeta_due' ) )['codice'] );
		$this->assertCount( 2, $this->tracce( $contenuto ), 'La stessa voce non e un cambio.' );
	}

	/**
	 * T-61: [attacco] voci non ammesse, valori non testuali, nonce e permessi sbagliati non scrivono niente.
	 */
	public function test_t61_rifiuti(): void {
		$contenuto = $this->contenuto();
		$altro     = $this->contenuto();

		foreach ( array( 'radice', 'zeta', 'inesistente', 'alfa<b>' ) as $voce ) {
			$this->assertSame( 'dati', SchedaContenuto::applica( $contenuto, $this->richiesta( $contenuto, $voce ) )['codice'], $voce );
		}

		$this->assertSame( 'dati', SchedaContenuto::applica( $contenuto, $this->richiesta( $contenuto, array( 'alfa' ) ) )['codice'], 'Voce non testuale.' );
		$this->assertSame( 'dati', SchedaContenuto::applica( $contenuto, $this->richiesta( $contenuto, 'alfa', array() ) )['codice'], 'Partenza non testuale.' );

		$senza_partenza = $this->richiesta( $contenuto, 'alfa' );
		unset( $senza_partenza[ SchedaContenuto::CAMPO_PARTENZA ] );
		$this->assertSame( 'dati', SchedaContenuto::applica( $contenuto, $senza_partenza )['codice'], 'Partenza mancante.' );

		$senza_nonce = $this->richiesta( $contenuto, 'alfa' );
		unset( $senza_nonce[ SchedaContenuto::CAMPO_NONCE ] );
		$this->assertSame( 'nonce', SchedaContenuto::applica( $contenuto, $senza_nonce )['codice'] );
		$this->assertSame( 'nonce', SchedaContenuto::applica( $contenuto, $this->richiesta( $altro, 'alfa' ) )['codice'], 'Nonce di un altro contenuto.' );

		$voce_di_trasparenza = (int) self::factory()->post->create( array( 'post_type' => TIPO_VOCE ) );
		$this->assertSame( 'permesso', SchedaContenuto::applica( $voce_di_trasparenza, $this->richiesta( $voce_di_trasparenza, 'alfa' ) )['codice'], 'Un contenuto di un altro tipo.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( 'permesso', SchedaContenuto::applica( $contenuto, $this->richiesta( $contenuto, 'alfa' ) )['codice'] );

		$this->assertSame( array(), $this->memorizzate( $contenuto ) );
		$this->assertSame( array(), get_post_meta( $voce_di_trasparenza, SchedaContenuto::META_COLLOCAZIONE, false ) );
		$this->assertCount( 0, $this->tracce( $contenuto ) );
	}

	/**
	 * T-61: [attacco] due moduli aperti insieme: il secondo che cambia la voce e un conflitto.
	 */
	public function test_t61_due_moduli(): void {
		$contenuto = $this->contenuto();
		$di_a      = $this->richiesta( $contenuto, 'alfa' );
		$di_b      = $this->richiesta( $contenuto, 'zeta_due' );
		$rimandato = $this->richiesta( $contenuto, '' );

		$this->assertSame( 'salvato', SchedaContenuto::applica( $contenuto, $di_a )['codice'] );
		$this->assertSame( 'conflitto', SchedaContenuto::applica( $contenuto, $di_b )['codice'] );
		$this->assertSame( 'invariato', SchedaContenuto::applica( $contenuto, $rimandato )['codice'], 'Chi non sceglie niente non tocca la voce.' );
		$this->assertSame( 'invariato', SchedaContenuto::applica( $contenuto, $this->richiesta( $contenuto, 'alfa', '' ) )['codice'], 'Chi sceglie la voce gia scritta da un altro non e in conflitto.' );

		$this->assertSame( array( 'alfa' ), $this->memorizzate( $contenuto ) );
		$this->assertCount( 1, $this->tracce( $contenuto ) );
	}

	/**
	 * T-61: [attacco] lettura fallita o due valori memorizzati: niente scritto.
	 */
	public function test_t61_lettura_fallita_o_ambigua(): void {
		global $wpdb;

		$contenuto = $this->contenuto();

		$wpdb->suppress_errors( true );
		add_filter(
			'query',
			static function ( $sql ) use ( $wpdb ) {
				$sql = (string) $sql;

				return 0 === strpos( $sql, "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id" ) && false !== strpos( $sql, SchedaContenuto::META_COLLOCAZIONE )
					? 'SELECT meta_value FROM ' . $wpdb->postmeta . '_inesistente'
					: $sql;
			}
		);

		$this->assertSame( 'lettura', SchedaContenuto::applica( $contenuto, $this->richiesta( $contenuto, 'alfa' ) )['codice'] );

		remove_all_filters( 'query' );
		$wpdb->suppress_errors( false );

		$this->assertSame( array(), $this->memorizzate( $contenuto ) );

		add_post_meta( $contenuto, SchedaContenuto::META_COLLOCAZIONE, 'alfa' );
		add_post_meta( $contenuto, SchedaContenuto::META_COLLOCAZIONE, 'zeta_uno' );

		$this->assertNull( SchedaContenuto::collocazione( $contenuto ), 'Due valori: nessuna voce valida.' );
		$this->assertCount( 0, $this->tracce( $contenuto ) );
	}

	/**
	 * T-61: con piu voci memorizzate il riquadro lo dice, e una scelta da quel riquadro rimette una voce sola.
	 */
	public function test_t61_piu_voci_riordinate(): void {
		$contenuto = $this->contenuto();

		add_post_meta( $contenuto, SchedaContenuto::META_COLLOCAZIONE, 'alfa' );
		add_post_meta( $contenuto, SchedaContenuto::META_COLLOCAZIONE, 'zeta_uno' );

		$html = SchedaContenuto::riquadro( get_post( $contenuto ) );

		$this->assertStringContainsString( 'più di una voce memorizzata', $html );
		$this->assertStringContainsString( '<option value="" selected>Da scegliere</option>', $html );
		$this->assertStringContainsString( 'name="' . SchedaContenuto::CAMPO_PARTENZA . '" value=""', $html );

		// Un modulo aperto quando la voce era una sola e un conflitto.
		$this->assertSame( 'conflitto', SchedaContenuto::applica( $contenuto, $this->richiesta( $contenuto, 'zeta_due', 'alfa' ) )['codice'] );
		$this->assertSame( array( 'alfa', 'zeta_uno' ), $this->memorizzate( $contenuto ) );
		$this->assertCount( 0, $this->tracce( $contenuto ) );

		$esito = SchedaContenuto::applica( $contenuto, $this->richiesta( $contenuto, 'zeta_due', '' ) );

		$this->assertSame( 'salvato', $esito['codice'] );
		$this->assertStringContainsString( 'più voci memorizzate', $esito['messaggio'] );
		$this->assertSame( array( 'zeta_due' ), $this->memorizzate( $contenuto ) );
		$this->assertSame( 'zeta_due', SchedaContenuto::collocazione( $contenuto ) );

		$tracce = $this->tracce( $contenuto );

		$this->assertCount( 1, $tracce );
		$this->assertSame(
			array(
				'prima'   => '',
				'dopo'    => 'zeta_due',
				'trovate' => array( 'alfa', 'zeta_uno' ),
			),
			$tracce[0]['dettagli']
		);
	}

	/**
	 * T-61: con piu voci memorizzate, voce nuova non scritta: l'esito lo dice.
	 */
	public function test_t61_piu_voci_non_scritte(): void {
		$contenuto = $this->contenuto();

		add_post_meta( $contenuto, SchedaContenuto::META_COLLOCAZIONE, 'alfa' );
		add_post_meta( $contenuto, SchedaContenuto::META_COLLOCAZIONE, 'zeta_uno' );

		add_filter(
			'add_post_metadata',
			static function ( $esito, $id, $chiave ) {
				return SchedaContenuto::META_COLLOCAZIONE === $chiave ? false : $esito;
			},
			10,
			3
		);

		$esito = SchedaContenuto::applica( $contenuto, $this->richiesta( $contenuto, 'zeta_due', '' ) );

		remove_all_filters( 'add_post_metadata' );

		$this->assertSame( 'scrittura', $esito['codice'] );
		$this->assertStringContainsString( 'cambio che non è avvenuto', $esito['messaggio'] );
		$this->assertNull( SchedaContenuto::collocazione( $contenuto ) );
	}

	/**
	 * T-61: con piu voci memorizzate, registro che rifiuta: le righe restano come sono.
	 */
	public function test_t61_piu_voci_registro_che_rifiuta(): void {
		global $wpdb;

		$contenuto = $this->contenuto();

		add_post_meta( $contenuto, SchedaContenuto::META_COLLOCAZIONE, 'alfa' );
		add_post_meta( $contenuto, SchedaContenuto::META_COLLOCAZIONE, 'zeta_uno' );

		$tabella = $wpdb->prefix . 'conformita_core_registro';

		add_filter(
			'query',
			static function ( $sql ) use ( $tabella ) {
				return 0 === strpos( ltrim( (string) $sql ), 'INSERT INTO `' . $tabella . '`' ) ? 'INSERT INTO `' . $tabella . '_inesistente` VALUES (1)' : $sql;
			}
		);
		$wpdb->suppress_errors( true );

		$this->assertSame( 'registro', SchedaContenuto::applica( $contenuto, $this->richiesta( $contenuto, 'zeta_due', '' ) )['codice'] );
		$this->assertSame( array( 'alfa', 'zeta_uno' ), $this->memorizzate( $contenuto ) );
	}

	/**
	 * T-61: albero non disponibile, registro che rifiuta, dato non scritto, blocco tenuto.
	 */
	public function test_t61_guasti(): void {
		global $wpdb;

		$contenuto = $this->contenuto();

		PaginaPubblica::fissa_file( __DIR__ . '/dati/inesistente.json' );
		PaginaPubblica::da_init();

		$this->assertSame( 'albero', SchedaContenuto::applica( $contenuto, $this->richiesta( $contenuto, 'alfa' ) )['codice'] );
		$this->assertStringContainsString( 'non è disponibile', SchedaContenuto::riquadro( get_post( $contenuto ) ) );

		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-finto.json' );
		PaginaPubblica::da_init();

		$tabella = $wpdb->prefix . 'conformita_core_registro';

		add_filter(
			'query',
			static function ( $sql ) use ( $tabella ) {
				return 0 === strpos( ltrim( (string) $sql ), 'INSERT INTO `' . $tabella . '`' ) ? 'INSERT INTO `' . $tabella . '_inesistente` VALUES (1)' : $sql;
			}
		);
		$wpdb->suppress_errors( true );
		$this->assertSame( 'registro', SchedaContenuto::applica( $contenuto, $this->richiesta( $contenuto, 'alfa' ) )['codice'] );
		$wpdb->suppress_errors( false );
		remove_all_filters( 'query' );

		$this->assertSame( array(), $this->memorizzate( $contenuto ) );
		$this->assertCount( 0, $this->tracce( $contenuto ) );

		add_filter(
			'update_post_metadata',
			static function ( $esito, $id, $chiave ) {
				return SchedaContenuto::META_COLLOCAZIONE === $chiave ? false : $esito;
			},
			10,
			3
		);

		$esito = SchedaContenuto::applica( $contenuto, $this->richiesta( $contenuto, 'alfa' ) );

		$this->assertSame( 'scrittura', $esito['codice'] );
		$this->assertStringContainsString( 'cambio che non è avvenuto', $esito['messaggio'] );
		$this->assertSame( array(), $this->memorizzate( $contenuto ) );

		remove_all_filters( 'update_post_metadata' );

		list( $host, $porta, $socket ) = $wpdb->parse_db_host( DB_HOST );

		$altra = mysqli_init(); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_init -- serve una seconda connessione, che $wpdb non offre.
		$this->assertTrue( $altra->real_connect( (string) $host, DB_USER, DB_PASSWORD, DB_NAME, $porta ? (int) $porta : null, $socket ? (string) $socket : null ) );

		try {
			$this->assertSame( '1', (string) $altra->query( "SELECT GET_LOCK('" . $altra->real_escape_string( Blocco::nome() ) . "', 0)" )->fetch_row()[0] );

			$esito = SchedaContenuto::applica( $contenuto, $this->richiesta( $contenuto, 'alfa' ) );
		} finally {
			$altra->close();
		}

		$this->assertSame( 'occupato', $esito['codice'] );
		$this->assertSame( array(), $this->memorizzate( $contenuto ) );
	}

	/**
	 * T-61: dal salvataggio del contenuto, l'esito si mostra una volta; senza il riquadro non si tocca niente.
	 */
	public function test_t61_dal_salvataggio(): void {
		$contenuto = $this->contenuto();

		$_POST = $this->richiesta( $contenuto, 'zeta_uno' );
		wp_update_post(
			array(
				'ID'         => $contenuto,
				'post_title' => 'Titolo cambiato',
			)
		);
		$_POST = array();

		$this->assertSame( array( 'zeta_uno' ), $this->memorizzate( $contenuto ) );

		// Un secondo contenuto salvato subito dopo: il suo esito non cancella quello del primo.
		$secondo = $this->contenuto();
		$_POST   = $this->richiesta( $secondo, 'radice' );
		wp_update_post(
			array(
				'ID'         => $secondo,
				'post_title' => 'Secondo',
			)
		);
		$_POST = array();

		set_current_screen( 'edit-' . TIPO_CONTENUTO );
		$GLOBALS['post'] = get_post( $contenuto ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- il contenuto della schermata, come lo mette WordPress.
		$this->assertStringNotContainsString( 'Voce della sezione', $this->avvisi_in_bacheca(), 'Solo sulla schermata del contenuto, non nell\'elenco.' );

		set_current_screen( TIPO_CONTENUTO );
		$GLOBALS['post'] = get_post( $secondo ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- come sopra.
		$avvisi          = $this->avvisi_in_bacheca();

		$this->assertStringContainsString( 'la voce scelta non è fra quelle', $avvisi );
		$this->assertStringNotContainsString( 'Voce della sezione salvata.', $avvisi, 'L\'esito del primo non compare sul secondo.' );

		$GLOBALS['post'] = get_post( $contenuto ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- come sopra.
		$avvisi          = $this->avvisi_in_bacheca();

		$this->assertStringContainsString( 'Voce della sezione salvata.', $avvisi );
		$this->assertStringNotContainsString( 'la voce scelta non è fra quelle', $avvisi );
		$this->assertStringNotContainsString( 'Voce della sezione salvata.', $this->avvisi_in_bacheca(), 'L\'esito si mostra una volta.' );

		unset( $GLOBALS['post'] );
		set_current_screen( 'front' );

		wp_update_post(
			array(
				'ID'         => $contenuto,
				'post_title' => 'Senza riquadro',
			)
		);

		$this->assertSame( array( 'zeta_uno' ), $this->memorizzate( $contenuto ) );
		$this->assertCount( 1, $this->tracce( $contenuto ) );
	}

	/**
	 * T-62: creazione, pubblicazione, modifica e cestino di un contenuto sono nel registro del componente comune.
	 */
	public function test_t62_registro_delle_modifiche(): void {
		$contenuto = $this->contenuto();

		wp_publish_post( $contenuto );
		wp_update_post(
			array(
				'ID'           => $contenuto,
				'post_content' => 'Testo cambiato',
			)
		);
		wp_trash_post( $contenuto );

		$righe = conformita_core_voci_registro( array( 'contenuto' => $contenuto ) );

		$this->assertIsArray( $righe );

		$stati = array();

		foreach ( $righe as $riga ) {
			$this->assertSame( SEZIONE, $riga['sezione'] );
			$this->assertSame( TIPO_CONTENUTO, $riga['tipo'] );
			$this->assertSame( (int) $this->amministratore, (int) $riga['utente'], 'Chi ha agito.' );

			if ( isset( $riga['dettagli']['stato_nuovo'] ) ) {
				$stati[] = (string) $riga['dettagli']['stato_nuovo'];
			}
		}

		$this->assertContains( 'publish', $stati, 'Pubblicazione.' );
		$this->assertContains( 'trash', $stati, 'Rimozione.' );
		$this->assertGreaterThanOrEqual( 4, count( $righe ), 'Creazione, pubblicazione, modifica, cestino.' );
	}
}
