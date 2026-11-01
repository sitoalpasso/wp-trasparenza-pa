<?php
/**
 * Prove delle voci visibili ai motori di ricerca: righe T-152..T-153.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Avvio;
use TrasparenzaPa\IndicizzazioneVoci;
use TrasparenzaPa\PaginaPubblica;
use WP_UnitTestCase;

/**
 * Ogni voce ha il suo indirizzo canonico e sta nella mappa del sito.
 */
class IndicizzazioneVociTest extends WP_UnitTestCase {

	/**
	 * Pagina della sezione.
	 *
	 * @var int
	 */
	private $pagina;

	/**
	 * Sezione registrata, albero finto, indirizzi parlanti, una pagina della sezione.
	 */
	public function set_up(): void {
		parent::set_up();

		\Conformita_Core_Sezioni::azzera();
		Avvio::azzera();
		Avvio::esegui();

		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-finto.json' );
		PaginaPubblica::da_init();
		update_option( 'blog_public', '1' );

		$this->set_permalink_structure( '/%postname%/' );
		$this->pagina = $this->pagina( 'Sezione', '[' . PaginaPubblica::CODICE_BREVE . ']' );
	}

	/**
	 * Rimette l'albero finto e i filtri.
	 */
	public function tear_down(): void {
		global $wpdb;

		remove_all_filters( 'query' );
		$wpdb->suppress_errors( false );
		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-finto.json' );
		PaginaPubblica::da_init();

		parent::tear_down();
	}

	/**
	 * Una pagina.
	 *
	 * @param string $titolo  Titolo.
	 * @param string $testo   Testo.
	 * @param array  $altro   Altri campi.
	 * @return int
	 */
	private function pagina( string $titolo, string $testo, array $altro = array() ): int {
		return (int) self::factory()->post->create(
			array_merge(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $titolo,
					'post_content' => $testo,
				),
				$altro
			)
		);
	}

	/**
	 * L'indirizzo canonico di una pagina aperta a un indirizzo.
	 *
	 * @param int    $pagina    Pagina.
	 * @param string $indirizzo Indirizzo.
	 * @return string
	 */
	private function canonico( int $pagina, string $indirizzo ): string {
		$this->go_to( $indirizzo );

		return (string) wp_get_canonical_url( $pagina );
	}

	/**
	 * Gli indirizzi della mappa della sezione.
	 *
	 * @return array<int, string>
	 */
	private function mappa(): array {
		$fornitore = wp_sitemaps_get_server()->registry->get_provider( IndicizzazioneVoci::FORNITORE );
		$this->assertInstanceOf( \WP_Sitemaps_Provider::class, $fornitore );

		return wp_list_pluck( $fornitore->get_url_list( 1 ), 'loc' );
	}

	/**
	 * T-152. Ogni voce ha come indirizzo canonico il proprio.
	 */
	public function test_t152_canonico() {
		$base = (string) get_permalink( $this->pagina );
		$voce = add_query_arg( PaginaPubblica::PARAMETRO_VOCE, 'zeta_uno', $base );

		$this->assertSame( $voce, $this->canonico( $this->pagina, $voce ) );
		$this->assertSame( $base, $this->canonico( $this->pagina, add_query_arg( PaginaPubblica::PARAMETRO_VOCE, 'radice', $base ) ) );
		$this->assertSame( $base, $this->canonico( $this->pagina, $base ) );
		$this->assertSame( $base, $this->canonico( $this->pagina, add_query_arg( PaginaPubblica::PARAMETRO_VOCE, 'inesistente', $base ) ) );
		$this->assertSame( $base, $this->canonico( $this->pagina, add_query_arg( PaginaPubblica::PARAMETRO_VOCE, '"><script>x</script>', $base ) ) );

		// Il canonico stampato dal sorgente.
		$this->go_to( $voce );
		ob_start();
		rel_canonical();
		$this->assertSame( '<link rel="canonical" href="' . esc_url( $voce ) . '" />' . "\n", ob_get_clean() );

		// Una pagina senza il codice breve non cambia.
		$altra = $this->pagina( 'Altra', 'Testo' );
		$base2 = (string) get_permalink( $altra );
		$this->assertSame( $base2, $this->canonico( $altra, add_query_arg( PaginaPubblica::PARAMETRO_VOCE, 'zeta_uno', $base2 ) ) );

		// Albero non disponibile: niente cambia.
		PaginaPubblica::fissa_file( __DIR__ . '/dati/non-esiste.json' );
		PaginaPubblica::da_init();
		$this->assertSame( $base, $this->canonico( $this->pagina, $voce ) );
	}

	/**
	 * T-153. La mappa della sezione elenca ogni voce di ogni pagina della sezione.
	 */
	public function test_t153_mappa() {
		global $wpdb;

		$seconda = $this->pagina( 'Seconda', 'Prima [' . PaginaPubblica::CODICE_BREVE . '] dopo' );
		$this->pagina( 'Bozza', '[' . PaginaPubblica::CODICE_BREVE . ']', array( 'post_status' => 'draft' ) );
		$this->pagina( 'Riservata', '[' . PaginaPubblica::CODICE_BREVE . ']', array( 'post_password' => 'segreta' ) );
		$this->pagina( 'Somigliante', '[' . PaginaPubblica::CODICE_BREVE . '_altro]' );

		$albero = PaginaPubblica::albero();
		$attesi = array();

		foreach ( array( $this->pagina, $seconda ) as $pagina ) {
			foreach ( $albero->identificativi() as $voce ) {
				if ( $voce !== $albero->radice() ) {
					$attesi[] = PaginaPubblica::indirizzo( (string) get_permalink( $pagina ), $voce, $albero->radice() );
				}
			}
		}

		$this->assertNotEmpty( $attesi );
		$this->assertSame( $attesi, $this->mappa() );
		$this->assertContains( IndicizzazioneVoci::FORNITORE, array_keys( wp_get_sitemap_providers() ) );

		// Lettura fallita: nessun indirizzo.
		$wpdb->suppress_errors( true );
		add_filter(
			'query',
			static function ( $sql ) use ( $wpdb ) {
				return false !== strpos( (string) $sql, 'post_content LIKE' ) ? 'SELECT ID FROM ' . $wpdb->posts . '_inesistente' : $sql;
			}
		);
		$this->assertSame( array(), $this->mappa() );
		remove_all_filters( 'query' );
		$wpdb->suppress_errors( false );

		// Albero non disponibile: nessun indirizzo.
		PaginaPubblica::fissa_file( __DIR__ . '/dati/non-esiste.json' );
		PaginaPubblica::da_init();
		$this->assertSame( array(), $this->mappa() );
	}
}
