<?php
/**
 * Prove dei vecchi indirizzi delle pagine portate: righe T-143..T-147.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Alberatura;
use TrasparenzaPa\Avvio;
use TrasparenzaPa\Blocco;
use TrasparenzaPa\ImportazionePagine;
use TrasparenzaPa\PaginaPubblica;
use TrasparenzaPa\Permessi;
use TrasparenzaPa\RegistroAccessi;
use TrasparenzaPa\SchedaContenuto;
use TrasparenzaPa\SchermataImportazione;
use TrasparenzaPa\VecchiIndirizzi;
use WP_UnitTestCase;

use const TrasparenzaPa\TIPO_CONTENUTO;

/**
 * L'indirizzo di una pagina portata, quando la pagina non risponde piu, porta alla voce nuova.
 */
class VecchiIndirizziTest extends WP_UnitTestCase {

	/**
	 * Amministratore.
	 *
	 * @var int
	 */
	private $amministratore;

	/**
	 * Pagina d'ingresso della sezione vecchia.
	 *
	 * @var int
	 */
	private $radice;

	/**
	 * Pagina della sezione nuova, con il codice breve.
	 *
	 * @var int
	 */
	private $sezione;

	/**
	 * Pagine sotto la pagina d'ingresso, per nome.
	 *
	 * @var array<string, int>
	 */
	private $pagine = array();

	/**
	 * Contenuti portati, per nome della pagina.
	 *
	 * @var array<string, int>
	 */
	private $contenuti = array();

	/**
	 * Sezione registrata, albero di prova, indirizzi parlanti, pagine portate.
	 */
	public function set_up(): void {
		parent::set_up();

		\Conformita_Core_Sezioni::azzera();
		Avvio::azzera();
		Avvio::esegui();

		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-importazione.json' );
		PaginaPubblica::da_init();
		$this->assertInstanceOf( Alberatura::class, PaginaPubblica::albero() );

		$this->set_permalink_structure( '/%postname%/' );

		$this->amministratore = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->amministratore );

		$this->sezione = $this->pagina( 'Trasparenza', 0, '[' . PaginaPubblica::CODICE_BREVE . ']' );
		$this->radice  = $this->pagina( 'Amministrazione trasparente' );

		foreach ( array( 'Bandi di concorso', 'Organizzazione', 'Varie' ) as $ordine => $titolo ) {
			$this->pagine[ $titolo ] = $this->pagina( $titolo, $this->radice, 'Testo', $ordine );
		}

		$this->pagine['Telefono'] = $this->pagina( 'Telefono e posta elettronica', $this->pagine['Organizzazione'] );

		$righe = ImportazionePagine::porta(
			$this->radice,
			array(
				$this->pagine['Bandi di concorso'] => 'bandi',
				$this->pagine['Varie']             => 'atti_altri',
				$this->pagine['Telefono']          => 'telefono',
			),
			PaginaPubblica::albero(),
			RegistroAccessi::oggi()
		);
		$this->assertIsArray( $righe );

		foreach ( $righe as $riga ) {
			$this->assertSame( 'portata', $riga['esito'] );
			$this->contenuti[ (string) array_search( $riga['pagina'], $this->pagine, true ) ] = (int) $riga['contenuto'];
		}
	}

	/**
	 * Rimette albero, blocco e filtri.
	 */
	public function tear_down(): void {
		global $wpdb;

		remove_all_filters( 'query' );
		$wpdb->suppress_errors( false );
		Blocco::rilascia();
		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-finto.json' );
		PaginaPubblica::da_init();

		parent::tear_down();
	}

	/**
	 * Una pagina pubblicata.
	 *
	 * @param string $titolo Titolo.
	 * @param int    $padre  Genitore.
	 * @param string $testo  Testo.
	 * @param int    $ordine Ordine.
	 * @return int
	 */
	private function pagina( string $titolo, int $padre = 0, string $testo = 'Testo', int $ordine = 0 ): int {
		return (int) self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $titolo,
				'post_parent'  => $padre,
				'post_content' => $testo,
				'menu_order'   => $ordine,
			)
		);
	}

	/**
	 * La richiesta del modulo.
	 *
	 * @param mixed           $registra Pagine da registrare.
	 * @param mixed           $togli    Contenuti da cui togliere l'indirizzo.
	 * @param mixed           $sezione  Pagina della sezione.
	 * @param int|string|null $radice   Pagina d'ingresso.
	 * @return array<string, mixed>
	 */
	private function richiesta( $registra, $togli = array(), $sezione = null, $radice = null ): array {
		return array(
			'_wpnonce'                                   => wp_create_nonce( SchermataImportazione::AZIONE_INDIRIZZI ),
			SchermataImportazione::CAMPO_RADICE          => (string) ( $radice ?? $this->radice ),
			SchermataImportazione::CAMPO_SEZIONE         => $sezione ?? (string) $this->sezione,
			SchermataImportazione::CAMPO_REGISTRA        => $registra,
			SchermataImportazione::CAMPO_TOGLI_INDIRIZZO => $togli,
		);
	}

	/**
	 * Gli esiti delle righe, per pagina.
	 *
	 * @param array<string, mixed> $esito Esito.
	 * @return array<int, string>
	 */
	private function esiti( array $esito ): array {
		$righe = array();

		foreach ( $esito['righe'] ?? array() as $riga ) {
			$righe[ $riga['pagina'] ] = $riga['esito'];
		}

		return $righe;
	}

	/**
	 * L'indirizzo conservato su un contenuto.
	 *
	 * @param int $contenuto Contenuto.
	 * @return array<int, string>
	 */
	private function indirizzo( int $contenuto ): array {
		return get_post_meta( $contenuto, VecchiIndirizzi::META_INDIRIZZO, false );
	}

	/**
	 * Il rinvio per un indirizzo chiesto.
	 *
	 * @param string $percorso Percorso dopo l'indirizzo del sito.
	 * @return string|null
	 */
	private function rinvio( string $percorso ): ?string {
		$this->go_to( home_url( $percorso ) );

		return VecchiIndirizzi::rinvio();
	}

	/**
	 * L'indirizzo della voce nella pagina della sezione.
	 *
	 * @param string $voce Voce.
	 * @return string
	 */
	private function voce( string $voce ): string {
		return PaginaPubblica::indirizzo( get_permalink( $this->sezione ), $voce, PaginaPubblica::albero()->radice() );
	}

	/**
	 * T-143. Chi registra gli indirizzi e cosa accetta.
	 */
	public function test_t143_permessi_nonce_e_righe_non_valide() {
		$bandi  = $this->pagine['Bandi di concorso'];
		$valida = array( (string) $bandi );

		$solo_sezione = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		get_user_by( 'id', $solo_sezione )->add_cap( Permessi::AMMINISTRAZIONE );
		wp_set_current_user( $solo_sezione );
		$this->assertSame( 'permesso', SchermataImportazione::salva_indirizzi( $this->richiesta( $valida ) )['codice'] );

		$solo_contenuti = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		get_user_by( 'id', $solo_contenuti )->add_cap( get_post_type_object( TIPO_CONTENUTO )->cap->create_posts );
		wp_set_current_user( $solo_contenuti );
		$this->assertSame( 'permesso', SchermataImportazione::salva_indirizzi( $this->richiesta( $valida ) )['codice'] );

		wp_set_current_user( $this->amministratore );

		$richiesta = $this->richiesta( $valida );
		unset( $richiesta['_wpnonce'] );
		$this->assertSame( 'nonce', SchermataImportazione::salva_indirizzi( $richiesta )['codice'] );
		$richiesta['_wpnonce'] = wp_create_nonce( SchermataImportazione::AZIONE_FILE );
		$this->assertSame( 'nonce', SchermataImportazione::salva_indirizzi( $richiesta )['codice'] );

		$this->assertSame( 'radice', SchermataImportazione::salva_indirizzi( $this->richiesta( $valida, array(), null, 'x' ) )['codice'] );
		$this->assertSame( 'dati', SchermataImportazione::salva_indirizzi( $this->richiesta( 'tutte' ) )['codice'] );
		$this->assertSame( 'dati', SchermataImportazione::salva_indirizzi( $this->richiesta( $valida, 'tutti' ) )['codice'] );

		// Pagina della sezione non valida: inesistente, senza codice breve, non pubblicata, non una pagina.
		$bozza    = (int) self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_content' => '[' . PaginaPubblica::CODICE_BREVE . ']',
			)
		);
		$articolo = (int) self::factory()->post->create( array( 'post_content' => '[' . PaginaPubblica::CODICE_BREVE . ']' ) );

		foreach ( array( '999999', (string) $this->radice, (string) $bozza, (string) $articolo, '0' . $this->sezione, array( (string) $this->sezione ) ) as $sezione ) {
			$this->assertSame( 'sezione', SchermataImportazione::salva_indirizzi( $this->richiesta( $valida, array(), $sezione ) )['codice'], wp_json_encode( $sezione ) );
		}

		$this->assertSame( array(), $this->indirizzo( $this->contenuti['Bandi di concorso'] ), 'Niente registrato fino a qui.' );
		$this->assertFalse( get_option( VecchiIndirizzi::OPZIONE_SEZIONE ) );

		// Righe non valide accanto a una valida.
		wp_trash_post( $this->contenuti['Varie'] );
		$esito = SchermataImportazione::salva_indirizzi(
			$this->richiesta(
				array(
					(string) $bandi,
					(string) $this->pagine['Organizzazione'],
					(string) $this->pagine['Varie'],
					'999999',
					'0' . $this->pagine['Telefono'],
					array( 'x' ),
				)
			)
		);
		$this->assertSame( 'registrato', $esito['codice'] );
		$esiti = $this->esiti( $esito );
		$this->assertSame( 'registrato', $esiti[ $bandi ] );
		$this->assertSame( 'scartato', $esiti[ $this->pagine['Organizzazione'] ] );
		$this->assertSame( 'scartato', $esiti[ $this->pagine['Varie'] ] );
		$this->assertSame( 'scartato', $esiti[999999] );
		$this->assertSame( 'scartato', $esiti[0] );
		$this->assertSame( array(), $this->indirizzo( $this->contenuti['Telefono'] ) );
		$this->assertSame( array(), $this->indirizzo( $this->contenuti['Varie'] ) );

		foreach ( $esito['righe'] as $riga ) {
			if ( 'scartato' === $riga['esito'] ) {
				$this->assertNotSame( '', $riga['motivo'] );
			}
		}

		// La pagina della sezione non registra sé stessa, anche se fosse portata.
		$this->assertSame( (string) $this->sezione, (string) get_option( VecchiIndirizzi::OPZIONE_SEZIONE ) );
		$esito = SchermataImportazione::salva_indirizzi( $this->richiesta( array( (string) $this->pagine['Telefono'] ), array(), (string) $this->pagine['Telefono'] ) );
		$this->assertSame( 'sezione', $esito['codice'], 'La pagina Telefono non ha il codice breve.' );
		wp_update_post(
			array(
				'ID'           => $this->pagine['Telefono'],
				'post_content' => '[' . PaginaPubblica::CODICE_BREVE . ']',
			)
		);
		$esito = SchermataImportazione::salva_indirizzi( $this->richiesta( array( (string) $this->pagine['Telefono'] ), array(), (string) $this->pagine['Telefono'] ) );
		$this->assertSame( 'scartato', $this->esiti( $esito )[ $this->pagine['Telefono'] ] );
		$this->assertSame( array(), $this->indirizzo( $this->contenuti['Telefono'] ) );
	}

	/**
	 * T-144. Indirizzi registrati e tolti; doppioni; guasti.
	 */
	public function test_t144_registrati_e_tolti() {
		global $wpdb;

		$telefono = $this->pagine['Telefono'];
		$esito    = SchermataImportazione::salva_indirizzi( $this->richiesta( array( (string) $telefono, (string) $this->pagine['Bandi di concorso'] ) ) );
		$this->assertSame( 'registrato', $this->esiti( $esito )[ $telefono ] );
		$this->assertStringContainsString( 'Indirizzi registrati: 2', $esito['messaggio'] );
		$this->assertSame( array( 'amministrazione-trasparente/organizzazione/telefono-e-posta-elettronica' ), $this->indirizzo( $this->contenuti['Telefono'] ) );
		$this->assertSame( array( 'amministrazione-trasparente/bandi-di-concorso' ), $this->indirizzo( $this->contenuti['Bandi di concorso'] ) );

		$righe = conformita_core_voci_registro(
			array(
				'contenuto' => $this->contenuti['Telefono'],
				'azione'    => VecchiIndirizzi::AZIONE_REGISTRO,
			)
		);
		$this->assertCount( 1, $righe );
		$this->assertSame( 'registrato', $righe[0]['dettagli']['operazione'] );
		$this->assertSame( $telefono, (int) $righe[0]['dettagli']['pagina'] );
		$this->assertSame( 'amministrazione-trasparente/organizzazione/telefono-e-posta-elettronica', $righe[0]['dettagli']['indirizzo'] );

		// Secondo passaggio: già registrato, nessun secondo metadato.
		$esito = SchermataImportazione::salva_indirizzi( $this->richiesta( array( (string) $telefono ) ) );
		$this->assertSame( 'gia_registrato', $this->esiti( $esito )[ $telefono ] );
		$this->assertCount( 1, $this->indirizzo( $this->contenuti['Telefono'] ) );

		// Lo stesso indirizzo su un secondo contenuto: scartato. La pagina Varie prende il percorso di Bandi.
		delete_post_meta( $this->contenuti['Bandi di concorso'], VecchiIndirizzi::META_INDIRIZZO );
		add_post_meta( $this->contenuti['Varie'], VecchiIndirizzi::META_INDIRIZZO, 'amministrazione-trasparente/bandi-di-concorso' );
		$esito = SchermataImportazione::salva_indirizzi( $this->richiesta( array( (string) $this->pagine['Bandi di concorso'] ) ) );
		$this->assertSame( 'scartato', $this->esiti( $esito )[ $this->pagine['Bandi di concorso'] ] );
		$this->assertSame( array(), $this->indirizzo( $this->contenuti['Bandi di concorso'] ) );
		delete_post_meta( $this->contenuti['Varie'], VecchiIndirizzi::META_INDIRIZZO );

		// Rimozione: solo da un contenuto che lo ha.
		$esito = SchermataImportazione::salva_indirizzi( $this->richiesta( array(), array( (string) $this->contenuti['Telefono'], (string) $this->contenuti['Varie'], 'x' ) ) );
		$esiti = array_column( $esito['righe'], 'esito', 'contenuto' );
		$this->assertSame( 'tolto', $esiti[ $this->contenuti['Telefono'] ] );
		$this->assertSame( 'scartato', $esiti[ $this->contenuti['Varie'] ] );
		$this->assertSame( 'scartato', $esiti[0] );
		$this->assertSame( array(), $this->indirizzo( $this->contenuti['Telefono'] ) );
		$righe = conformita_core_voci_registro(
			array(
				'contenuto' => $this->contenuti['Telefono'],
				'azione'    => VecchiIndirizzi::AZIONE_REGISTRO,
			)
		);
		$this->assertCount( 2, $righe );
		$this->assertSame( 'tolto', end( $righe )['dettagli']['operazione'] );

		// Registro che rifiuta: niente scritto, niente tolto.
		SchermataImportazione::salva_indirizzi( $this->richiesta( array( (string) $this->pagine['Varie'] ) ) );
		$this->assertCount( 1, $this->indirizzo( $this->contenuti['Varie'] ) );
		$tabella = $wpdb->prefix . 'conformita_core_registro';
		add_filter(
			'query',
			static function ( $sql ) use ( $tabella ) {
				return 0 === strpos( ltrim( (string) $sql ), 'INSERT INTO `' . $tabella . '`' ) ? 'INSERT INTO `' . $tabella . '_inesistente` VALUES (1)' : $sql;
			}
		);
		$wpdb->suppress_errors( true );
		$esito = SchermataImportazione::salva_indirizzi( $this->richiesta( array( (string) $telefono ), array( (string) $this->contenuti['Varie'] ) ) );
		remove_all_filters( 'query' );
		$wpdb->suppress_errors( false );
		$this->assertSame( 'errore', $this->esiti( $esito )[ $telefono ] );
		$this->assertSame( 'errore', array_column( $esito['righe'], 'esito', 'contenuto' )[ $this->contenuti['Varie'] ] );
		$this->assertSame( array(), $this->indirizzo( $this->contenuti['Telefono'] ) );
		$this->assertCount( 1, $this->indirizzo( $this->contenuti['Varie'] ) );

		// Blocco occupato: niente registrato.
		list( $host, $porta, $socket ) = $wpdb->parse_db_host( DB_HOST );
		$altra                         = mysqli_init(); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_init -- serve una seconda connessione, che $wpdb non offre.
		$this->assertTrue( $altra->real_connect( (string) $host, DB_USER, DB_PASSWORD, DB_NAME, $porta ? (int) $porta : null, $socket ? (string) $socket : null ) );

		try {
			$this->assertSame( '1', (string) $altra->query( "SELECT GET_LOCK('" . $altra->real_escape_string( Blocco::nome() ) . "', 0)" )->fetch_row()[0] );
			$esito = SchermataImportazione::salva_indirizzi( $this->richiesta( array( (string) $telefono ) ) );
		} finally {
			$altra->close();
		}

		$this->assertSame( 'occupato', $esito['codice'] );
		$this->assertSame( array(), $this->indirizzo( $this->contenuti['Telefono'] ) );
	}

	/**
	 * T-145. Il rinvio, e quando non c'e.
	 */
	public function test_t145_rinvio() {
		global $wpdb;

		SchermataImportazione::salva_indirizzi( $this->richiesta( array( (string) $this->pagine['Bandi di concorso'], (string) $this->pagine['Telefono'], (string) $this->pagine['Varie'] ) ) );

		// Pagina vecchia ancora pubblicata: nessun rinvio.
		$this->assertNull( $this->rinvio( '/amministrazione-trasparente/bandi-di-concorso/' ) );
		$this->assertFalse( is_404() );

		wp_trash_post( $this->pagine['Bandi di concorso'] );
		wp_delete_post( $this->pagine['Telefono'], true );

		$this->assertSame( $this->voce( 'bandi' ), $this->rinvio( '/amministrazione-trasparente/bandi-di-concorso/' ) );
		$this->assertTrue( is_404() );
		$this->assertSame( $this->voce( 'bandi' ), $this->rinvio( '/amministrazione-trasparente/bandi-di-concorso' ) );
		$this->assertSame( $this->voce( 'bandi' ), $this->rinvio( '/Amministrazione-Trasparente/Bandi-di-concorso/?utm=x' ) );
		$this->assertSame( $this->voce( 'telefono' ), $this->rinvio( '/amministrazione-trasparente/organizzazione/telefono-e-posta-elettronica/' ) );
		$this->assertNull( $this->rinvio( '/amministrazione-trasparente/bandi/' ), 'Un indirizzo mai registrato.' );

		// Contenuto spostato in un'altra voce: il rinvio lo segue.
		update_post_meta( $this->contenuti['Bandi di concorso'], SchedaContenuto::META_COLLOCAZIONE, 'atti_altri' );
		$this->assertSame( $this->voce( 'atti_altri' ), $this->rinvio( '/amministrazione-trasparente/bandi-di-concorso/' ) );

		// Voce che non c'e piu: la pagina della sezione.
		update_post_meta( $this->contenuti['Bandi di concorso'], SchedaContenuto::META_COLLOCAZIONE, 'voce_tolta' );
		$this->assertSame( get_permalink( $this->sezione ), $this->rinvio( '/amministrazione-trasparente/bandi-di-concorso/' ) );
		update_post_meta( $this->contenuti['Bandi di concorso'], SchedaContenuto::META_COLLOCAZIONE, 'bandi' );

		// Lettura fallita: nessun rinvio.
		$wpdb->suppress_errors( true );
		add_filter(
			'query',
			static function ( $sql ) use ( $wpdb ) {
				return false !== strpos( (string) $sql, VecchiIndirizzi::META_INDIRIZZO ) ? 'SELECT ID FROM ' . $wpdb->posts . '_inesistente' : $sql;
			}
		);
		$this->assertNull( $this->rinvio( '/amministrazione-trasparente/bandi-di-concorso/' ) );
		remove_all_filters( 'query' );
		$wpdb->suppress_errors( false );

		// Contenuto nel cestino: nessun rinvio.
		wp_trash_post( $this->contenuti['Bandi di concorso'] );
		$this->assertNull( $this->rinvio( '/amministrazione-trasparente/bandi-di-concorso/' ) );
		wp_untrash_post( $this->contenuti['Bandi di concorso'] );
		$this->assertSame( $this->voce( 'bandi' ), $this->rinvio( '/amministrazione-trasparente/bandi-di-concorso/' ) );

		// Destinazione uguale all'indirizzo chiesto: nessun rinvio.
		$sezione = $this->pagina( 'Sezione nuova', 0, '[' . PaginaPubblica::CODICE_BREVE . ']' );
		update_option( VecchiIndirizzi::OPZIONE_SEZIONE, (string) $sezione );
		update_post_meta( $this->contenuti['Varie'], SchedaContenuto::META_COLLOCAZIONE, 'radice' );
		update_post_meta( $this->contenuti['Varie'], VecchiIndirizzi::META_INDIRIZZO, 'sezione-nuova' );
		$this->assertSame( 'sezione-nuova', VecchiIndirizzi::percorso( (string) get_permalink( $sezione ) ) );
		$this->assertNull( VecchiIndirizzi::destinazione( 'sezione-nuova' ) );
		$this->assertSame( add_query_arg( PaginaPubblica::PARAMETRO_VOCE, 'bandi', get_permalink( $sezione ) ), VecchiIndirizzi::destinazione( 'amministrazione-trasparente/bandi-di-concorso' ) );

		// Pagina della sezione non pubblicata: nessun rinvio.
		wp_update_post(
			array(
				'ID'          => $sezione,
				'post_status' => 'draft',
			)
		);
		$this->assertNull( $this->rinvio( '/amministrazione-trasparente/bandi-di-concorso/' ) );

		// Pagina della sezione non più valida: nessun rinvio.
		update_option( VecchiIndirizzi::OPZIONE_SEZIONE, (string) $this->sezione );
		wp_update_post(
			array(
				'ID'           => $this->sezione,
				'post_content' => 'Testo senza codice breve',
			)
		);
		$this->assertNull( $this->rinvio( '/amministrazione-trasparente/bandi-di-concorso/' ) );
	}

	/**
	 * T-146. Il rinvio arriva prima delle ipotesi di WordPress.
	 */
	public function test_t146_priorita() {
		$priorita = has_action( 'template_redirect', array( VecchiIndirizzi::class, 'da_template_redirect' ) );
		$this->assertIsInt( $priorita );
		$this->assertLessThan( has_action( 'template_redirect', 'redirect_canonical' ), $priorita );
		$this->assertLessThan( has_action( 'template_redirect', 'wp_old_slug_redirect' ), $priorita );
	}

	/**
	 * T-147. La schermata.
	 */
	public function test_t147_schermata() {
		wp_update_post(
			array(
				'ID'         => $this->pagine['Varie'],
				'post_title' => 'Varie <b>e</b> "altro"',
			)
		);
		$get = array(
			'page'                              => SchermataImportazione::PAGINA,
			SchermataImportazione::CAMPO_RADICE => (string) $this->radice,
			'_wpnonce'                          => wp_create_nonce( SchermataImportazione::AZIONE ),
		);

		$this->assertSame( 10, has_action( 'admin_post_' . SchermataImportazione::AZIONE_INDIRIZZI, array( SchermataImportazione::class, 'da_admin_post' ) ) );

		$html = SchermataImportazione::html( $get );
		$this->assertStringContainsString( 'name="action" value="' . SchermataImportazione::AZIONE_INDIRIZZI . '"', $html );
		$this->assertMatchesRegularExpression( '/name="_wpnonce" value="' . preg_quote( wp_create_nonce( SchermataImportazione::AZIONE_INDIRIZZI ), '/' ) . '"/', $html );
		$this->assertMatchesRegularExpression( '/<select name="' . SchermataImportazione::CAMPO_SEZIONE . '"[^>]*>.*<option value="' . $this->sezione . '"/s', $html );
		$this->assertDoesNotMatchRegularExpression( '/<select name="' . SchermataImportazione::CAMPO_SEZIONE . '"[^>]*>(?:(?!<\/select>).)*<option value="' . $this->radice . '"/s', $html, 'Solo pagine con il codice breve.' );
		$this->assertStringContainsString( '<input type="checkbox" name="' . SchermataImportazione::CAMPO_REGISTRA . '[]" value="' . $this->pagine['Bandi di concorso'] . '" checked>', $html );
		$this->assertStringContainsString( 'amministrazione-trasparente/bandi-di-concorso', $html );
		$this->assertStringNotContainsString( 'value="' . $this->pagine['Organizzazione'] . '"', $html, 'Pagina non portata.' );
		$this->assertStringContainsString( 'Varie &lt;b&gt;e&lt;/b&gt; &quot;altro&quot;', $html );
		$this->assertStringNotContainsString( 'Varie <b>e</b>', $html );

		// Dopo la registrazione: esito una volta; indirizzo registrato con "togli".
		$esito = SchermataImportazione::salva_indirizzi( $this->richiesta( array( (string) $this->pagine['Bandi di concorso'] ) ) );
		SchermataImportazione::conserva_esito( $esito );
		$html = SchermataImportazione::html( $get );
		$this->assertStringContainsString( 'Indirizzi registrati: 1', $html );
		$this->assertStringNotContainsString( 'Indirizzi registrati: 1', SchermataImportazione::html( $get ) );
		$this->assertStringNotContainsString( 'name="' . SchermataImportazione::CAMPO_REGISTRA . '[]" value="' . $this->pagine['Bandi di concorso'] . '"', $html );
		$this->assertStringContainsString( '<input type="checkbox" name="' . SchermataImportazione::CAMPO_TOGLI_INDIRIZZO . '[]" value="' . $this->contenuti['Bandi di concorso'] . '">', $html );
		$this->assertMatchesRegularExpression( '/<option value="' . $this->sezione . '" selected/', $html );

		// Indirizzo registrato di una pagina non più pubblicata: resta fra i registrati.
		wp_trash_post( $this->pagine['Bandi di concorso'] );
		$this->assertStringContainsString( 'name="' . SchermataImportazione::CAMPO_TOGLI_INDIRIZZO . '[]" value="' . $this->contenuti['Bandi di concorso'] . '"', SchermataImportazione::html( $get ) );

		// Pagina della sezione non più valida: la schermata lo dice.
		wp_update_post(
			array(
				'ID'           => $this->sezione,
				'post_content' => 'Testo',
			)
		);
		$this->assertStringContainsString( 'nessun vecchio indirizzo porta alla sezione', SchermataImportazione::html( $get ) );

		// Chi non può creare contenuti non vede il modulo.
		$solo_sezione = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		get_user_by( 'id', $solo_sezione )->add_cap( Permessi::AMMINISTRAZIONE );
		wp_set_current_user( $solo_sezione );
		$get['_wpnonce'] = wp_create_nonce( SchermataImportazione::AZIONE );
		$this->assertStringNotContainsString( SchermataImportazione::AZIONE_INDIRIZZI, SchermataImportazione::html( $get ) );
	}
}
