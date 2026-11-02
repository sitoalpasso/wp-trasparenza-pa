<?php
/**
 * Prove del contenuto scaduto su ogni strada di lettura: riga T-156.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Avvio;
use TrasparenzaPa\ContenutiPubblici;
use TrasparenzaPa\Installazione;
use TrasparenzaPa\PaginaPubblica;
use TrasparenzaPa\SchedaContenuto;
use TrasparenzaPa\SchedaDocumenti;
use TrasparenzaPa\TipoContenuto;
use TrasparenzaPa\TipoVoce;
use WP_Query;
use WP_REST_Request;
use WP_UnitTestCase;

use const TrasparenzaPa\OPZIONE_VERSIONE;
use const TrasparenzaPa\TIPO_CONTENUTO;

/**
 * Un contenuto scaduto, con il compito pianificato mai eseguito, esce da ogni
 * strada di lettura pubblica e resta intero nella banca dati.
 */
class ScadutiPercorsiTest extends WP_UnitTestCase {

	use DepositoDiProva;

	/**
	 * Contenuto scaduto.
	 *
	 * @var int
	 */
	private $scaduto = 0;

	/**
	 * Documento del contenuto scaduto.
	 *
	 * @var int
	 */
	private $documento = 0;

	/**
	 * Contenuto in vigore, per vedere che la strada funziona.
	 *
	 * @var int
	 */
	private $in_vigore = 0;

	/**
	 * Documento del contenuto in vigore.
	 *
	 * @var int
	 */
	private $documento_in_vigore = 0;

	/**
	 * Sezione e tipi registrati, permessi, un contenuto scaduto e uno in vigore, nessun compito pianificato eseguito.
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
		$this->assertTrue( Installazione::aggiorna_se_serve() );

		$this->set_permalink_structure( '/%postname%/' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->aggancia_server_finto();

		list( $this->scaduto, $this->documento )             = $this->con_documento( 'Bilancio scaduto' );
		list( $this->in_vigore, $this->documento_in_vigore ) = $this->con_documento( 'Bilancio in vigore' );

		$this->assertTrue( conformita_core_imposta_fine_pubblicazione( $this->scaduto, '2000-01-01' ) );

		wp_set_current_user( 0 );
	}

	/**
	 * Toglie server finto e file.
	 */
	public function tear_down(): void {
		$this->sgancia_server_finto();

		parent::tear_down();
	}

	/**
	 * Un contenuto pubblicato in una voce, con un documento depositato.
	 *
	 * @param string $titolo Titolo.
	 * @return array{0: int, 1: int}
	 */
	private function con_documento( string $titolo ): array {
		$contenuto = (int) self::factory()->post->create(
			array(
				'post_type'    => TIPO_CONTENUTO,
				'post_status'  => 'publish',
				'post_title'   => $titolo,
				'post_content' => $titolo . ', testo.',
			)
		);

		add_post_meta( $contenuto, SchedaContenuto::META_COLLOCAZIONE, 'zeta_uno' );
		$this->assertTrue( conformita_core_imposta_fine_pubblicazione( $contenuto, '2099-12-31' ) );

		$esito = SchedaDocumenti::deposita( $contenuto, $this->file_temporaneo( sanitize_title( $titolo ) . '.pdf' ), 'percorso_locale' );
		$this->assertSame( 'salvato', $esito['codice'], $esito['messaggio'] );

		$documenti = SchedaDocumenti::documenti( $contenuto );

		return array( $contenuto, (int) end( $documenti ) );
	}

	/**
	 * Lo stato della risposta del punto pubblico a una richiesta anonima del documento.
	 *
	 * @param int $documento Documento.
	 * @return int|null
	 */
	private function scarica( int $documento ): ?int {
		$indirizzo = conformita_core_indirizzo_consegna( $documento );
		$this->assertIsString( $indirizzo );
		parse_str( (string) wp_parse_url( $indirizzo, PHP_URL_QUERY ), $variabili );

		$risposte = array();
		\Conformita_Core_Consegna::fissa_emettitore(
			static function ( array $risposta ) use ( &$risposte ) {
				$risposte[] = $risposta;
			}
		);

		try {
			$richiesta             = new \WP();
			$richiesta->query_vars = $variabili;
			\Conformita_Core_Consegna::serve_pubblica( $richiesta );
		} finally {
			\Conformita_Core_Consegna::azzera_emettitore();
		}

		return array() === $risposte ? null : (int) $risposte[0]['stato'];
	}

	/**
	 * Gli identificativi trovati da un'interrogazione.
	 *
	 * @param array<string, mixed> $argomenti Argomenti.
	 * @return array<int, int>
	 */
	private function trovati( array $argomenti ): array {
		return array_map( 'intval', wp_list_pluck( ( new WP_Query( $argomenti ) )->posts, 'ID' ) );
	}

	/**
	 * La risposta dell'interfaccia informatica a una richiesta anonima.
	 *
	 * @param string               $percorso  Percorso.
	 * @param array<string, mixed> $parametri Parametri.
	 * @return \WP_REST_Response
	 */
	private function rest( string $percorso, array $parametri = array() ) {
		$richiesta = new WP_REST_Request( 'GET', $percorso );
		$richiesta->set_query_params( $parametri );

		return rest_do_request( $richiesta );
	}

	/**
	 * T-156. Il contenuto scaduto esce da ogni strada di lettura pubblica, a compito pianificato fermo.
	 */
	public function test_t156_strade_di_lettura() {
		// Nessun compito pianificato e passato: la scadenza si legge sul dato.
		$this->assertSame( 'publish', get_post_status( $this->scaduto ) );
		$this->assertTrue( conformita_core_scaduto( $this->scaduto ) );
		$this->assertFalse( conformita_core_scaduto( $this->in_vigore ) );

		// Pagina della sezione: il contenuto e il suo documento non compaiono.
		$this->assertFalse( ContenutiPubblici::visibile( get_post( $this->scaduto ) ) );
		$this->assertTrue( ContenutiPubblici::visibile( get_post( $this->in_vigore ) ) );
		$pagina = PaginaPubblica::disegna( PaginaPubblica::albero(), 'zeta_uno', 'https://example.org/sezione/' );
		$this->assertStringContainsString( 'Bilancio in vigore', $pagina );
		$this->assertStringNotContainsString( 'Bilancio scaduto', $pagina );
		$this->assertStringNotContainsString( 'bilancio-scaduto', $pagina );

		// Il documento non si scarica dal punto pubblico.
		$this->assertSame( 404, $this->scarica( $this->documento ) );

		// Contenuto singolo e elenchi (C-10, C-11, C-21).
		$this->assertSame(
			array(),
			$this->trovati(
				array(
					'p'         => $this->scaduto,
					'post_type' => TIPO_CONTENUTO,
				)
			)
		);
		$this->assertSame( array( $this->in_vigore ), $this->trovati( array( 'post_type' => TIPO_CONTENUTO ) ) );

		// Ricerca chiedendo il tipo (C-12): trova quello in vigore, non lo scaduto.
		$this->assertSame(
			array( $this->in_vigore ),
			$this->trovati(
				array(
					's'         => 'Bilancio',
					'post_type' => TIPO_CONTENUTO,
				)
			)
		);

		// Feed del tipo (C-13): c'e quello in vigore, non lo scaduto.
		$this->go_to(
			add_query_arg(
				array(
					'post_type' => TIPO_CONTENUTO,
					'feed'      => 'rss2',
				),
				home_url( '/' )
			)
		);
		$nel_feed = array_map( 'intval', wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
		$this->assertContains( $this->in_vigore, $nel_feed );
		$this->assertNotContains( $this->scaduto, $nel_feed );

		// Mappa del sito (C-14): il tipo non ha una mappa, e la mappa delle voci elenca voci, non contenuti.
		$this->assertArrayNotHasKey( TIPO_CONTENUTO, wp_sitemaps_get_server()->registry->get_provider( 'posts' )->get_object_subtypes() );

		// Interfaccia informatica (C-16): il documento dello scaduto risponde come inesistente, quello in vigore no.
		$this->assertSame( 404, $this->rest( '/wp/v2/media/' . $this->documento )->get_status() );
		$this->assertSame( 401, $this->rest( '/wp/v2/media/' . $this->documento_in_vigore )->get_status() );

		// Anteprima incorporata (C-17).
		$this->assertFalse( get_oembed_response_data( $this->scaduto, 600 ) );

		// XML-RPC (C-18): anche chi amministra, da li, non legge lo scaduto.
		$amministratore = self::factory()->user->create(
			array(
				'role'      => 'administrator',
				'user_pass' => 'parola-di-prova',
			)
		);
		add_filter( 'xmlrpc_enabled', '__return_true' );
		$server = new \wp_xmlrpc_server();
		$utente = get_userdata( $amministratore )->user_login;
		$this->assertSame( array(), $server->wp_getPost( array( 1, $utente, 'parola-di-prova', $this->scaduto ) ) );
		$this->assertSame( 'Bilancio in vigore', $server->wp_getPost( array( 1, $utente, 'parola-di-prova', $this->in_vigore ) )['post_title'] ?? null );
		wp_set_current_user( 0 );

		// Pagina dell'allegato (C-19): quella del documento in vigore esiste, quella dello scaduto no.
		$this->go_to( get_attachment_link( $this->documento_in_vigore ) );
		$this->assertContains( $this->documento_in_vigore, array_map( 'intval', wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) ) );
		$this->go_to( get_attachment_link( $this->documento ) );
		$this->assertNotContains( $this->documento, array_map( 'intval', wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) ) );
		$this->assertSame(
			array(),
			$this->trovati(
				array(
					'p'           => $this->documento,
					'post_type'   => 'attachment',
					'post_status' => 'inherit',
				)
			)
		);

		// Il dato resta intero: contenuto pubblicato, documento e file al loro posto.
		$this->assertSame( 'publish', get_post_status( $this->scaduto ) );
		$this->assertSame( 'Bilancio scaduto, testo.', get_post_field( 'post_content', $this->scaduto ) );
		$this->assertSame( array( $this->documento ), SchedaDocumenti::documenti( $this->scaduto ) );
		$this->assertFileExists( (string) get_attached_file( $this->documento, true ) );
	}
}
