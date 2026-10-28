<?php
/**
 * Prove della voce di trasparenza: righe T-38..T-40.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Avvio;
use TrasparenzaPa\ChiusuraPubblicazione;
use TrasparenzaPa\Installazione;
use TrasparenzaPa\Permessi;
use TrasparenzaPa\TipoVoce;
use WP_UnitTestCase;

use const TrasparenzaPa\OPZIONE_VERSIONE;
use const TrasparenzaPa\SEZIONE;
use const TrasparenzaPa\TIPO_VOCE;
use const TrasparenzaPa\VERSIONE;

/**
 * Il tipo, i permessi, la pubblicazione chiusa.
 */
class VoceTest extends WP_UnitTestCase {

	use AmbienteTrasparenza;

	/**
	 * Amministratore delle prove.
	 *
	 * @var int
	 */
	private $amministratore;

	/**
	 * Sezione e tipo registrati, permessi assegnati, amministratore corrente.
	 *
	 * Altre prove azzerano le registrazioni del componente comune: qui si
	 * ricostruiscono, cosi l'esito non dipende dall'ordine delle prove.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! TipoVoce::registrato() ) {
			\Conformita_Core_Tipi::azzera();
			TipoVoce::azzera();

			if ( ! Avvio::registrata() ) {
				\Conformita_Core_Sezioni::azzera();
				Avvio::azzera();
				update_option( 'active_plugins', array( plugin_basename( \TrasparenzaPa\FILE_PRINCIPALE ) ) );
				Avvio::esegui();
			}

			$this->assertTrue( TipoVoce::registra() );
		}

		delete_option( OPZIONE_VERSIONE );
		$this->assertTrue( Installazione::aggiorna_se_serve(), 'L\'installazione deve assegnare i permessi.' );

		$this->amministratore = self::factory()->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $this->amministratore );
	}

	/**
	 * Una voce in bozza.
	 *
	 * @return int
	 */
	private function voce(): int {
		return (int) self::factory()->post->create(
			array(
				'post_type'   => TIPO_VOCE,
				'post_status' => 'draft',
				'post_title'  => 'Voce di prova',
			)
		);
	}

	/**
	 * T-38: il tipo passa dal componente comune, con le scelte dichiarate.
	 */
	public function test_t38_tipo_registrato_attraverso_il_componente_comune(): void {
		$this->assertTrue( conformita_core_tipo_registrato( TIPO_VOCE ) );
		$this->assertSame( SEZIONE, conformita_core_sezione_del_tipo( TIPO_VOCE ) );

		$tipo = get_post_type_object( TIPO_VOCE );

		$this->assertFalse( $tipo->show_in_rest, 'La voce non e esposta all\'interfaccia REST.' );
		$this->assertFalse( $tipo->public );
		$this->assertFalse( $tipo->publicly_queryable );
		$this->assertTrue( $tipo->show_ui );
		$this->assertTrue( (bool) $tipo->show_in_menu );
		$this->assertSame( 'Trasparenza', $tipo->labels->menu_name );
	}

	/**
	 * T-38: una richiesta anonima non trova la voce, nemmeno se fosse pubblicata.
	 */
	public function test_t38_voce_chiusa_al_pubblico(): void {
		global $wpdb;

		$voce = $this->voce();

		// Pubblicata scrivendo direttamente nella banca dati, aggirando la chiusura: il caso peggiore.
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $voce ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- aggira apposta la chiusura.
		clean_post_cache( $voce );

		// Fine della pubblicazione lontana: senza, il filtro di scadenza del componente comune la nasconderebbe comunque e la prova non proverebbe la chiusura.
		$this->assertTrue( conformita_core_imposta_fine_pubblicazione( $voce, '2099-12-31' ) );

		wp_set_current_user( 0 );

		// Come arriva una richiesta vera: parametri nell'indirizzo, letti da WordPress e non passati come variabili interne.
		foreach ( array(
			array(
				'post_type' => TIPO_VOCE,
				'p'         => (string) $voce,
			),
			array( 's' => 'Voce di prova' ),
		) as $parametri ) {
			$_GET      = $parametri;
			$richiesta = new \WP();

			$richiesta->public_query_vars  = $GLOBALS['wp']->public_query_vars;
			$richiesta->private_query_vars = $GLOBALS['wp']->private_query_vars;
			$richiesta->parse_request();

			$trovati = ( new \WP_Query( $richiesta->query_vars ) )->posts;

			$this->assertNotContains( $voce, wp_list_pluck( $trovati, 'ID' ), 'Richiesta anonima: ' . wp_json_encode( $parametri ) );
		}

		$_GET = array();
	}

	/**
	 * T-39: l'amministratore gestisce le voci, Autore e Redattore no.
	 */
	public function test_t39_permessi(): void {
		$this->assertSame( VERSIONE, Installazione::versione_installata() );

		$voce  = $this->voce();
		$mappa = Permessi::mappa();

		$this->assertIsArray( $mappa );
		$this->assertTrue( get_role( 'administrator' )->has_cap( $mappa['edit_posts'] ) );
		$this->assertTrue( user_can( $this->amministratore, 'edit_post', $voce ) );

		foreach ( array( 'author', 'editor', 'contributor' ) as $ruolo ) {
			$utente = self::factory()->user->create( array( 'role' => $ruolo ) );

			$this->assertFalse( user_can( $utente, 'edit_post', $voce ), "Il ruolo {$ruolo} non deve modificare le voci." );
			$this->assertFalse( user_can( $utente, $mappa['edit_posts'] ), "Il ruolo {$ruolo} non deve vedere le voci." );
		}

		// Con la versione gia memorizzata non si ripete.
		$this->assertFalse( Installazione::aggiorna_se_serve() );
	}

	/**
	 * T-39 [statico]: i nomi dei permessi derivati non sono scritti nei sorgenti.
	 */
	public function test_t39_nomi_derivati_fuori_dai_sorgenti(): void {
		$mappa    = Permessi::mappa();
		$derivati = array_diff( array_values( $mappa ), array_keys( $mappa ), array( 'read' ) );

		$this->assertNotEmpty( $derivati );

		$radice   = dirname( __DIR__ );
		$sorgenti = array_merge( array( $radice . '/trasparenza-pa.php' ), (array) glob( $radice . '/src/*.php' ) );

		foreach ( $sorgenti as $sorgente ) {
			$testo = (string) file_get_contents( $sorgente );

			foreach ( $derivati as $nome ) {
				$this->assertStringNotContainsString( $nome, $testo, "Il permesso {$nome} compare in " . basename( $sorgente ) );
			}
		}
	}

	/**
	 * Lo stato che dice l'ultima voce di cambio di stato del registro del componente comune.
	 *
	 * @param int $voce Voce.
	 * @return string|null
	 */
	private static function stato_nel_registro( int $voce ): ?string {
		$voci = conformita_core_voci_registro(
			array(
				'contenuto' => $voce,
				'ordine'    => 'decrescente',
			)
		);

		foreach ( is_array( $voci ) ? $voci : array() as $riga ) {
			if ( isset( $riga['dettagli']['stato_nuovo'] ) ) {
				return (string) $riga['dettagli']['stato_nuovo'];
			}
		}

		return null;
	}

	/**
	 * T-40: ogni strada verso la pubblicazione lascia la voce in bozza.
	 */
	public function test_t40_pubblicazione_chiusa(): void {
		$diretta = (int) wp_insert_post(
			array(
				'post_type'   => TIPO_VOCE,
				'post_status' => 'publish',
				'post_title'  => 'Pubblicata alla creazione',
			)
		);

		$this->assertSame( 'draft', get_post_status( $diretta ), 'Creazione gia pubblicata.' );
		$this->assertSame( 'draft', self::stato_nel_registro( $diretta ), 'Il registro del componente comune dice lo stesso stato: Creazione gia pubblicata.' );

		$aggiornata = $this->voce();

		wp_update_post(
			array(
				'ID'          => $aggiornata,
				'post_status' => 'publish',
			)
		);

		$this->assertSame( 'draft', get_post_status( $aggiornata ), 'Aggiornamento a pubblicata.' );
		$this->assertSame( 'draft', self::stato_nel_registro( $aggiornata ), 'Il registro del componente comune dice lo stesso stato: Aggiornamento a pubblicata.' );

		$programmata = (int) wp_insert_post(
			array(
				'post_type'   => TIPO_VOCE,
				'post_status' => 'future',
				'post_date'   => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
				'post_title'  => 'Programmata',
			)
		);

		$this->assertSame( 'draft', get_post_status( $programmata ), 'Pubblicazione programmata.' );
		$this->assertSame( 'draft', self::stato_nel_registro( $programmata ), 'Il registro del componente comune dice lo stesso stato: Pubblicazione programmata.' );

		$privata = (int) wp_insert_post(
			array(
				'post_type'   => TIPO_VOCE,
				'post_status' => 'private',
				'post_title'  => 'Privata',
			)
		);

		$this->assertSame( 'draft', get_post_status( $privata ), 'Pubblicazione privata.' );
		$this->assertSame( 'draft', self::stato_nel_registro( $privata ), 'Il registro del componente comune dice lo stesso stato: Pubblicazione privata.' );

		$da_codice = $this->voce();

		wp_publish_post( $da_codice );

		$this->assertSame( 'draft', get_post_status( $da_codice ), 'Pubblicazione diretta da codice.' );
		$this->assertSame( 'draft', get_post( $da_codice )->post_status, 'Lo stato riletto dalla banca dati.' );
		$this->assertSame( 'draft', self::stato_nel_registro( $da_codice ), 'Il registro del componente comune dice lo stesso stato: Lo stato riletto dalla banca dati.' );

		$avvisi = $this->avvisi_in_bacheca();

		$this->assertStringContainsString( esc_html( ChiusuraPubblicazione::motivo() ), $avvisi );
		$this->assertStringNotContainsString( esc_html( ChiusuraPubblicazione::motivo() ), $this->avvisi_in_bacheca(), 'Il motivo si mostra una volta.' );
	}

	/**
	 * T-40: chi ascolta il cambio di stato con priorita normale trova gia la bozza nella banca dati.
	 */
	public function test_t40_bozza_prima_degli_altri_ascoltatori(): void {
		global $wpdb;

		$voce  = $this->voce();
		$letti = array();

		$osservatore = static function ( $nuovo, $vecchio, $articolo ) use ( $voce, $wpdb, &$letti ) {
			unset( $vecchio );

			if ( 'publish' === $nuovo && $articolo->ID === $voce ) {
				$letti[] = $wpdb->get_var( $wpdb->prepare( "SELECT post_status FROM {$wpdb->posts} WHERE ID = %d", $voce ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- legge apposta la banca dati, non la cache.
			}
		};

		add_action( 'transition_post_status', $osservatore, 10, 3 );
		wp_publish_post( $voce );
		remove_action( 'transition_post_status', $osservatore, 10 );

		$this->assertSame( array( 'draft' ), $letti );
	}

	/**
	 * T-40: gli altri tipi di contenuto non sono toccati.
	 */
	public function test_t40_altri_tipi_liberi(): void {
		$articolo = (int) wp_insert_post(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Articolo',
			)
		);

		$this->assertSame( 'publish', get_post_status( $articolo ) );
	}
}
