<?php
/**
 * Prove del passaggio in bozza dalle pagine del sito: righe T-129..T-134.
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
use TrasparenzaPa\SchedaContenuto;
use TrasparenzaPa\SchedaPubblicazione;
use TrasparenzaPa\SchermataImportazione;
use WP_UnitTestCase;

use const TrasparenzaPa\TIPO_CONTENUTO;

/**
 * Dalle pagine scelte nascono contenuti in bozza, una volta sola, con la loro traccia.
 */
class ImportazioneBozzeTest extends WP_UnitTestCase {

	/**
	 * Amministratore, con il permesso della sezione e quello dei contenuti.
	 *
	 * @var int
	 */
	private $amministratore;

	/**
	 * Pagina d'ingresso.
	 *
	 * @var int
	 */
	private $radice;

	/**
	 * Pagine sotto la pagina d'ingresso, per nome.
	 *
	 * @var array<string, int>
	 */
	private $pagine = array();

	/**
	 * Sezione registrata, albero di prova, pagine.
	 */
	public function set_up(): void {
		parent::set_up();

		\Conformita_Core_Sezioni::azzera();
		Avvio::azzera();
		Avvio::esegui();

		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-importazione.json' );
		PaginaPubblica::da_init();
		$this->assertInstanceOf( Alberatura::class, PaginaPubblica::albero() );

		$this->amministratore = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->amministratore );
		$this->assertTrue( current_user_can( Permessi::AMMINISTRAZIONE ) );
		$this->assertTrue( current_user_can( get_post_type_object( TIPO_CONTENUTO )->cap->create_posts ) );

		$this->radice = $this->pagina( 'Amministrazione trasparente' );

		foreach ( array( 'Bandi di concorso', 'Organizzazione', 'Varie' ) as $ordine => $titolo ) {
			$this->pagine[ $titolo ] = $this->pagina( $titolo, $this->radice, array( 'menu_order' => $ordine ) );
		}
	}

	/**
	 * Rimette albero e blocco.
	 */
	public function tear_down(): void {
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
	 * @param array  $altro  Altri campi.
	 * @return int
	 */
	private function pagina( string $titolo, int $padre = 0, array $altro = array() ): int {
		return (int) self::factory()->post->create(
			array_merge(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $titolo,
					'post_parent'  => $padre,
					'post_content' => '<p>Testo della pagina ' . $titolo . '</p>',
				),
				$altro
			)
		);
	}

	/**
	 * La richiesta del modulo.
	 *
	 * @param array<int|string, mixed> $voci   Voce per pagina.
	 * @param int|string|null          $radice Pagina d'ingresso.
	 * @return array<string, mixed>
	 */
	private function richiesta( array $voci, $radice = null ): array {
		return array(
			'_wpnonce'                          => wp_create_nonce( SchermataImportazione::AZIONE_BOZZE ),
			SchermataImportazione::CAMPO_RADICE => (string) ( $radice ?? $this->radice ),
			SchermataImportazione::CAMPO_VOCI   => $voci,
		);
	}

	/**
	 * I contenuti della sezione, di ogni stato, cestino compreso.
	 *
	 * @return array<int, \WP_Post>
	 */
	private function contenuti(): array {
		return get_posts(
			array(
				'post_type'      => TIPO_CONTENUTO,
				'post_status'    => array( 'draft', 'pending', 'private', 'future', 'publish', 'trash' ),
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
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
	 * Fa fallire le scritture nel registro di core.
	 */
	private function registro_che_rifiuta(): void {
		global $wpdb;

		$tabella = $wpdb->prefix . 'conformita_core_registro';

		add_filter(
			'query',
			static function ( $sql ) use ( $tabella ) {
				return 0 === strpos( ltrim( (string) $sql ), 'INSERT INTO `' . $tabella . '`' ) ? 'INSERT INTO `' . $tabella . '_inesistente` VALUES (1)' : $sql;
			}
		);
		$wpdb->suppress_errors( true );
	}

	/**
	 * T-129. Chi fa il passaggio e cosa accetta.
	 */
	public function test_t129_permessi_nonce_e_righe_non_valide() {
		$bandi = $this->pagine['Bandi di concorso'];

		// Senza permesso della sezione, senza permesso di creare contenuti, senza nonce: niente creato.
		$abbonato = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $abbonato );
		$this->assertSame( 'permesso', SchermataImportazione::salva( $this->richiesta( array( $bandi => 'bandi' ) ) )['codice'] );

		$solo_sezione = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		get_user_by( 'id', $solo_sezione )->add_cap( Permessi::AMMINISTRAZIONE );
		wp_set_current_user( $solo_sezione );
		$this->assertSame( 'permesso', SchermataImportazione::salva( $this->richiesta( array( $bandi => 'bandi' ) ) )['codice'] );

		$solo_contenuti = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$tipo           = get_post_type_object( TIPO_CONTENUTO );
		get_user_by( 'id', $solo_contenuti )->add_cap( $tipo->cap->create_posts );
		get_user_by( 'id', $solo_contenuti )->add_cap( $tipo->cap->edit_posts );
		wp_set_current_user( $solo_contenuti );
		$this->assertTrue( current_user_can( $tipo->cap->create_posts ) );
		$this->assertSame( 'permesso', SchermataImportazione::salva( $this->richiesta( array( $bandi => 'bandi' ) ) )['codice'] );

		wp_set_current_user( $this->amministratore );

		foreach ( array( null, 'sbagliato', array( 'x' ) ) as $nonce ) {
			$richiesta = $this->richiesta( array( $bandi => 'bandi' ) );

			if ( null === $nonce ) {
				unset( $richiesta['_wpnonce'] );
			} else {
				$richiesta['_wpnonce'] = $nonce;
			}

			$this->assertSame( 'nonce', SchermataImportazione::salva( $richiesta )['codice'] );
		}

		// Il nonce del rapporto non vale per il passaggio.
		$richiesta             = $this->richiesta( array( $bandi => 'bandi' ) );
		$richiesta['_wpnonce'] = wp_create_nonce( SchermataImportazione::AZIONE );
		$this->assertSame( 'nonce', SchermataImportazione::salva( $richiesta )['codice'] );

		foreach ( array( 'abc', '0', $this->radice . "\n", (string) $this->pagine['Varie'] . 'x' ) as $radice ) {
			$this->assertSame( 'radice', SchermataImportazione::salva( $this->richiesta( array( $bandi => 'bandi' ), $radice ) )['codice'], wp_json_encode( $radice ) );
		}

		$this->assertSame( 'dati', SchermataImportazione::salva( array_merge( $this->richiesta( array() ), array( SchermataImportazione::CAMPO_VOCI => 'bandi' ) ) )['codice'] );
		$this->assertSame( array(), $this->contenuti() );

		// Righe non valide: scartate con il motivo, le altre portate.
		$bozza    = $this->pagina( 'Bozza', $this->radice, array( 'post_status' => 'draft' ) );
		$protetta = $this->pagina( 'Protetta', $this->radice, array( 'post_password' => 'x' ) );
		$fuori    = $this->pagina( 'Fuori' );

		$esito = SchermataImportazione::salva(
			$this->richiesta(
				array(
					$bandi                          => 'bandi',
					$this->pagine['Organizzazione'] => 'organizzazione',
					$this->pagine['Varie']          => 'radice',
					$this->radice                   => 'inesistente',
					$bozza                          => 'telefono',
					$protetta                       => 'telefono',
					$fuori                          => 'telefono',
					999999999                       => 'telefono',
					'x' . $bandi                    => 'telefono',
				)
			)
		);

		$this->assertSame( 'portato', $esito['codice'] );
		$this->assertSame( 'portata', $this->esiti( $esito )[ $bandi ] );

		foreach ( array( $this->pagine['Organizzazione'], $this->pagine['Varie'], $this->radice, $bozza, $protetta, $fuori, 999999999 ) as $scartata ) {
			$this->assertSame( 'scartata', $this->esiti( $esito )[ $scartata ] ?? null, (string) $scartata );
		}

		$this->assertCount( 1, $this->contenuti() );

		// Pagina scritta con uno zero davanti: non è la stessa pagina.
		$esito = SchermataImportazione::salva( $this->richiesta( array( '0' . $this->pagine['Varie'] => 'atti_altri' ) ) );
		$this->assertSame( array( 'scartata' ), array_column( $esito['righe'], 'esito' ) );
		$this->assertCount( 1, $this->contenuti() );

		// Voce non testo.
		$esito = SchermataImportazione::salva( $this->richiesta( array( $this->pagine['Varie'] => array( 'telefono' ) ) ) );
		$this->assertSame( 'scartata', $this->esiti( $esito )[ $this->pagine['Varie'] ] );
		$this->assertCount( 1, $this->contenuti() );
	}

	/**
	 * T-130. I contenuti creati.
	 */
	public function test_t130_contenuti_in_bozza() {
		$esito = SchermataImportazione::salva(
			$this->richiesta(
				array(
					$this->pagine['Bandi di concorso'] => 'bandi',
					$this->pagine['Varie']             => 'atti_altri',
					$this->pagine['Organizzazione']    => '',
				)
			)
		);

		$this->assertSame( 'portato', $esito['codice'] );
		$contenuti = $this->contenuti();
		$this->assertCount( 2, $contenuti );

		$attesi = array(
			array( 'Bandi di concorso', 'bandi', $this->pagine['Bandi di concorso'] ),
			array( 'Varie', 'atti_altri', $this->pagine['Varie'] ),
		);

		foreach ( $contenuti as $i => $contenuto ) {
			$this->assertSame( 'draft', $contenuto->post_status );
			$this->assertSame( $attesi[ $i ][0], $contenuto->post_title );
			$this->assertSame( '', $contenuto->post_content );
			$this->assertSame( $this->amministratore, (int) $contenuto->post_author );
			$this->assertSame( array( $attesi[ $i ][1] ), get_post_meta( $contenuto->ID, SchedaContenuto::META_COLLOCAZIONE, false ) );
			$this->assertSame( array( (string) $attesi[ $i ][2] ), get_post_meta( $contenuto->ID, ImportazionePagine::META_ORIGINE, false ) );
			$this->assertSame( array(), get_post_meta( $contenuto->ID, SchedaPubblicazione::META_DECORRENZA, false ) );
			$this->assertSame( array(), get_children( array( 'post_parent' => $contenuto->ID ) ) );
		}

		$this->assertSame( 'portata', $this->esiti( $esito )[ $this->pagine['Bandi di concorso'] ] );
		$this->assertArrayNotHasKey( $this->pagine['Organizzazione'], $this->esiti( $esito ) );

		// Il contenuto non compare al pubblico.
		$html = PaginaPubblica::disegna( PaginaPubblica::albero(), 'bandi', 'https://example.org/sezione/' );
		$this->assertStringNotContainsString( 'Bandi di concorso</a>', $html );
		$this->assertStringNotContainsString( 'Testo della pagina', $html );
	}

	/**
	 * T-131. Una riga di registro per contenuto; registro che rifiuta.
	 */
	public function test_t131_registro() {
		$bandi = $this->pagine['Bandi di concorso'];
		$esito = SchermataImportazione::salva( $this->richiesta( array( $bandi => 'bandi' ) ) );
		$id    = $esito['righe'][0]['contenuto'];

		$righe = conformita_core_voci_registro(
			array(
				'contenuto' => $id,
				'azione'    => ImportazionePagine::AZIONE_REGISTRO,
			)
		);
		$this->assertIsArray( $righe );
		$this->assertCount( 1, $righe );
		$this->assertSame( $bandi, (int) $righe[0]['dettagli']['pagina'] );
		$this->assertSame( 'bandi', $righe[0]['dettagli']['voce'] );
		$this->assertSame( $this->amministratore, (int) $righe[0]['utente'] );

		$this->registro_che_rifiuta();
		$esito = SchermataImportazione::salva( $this->richiesta( array( $this->pagine['Varie'] => 'atti_altri' ) ) );

		$this->assertSame( 'errore', $this->esiti( $esito )[ $this->pagine['Varie'] ] );
		$this->assertCount( 1, $this->contenuti() );

		global $wpdb;
		$this->assertSame( '0', (string) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s", ImportazionePagine::META_ORIGINE, (string) $this->pagine['Varie'] ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * T-131. Metadati non scritti: il contenuto non resta.
	 */
	public function test_t131_metadati_non_scritti() {
		add_filter(
			'add_post_metadata',
			static function ( $esito, $id, $chiave ) {
				return ImportazionePagine::META_ORIGINE === $chiave ? false : $esito;
			},
			10,
			3
		);

		$esito = SchermataImportazione::salva( $this->richiesta( array( $this->pagine['Varie'] => 'atti_altri' ) ) );

		$this->assertSame( 'errore', $this->esiti( $esito )[ $this->pagine['Varie'] ] );
		$this->assertSame( array(), $this->contenuti() );
	}

	/**
	 * T-132. Pagine già portate: nessun doppione; il rapporto le mostra; guasti.
	 */
	public function test_t132_gia_portate() {
		global $wpdb;

		$bandi = $this->pagine['Bandi di concorso'];
		$primo = SchermataImportazione::salva( $this->richiesta( array( $bandi => 'bandi' ) ) );
		$id    = $primo['righe'][0]['contenuto'];

		$secondo = SchermataImportazione::salva( $this->richiesta( array( $bandi => 'atti_altri' ) ) );
		$this->assertSame( 'gia_portata', $this->esiti( $secondo )[ $bandi ] );
		$this->assertSame( $id, $secondo['righe'][0]['contenuto'] );
		$this->assertCount( 1, $this->contenuti() );

		$rapporto = ImportazionePagine::rapporto( $this->radice, PaginaPubblica::albero(), '2026-06-15' );
		$this->assertIsArray( $rapporto );
		$portate = array_column( $rapporto['pagine'], 'portata', 'id' );
		$this->assertSame( $id, $portate[ $bandi ] );
		$this->assertNull( $portate[ $this->pagine['Varie'] ] );

		// Dal cestino non si riporta.
		wp_trash_post( $id );
		$terzo = SchermataImportazione::salva( $this->richiesta( array( $bandi => 'bandi' ) ) );
		$this->assertSame( 'gia_portata', $this->esiti( $terzo )[ $bandi ] );
		$this->assertCount( 1, $this->contenuti() );

		// Lettura delle pagine già portate fallita: niente creato.
		$wpdb->suppress_errors( true );
		$filtro = static function ( $sql ) use ( $wpdb ) {
			return false !== strpos( (string) $sql, 'trasparenza-pa-importazione' ) && false !== strpos( (string) $sql, ImportazionePagine::META_ORIGINE )
				? 'SELECT ID FROM ' . $wpdb->posts . '_inesistente'
				: $sql;
		};
		add_filter( 'query', $filtro );

		try {
			$esito    = SchermataImportazione::salva( $this->richiesta( array( $this->pagine['Varie'] => 'atti_altri' ) ) );
			$rapporto = ImportazionePagine::rapporto( $this->radice, PaginaPubblica::albero(), '2026-06-15' );
		} finally {
			remove_filter( 'query', $filtro );
			$wpdb->suppress_errors( false );
		}

		$this->assertSame( 'lettura', $esito['codice'] );
		$this->assertInstanceOf( \WP_Error::class, $rapporto );
		$this->assertCount( 1, $this->contenuti() );

		// Blocco occupato: niente creato.
		list( $host, $porta, $socket ) = $wpdb->parse_db_host( DB_HOST );
		$altra                         = mysqli_init(); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_init -- serve una seconda connessione, che $wpdb non offre.
		$this->assertTrue( $altra->real_connect( (string) $host, DB_USER, DB_PASSWORD, DB_NAME, $porta ? (int) $porta : null, $socket ? (string) $socket : null ) );

		try {
			$this->assertSame( '1', (string) $altra->query( "SELECT GET_LOCK('" . $altra->real_escape_string( Blocco::nome() ) . "', 0)" )->fetch_row()[0] );
			$esito = SchermataImportazione::salva( $this->richiesta( array( $this->pagine['Varie'] => 'atti_altri' ) ) );
		} finally {
			$altra->close();
		}

		$this->assertSame( 'occupato', $esito['codice'] );
		$this->assertCount( 1, $this->contenuti() );
	}

	/**
	 * T-133. La schermata: modulo, preselezione, pagina già portata, esito, escaping.
	 */
	public function test_t133_schermata() {
		$codice = $this->pagina( 'Bandi <b>di</b> "concorso"', $this->radice, array( 'menu_order' => 9 ) );
		$get    = array(
			'page'                              => SchermataImportazione::PAGINA,
			SchermataImportazione::CAMPO_RADICE => (string) $this->radice,
			'_wpnonce'                          => wp_create_nonce( SchermataImportazione::AZIONE ),
		);

		$html = SchermataImportazione::html( $get );
		$this->assertStringContainsString( 'method="post"', $html );
		$this->assertStringContainsString( 'name="action" value="' . SchermataImportazione::AZIONE_BOZZE . '"', $html );
		$this->assertMatchesRegularExpression( '/name="_wpnonce" value="' . preg_quote( wp_create_nonce( SchermataImportazione::AZIONE_BOZZE ), '/' ) . '"/', $html );

		$bandi = $this->pagine['Bandi di concorso'];
		$this->assertMatchesRegularExpression( '/<select name="' . SchermataImportazione::CAMPO_VOCI . '\[' . $bandi . '\]"[^>]*>.*?<option value="bandi" selected=\'selected\'>/s', $html );
		$this->assertMatchesRegularExpression( '/<select name="' . SchermataImportazione::CAMPO_VOCI . '\[' . $this->pagine['Varie'] . '\]"[^>]*><option value="" selected=\'selected\'>/s', $html );
		$this->assertStringContainsString( 'Bandi &lt;b&gt;di&lt;/b&gt; &quot;concorso&quot;', $html );
		$this->assertStringNotContainsString( 'Bandi <b>di</b>', $html );
		$this->assertStringContainsString( 'name="' . SchermataImportazione::CAMPO_VOCI . '[' . $codice . ']"', $html );

		// Dopo il passaggio: esito una volta, pagina portata senza scelta.
		$esito = SchermataImportazione::salva( $this->richiesta( array( $bandi => 'bandi' ) ) );
		SchermataImportazione::conserva_esito( $esito );

		$html = SchermataImportazione::html( $get );
		$this->assertStringContainsString( 'Pagine portate in bozza: 1', $html );
		$this->assertStringNotContainsString( 'name="' . SchermataImportazione::CAMPO_VOCI . '[' . $bandi . ']"', $html );
		$this->assertStringContainsString( esc_url( get_edit_post_link( $esito['righe'][0]['contenuto'], 'raw' ) ), $html );
		$this->assertStringNotContainsString( 'Pagine portate in bozza: 1', SchermataImportazione::html( $get ) );

		// Chi ha solo il permesso della sezione vede il rapporto ma non il modulo.
		$solo_sezione = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		get_user_by( 'id', $solo_sezione )->add_cap( Permessi::AMMINISTRAZIONE );
		wp_set_current_user( $solo_sezione );
		$get['_wpnonce'] = wp_create_nonce( SchermataImportazione::AZIONE );
		$html            = SchermataImportazione::html( $get );
		$this->assertStringContainsString( 'trasparenza-pa-rapporto', $html );
		$this->assertStringNotContainsString( SchermataImportazione::AZIONE_BOZZE, $html );
	}

	/**
	 * T-134. Titoli con caratteri particolari e rientro delle pagine figlie.
	 */
	public function test_t134_titoli_e_rientro() {
		$titolo = 'Documenti A\\B "doppie" l\'apostrofo & C\\\\D';
		$id     = $this->pagina( wp_slash( $titolo ), $this->radice, array( 'menu_order' => 9 ) );
		$this->assertSame( $titolo, get_post( $id )->post_title );

		$esito = SchermataImportazione::salva( $this->richiesta( array( $id => 'bandi' ) ) );
		$this->assertSame( 'portato', $esito['codice'] );
		$contenuti = $this->contenuti();
		$this->assertCount( 1, $contenuti );
		$this->assertSame( $titolo, $contenuti[0]->post_title );
		$this->assertSame( $titolo, $esito['righe'][0]['titolo'] );

		$figlia = $this->pagina( 'Pagina figlia', $id );
		$html   = SchermataImportazione::html(
			array(
				'page'                              => SchermataImportazione::PAGINA,
				SchermataImportazione::CAMPO_RADICE => (string) $this->radice,
				'_wpnonce'                          => wp_create_nonce( SchermataImportazione::AZIONE ),
			)
		);
		$this->assertStringContainsString( 'Pagina figlia', $html );
		$this->assertStringNotContainsString( '&mdash;', $html );
		$this->assertStringNotContainsString( "\u{2014}", $html );
		$this->assertStringContainsString( '&nbsp;&nbsp;&nbsp;&nbsp;- <a href="' . esc_url( get_permalink( $figlia ) ) . '">Pagina figlia</a>', $html );
	}
}
