<?php
/**
 * Prove della pagina pubblica: righe T-15..T-19.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Avvio;
use TrasparenzaPa\PaginaPubblica;
use TrasparenzaPa\StatiObbligo;
use WP_UnitTestCase;

/**
 * La pagina con il codice breve, vista da un visitatore qualunque.
 */
class PaginaPubblicaTest extends WP_UnitTestCase {

	use AmbienteTrasparenza;

	/**
	 * Pagina della sezione.
	 *
	 * @var int
	 */
	private $pagina;

	/**
	 * Sezione registrata, albero finto caricato, pagina creata, visitatore anonimo.
	 */
	public function set_up(): void {
		parent::set_up();

		\Conformita_Core_Sezioni::azzera();
		Avvio::azzera();
		Avvio::esegui();

		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-finto.json' );
		PaginaPubblica::da_init();

		delete_option( StatiObbligo::OPZIONE );
		update_option( 'blog_public', '1' );

		$this->pagina = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Sezione',
				'post_content' => '[' . PaginaPubblica::CODICE_BREVE . ']',
			)
		);

		wp_set_current_user( 0 );
	}

	/**
	 * Rimette l'albero finto, che una prova sostituisce con un file assente.
	 */
	public function tear_down(): void {
		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-finto.json' );
		PaginaPubblica::da_init();

		parent::tear_down();
	}

	/**
	 * Visita la pagina, con la voce chiesta o senza, come farebbe WordPress.
	 *
	 * @param string|null $voce Voce chiesta.
	 * @return string HTML del contenuto, vuoto se la risposta e 404.
	 */
	private function visita( ?string $voce = null ): string {
		$indirizzo = get_permalink( $this->pagina );

		if ( null !== $voce ) {
			$indirizzo = add_query_arg( PaginaPubblica::PARAMETRO_VOCE, $voce, $indirizzo );
		}

		$this->go_to( $indirizzo );

		PaginaPubblica::da_template_redirect();

		if ( is_404() ) {
			return '';
		}

		$html = '';

		while ( have_posts() ) {
			the_post();
			$html .= apply_filters( 'the_content', get_the_content() );
		}

		wp_reset_postdata();

		return $html;
	}

	/**
	 * Testi degli elementi scelti da un'espressione XPath.
	 *
	 * @param string $html  HTML.
	 * @param string $xpath Espressione.
	 * @return array<int, string>
	 */
	private function testi( string $html, string $xpath ): array {
		return array_map(
			static function ( \DOMNode $nodo ): string {
				return $nodo->textContent; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- proprieta di DOMNode.
			},
			iterator_to_array( $this->trova( $html, $xpath ) )
		);
	}

	/**
	 * Nodi scelti da un'espressione XPath.
	 *
	 * @param string $html  HTML.
	 * @param string $xpath Espressione.
	 * @return \DOMNodeList<\DOMNode>
	 */
	private function trova( string $html, string $xpath ): \DOMNodeList {
		$documento = new \DOMDocument();

		libxml_use_internal_errors( true );
		$documento->loadHTML( '<?xml encoding="utf-8"?><body>' . $html . '</body>' );
		libxml_clear_errors();

		return ( new \DOMXPath( $documento ) )->query( $xpath );
	}

	/**
	 * T-15: la dicitura esce carattere per carattere.
	 *
	 * Il testo atteso sta qui e non si legge dal file: se il file cambiasse la
	 * dicitura, la prova lo direbbe.
	 */
	public function test_t15_dicitura_non_applicabile_esatta(): void {
		StatiObbligo::imposta( PaginaPubblica::albero(), 'alfa', StatiObbligo::NON_APPLICABILE );

		$html = $this->visita( 'alfa' );

		$this->assertSame(
			array( "L'obbligo di pubblicazione non è applicabile all'amministrazione" ),
			$this->testi( $html, '//p[@class="trasparenza-pa-dicitura"]' )
		);
	}

	/**
	 * T-15: una voce applicabile o non dichiarata non stampa dicitura.
	 */
	public function test_t15_nessuna_dicitura_senza_stato_che_la_chieda(): void {
		StatiObbligo::imposta( PaginaPubblica::albero(), 'zeta', StatiObbligo::APPLICABILE );

		$this->assertSame( array(), $this->testi( $this->visita( 'zeta' ), '//p[@class="trasparenza-pa-dicitura"]' ) );
		$this->assertSame( array(), $this->testi( $this->visita( 'alfa' ), '//p[@class="trasparenza-pa-dicitura"]' ) );
	}

	/**
	 * T-16: senza parametro la radice, con i figli nell'ordine dell'albero.
	 */
	public function test_t16_radice_senza_parametro(): void {
		$html = $this->visita();

		$this->assertFalse( is_404() );
		$this->assertSame( array( 'Voce Zeta', 'Voce Alfa' ), $this->testi( $html, '//ul[@class="trasparenza-pa-voci"]/li/a' ) );
		$this->assertSame( array(), $this->testi( $html, '//h2' ), 'La radice non ripete il proprio nome: e il titolo della pagina.' );
	}

	/**
	 * T-16: una voce interna con nome, percorso e figli dagli indirizzi stabili.
	 */
	public function test_t16_voce_con_percorso_e_figli(): void {
		$base = get_permalink( $this->pagina );
		$html = $this->visita( 'zeta' );

		$this->assertFalse( is_404() );
		$this->assertSame( array( 'Voce Zeta' ), $this->testi( $html, '//h2' ) );
		$this->assertSame( array( 'Riferimento: Riferimento finto 1' ), $this->testi( $html, '//p[@class="trasparenza-pa-riferimento"]' ) );

		$passi = $this->trova( $html, '//nav//li' );

		$this->assertSame( 2, $passi->length );
		$this->assertSame( $base, $this->trova( $html, '//nav//a' )->item( 0 )->getAttribute( 'href' ), 'Il primo passo porta alla radice.' );
		$this->assertSame( 'page', $this->trova( $html, '//nav//span' )->item( 0 )->getAttribute( 'aria-current' ) );

		$figli = $this->trova( $html, '//ul[@class="trasparenza-pa-voci"]/li/a' );

		$this->assertSame( 2, $figli->length );
		$this->assertSame( add_query_arg( 'voce', 'zeta_uno', $base ), $figli->item( 0 )->getAttribute( 'href' ) );
		$this->assertSame( add_query_arg( 'voce', 'zeta_due', $base ), $figli->item( 1 )->getAttribute( 'href' ) );
	}

	/**
	 * T-17: una voce che non esiste risponde 404, non con la radice.
	 */
	public function test_t17_voce_sconosciuta_404(): void {
		$this->assertSame( '', $this->visita( 'inesistente' ) );
		$this->assertTrue( is_404() );
	}

	/**
	 * T-17: un parametro che non e una stringa vale come voce sconosciuta.
	 */
	public function test_t17_parametro_non_stringa_404(): void {
		$this->go_to( add_query_arg( 'voce[]', 'zeta', get_permalink( $this->pagina ) ) );

		PaginaPubblica::da_template_redirect();

		$this->assertTrue( is_404() );
	}

	/**
	 * T-18: nessun noindex, e il componente non tocca gli strumenti che lo mettono.
	 */
	public function test_t18_pagina_indicizzabile(): void {
		$this->visita( 'zeta' );

		ob_start();
		wp_robots();
		$meta = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'noindex', $meta );

		global $wp_filter;

		foreach ( array( 'wp_robots', 'wp_headers', 'send_headers' ) as $aggancio ) {
			if ( ! isset( $wp_filter[ $aggancio ] ) ) {
				continue;
			}

			foreach ( $wp_filter[ $aggancio ]->callbacks as $richiami ) {
				foreach ( $richiami as $richiamo ) {
					$funzione = $richiamo['function'];
					$nome     = is_array( $funzione ) && is_string( $funzione[0] ) ? $funzione[0] : ( is_string( $funzione ) ? $funzione : '' );

					$this->assertStringStartsNotWith( 'TrasparenzaPa\\', $nome, "Il componente non deve agganciare {$aggancio}." );
				}
			}
		}
	}

	/**
	 * T-18: il sito che chiede di non farsi indicizzare lo si dice a chi amministra.
	 */
	public function test_t18_avviso_se_il_sito_non_si_fa_indicizzare(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertStringNotContainsString( 'notice-warning', $this->avvisi_in_bacheca() );

		update_option( 'blog_public', '0' );

		$this->assertStringContainsString( 'notice-warning', $this->avvisi_in_bacheca() );
	}

	/**
	 * T-19: albero assente, avviso riservato e niente albero disegnato.
	 */
	public function test_t19_albero_non_disponibile(): void {
		PaginaPubblica::fissa_file( __DIR__ . '/dati/non-esiste.json' );
		PaginaPubblica::da_init();

		$this->assertNull( PaginaPubblica::albero() );

		$html = $this->visita();

		$this->assertSame( array(), $this->testi( $html, '//*[@class="trasparenza-pa"]' ) );
		$this->assertStringNotContainsString( '[' . PaginaPubblica::CODICE_BREVE . ']', $html, 'Il codice breve non deve uscire come testo.' );

		// La bacheca non e la pagina appena visitata: WordPress legge l'articolo corrente per i suoi avvisi.
		$GLOBALS['post'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- si esce dalla pagina visitata.

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertStringNotContainsString( 'notice-error', $this->avvisi_in_bacheca() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertStringContainsString( 'notice-error', $this->avvisi_in_bacheca() );
		$this->assertStringContainsString( \TrasparenzaPa\NOME, $this->avvisi_in_bacheca() );
	}
}
