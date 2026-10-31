<?php
/**
 * Prove del rapporto di importazione dalle pagine del sito: righe T-121..T-126.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Alberatura;
use TrasparenzaPa\Avvio;
use TrasparenzaPa\ImportazionePagine;
use TrasparenzaPa\PaginaPubblica;
use TrasparenzaPa\Permessi;
use TrasparenzaPa\SchermataImportazione;
use WP_UnitTestCase;

/**
 * Cosa mostra il rapporto che precede il passaggio, e che non scrive niente.
 */
class ImportazionePagineTest extends WP_UnitTestCase {

	/**
	 * Chi amministra la sezione.
	 *
	 * @var int
	 */
	private $responsabile;

	/**
	 * File e collegamenti creati dalla prova, da togliere alla fine.
	 *
	 * @var array<int, string>
	 */
	private $creati = array();

	/**
	 * Sezione registrata, albero di prova con la durata, utente con il permesso della sezione.
	 */
	public function set_up(): void {
		parent::set_up();

		\Conformita_Core_Sezioni::azzera();
		Avvio::azzera();
		Avvio::esegui();

		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-importazione.json' );
		PaginaPubblica::da_init();
		$this->assertInstanceOf( Alberatura::class, PaginaPubblica::albero() );

		get_role( 'administrator' )->remove_cap( Permessi::AMMINISTRAZIONE );
		$this->responsabile = self::factory()->user->create( array( 'role' => 'editor' ) );
		get_user_by( 'id', $this->responsabile )->add_cap( Permessi::AMMINISTRAZIONE );
		wp_set_current_user( $this->responsabile );
	}

	/**
	 * Toglie i file, rimette albero, permessi e fuso.
	 */
	public function tear_down(): void {
		foreach ( array_reverse( $this->creati ) as $percorso ) {
			if ( is_link( $percorso ) || is_file( $percorso ) ) {
				unlink( $percorso );
			}
		}

		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-finto.json' );
		PaginaPubblica::da_init();
		get_role( 'administrator' )->add_cap( Permessi::AMMINISTRAZIONE );
		update_option( 'timezone_string', '' );
		update_option( 'gmt_offset', 0 );

		parent::tear_down();
	}

	/**
	 * Una pagina.
	 *
	 * @param string $titolo Titolo.
	 * @param int    $padre  Genitore.
	 * @param string $testo  Testo.
	 * @param array  $altro  Altri campi.
	 * @return int
	 */
	private function pagina( string $titolo, int $padre = 0, string $testo = '', array $altro = array() ): int {
		return (int) self::factory()->post->create(
			array_merge(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $titolo,
					'post_parent'  => $padre,
					'post_content' => $testo,
				),
				$altro
			)
		);
	}

	/**
	 * Scrive un file nella cartella dei caricamenti.
	 *
	 * @param string $relativo Percorso relativo.
	 * @param string $byte     Contenuto.
	 * @return string Percorso pieno.
	 */
	private function file( string $relativo, string $byte ): string {
		$pieno = wp_upload_dir( null, false )['basedir'] . '/' . $relativo;
		wp_mkdir_p( dirname( $pieno ) );
		file_put_contents( $pieno, $byte ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$this->creati[] = $pieno;

		return $pieno;
	}

	/**
	 * Un allegato della libreria con il suo file.
	 *
	 * @param string $relativo Percorso relativo.
	 * @param string $byte     Contenuto.
	 * @param int    $padre    Genitore.
	 * @param string $data     Data di caricamento.
	 * @return int
	 */
	private function allegato( string $relativo, string $byte, int $padre, string $data ): int {
		$pieno = $this->file( $relativo, $byte );

		return (int) wp_insert_attachment(
			array(
				'post_mime_type' => 'application/pdf',
				'post_title'     => basename( $relativo ),
				'post_status'    => 'inherit',
				'post_date'      => $data,
			),
			$pieno,
			$padre
		);
	}

	/**
	 * Indirizzo della cartella dei caricamenti.
	 *
	 * @return string
	 */
	private function base(): string {
		return wp_upload_dir( null, false )['baseurl'];
	}

	/**
	 * Il rapporto, con l'albero in vigore.
	 *
	 * @param int    $radice Pagina d'ingresso.
	 * @param string $oggi   Oggi.
	 * @return array<string, mixed>
	 */
	private function rapporto( int $radice, string $oggi = '2026-06-15' ): array {
		$rapporto = ImportazionePagine::rapporto( $radice, PaginaPubblica::albero(), $oggi );
		$this->assertIsArray( $rapporto );

		return $rapporto;
	}

	/**
	 * Una pagina del rapporto, per identificativo.
	 *
	 * @param array<string, mixed> $rapporto Rapporto.
	 * @param int                  $id       Pagina.
	 * @return array<string, mixed>
	 */
	private function riga( array $rapporto, int $id ): array {
		foreach ( $rapporto['pagine'] as $pagina ) {
			if ( $id === $pagina['id'] ) {
				return $pagina;
			}
		}

		$this->fail( 'Pagina ' . $id . ' assente dal rapporto.' );
	}

	/**
	 * I file di una pagina, per percorso.
	 *
	 * @param array<string, mixed> $pagina Pagina del rapporto.
	 * @return array<string, array<string, mixed>>
	 */
	private function file_per_percorso( array $pagina ): array {
		$file = array();

		foreach ( $pagina['file'] as $uno ) {
			$file[ $uno['percorso'] ] = $uno;
		}

		return $file;
	}

	/**
	 * La schermata, con la richiesta data.
	 *
	 * @param array<string, mixed> $richiesta Parametri.
	 * @return string
	 */
	private function schermata( array $richiesta ): string {
		return SchermataImportazione::html( $richiesta );
	}

	/**
	 * Richiesta valida per una pagina d'ingresso.
	 *
	 * @param int|string $radice Pagina d'ingresso.
	 * @return array<string, mixed>
	 */
	private function richiesta( $radice ): array {
		return array(
			'page'                              => SchermataImportazione::PAGINA,
			SchermataImportazione::CAMPO_RADICE => (string) $radice,
			'_wpnonce'                          => wp_create_nonce( SchermataImportazione::AZIONE ),
		);
	}

	/**
	 * Conta le interrogazioni che leggono le pagine mentre gira la funzione data.
	 *
	 * @param callable $fai Funzione.
	 * @return array{0: mixed, 1: int}
	 */
	private function letture_pagine( callable $fai ): array {
		$conta  = 0;
		$filtro = static function ( $sql ) use ( &$conta ) {
			if ( false !== strpos( (string) $sql, "post_type = 'page'" ) ) {
				++$conta;
			}

			return $sql;
		};

		add_filter( 'query', $filtro );

		try {
			$esito = $fai();
		} finally {
			remove_filter( 'query', $filtro );
		}

		return array( $esito, $conta );
	}

	/**
	 * T-121. Chi apre la schermata, e chi no; pagina d'ingresso non valida; escaping.
	 */
	public function test_t121_schermata_permesso_nonce_e_radice() {
		$radice = $this->pagina( 'Amministrazione trasparente' );
		$this->pagina( '<script>alert(1)</script>', $radice );

		list( $html, $letture ) = $this->letture_pagine( fn() => $this->schermata( $this->richiesta( $radice ) ) );
		$this->assertStringContainsString( 'trasparenza-pa-rapporto', $html );
		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $html );
		$this->assertStringNotContainsString( '<script>alert', $html );
		$this->assertGreaterThan( 0, $letture );

		// Il modulo chiede la pagina d'ingresso fra le pagine pubblicate, con il nonce.
		$modulo = $this->schermata( array() );
		$this->assertStringContainsString( 'value="' . $radice . '"', $modulo );
		$this->assertStringContainsString( 'name="_wpnonce"', $modulo );
		$this->assertStringContainsString( 'method="get"', $modulo );
		$this->assertStringNotContainsString( 'trasparenza-pa-rapporto', $modulo );

		// Senza permesso: niente letto.
		$abbonato = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $abbonato );
		list( $html, $letture ) = $this->letture_pagine( fn() => $this->schermata( $this->richiesta( $radice ) ) );
		$this->assertSame( 0, $letture );
		$this->assertStringNotContainsString( 'trasparenza-pa-rapporto', $html );
		$this->assertStringContainsString( 'Non avete il permesso', $html );

		$amministratore = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $amministratore );
		list( $html, $letture ) = $this->letture_pagine( fn() => $this->schermata( $this->richiesta( $radice ) ) );
		$this->assertSame( 0, $letture );
		$this->assertStringContainsString( 'Non avete il permesso', $html );

		wp_set_current_user( $this->responsabile );

		// Nonce mancante o sbagliato: niente letto.
		foreach ( array( null, 'sbagliato', array( 'x' ) ) as $nonce ) {
			$richiesta = $this->richiesta( $radice );

			if ( null === $nonce ) {
				unset( $richiesta['_wpnonce'] );
			} else {
				$richiesta['_wpnonce'] = $nonce;
			}

			list( $html, $letture ) = $this->letture_pagine( fn() => $this->schermata( $richiesta ) );
			$this->assertSame( 0, $letture );
			$this->assertStringNotContainsString( 'trasparenza-pa-rapporto', $html );
			$this->assertStringContainsString( 'La richiesta è scaduta', $html );
		}

		// Pagina d'ingresso non valida.
		$bozza    = $this->pagina( 'Bozza', 0, '', array( 'post_status' => 'draft' ) );
		$articolo = (int) self::factory()->post->create( array( 'post_type' => 'post' ) );

		foreach ( array( 'abc', '0', '-3', $radice . ' ', $radice . "\n", '0' . $radice, '999999999', (string) $bozza, (string) $articolo, array( $radice ) ) as $valore ) {
			$richiesta                                        = $this->richiesta( $radice );
			$richiesta[ SchermataImportazione::CAMPO_RADICE ] = $valore;
			$html = $this->schermata( $richiesta );
			$this->assertStringNotContainsString( 'trasparenza-pa-rapporto', $html, wp_json_encode( $valore ) );
			$this->assertStringContainsString( 'La pagina d&#039;ingresso indicata', $html, wp_json_encode( $valore ) );
		}

		$this->assertInstanceOf( \WP_Error::class, ImportazionePagine::rapporto( $bozza, PaginaPubblica::albero(), '2026-06-15' ) );
		$this->assertInstanceOf( \WP_Error::class, ImportazionePagine::rapporto( $articolo, PaginaPubblica::albero(), '2026-06-15' ) );
	}

	/**
	 * T-122. Pagine sotto la pagina d'ingresso e voce proposta.
	 */
	public function test_t122_pagine_e_voce_proposta() {
		$radice   = $this->pagina( 'Amministrazione trasparente' );
		$bandi    = $this->pagina( 'Bandi di Concorso!', $radice, '', array( 'menu_order' => 1 ) );
		$org      = $this->pagina( 'Organizzazione', $radice, '', array( 'menu_order' => 2 ) );
		$tel      = $this->pagina( 'TELEFONO  e posta-elettronica', $org );
		$proc     = $this->pagina( "ATTIVITA' E PROCEDIMENTI", $radice, '', array( 'menu_order' => 3 ) );
		$atti     = $this->pagina( 'Atti', $radice, '', array( 'menu_order' => 4 ) );
		$varie    = $this->pagina( 'Varie', $radice, '', array( 'menu_order' => 5 ) );
		$accento  = $this->pagina( 'Attività e procedimenti', $varie );
		$bozza    = $this->pagina( 'Bandi di concorso', $radice, '', array( 'post_status' => 'draft' ) );
		$privata  = $this->pagina( 'Bandi di concorso', $radice, '', array( 'post_status' => 'private' ) );
		$sotto    = $this->pagina( 'Sotto la bozza', $bozza );
		$fuori    = $this->pagina( 'Bandi di concorso' );
		$rapporto = $this->rapporto( $radice );

		$this->assertSame( array( $radice, $bandi, $org, $tel, $proc, $atti, $varie, $accento ), array_column( $rapporto['pagine'], 'id' ) );
		$this->assertSame( array( 0, 1, 1, 2, 1, 1, 1, 2 ), array_column( $rapporto['pagine'], 'profondita' ) );

		foreach ( array( $bozza, $privata, $sotto, $fuori ) as $assente ) {
			$this->assertNotContains( $assente, array_column( $rapporto['pagine'], 'id' ) );
		}

		$attesi = array(
			$radice  => array( 'nessuna', null ),
			$bandi   => array( 'proposta', 'bandi' ),
			$org     => array( 'con_sottovoci', 'organizzazione' ),
			$tel     => array( 'proposta', 'telefono' ),
			$proc    => array( 'proposta', 'procedimenti' ),
			$atti    => array( 'piu_voci', null ),
			$varie   => array( 'nessuna', null ),
			$accento => array( 'proposta', 'procedimenti' ),
		);

		foreach ( $attesi as $id => $atteso ) {
			$riga = $this->riga( $rapporto, $id );
			$this->assertSame( $atteso, array( $riga['esito_voce'], $riga['voce'] ), get_the_title( $id ) );
			$this->assertSame( get_permalink( $id ), $riga['indirizzo'] );
		}

		$this->assertSame( 'Bandi di Concorso!', $this->riga( $rapporto, $bandi )['titolo'] );
		// Bozza, privata e la pagina pubblicata sotto la bozza: contate, non lette.
		$this->assertSame( 3, $rapporto['non_lette'] );
		$this->assertStringContainsString( '3 pagine non pubblicate o protette da password, o sotto di loro, non lette', $this->schermata( $this->richiesta( $radice ) ) );

		// Genitori che girano in tondo: il rapporto finisce, ogni pagina una volta.
		global $wpdb;
		$a = $this->pagina( 'Giro A' );
		$b = $this->pagina( 'Giro B', $a );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- wp_update_post() non permette un giro di genitori.
		$wpdb->update( $wpdb->posts, array( 'post_parent' => $b ), array( 'ID' => $a ) );
		clean_post_cache( $a );

		$giro = $this->rapporto( $a );
		$this->assertSame( array( $a, $b ), array_column( $giro['pagine'], 'id' ) );
		$this->assertSame( 0, $giro['non_lette'] );
	}

	/**
	 * T-123. I file di una pagina: dove si trovano, presenti, mancanti, fuori.
	 */
	public function test_t123_file_della_pagina() {
		$base   = $this->base();
		$senza  = preg_replace( '#\Ahttps?:#', '', $base );
		$locale = (string) wp_parse_url( $base, PHP_URL_PATH );
		$fuori  = get_temp_dir() . 'trasparenza-fuori-' . wp_generate_password( 8, false ) . '.pdf';

		file_put_contents( $fuori, 'segreto' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$this->creati[] = $fuori;

		$this->file( '2020/05/due.pdf', 'contenuto due' );
		$this->file( '2021/01/tre.pdf', 'contenuto tre' );
		$this->file( '2019/03/spazio e accento è.pdf', 'contenuto spazio' );
		$collegamento = wp_upload_dir( null, false )['basedir'] . '/2019/03/collegamento.pdf';
		wp_mkdir_p( dirname( $collegamento ) );
		symlink( $fuori, $collegamento );
		$this->creati[] = $collegamento;

		$testo = '<a href="' . $base . '/2019/03/uno.pdf?ver=2#pagina">Uno</a>'
			. '[fusion_button link="' . $base . '/2020/05/due.pdf" target="_blank"]Due[/fusion_button]'
			. '<a href="' . $senza . '/2021/01/tre.pdf">Tre</a>'
			. '<a href=\'' . $locale . '/2022/02/mancante.pdf\'>Mancante</a>'
			. '<a href="https://altro.example' . $locale . '/2017/01/altrui.pdf">Altro sito</a>'
			. '<a href="' . $base . '/2019/03/../../../wp-config.php">Su</a>'
			. '<a href="' . $base . '/2019/03/collegamento.pdf">Collegamento</a>'
			. '<a href="' . $base . '/2019/03/spazio%20e%20accento%20%C3%A8.pdf">Spazio</a>'
			. '<a href="' . $base . '/2019/03/uno.pdf">Ancora uno</a>';

		$radice = $this->pagina( 'Amministrazione trasparente' );
		$pagina = $this->pagina( 'Bandi di concorso', $radice, $testo );
		$uno    = $this->allegato( '2019/03/uno.pdf', 'contenuto uno', $pagina, '2019-03-10 09:00:00' );
		$solo   = $this->allegato( '2018/07/solo-allegato.pdf', 'contenuto solo', $pagina, '2018-07-03 12:00:00' );
		$altra  = $this->pagina( 'Altra', $radice );
		$this->allegato( '2018/07/di-altra.pdf', 'contenuto altra', $altra, '2018-07-04 12:00:00' );

		$riga = $this->riga( $this->rapporto( $radice ), $pagina );
		$file = $this->file_per_percorso( $riga );

		$this->assertCount( 8, $riga['file'] );
		$this->assertSame(
			array( '2019/03/uno.pdf', '2020/05/due.pdf', '2021/01/tre.pdf', '2022/02/mancante.pdf', '2019/03/../../../wp-config.php', '2019/03/collegamento.pdf', '2019/03/spazio e accento è.pdf', '2018/07/solo-allegato.pdf' ),
			array_keys( $file )
		);

		$this->assertSame( 'presente', $file['2019/03/uno.pdf']['stato'] );
		$this->assertSame( strlen( 'contenuto uno' ), $file['2019/03/uno.pdf']['byte'] );
		$this->assertSame( hash( 'sha256', 'contenuto uno' ), $file['2019/03/uno.pdf']['impronta'] );
		$this->assertSame( $uno, $file['2019/03/uno.pdf']['allegato'] );
		$this->assertSame( '2019-03-10', $file['2019/03/uno.pdf']['data'] );

		$this->assertSame( 'presente', $file['2020/05/due.pdf']['stato'] );
		$this->assertNull( $file['2020/05/due.pdf']['allegato'] );
		$this->assertSame( hash( 'sha256', 'contenuto due' ), $file['2020/05/due.pdf']['impronta'] );
		$this->assertSame( 'presente', $file['2021/01/tre.pdf']['stato'] );
		$this->assertSame( 'presente', $file['2019/03/spazio e accento è.pdf']['stato'] );

		$this->assertSame( 'mancante', $file['2022/02/mancante.pdf']['stato'] );
		$this->assertNull( $file['2022/02/mancante.pdf']['byte'] );
		$this->assertNull( $file['2022/02/mancante.pdf']['impronta'] );

		foreach ( array( '2019/03/../../../wp-config.php', '2019/03/collegamento.pdf' ) as $fuori_cartella ) {
			$this->assertSame( 'fuori', $file[ $fuori_cartella ]['stato'], $fuori_cartella );
			$this->assertNull( $file[ $fuori_cartella ]['byte'], $fuori_cartella );
			$this->assertNull( $file[ $fuori_cartella ]['impronta'], $fuori_cartella );
		}

		$this->assertSame( 'presente', $file['2018/07/solo-allegato.pdf']['stato'] );
		$this->assertSame( $solo, $file['2018/07/solo-allegato.pdf']['allegato'] );
		$this->assertSame( '2018-07-03', $file['2018/07/solo-allegato.pdf']['data'] );
		$this->assertArrayNotHasKey( '2018/07/di-altra.pdf', $file );
		$this->assertArrayNotHasKey( '2017/01/altrui.pdf', $file );
	}

	/**
	 * T-124. Pubblicazione finita di sicuro.
	 */
	public function test_t124_pubblicazione_finita_di_sicuro() {
		$this->assertTrue( ImportazionePagine::finita( '2020-12-31', 5, '2026-01-01' ) );
		$this->assertFalse( ImportazionePagine::finita( '2021-01-01', 5, '2026-12-31' ) );
		$this->assertTrue( ImportazionePagine::finita( '2020-03', 5, '2026-06-15' ) );
		$this->assertFalse( ImportazionePagine::finita( '2021-03', 5, '2026-06-15' ) );
		$this->assertTrue( ImportazionePagine::finita( '2023-01-01', 2, '2026-06-15' ) );
		$this->assertFalse( ImportazionePagine::finita( '2024-01-01', 2, '2026-06-15' ) );
		$this->assertNull( ImportazionePagine::finita( null, 5, '2026-06-15' ) );
		$this->assertNull( ImportazionePagine::finita( '2010-01-01', null, '2026-06-15' ) );

		$radice = $this->pagina( 'Amministrazione trasparente' );
		$pagina = $this->pagina(
			'Bandi di concorso',
			$radice,
			'<a href="' . $this->base() . '/2020/02/vecchio.pdf">x</a><a href="' . $this->base() . '/2021/02/recente.pdf">y</a><a href="' . $this->base() . '/senza-cartella.pdf">z</a>'
		);
		$this->file( '2020/02/vecchio.pdf', 'v' );
		$this->file( '2021/02/recente.pdf', 'r' );
		$this->file( 'senza-cartella.pdf', 's' );
		$this->allegato( '2021/03/caricato.pdf', 'c', $pagina, '2020-12-31 23:00:00' );

		$file = $this->file_per_percorso( $this->riga( $this->rapporto( $radice, '2026-06-15' ), $pagina ) );
		$this->assertSame( '2020-02', $file['2020/02/vecchio.pdf']['data'] );
		$this->assertTrue( $file['2020/02/vecchio.pdf']['finita'] );
		$this->assertFalse( $file['2021/02/recente.pdf']['finita'] );
		$this->assertNull( $file['senza-cartella.pdf']['data'] );
		$this->assertNull( $file['senza-cartella.pdf']['finita'] );
		// Conta la data dell'allegato, non la cartella.
		$this->assertSame( '2020-12-31', $file['2021/03/caricato.pdf']['data'] );
		$this->assertTrue( $file['2021/03/caricato.pdf']['finita'] );

		// Albero senza durata: non si sa.
		$dati = json_decode( (string) file_get_contents( __DIR__ . '/dati/albero-importazione.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		unset( $dati['durata_pubblicazione'] );
		$senza_durata = Alberatura::da_dati( $dati );
		$this->assertInstanceOf( Alberatura::class, $senza_durata );
		$rapporto = ImportazionePagine::rapporto( $radice, $senza_durata, '2026-06-15' );
		$this->assertIsArray( $rapporto );
		$this->assertNull( $rapporto['durata'] );
		$html = $this->schermata( $this->richiesta( $radice ) );
		$this->assertStringContainsString( 'oltre il termine ordinario di pubblicazione (da verificare: atti che producono ancora effetti, durate degli artt. 14 e 15)', $html );
		$this->assertStringContainsString( 'File oltre il termine ordinario di 5 anni: ', $html );
		$this->assertStringNotContainsString( 'finita di sicuro', $html );

		$this->assertSame( array( null ), array_values( array_unique( array_column( $this->riga( $rapporto, $pagina )['file'], 'finita' ), SORT_REGULAR ) ) );
	}

	/**
	 * T-124. La schermata conta da oggi nel fuso del sito.
	 */
	public function test_t124_oggi_nel_fuso_del_sito() {
		$lontano = (int) gmdate( 'G' ) >= 10 ? 'Pacific/Kiritimati' : 'Pacific/Pago_Pago';
		$scarto  = 'Pacific/Kiritimati' === $lontano ? 14 : -11;
		update_option( 'timezone_string', $lontano );
		update_option( 'date_format', 'd/m/Y' );

		$radice = $this->pagina( 'Amministrazione trasparente' );
		$html   = $this->schermata( $this->richiesta( $radice ) );

		$this->assertStringContainsString( 'Calcolato al ' . gmdate( 'd/m/Y', time() + $scarto * HOUR_IN_SECONDS ), $html );
		$this->assertStringNotContainsString( 'Calcolato al ' . gmdate( 'd/m/Y' ), $html );
	}

	/**
	 * T-125. Testo, collegamenti esterni, pagine vuote, voci senza pagina.
	 */
	public function test_t125_testo_esterni_vuote_e_voci_senza_pagina() {
		$radice  = $this->pagina( 'Amministrazione trasparente' );
		$testo   = $this->pagina( 'Bandi di concorso', $radice, '<p>Ciao <b>mondo</b></p>[fusion_text id="1"]x[/fusion_text]<p>&egrave;</p>' );
		$esterni = $this->pagina(
			'Telefono e posta elettronica',
			$radice,
			'[fusion_button link="https://banca.example/dati?a=1"][/fusion_button]<a href="https://banca.example/dati?a=1">di nuovo</a> https://altra.example/pagina. <a href="' . home_url( '/contatti/' ) . '">sito</a>'
		);
		$vuota   = $this->pagina( 'Varie', $radice, '<p> </p>[fusion_separator /]' );
		$rinvio  = $this->pagina( 'Rinvio', $radice, '[fusion_button link="https://banca.example/sola"][/fusion_button]' );

		$rapporto = $this->rapporto( $radice );

		$this->assertSame( mb_strlen( 'Ciao mondo x è' ), $this->riga( $rapporto, $testo )['caratteri'] );
		$this->assertFalse( $this->riga( $rapporto, $testo )['vuota'] );
		$this->assertSame( array( 'https://banca.example/dati?a=1', 'https://altra.example/pagina' ), $this->riga( $rapporto, $esterni )['esterni'] );
		$this->assertFalse( $this->riga( $rapporto, $esterni )['vuota'] );
		$this->assertSame( 0, $this->riga( $rapporto, $vuota )['caratteri'] );
		$this->assertSame( array(), $this->riga( $rapporto, $vuota )['esterni'] );
		$this->assertTrue( $this->riga( $rapporto, $vuota )['vuota'] );
		$this->assertSame( 0, $this->riga( $rapporto, $rinvio )['caratteri'] );
		$this->assertSame( array( 'https://banca.example/sola' ), $this->riga( $rapporto, $rinvio )['esterni'] );
		$this->assertFalse( $this->riga( $rapporto, $rinvio )['vuota'] );

		$this->assertSame( array( 'articolazione', 'atti_org', 'procedimenti', 'atti_altri' ), $rapporto['voci_senza_pagina'] );
	}

	/**
	 * T-127. Le pagine protette da password restano fuori dal rapporto.
	 */
	public function test_t127_pagine_con_password() {
		$this->file( '2020/01/protetto.pdf', 'protetto' );

		$radice   = $this->pagina( 'Amministrazione trasparente' );
		$aperta   = $this->pagina( 'Bandi di concorso', $radice );
		$chiusa   = $this->pagina( 'Telefono e posta elettronica', $radice, '<a href="' . $this->base() . '/2020/01/protetto.pdf">p</a> https://riservato.example/x', array( 'post_password' => 'segreta' ) );
		$sotto    = $this->pagina( 'Sotto la protetta', $chiusa );
		$rapporto = $this->rapporto( $radice );

		$this->assertSame( array( $radice, $aperta ), array_column( $rapporto['pagine'], 'id' ) );
		$this->assertSame( 2, $rapporto['non_lette'] );
		$this->assertContains( 'telefono', $rapporto['voci_senza_pagina'] );

		$html = $this->schermata( $this->richiesta( $radice ) );
		$this->assertStringNotContainsString( 'protetto.pdf', $html );
		$this->assertStringNotContainsString( 'riservato.example', $html );
		$this->assertStringContainsString( '2 pagine non pubblicate o protette da password, o sotto di loro, non lette', $html );

		// Una pagina protetta non fa da pagina d'ingresso.
		$errore = ImportazionePagine::rapporto( $chiusa, PaginaPubblica::albero(), '2026-06-15' );
		$this->assertInstanceOf( \WP_Error::class, $errore );
		$this->assertSame( 'trasparenza_importazione_radice', $errore->get_error_code() );
		$html = $this->schermata( $this->richiesta( $chiusa ) );
		$this->assertStringNotContainsString( 'trasparenza-pa-rapporto', $html );
		$this->assertStringNotContainsString( 'protetto.pdf', $html );
	}

	/**
	 * T-128. Solo gli indirizzi che cominciano con la cartella dei caricamenti del sito sono file.
	 */
	public function test_t128_indirizzi_ancorati() {
		$locale = (string) wp_parse_url( $this->base(), PHP_URL_PATH );
		$this->file( '2020/01/atto.pdf', 'atto locale' );

		$radice = $this->pagina( 'Amministrazione trasparente' );
		$pagina = $this->pagina(
			'Bandi di concorso',
			$radice,
			'<a href="https://altro.example/archivio' . $locale . '/2020/01/atto.pdf">altro sito in sottocartella</a>'
			. '<a href="//altro.example/archivio' . $locale . '/2020/01/atto.pdf">senza schema</a>'
			. '<a href="/archivio' . $locale . '/2020/01/atto.pdf">stesso sito, altra cartella</a>'
			. '<a href="' . home_url( '/archivio' . $locale . '/2020/01/atto.pdf' ) . '">stesso dominio, altra cartella</a>'
			. ' testo/archivio' . $locale . '/2020/01/atto.pdf'
		);

		$riga = $this->riga( $this->rapporto( $radice ), $pagina );
		$this->assertSame( array(), $riga['file'] );
		$this->assertContains( 'https://altro.example/archivio' . $locale . '/2020/01/atto.pdf', $riga['esterni'] );

		// Lo stesso file, scritto con il prefisso giusto, si trova.
		$giusta = $this->pagina( 'Atti', $radice, '<a href="' . $locale . '/2020/01/atto.pdf">x</a>' );
		$this->assertSame( array( '2020/01/atto.pdf' ), array_column( $this->riga( $this->rapporto( $radice ), $giusta )['file'], 'percorso' ) );
	}

	/**
	 * T-126. Il rapporto non scrive niente; lettura fallita dichiarata.
	 */
	public function test_t126_non_scrive_niente_e_lettura_fallita() {
		global $wpdb;

		$radice = $this->pagina( 'Amministrazione trasparente' );
		$pagina = $this->pagina( 'Bandi di concorso', $radice, '<a href="' . $this->base() . '/2019/03/uno.pdf">Uno</a>' );
		$this->allegato( '2019/03/uno.pdf', 'contenuto uno', $pagina, '2019-03-10 09:00:00' );

		$fotografia = function () use ( $wpdb ): array {
			$cartella = wp_upload_dir( null, false )['basedir'];
			$file     = array();

			foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $cartella, \FilesystemIterator::SKIP_DOTS ) ) as $voce ) {
				$file[] = $voce->getPathname() . ':' . $voce->getSize() . ':' . $voce->getMTime();
			}

			sort( $file );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery -- la fotografia legge la banca dati, non la memoria.
			return array(
				(int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" ),
				(string) $wpdb->get_var( "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts}" ),
				(int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta}" ),
				(string) $wpdb->get_var( "SELECT MD5(GROUP_CONCAT(option_name, option_value ORDER BY option_name)) FROM {$wpdb->options}" ),
				$file,
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery
		};

		$prima = $fotografia();
		$html  = $this->schermata( $this->richiesta( $radice ) );
		$this->assertStringContainsString( 'trasparenza-pa-rapporto', $html );
		$this->assertSame( $prima, $fotografia() );

		foreach ( array( "post_type = 'page' AND post_status = 'publish'", 'post_status NOT IN', "post_type = 'attachment'" ) as $lettura ) {
			$wpdb->suppress_errors( true );
			$filtro = static function ( $sql ) use ( $wpdb, $lettura ) {
				$sql = (string) $sql;

				return false !== strpos( $sql, $lettura ) && false !== strpos( $sql, 'trasparenza-pa-importazione' )
					? 'SELECT ID FROM ' . $wpdb->posts . '_inesistente'
					: $sql;
			};
			add_filter( 'query', $filtro );

			try {
				$rapporto = ImportazionePagine::rapporto( $radice, PaginaPubblica::albero(), '2026-06-15' );
				$html     = $this->schermata( $this->richiesta( $radice ) );
			} finally {
				remove_filter( 'query', $filtro );
				$wpdb->suppress_errors( false );
			}

			$this->assertInstanceOf( \WP_Error::class, $rapporto, $lettura );
			$this->assertSame( 'trasparenza_importazione_lettura', $rapporto->get_error_code() );
			$this->assertStringContainsString( 'Il rapporto non si può fare: la lettura delle pagine non è riuscita.', $html, $lettura );
			$this->assertStringNotContainsString( 'trasparenza-pa-rapporto', $html, $lettura );
		}
	}
}
