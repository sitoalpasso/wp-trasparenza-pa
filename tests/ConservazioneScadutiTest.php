<?php
/**
 * Prove della conservazione dei contenuti scaduti: righe T-157..T-158.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Avvio;
use TrasparenzaPa\ConservazioneScaduti;
use TrasparenzaPa\Installazione;
use TrasparenzaPa\PaginaPubblica;
use TrasparenzaPa\SchedaContenuto;
use TrasparenzaPa\SchedaDocumenti;
use TrasparenzaPa\TipoContenuto;
use TrasparenzaPa\TipoVoce;
use WP_UnitTestCase;

use const TrasparenzaPa\OPZIONE_VERSIONE;
use const TrasparenzaPa\TIPO_CONTENUTO;

/**
 * Un contenuto pubblicato e scaduto, con i suoi documenti, non si cancella e non esce dalla pubblicazione.
 */
class ConservazioneScadutiTest extends WP_UnitTestCase {

	use AmbienteTrasparenza;
	use DepositoDiProva;

	/**
	 * Amministratore.
	 *
	 * @var int
	 */
	private $amministratore = 0;

	/**
	 * Sezione e tipi registrati, permessi, albero finto, amministratore.
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
		$this->aggancia_server_finto();
	}

	/**
	 * Toglie guasti, server finto e file.
	 */
	public function tear_down(): void {
		global $wpdb;

		remove_all_filters( 'query' );
		$wpdb->suppress_errors( false );
		$this->sgancia_server_finto();

		parent::tear_down();
	}

	/**
	 * Un contenuto con un documento, nello stato e con la fine dati.
	 *
	 * @param string $stato  Stato.
	 * @param string $fine   Fine della pubblicazione.
	 * @param int    $autore Autore, zero per l'utente corrente.
	 * @return array{0: int, 1: int} Contenuto e documento.
	 */
	private function con_documento( string $stato, string $fine, int $autore = 0 ): array {
		$contenuto = (int) self::factory()->post->create(
			array(
				'post_type'    => TIPO_CONTENUTO,
				'post_status'  => 'publish',
				'post_title'   => 'Contenuto',
				'post_content' => 'Testo.',
				'post_author'  => $autore ? $autore : get_current_user_id(),
			)
		);

		add_post_meta( $contenuto, SchedaContenuto::META_COLLOCAZIONE, 'zeta_uno' );
		$this->assertTrue( conformita_core_imposta_fine_pubblicazione( $contenuto, '2099-12-31' ) );

		// Il documento si deposita prima della pubblicazione, come fa l'addetto.
		wp_update_post(
			array(
				'ID'          => $contenuto,
				'post_status' => 'draft',
			)
		);
		$esito = SchedaDocumenti::deposita( $contenuto, $this->file_temporaneo( 'documento.pdf' ), 'percorso_locale' );
		$this->assertSame( 'salvato', $esito['codice'], $esito['messaggio'] );

		if ( 'draft' !== $stato ) {
			wp_update_post(
				array(
					'ID'          => $contenuto,
					'post_status' => $stato,
				)
			);
		}

		$this->assertTrue( conformita_core_imposta_fine_pubblicazione( $contenuto, $fine ) );
		$this->assertSame( $stato, get_post_status( $contenuto ) );

		$documenti = SchedaDocumenti::documenti( $contenuto );

		return array( $contenuto, (int) end( $documenti ) );
	}

	/**
	 * Lo scaduto e il suo documento sono ancora al loro posto.
	 *
	 * @param int $contenuto Contenuto.
	 * @param int $documento Documento.
	 */
	private function intero( int $contenuto, int $documento ): void {
		clean_post_cache( $contenuto );
		clean_post_cache( $documento );

		$this->assertSame( 'publish', get_post_status( $contenuto ) );
		$this->assertSame( 'inherit', get_post_field( 'post_status', $documento ) );
		$this->assertSame( $contenuto, (int) get_post_field( 'post_parent', $documento ) );
		$this->assertFileExists( (string) get_attached_file( $documento, true ) );
	}

	/**
	 * T-157. Lo scaduto e il suo documento non vanno nel cestino e non si cancellano.
	 */
	public function test_t157_cestino_e_cancellazione() {
		global $wpdb;

		list( $scaduto, $documento ) = $this->con_documento( 'publish', '2000-01-01' );
		$this->assertTrue( conformita_core_scaduto( $scaduto ) );

		// Nessun permesso di cancellare, nemmeno a chi amministra.
		$this->assertFalse( current_user_can( 'delete_post', $scaduto ) );
		$this->assertFalse( current_user_can( 'delete_post', $documento ) );

		// Le funzioni che non guardano i permessi si fermano lo stesso.
		$this->assertFalse( wp_trash_post( $scaduto ) );
		$this->assertFalse( wp_delete_post( $scaduto, true ) );
		$this->assertFalse( wp_delete_attachment( $documento, true ) );
		$this->assertFalse( wp_trash_post( $documento ) );
		$this->intero( $scaduto, $documento );
		$this->assertStringContainsString( 'si conserva', $this->avvisi_in_bacheca() );
		$this->assertStringNotContainsString( 'si conserva', $this->avvisi_in_bacheca(), 'Il motivo si mostra una volta.' );

		// Scollegare o ricollegare il documento dalla libreria, poi cancellarlo: il documento resta legato.
		$this->assertFalse( current_user_can( 'edit_post', $documento ) );
		$altro = (int) self::factory()->post->create(
			array(
				'post_type'   => TIPO_CONTENUTO,
				'post_status' => 'draft',
			)
		);
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$_REQUEST['media'] = array( $documento );
		wp_media_attach_action( $scaduto, 'detach' );
		wp_media_attach_action( $altro, 'attach' );
		unset( $_REQUEST['media'] );
		$this->assertFalse( wp_delete_attachment( $documento, true ) );
		$this->intero( $scaduto, $documento );

		// Da codice: cambiare il padre del documento, o il tipo del contenuto, non passa.
		wp_update_post(
			array(
				'ID'          => $documento,
				'post_parent' => 0,
			)
		);
		$this->intero( $scaduto, $documento );
		wp_update_post(
			array(
				'ID'          => $scaduto,
				'post_type'   => 'post',
				'post_status' => 'draft',
			)
		);
		$this->intero( $scaduto, $documento );
		$this->assertSame( TIPO_CONTENUTO, get_post_type( $scaduto ) );
		$this->avvisi_in_bacheca();

		// Nessun documento nuovo su uno scaduto, nemmeno con il registro che rifiuta: niente da annullare.
		$tabella = $wpdb->prefix . 'conformita_core_registro';
		$wpdb->suppress_errors( true );
		add_filter(
			'query',
			static function ( $sql ) use ( $tabella ) {
				return 0 === strpos( ltrim( (string) $sql ), 'INSERT INTO `' . $tabella . '`' ) ? 'INSERT INTO `' . $tabella . '_inesistente` VALUES (1)' : $sql;
			}
		);
		$this->assertSame( 'scaduto', SchedaDocumenti::deposita( $scaduto, $this->file_temporaneo( 'nuovo.pdf' ), 'percorso_locale' )['codice'] );
		remove_all_filters( 'query' );
		$wpdb->suppress_errors( false );
		$this->assertSame( 'scaduto', SchedaDocumenti::deposita( $scaduto, $this->file_temporaneo( 'nuovo.pdf' ), 'percorso_locale' )['codice'] );
		$this->assertSame( array( $documento ), SchedaDocumenti::documenti( $scaduto ) );

		// La cancellazione dell'autore non porta via niente.
		$autore                      = (int) self::factory()->user->create( array( 'role' => 'editor' ) );
		list( $suo, $suo_documento ) = $this->con_documento( 'publish', '2000-01-01', $autore );
		$this->assertTrue( wp_delete_user( $autore ) );
		$this->intero( $suo, $suo_documento );

		// Lettura dello stato fallita: vale come fermo.
		$wpdb->suppress_errors( true );
		add_filter(
			'query',
			static function ( $sql ) use ( $wpdb, $scaduto ) {
				return false !== strpos( (string) $sql, 'SELECT post_status FROM ' . $wpdb->posts . ' WHERE ID = ' . $scaduto ) ? 'SELECT post_status FROM ' . $wpdb->posts . '_inesistente' : $sql;
			}
		);
		$this->assertFalse( wp_trash_post( $scaduto ) );
		remove_all_filters( 'query' );
		$wpdb->suppress_errors( false );
		$this->intero( $scaduto, $documento );

		// In vigore e bozza: come prima.
		list( $in_vigore, $documento_in_vigore ) = $this->con_documento( 'publish', '2099-12-31' );
		list( $bozza, $documento_bozza )         = $this->con_documento( 'draft', '2000-01-01' );

		foreach ( array( $in_vigore, $bozza ) as $contenuto ) {
			$this->assertTrue( current_user_can( 'delete_post', $contenuto ) );
		}

		$this->assertTrue( current_user_can( 'delete_post', $documento_bozza ) );
		$this->assertTrue( current_user_can( 'edit_post', $documento_bozza ) );
		$this->assertInstanceOf( \WP_Post::class, wp_delete_attachment( $documento_bozza, true ) );
		$this->assertInstanceOf( \WP_Post::class, wp_trash_post( $in_vigore ) );
		$this->assertInstanceOf( \WP_Post::class, wp_delete_post( $bozza, true ) );
		$this->assertNull( get_post( $bozza ) );
		$this->assertSame( 'trash', get_post_status( $in_vigore ) );
		$this->assertIsInt( $documento_in_vigore );

		// Pubblicato senza data di fine: non e mai stato visibile, quindi non e uno scaduto da conservare.
		$senza_fine = (int) self::factory()->post->create(
			array(
				'post_type'   => TIPO_CONTENUTO,
				'post_status' => 'publish',
			)
		);
		$this->assertSame( '', conformita_core_fine_pubblicazione( $senza_fine ) );
		$this->assertTrue( current_user_can( 'delete_post', $senza_fine ) );
		$this->assertInstanceOf( \WP_Post::class, wp_trash_post( $senza_fine ) );

		// Data di fine doppia, che non si sa leggere: vale come fermo.
		list( $doppio ) = $this->con_documento( 'publish', '2099-12-31' );
		add_post_meta( $doppio, conformita_core_chiave_fine_pubblicazione(), '2000-01-01' );
		$this->assertWPError( conformita_core_fine_pubblicazione( $doppio ) );
		$this->assertFalse( wp_trash_post( $doppio ) );
		$this->assertSame( 'publish', get_post_status( $doppio ) );

		// Gli altri tipi non c'entrano: un articolo pubblicato si cestina come prima.
		$articolo = (int) self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$this->assertTrue( current_user_can( 'delete_post', $articolo ) );
		$this->assertInstanceOf( \WP_Post::class, wp_trash_post( $articolo ) );
	}

	/**
	 * T-158. Lo scaduto non esce dalla pubblicazione, ma il testo si corregge.
	 */
	public function test_t158_stato() {
		list( $scaduto, $documento ) = $this->con_documento( 'publish', '2000-01-01' );
		$data                        = get_post_field( 'post_date', $scaduto );

		foreach ( array( 'draft', 'pending', 'private', 'future' ) as $stato ) {
			wp_update_post(
				array(
					'ID'            => $scaduto,
					'post_status'   => $stato,
					'post_date'     => 'future' === $stato ? get_date_from_gmt( gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) ) : '',
					'post_date_gmt' => 'future' === $stato ? gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) : '',
					'edit_date'     => 'future' === $stato,
				)
			);
			$this->intero( $scaduto, $documento );
			$this->assertSame( $data, get_post_field( 'post_date', $scaduto ), $stato );
		}

		wp_update_post(
			array(
				'ID'           => $scaduto,
				'post_content' => 'Testo corretto.',
			)
		);
		$this->assertSame( 'Testo corretto.', get_post_field( 'post_content', $scaduto ) );
		$this->intero( $scaduto, $documento );

		$avvisi = $this->avvisi_in_bacheca();
		$this->assertStringContainsString( 'si conserva', $avvisi );
		$this->assertSame( 1, substr_count( $avvisi, 'si conserva' ) );

		// In vigore: torna in bozza come prima.
		list( $in_vigore ) = $this->con_documento( 'publish', '2099-12-31' );
		wp_update_post(
			array(
				'ID'          => $in_vigore,
				'post_status' => 'draft',
			)
		);
		$this->assertSame( 'draft', get_post_status( $in_vigore ) );
		$this->assertStringNotContainsString( 'si conserva', $this->avvisi_in_bacheca() );

		// [guasto] Una bozza riscritta con la lettura dello stato fallita resta bozza: lo stato non cambia, il testo si salva.
		global $wpdb;
		$wpdb->suppress_errors( true );
		add_filter(
			'query',
			static function ( $sql ) use ( $wpdb, $in_vigore ) {
				return false !== strpos( (string) $sql, 'SELECT post_status FROM ' . $wpdb->posts . ' WHERE ID = ' . $in_vigore ) ? 'SELECT post_status FROM ' . $wpdb->posts . '_inesistente' : $sql;
			}
		);
		wp_update_post(
			array(
				'ID'           => $in_vigore,
				'post_status'  => 'pending',
				'post_content' => 'Bozza corretta.',
			)
		);
		remove_all_filters( 'query' );
		$wpdb->suppress_errors( false );
		clean_post_cache( $in_vigore );
		$this->assertSame( 'draft', get_post_status( $in_vigore ) );
		$this->assertSame( 'Bozza corretta.', get_post_field( 'post_content', $in_vigore ) );

		// [guasto] Lo scaduto riscritto in bozza con la lettura fallita non cambia stato, e resta fermo quando le letture riprendono.
		$wpdb->suppress_errors( true );
		add_filter(
			'query',
			static function ( $sql ) use ( $wpdb, $scaduto ) {
				return false !== strpos( (string) $sql, 'SELECT post_status FROM ' . $wpdb->posts . ' WHERE ID = ' . $scaduto ) ? 'SELECT post_status FROM ' . $wpdb->posts . '_inesistente' : $sql;
			}
		);
		wp_update_post(
			array(
				'ID'          => $scaduto,
				'post_status' => 'draft',
			)
		);
		remove_all_filters( 'query' );
		$wpdb->suppress_errors( false );
		$this->intero( $scaduto, $documento );
		$this->assertFalse( wp_trash_post( $scaduto ) );
		$this->assertFalse( wp_delete_post( $scaduto, true ) );
		$this->intero( $scaduto, $documento );
		$this->assertTrue( class_exists( ConservazioneScaduti::class ) );
	}
}
