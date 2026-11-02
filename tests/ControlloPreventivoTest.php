<?php
/**
 * Prove della conferma sui dati personali prima di pubblicare: righe T-159..T-160.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Avvio;
use TrasparenzaPa\ControlloPreventivo;
use TrasparenzaPa\Installazione;
use TrasparenzaPa\PaginaPubblica;
use TrasparenzaPa\TipoContenuto;
use TrasparenzaPa\TipoVoce;
use WP_UnitTestCase;

use const TrasparenzaPa\OPZIONE_VERSIONE;
use const TrasparenzaPa\TIPO_CONTENUTO;

/**
 * Un contenuto si pubblica solo con la conferma sui dati personali.
 */
class ControlloPreventivoTest extends WP_UnitTestCase {

	use AmbienteTrasparenza;

	/**
	 * Amministratore.
	 *
	 * @var int
	 */
	private $amministratore = 0;

	/**
	 * Passaggi da bozza a pubblicato annunciati per i contenuti.
	 *
	 * @var int
	 */
	private $annunci = 0;

	/**
	 * Sezione e tipi registrati, permessi, conferma richiesta davvero.
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

		$this->amministratore = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->amministratore );

		ControlloPreventivo::fissa_conferma_per_prove( false );

		$this->annunci = 0;
		add_action(
			'draft_to_publish',
			function ( $articolo ) {
				if ( TIPO_CONTENUTO === $articolo->post_type ) {
					++$this->annunci;
				}
			}
		);
	}

	/**
	 * Rimette la conferma delle prove, i guasti e la richiesta.
	 */
	public function tear_down(): void {
		global $wpdb;

		ControlloPreventivo::fissa_conferma_per_prove( true );
		remove_all_filters( 'query' );
		$wpdb->suppress_errors( false );
		$_POST    = array();
		$_REQUEST = array();

		parent::tear_down();
	}

	/**
	 * Un contenuto in bozza.
	 *
	 * @return int
	 */
	private function bozza(): int {
		return (int) self::factory()->post->create(
			array(
				'post_type'   => TIPO_CONTENUTO,
				'post_status' => 'draft',
				'post_title'  => 'Contenuto',
			)
		);
	}

	/**
	 * La casella spuntata nella richiesta, con il gettone del contenuto dato.
	 *
	 * @param int         $contenuto Contenuto della casella.
	 * @param string|null $gettone   Gettone, nullo per quello giusto del contenuto.
	 */
	private function casella( int $contenuto, ?string $gettone = null ): void {
		$_POST[ ControlloPreventivo::CAMPO_CONFERMA ] = (string) $contenuto;
		$_POST[ ControlloPreventivo::CAMPO_GETTONE ]  = $gettone ?? wp_create_nonce( ControlloPreventivo::azione( $contenuto ) );
	}

	/**
	 * Prova a pubblicare con la scrittura del contenuto.
	 *
	 * @param int    $contenuto Contenuto.
	 * @param string $stato     Stato chiesto.
	 * @return string Stato dopo.
	 */
	private function pubblica( int $contenuto, string $stato = 'publish' ): string {
		$argomenti = array(
			'ID'          => $contenuto,
			'post_status' => $stato,
		);

		if ( 'future' === $stato ) {
			$argomenti['post_date']     = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) );
			$argomenti['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS );
			$argomenti['edit_date']     = true;
		}

		wp_update_post( $argomenti );
		clean_post_cache( $contenuto );

		return (string) get_post_status( $contenuto );
	}

	/**
	 * Le conferme nel registro per un contenuto.
	 *
	 * @param int $contenuto Contenuto.
	 * @return array<int, array<string, mixed>>
	 */
	private function conferme( int $contenuto ): array {
		return conformita_core_voci_registro(
			array(
				'contenuto' => $contenuto,
				'azione'    => ControlloPreventivo::AZIONE_REGISTRO,
			)
		);
	}

	/**
	 * T-159. Senza conferma non si pubblica e non si programma, su nessuna strada.
	 */
	public function test_t159_senza_conferma() {
		foreach ( array( 'publish', 'future' ) as $stato ) {
			$contenuto = $this->bozza();
			$this->assertSame( 'draft', $this->pubblica( $contenuto, $stato ), $stato );
		}

		// Sulla scrittura il contenuto non passa neppure un istante da pubblicato.
		$this->assertSame( 0, $this->annunci );
		$this->assertStringContainsString( 'dati personali', $this->avvisi_in_bacheca() );
		$this->assertStringNotContainsString( 'dati personali', $this->avvisi_in_bacheca(), 'Il motivo si mostra una volta.' );

		// Il cambio di stato fatto direttamente.
		$diretto = $this->bozza();
		wp_publish_post( $diretto );
		clean_post_cache( $diretto );
		$this->assertSame( 'draft', get_post_status( $diretto ) );

		// Un contenuto nuovo che nasce pubblicato.
		$nuovo = (int) wp_insert_post(
			array(
				'post_type'   => TIPO_CONTENUTO,
				'post_status' => 'publish',
				'post_title'  => 'Nuovo',
			)
		);
		$this->assertSame( 'draft', get_post_status( $nuovo ) );

		$this->assertSame( array(), $this->conferme( $diretto ) );
	}

	/**
	 * T-159. Con la conferma si pubblica, e il registro dice chi l'ha data.
	 */
	public function test_t159_con_conferma() {
		$contenuto = $this->bozza();
		$this->casella( $contenuto );

		$this->assertSame( 'publish', $this->pubblica( $contenuto ) );

		$conferme = $this->conferme( $contenuto );
		$this->assertCount( 1, $conferme );
		$this->assertSame( $this->amministratore, (int) $conferme[0]['utente'] );
		$this->assertStringNotContainsString( 'dati personali', $this->avvisi_in_bacheca() );

		// Gia pubblicato: una modifica non chiede di nuovo.
		$_POST = array();
		wp_update_post(
			array(
				'ID'           => $contenuto,
				'post_content' => 'Testo corretto.',
			)
		);
		$this->assertSame( 'publish', get_post_status( $contenuto ) );
		$this->assertCount( 1, $this->conferme( $contenuto ) );

		// Programmato con la conferma: alla data si pubblica da solo.
		$programmato = $this->bozza();
		$this->casella( $programmato );
		$this->assertSame( 'future', $this->pubblica( $programmato, 'future' ) );
		$_POST = array();
		wp_publish_post( $programmato );
		clean_post_cache( $programmato );
		$this->assertSame( 'publish', get_post_status( $programmato ) );

		// La conferma vale per una richiesta: il contenuto rimesso in bozza la chiede di nuovo.
		wp_update_post(
			array(
				'ID'          => $contenuto,
				'post_status' => 'draft',
			)
		);
		$this->assertSame( 'draft', $this->pubblica( $contenuto ) );
		wp_publish_post( $contenuto );
		clean_post_cache( $contenuto );
		$this->assertSame( 'draft', get_post_status( $contenuto ) );
	}

	/**
	 * T-159. [attacco] e [guasto]: resta in bozza.
	 */
	public function test_t159_attacchi_e_guasti() {
		global $wpdb;

		// Conferma per un altro contenuto.
		$contenuto = $this->bozza();
		$altro     = $this->bozza();
		$this->casella( $altro );
		$this->assertSame( 'draft', $this->pubblica( $contenuto ) );

		// Casella non spuntata: il modulo manda comunque il gettone.
		$this->casella( $contenuto );
		unset( $_POST[ ControlloPreventivo::CAMPO_CONFERMA ] );
		$this->assertSame( 'draft', $this->pubblica( $contenuto ) );

		// Gettone sbagliato, o di un altro contenuto.
		$this->casella( $contenuto, 'sbagliato' );
		$this->assertSame( 'draft', $this->pubblica( $contenuto ) );
		$this->casella( $contenuto, wp_create_nonce( ControlloPreventivo::azione( $altro ) ) );
		$this->assertSame( 'draft', $this->pubblica( $contenuto ) );

		// Chi non puo pubblicare.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->casella( $contenuto );
		$this->assertSame( 'draft', $this->pubblica( $contenuto ) );
		wp_set_current_user( $this->amministratore );

		// Registro che rifiuta.
		$tabella = $wpdb->prefix . 'conformita_core_registro';
		$wpdb->suppress_errors( true );
		add_filter(
			'query',
			static function ( $sql ) use ( $tabella ) {
				return 0 === strpos( ltrim( (string) $sql ), 'INSERT INTO `' . $tabella . '`' ) ? 'INSERT INTO `' . $tabella . '_inesistente` VALUES (1)' : $sql;
			}
		);
		$this->casella( $contenuto );
		$this->assertSame( 'draft', $this->pubblica( $contenuto ) );
		remove_all_filters( 'query' );

		// Lettura dello stato di partenza fallita, senza conferma: non vale come gia pubblicato.
		$_POST = array();
		add_filter(
			'query',
			static function ( $sql ) use ( $wpdb, $contenuto ) {
				return false !== strpos( (string) $sql, 'SELECT post_status FROM ' . $wpdb->posts . ' WHERE ID = ' . $contenuto ) ? 'SELECT post_status FROM ' . $wpdb->posts . '_inesistente' : $sql;
			}
		);
		$this->assertSame( 'draft', $this->pubblica( $contenuto ) );
		remove_all_filters( 'query' );
		$wpdb->suppress_errors( false );

		$this->assertSame( array(), $this->conferme( $contenuto ) );
		$this->assertSame( 0, $this->annunci );

		// Gli altri tipi non c'entrano.
		$articolo = (int) self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$_POST    = array();
		wp_update_post(
			array(
				'ID'          => $articolo,
				'post_status' => 'publish',
			)
		);
		$this->assertSame( 'publish', get_post_status( $articolo ) );
	}

	/**
	 * T-160. Il riquadro, solo dove serve.
	 */
	public function test_t160_riquadro() {
		$contenuto = $this->bozza();
		$riquadro  = ControlloPreventivo::riquadro( get_post( $contenuto ) );

		foreach ( array( 'curriculum', 'coniuge', 'contributi', 'coordinate bancarie', 'graduatorie', '2-septies' ) as $parola ) {
			$this->assertStringContainsString( $parola, $riquadro, $parola );
		}

		$this->assertStringContainsString( 'name="' . ControlloPreventivo::CAMPO_CONFERMA . '"', $riquadro );
		$this->assertStringContainsString( 'value="' . $contenuto . '"', $riquadro );
		$this->assertStringContainsString( 'name="' . ControlloPreventivo::CAMPO_GETTONE . '"', $riquadro );
		$this->assertStringContainsString( wp_create_nonce( ControlloPreventivo::azione( $contenuto ) ), $riquadro );

		// Gia pubblicato o programmato: niente.
		ControlloPreventivo::fissa_conferma_per_prove( true );
		$pubblicato = (int) self::factory()->post->create(
			array(
				'post_type'   => TIPO_CONTENUTO,
				'post_status' => 'publish',
			)
		);
		$this->assertSame( '', ControlloPreventivo::riquadro( get_post( $pubblicato ) ) );
		$copia              = get_post( $contenuto );
		$copia->post_status = 'future';
		$this->assertSame( '', ControlloPreventivo::riquadro( $copia ) );

		// Chi non puo pubblicare: niente.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertSame( '', ControlloPreventivo::riquadro( get_post( $contenuto ) ) );

		// Il riquadro e agganciato alla schermata del contenuto.
		$this->assertNotFalse( has_action( 'add_meta_boxes_' . TIPO_CONTENUTO, array( ControlloPreventivo::class, 'aggiungi_riquadro' ) ) );
	}
}
