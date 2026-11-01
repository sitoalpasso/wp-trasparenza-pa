<?php
/**
 * Prove del controllo sul file robots.txt: righe T-154..T-155.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Avvio;
use TrasparenzaPa\ControlloRobots;
use TrasparenzaPa\PaginaPubblica;
use WP_UnitTestCase;

/**
 * Il file robots.txt non deve chiudere la sezione, o almeno chi amministra lo sa.
 */
class ControlloRobotsTest extends WP_UnitTestCase {

	use AmbienteTrasparenza;

	/**
	 * Pagina della sezione.
	 *
	 * @var int
	 */
	private $pagina;

	/**
	 * File di prova da togliere.
	 *
	 * @var array<int, string>
	 */
	private $da_togliere = array();

	/**
	 * Sezione registrata, albero finto, indirizzi parlanti, una pagina, nessun file vero.
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
		$this->pagina = (int) self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Sezione',
				'post_name'    => 'sezione',
				'post_content' => '[' . PaginaPubblica::CODICE_BREVE . ']',
			)
		);

		ControlloRobots::fissa_file( $this->file( null ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Toglie i file di prova e rimette il file predefinito.
	 */
	public function tear_down(): void {
		ControlloRobots::fissa_file( null );
		remove_all_filters( 'robots_txt' );
		remove_all_actions( 'do_robotstxt' );

		foreach ( $this->da_togliere as $percorso ) {
			if ( is_dir( $percorso ) ) {
				rmdir( $percorso ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- file di prova.
			} elseif ( file_exists( $percorso ) ) {
				wp_delete_file( $percorso );
			}
		}

		parent::tear_down();
	}

	/**
	 * Un percorso di prova per il file vero, con il testo dato o assente.
	 *
	 * @param string|null $testo Testo, nullo per un file che non c'e.
	 * @return string
	 */
	private function file( ?string $testo ): string {
		$percorso            = get_temp_dir() . 'robots-' . wp_generate_password( 8, false ) . '.txt';
		$this->da_togliere[] = $percorso;

		if ( null !== $testo ) {
			file_put_contents( $percorso, $testo ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- file di prova.
		}

		return $percorso;
	}

	/**
	 * T-154. Le regole di lettura del file.
	 */
	public function test_t154_regole() {
		$casi = array(
			'regola sulla pagina'             => array( "User-agent: *\nDisallow: /sezione/", '/sezione/', 'Disallow: /sezione/' ),
			'prefisso'                        => array( "User-agent: *\nDisallow: /sez", '/sezione/', 'Disallow: /sez' ),
			'altra pagina'                    => array( "User-agent: *\nDisallow: /altra/", '/sezione/', null ),
			'regola sui parametri'            => array( "User-agent: *\nDisallow: /*?", '/sezione/?voce=zeta', 'Disallow: /*?' ),
			'parametri, pagina senza'         => array( "User-agent: *\nDisallow: /*?", '/sezione/', null ),
			'allow piu lungo'                 => array( "User-agent: *\nDisallow: /\nAllow: /sezione/", '/sezione/', null ),
			'disallow piu lungo'              => array( "User-agent: *\nAllow: /\nDisallow: /sezione/", '/sezione/', 'Disallow: /sezione/' ),
			'parita, vince allow'             => array( "User-agent: *\nDisallow: /sezione/\nAllow: /sezione/", '/sezione/', null ),
			'fine indirizzo, non chiude'      => array( "User-agent: *\nDisallow: /sezione/$", '/sezione/?voce=zeta', null ),
			'fine indirizzo, chiude'          => array( "User-agent: *\nDisallow: /sezione/$", '/sezione/', 'Disallow: /sezione/$' ),
			'asterisco in mezzo'              => array( "User-agent: *\nDisallow: /*zione/", '/sezione/', 'Disallow: /*zione/' ),
			'disallow vuoto'                  => array( "User-agent: *\nDisallow:", '/sezione/', null ),
			'solo un motore'                  => array( "User-agent: Googlebot\nDisallow: /", '/sezione/', null ),
			'piu user-agent in fila'          => array( "User-agent: Googlebot\nUser-agent: *\nDisallow: /", '/sezione/', 'Disallow: /' ),
			'gruppo dopo un altro'            => array( "User-agent: *\nAllow: /x\nUser-agent: Googlebot\nDisallow: /", '/sezione/', null ),
			'due gruppi per tutti'            => array( "User-agent: *\nAllow: /x\n\nUser-agent: *\nDisallow: /sezione", '/sezione/', 'Disallow: /sezione' ),
			'commenti'                        => array( "# Disallow: /\nUser-agent: * # tutti\nDisallow: /sezione/ # chiusa", '/sezione/', 'Disallow: /sezione/' ),
			'maiuscole nei nomi'              => array( "USER-AGENT: *\nDISALLOW: /sezione/", '/sezione/', 'Disallow: /sezione/' ),
			'maiuscole nel percorso contano'  => array( "User-agent: *\nDisallow: /Sezione/", '/sezione/', null ),
			'righe finite con a capo windows' => array( "User-agent: *\r\nDisallow: /sezione/\r\n", '/sezione/', 'Disallow: /sezione/' ),
			'punto nel percorso'              => array( "User-agent: *\nDisallow: /s.zione/", '/sezione/', null ),
		);

		foreach ( $casi as $nome => $caso ) {
			$this->assertSame( $caso[2], ControlloRobots::riga_che_chiude( $caso[0], $caso[1] ), $nome );
		}
	}

	/**
	 * T-155. L'avviso, dal file virtuale e dal file vero.
	 */
	public function test_t155_avviso() {
		$this->assertStringNotContainsString( 'robots.txt', $this->avvisi_in_bacheca(), 'Il file predefinito di WordPress non chiude la sezione.' );

		// Riga aggiunta dal filtro di WordPress: chiude le voci.
		add_filter(
			'robots_txt',
			static function ( $testo ) {
				return $testo . "Disallow: /*?\n";
			}
		);
		$avvisi = $this->avvisi_in_bacheca();
		$this->assertStringContainsString( 'robots.txt', $avvisi );
		$this->assertStringContainsString( 'Sezione', $avvisi );
		$this->assertStringContainsString( 'Disallow: /*?', $avvisi );
		$this->assertStringContainsString( 'le sue voci', $avvisi );
		remove_all_filters( 'robots_txt' );

		// Riga scritta da un componente sull'azione di WordPress: chiude la pagina.
		add_action(
			'do_robotstxt',
			static function () {
				echo "User-agent: *\nDisallow: /sezione/\n";
			}
		);
		$avvisi = $this->avvisi_in_bacheca();
		$this->assertStringContainsString( 'Disallow: /sezione/', $avvisi );
		$this->assertStringContainsString( 'la pagina', $avvisi );
		remove_all_actions( 'do_robotstxt' );

		// Il file vero vince su quello virtuale.
		add_filter(
			'robots_txt',
			static function ( $testo ) {
				return $testo . "Disallow: /sezione/\n";
			}
		);
		ControlloRobots::fissa_file( $this->file( "User-agent: *\nDisallow:\n" ) );
		$this->assertStringNotContainsString( 'robots.txt', $this->avvisi_in_bacheca() );
		remove_all_filters( 'robots_txt' );
		ControlloRobots::fissa_file( $this->file( "User-agent: *\nDisallow: /\n" ) );
		$this->assertStringContainsString( 'Disallow: /', $this->avvisi_in_bacheca() );

		// File vero non leggibile.
		$cartella            = get_temp_dir() . 'robots-cartella-' . wp_generate_password( 8, false );
		$this->da_togliere[] = $cartella;
		mkdir( $cartella ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- file di prova.
		ControlloRobots::fissa_file( $cartella );
		$this->assertStringContainsString( 'non si è potuto leggere', $this->avvisi_in_bacheca() );

		// A chi non puo rimediare, niente.
		ControlloRobots::fissa_file( $this->file( "User-agent: *\nDisallow: /\n" ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertStringNotContainsString( 'robots.txt', $this->avvisi_in_bacheca() );
	}

	/**
	 * T-155. [attacco] Il titolo della pagina con codice esce come testo.
	 */
	public function test_t155_escaping() {
		wp_update_post(
			array(
				'ID'         => $this->pagina,
				'post_title' => 'Sezione <b>x</b>',
			)
		);
		ControlloRobots::fissa_file( $this->file( "User-agent: *\nDisallow: /sezione/\n" ) );

		$avvisi = $this->avvisi_in_bacheca();
		$this->assertStringContainsString( 'Sezione &lt;b&gt;x&lt;/b&gt;', $avvisi );
		$this->assertStringNotContainsString( '<b>x</b>', $avvisi );
	}
}
