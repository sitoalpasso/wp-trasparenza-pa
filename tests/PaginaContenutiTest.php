<?php
/**
 * Prove dei contenuti sulla pagina pubblica: righe T-71..T-73.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Avvio;
use TrasparenzaPa\ContenutiPubblici;
use TrasparenzaPa\PaginaPubblica;
use TrasparenzaPa\SchedaContenuto;
use TrasparenzaPa\TipoContenuto;
use TrasparenzaPa\TipoVoce;
use WP_UnitTestCase;

use const TrasparenzaPa\TIPO_CONTENUTO;
use const TrasparenzaPa\TIPO_VOCE;

/**
 * Quali contenuti compaiono in una voce, con quali date, con quale testo.
 */
class PaginaContenutiTest extends WP_UnitTestCase {

	use AmbienteTrasparenza;

	/**
	 * Sezione e tipi registrati, albero finto, fuso e formato della data fissati, visitatore anonimo.
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

		update_option( 'timezone_string', 'Europe/Rome' );
		update_option( 'date_format', 'Y-m-d' );

		wp_set_current_user( 0 );
	}

	/**
	 * Rimette l'orologio del componente comune e toglie il codice breve di prova.
	 */
	public function tear_down(): void {
		\Conformita_Core_Scadenza::azzera_orologio();
		remove_shortcode( 'prova_codice' );
		remove_all_filters( 'pre_get_posts' );
		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-finto.json' );
		PaginaPubblica::da_init();

		parent::tear_down();
	}

	/**
	 * Un contenuto pubblicato e visibile, da guastare un pezzo alla volta.
	 *
	 * @param array<string, mixed> $campi      Campi del contenuto.
	 * @param string|null          $voce       Voce; nulla per nessuna.
	 * @param string|null          $fine       Fine della pubblicazione; nulla per nessuna.
	 * @param string|null          $modificato Ultima modifica in UTC, AAAA-MM-GG HH:MM:SS.
	 * @return int
	 */
	private function contenuto( array $campi = array(), ?string $voce = 'zeta_uno', ?string $fine = '2099-12-31', ?string $modificato = null ): int {
		global $wpdb;

		$id = (int) self::factory()->post->create(
			array_merge(
				array(
					'post_type'    => TIPO_CONTENUTO,
					'post_status'  => 'publish',
					'post_title'   => 'Contenuto di prova',
					'post_content' => 'Testo di prova.',
				),
				$campi
			)
		);

		if ( null !== $voce ) {
			add_post_meta( $id, SchedaContenuto::META_COLLOCAZIONE, $voce );
		}

		if ( null !== $fine ) {
			$this->assertTrue( conformita_core_imposta_fine_pubblicazione( $id, $fine ) );
		}

		if ( null !== $modificato ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- WordPress riscrive la data di modifica a ogni salvataggio: la prova la fissa nella banca dati.
			$wpdb->update(
				$wpdb->posts,
				array(
					'post_modified_gmt' => $modificato,
					'post_modified'     => get_date_from_gmt( $modificato ),
				),
				array( 'ID' => $id )
			);
			clean_post_cache( $id );
		}

		return $id;
	}

	/**
	 * Una voce dell'albero finto, disegnata sulla pagina pubblica.
	 *
	 * @param string $voce Voce.
	 * @return string
	 */
	private function pagina( string $voce = 'zeta_uno' ): string {
		return PaginaPubblica::disegna( PaginaPubblica::albero(), $voce, 'https://example.org/sezione/' );
	}

	/**
	 * Gli identificativi dei contenuti visibili in una voce, nell'ordine della pagina.
	 *
	 * @param string $voce Voce.
	 * @return array<int, int>
	 */
	private function visibili( string $voce = 'zeta_uno' ): array {
		return array_map(
			static function ( $contenuto ) {
				return (int) $contenuto->ID;
			},
			ContenutiPubblici::visibili( array( $voce ) )
		);
	}

	/**
	 * Le date di aggiornamento stampate, nell'ordine della pagina.
	 *
	 * @param string $html HTML.
	 * @return array<int, string>
	 */
	private function date( string $html ): array {
		preg_match_all( '#<p class="trasparenza-pa-aggiornamento">([^<]*)</p>#', $html, $trovate );

		return $trovate[1];
	}

	/**
	 * T-71: un contenuto pubblicato, senza password, con una voce valida e la fine non passata compare nella sua voce, da anonimo.
	 */
	public function test_t71_contenuto_visibile(): void {
		$id = $this->contenuto( array( 'post_title' => 'Bando di prova' ) );

		$this->assertSame( array( $id ), $this->visibili() );

		$html = $this->pagina();

		$this->assertStringContainsString( '<article class="trasparenza-pa-contenuto"><h3>Bando di prova</h3>', $html );
		$this->assertStringContainsString( '<p>Testo di prova.</p>', $html );
		$this->assertStringNotContainsString( 'Bando di prova', $this->pagina( 'zeta_due' ), 'Solo nella sua voce.' );
		$this->assertStringNotContainsString( '<article', $this->pagina( 'zeta' ), 'La voce sopra non ripete i contenuti delle voci sotto.' );
	}

	/**
	 * T-71: [attacco] stato, password, voce e fine sbagliati: il contenuto non compare.
	 */
	public function test_t71_contenuti_nascosti(): void {
		$nascosti = array(
			'bozza'            => $this->contenuto( array( 'post_status' => 'draft' ) ),
			'in revisione'     => $this->contenuto( array( 'post_status' => 'pending' ) ),
			'privato'          => $this->contenuto( array( 'post_status' => 'private' ) ),
			'pianificato'      => $this->contenuto(
				array(
					'post_status' => 'future',
					'post_date'   => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
				)
			),
			'cestinato'        => $this->contenuto( array( 'post_status' => 'trash' ) ),
			'con password'     => $this->contenuto( array( 'post_password' => 'segreta' ) ),
			'senza fine'       => $this->contenuto( array(), 'zeta_uno', null ),
			'fine passata'     => $this->contenuto( array(), 'zeta_uno', '2000-01-01' ),
			'senza voce'       => $this->contenuto( array(), null ),
			'voce non ammessa' => $this->contenuto( array(), 'zeta' ),
			'voce inesistente' => $this->contenuto( array(), 'voce_inesistente' ),
		);

		$piu_fini = $this->contenuto();
		add_post_meta( $piu_fini, conformita_core_chiave_fine_pubblicazione(), '2098-12-31' );
		$nascosti['piu fini'] = $piu_fini;

		$piu_voci = $this->contenuto();
		add_post_meta( $piu_voci, SchedaContenuto::META_COLLOCAZIONE, 'zeta_uno' );
		$nascosti['piu voci'] = $piu_voci;

		$voce = self::factory()->post->create(
			array(
				'post_type'   => TIPO_VOCE,
				'post_status' => 'draft',
			)
		);
		add_post_meta( $voce, SchedaContenuto::META_COLLOCAZIONE, 'zeta_uno' );

		$articolo = self::factory()->post->create( array( 'post_title' => 'Articolo qualunque' ) );
		add_post_meta( $articolo, SchedaContenuto::META_COLLOCAZIONE, 'zeta_uno' );

		$this->assertSame( array(), $this->visibili(), 'Nascosti: ' . implode( ', ', array_keys( $nascosti ) ) );
		$this->assertSame( array(), $this->visibili( 'zeta' ) );
		$this->assertSame( '', ContenutiPubblici::html( PaginaPubblica::albero(), 'zeta_uno' ) );
		$this->assertStringNotContainsString( 'Articolo qualunque', $this->pagina() );
	}

	/**
	 * T-71: il controllo su ogni contenuto non si fida dell'interrogazione.
	 */
	public function test_t71_controllo_sul_singolo_contenuto(): void {
		$visibile = get_post( $this->contenuto() );

		$this->assertTrue( ContenutiPubblici::visibile( $visibile ) );

		$guasti = array(
			'post_type'     => 'post',
			'post_status'   => 'private',
			'post_password' => 'segreta',
		);

		foreach ( $guasti as $campo => $valore ) {
			$copia         = clone $visibile;
			$copia->$campo = $valore;

			$this->assertFalse( ContenutiPubblici::visibile( $copia ), $campo );
		}

		$scaduto = get_post( $this->contenuto( array(), 'zeta_uno', '2000-01-01' ) );

		$this->assertFalse( ContenutiPubblici::visibile( $scaduto ), 'Fine passata.' );
	}

	/**
	 * T-71: [attacco] un altro componente che allarga l'interrogazione non fa comparire niente di nascosto.
	 */
	public function test_t71_interrogazione_allargata(): void {
		$visibile = $this->contenuto();
		$this->contenuto( array( 'post_status' => 'draft' ) );
		$this->contenuto( array( 'post_status' => 'private' ) );
		$this->contenuto( array( 'post_password' => 'segreta' ) );

		add_action(
			'pre_get_posts',
			static function ( $interrogazione ) {
				$interrogazione->set( 'post_status', 'any' );
				$interrogazione->set( 'has_password', null );
			},
			PHP_INT_MAX
		);

		$this->assertSame( array( $visibile ), $this->visibili() );
	}

	/**
	 * T-71: un contenuto sparisce quando scade, senza che il compito pianificato sia passato.
	 */
	public function test_t71_scadenza_letta_sul_dato(): void {
		$oggi = new \DateTimeImmutable( 'now', wp_timezone() );
		$id   = $this->contenuto( array(), 'zeta_uno', $oggi->format( 'Y-m-d' ) );

		$this->assertSame( array( $id ), $this->visibili() );

		\Conformita_Core_Scadenza::fissa_orologio( $oggi->modify( 'tomorrow' ) );

		$this->assertSame( array(), $this->visibili() );
		$this->assertSame( 'publish', get_post_status( $id ), 'Nessuno lo ha spostato.' );
		$this->assertStringNotContainsString( '<article', $this->pagina() );
	}

	/**
	 * T-71: i piu recenti per primi; a parita di data, l'ultimo creato.
	 */
	public function test_t71_ordine(): void {
		$vecchio = $this->contenuto( array( 'post_date' => '2026-01-10 10:00:00' ) );
		$nuovo   = $this->contenuto( array( 'post_date' => '2026-03-10 10:00:00' ) );
		$medio   = $this->contenuto( array( 'post_date' => '2026-02-10 10:00:00' ) );
		$gemello = $this->contenuto( array( 'post_date' => '2026-02-10 10:00:00' ) );

		$this->assertSame( array( $nuovo, $gemello, $medio, $vecchio ), $this->visibili() );
	}

	/**
	 * T-72: la data di ogni contenuto, della sua voce e di ogni voce sopra, nel fuso del sito.
	 */
	public function test_t72_date_di_aggiornamento(): void {
		$this->contenuto( array(), 'zeta_uno', '2099-12-31', '2026-02-01 10:00:00' );
		// Alle 23:30 in UTC a Roma e gia il giorno dopo.
		$this->contenuto( array(), 'zeta_uno', '2099-12-31', '2026-03-09 23:30:00' );
		$this->contenuto( array(), 'zeta_due', '2099-12-31', '2026-04-20 10:00:00' );
		$this->contenuto( array(), 'alfa', '2099-12-31', '2026-01-05 10:00:00' );

		$this->assertSame(
			array( 'Ultimo aggiornamento: 2026-03-10', 'Aggiornato il 2026-03-10', 'Aggiornato il 2026-02-01' ),
			$this->date( $this->pagina( 'zeta_uno' ) )
		);
		$this->assertSame( array( 'Ultimo aggiornamento: 2026-04-20', 'Aggiornato il 2026-04-20' ), $this->date( $this->pagina( 'zeta_due' ) ) );
		$this->assertSame( array( 'Ultimo aggiornamento: 2026-04-20' ), $this->date( $this->pagina( 'zeta' ) ), 'La voce sopra prende la data piu recente fra quelle sotto.' );
		$this->assertSame( array( 'Ultimo aggiornamento: 2026-04-20' ), $this->date( $this->pagina( 'radice' ) ) );
		$this->assertSame( array( 'Ultimo aggiornamento: 2026-01-05', 'Aggiornato il 2026-01-05' ), $this->date( $this->pagina( 'alfa' ) ) );
	}

	/**
	 * T-72: niente data dove non c'e niente di visibile; i contenuti nascosti non spostano la data.
	 */
	public function test_t72_date_solo_dai_visibili(): void {
		$this->assertSame( array(), $this->date( $this->pagina( 'zeta' ) ), 'Voce vuota: nessuna data.' );

		$this->contenuto( array( 'post_status' => 'draft' ), 'zeta_uno', '2099-12-31', '2026-05-01 10:00:00' );
		$this->contenuto( array(), 'zeta_uno', '2000-01-01', '2026-05-02 10:00:00' );

		$this->assertSame( array(), $this->date( $this->pagina( 'zeta' ) ) );
		$this->assertSame( array(), $this->date( $this->pagina( 'zeta_uno' ) ) );

		$this->contenuto( array(), 'zeta_uno', '2099-12-31', '2026-02-01 10:00:00' );

		$this->assertSame( array( 'Ultimo aggiornamento: 2026-02-01' ), $this->date( $this->pagina( 'zeta' ) ) );
		$this->assertSame( array( 'Ultimo aggiornamento: 2026-02-01', 'Aggiornato il 2026-02-01' ), $this->date( $this->pagina( 'zeta_uno' ) ) );
	}

	/**
	 * T-73: [attacco] il testo passa per il filtro degli articoli, i codici brevi restano testo, il titolo e senza codice.
	 */
	public function test_t73_testo_ripulito(): void {
		add_shortcode(
			'prova_codice',
			static function () {
				return 'ESEGUITO';
			}
		);

		global $wpdb;

		$id = $this->contenuto();

		// Scritti nella banca dati come li salverebbe chi ha il permesso di scrivere HTML libero, senza il filtro del salvataggio.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- la prova aggira apposta il filtro del salvataggio.
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_title'   => '<img src=x onerror=alert(1)>Titolo',
				'post_content' => "Primo paragrafo <strong>forte</strong>.\n\n<script>alert(1)</script>[prova_codice]<a href=\"javascript:alert(1)\" onclick=\"alert(1)\">collegamento</a>",
			),
			array( 'ID' => $id )
		);
		clean_post_cache( $id );

		$this->assertStringContainsString( '<script>', get_post( $id )->post_content, 'Il codice e davvero nella banca dati.' );

		$html = $this->pagina();

		$this->assertStringContainsString( '<strong>forte</strong>', $html );
		$this->assertStringContainsString( '[prova_codice]', $html );
		$this->assertStringNotContainsString( 'ESEGUITO', $html );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( 'javascript:', $html );
		$this->assertStringNotContainsString( 'onclick', $html );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringContainsString( 'Titolo</h3>', $html );
	}

	/**
	 * T-73: chi amministra vede sulla pagina pubblica quello che vede il pubblico.
	 */
	public function test_t73_amministratore_come_il_pubblico(): void {
		$this->contenuto( array( 'post_status' => 'draft' ) );
		$this->contenuto( array( 'post_status' => 'private' ) );
		$this->contenuto( array( 'post_password' => 'segreta' ) );
		$this->contenuto( array(), 'zeta_uno', '2000-01-01' );
		$visibile = $this->contenuto();

		$anonimo = $this->pagina();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertSame( array( $visibile ), $this->visibili() );
		$this->assertSame( $anonimo, $this->pagina() );
	}
}
