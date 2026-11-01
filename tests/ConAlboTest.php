<?php
/**
 * Prove della convivenza con l'albo pretorio: righe T-148..T-151.
 *
 * Girano solo nella suite della convivenza (`composer test:con-albo`), in un
 * avvio loro, dove componente comune, albo e trasparenza sono caricati e
 * registrati insieme e nessuna prova prima di queste azzera le registrazioni.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\PaginaPubblica;
use WP_UnitTestCase;

/**
 * Albo e trasparenza sullo stesso sito, ciascuno con la sua politica.
 */
class ConAlboTest extends WP_UnitTestCase {

	use AmbienteTrasparenza;

	/**
	 * Pagina dell'albo.
	 *
	 * @var int
	 */
	private $albo;

	/**
	 * Pagina della sezione.
	 *
	 * @var int
	 */
	private $sezione;

	/**
	 * Indirizzi parlanti, una pagina per sezione.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->set_permalink_structure( '/%postname%/' );

		$this->albo    = $this->pagina( 'Albo', '[' . \AlboPretorioPa\PaginaPubblica::CODICE_BREVE . ']' );
		$this->sezione = $this->pagina( 'Trasparenza', '[' . PaginaPubblica::CODICE_BREVE . ']' );

		// L'albo ricorda le sue pagine per la richiesta: qui le pagine sono nuove.
		\AlboPretorioPa\PaginaPubblica::azzera();
	}

	/**
	 * Rimette la richiesta dell'albo.
	 */
	public function tear_down(): void {
		\AlboPretorioPa\PaginaPubblica::azzera();
		\AlboPretorioPa\PaginaPubblica::azzera_richiesta();

		parent::tear_down();
	}

	/**
	 * Una pagina pubblicata.
	 *
	 * @param string $titolo Titolo.
	 * @param string $testo  Testo.
	 * @return int
	 */
	private function pagina( string $titolo, string $testo ): int {
		return (int) self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $titolo,
				'post_content' => $testo,
			)
		);
	}

	/**
	 * Le direttive per i motori e l'intestazione della risposta, per un indirizzo.
	 *
	 * @param string $indirizzo Indirizzo.
	 * @return array{robots: array<string, mixed>, intestazione: string}
	 */
	private function divieti( string $indirizzo ): array {
		\AlboPretorioPa\PaginaPubblica::azzera_richiesta();
		$this->go_to( $indirizzo );

		$intestazioni = apply_filters( 'wp_headers', array(), $GLOBALS['wp'] );
		$robot        = '';

		foreach ( (array) $intestazioni as $nome => $valore ) {
			if ( is_string( $nome ) && 0 === strcasecmp( $nome, 'X-Robots-Tag' ) ) {
				$robot = (string) $valore;
			}
		}

		return array(
			'robots'       => (array) apply_filters( 'wp_robots', array( 'max-image-preview' => 'large' ) ),
			'intestazione' => $robot,
		);
	}

	/**
	 * T-148. Avvio congiunto: tre sezioni, ciascuna con la sua politica.
	 */
	public function test_t148_avvio_congiunto() {
		$this->assertTrue( \AlboPretorioPa\Avvio::registrata(), 'L\'albo si avvia.' );
		$this->assertTrue( \TrasparenzaPa\Avvio::registrata(), 'La trasparenza si avvia.' );

		$attese = array(
			\AlboPretorioPa\SEZIONE         => array( 'vietata', 'irraggiungibile' ),
			\AlboPretorioPa\SEZIONE_STORICO => array( 'vietata', 'irraggiungibile' ),
			\TrasparenzaPa\SEZIONE          => array( 'consentita', 'archivio' ),
		);

		$this->assertEqualsCanonicalizing( array_keys( $attese ), conformita_core_sezioni_registrate() );

		foreach ( $attese as $sezione => $politica ) {
			$letta = conformita_core_politica_sezione( $sezione );
			$this->assertInstanceOf( \Conformita_Core_Politica::class, $letta, $sezione );
			$this->assertSame( $politica, array( $letta->indicizzazione(), $letta->scadenza() ), $sezione );
		}

		$tipi = array(
			\AlboPretorioPa\TIPO          => \AlboPretorioPa\SEZIONE,
			\AlboPretorioPa\TIPO_STORICO  => \AlboPretorioPa\SEZIONE_STORICO,
			\TrasparenzaPa\TIPO_VOCE      => \TrasparenzaPa\SEZIONE,
			\TrasparenzaPa\TIPO_CONTENUTO => \TrasparenzaPa\SEZIONE,
		);

		foreach ( $tipi as $tipo => $sezione ) {
			$this->assertSame( $sezione, conformita_core_sezione_del_tipo( $tipo ), $tipo );
		}

		/*
		 * In bacheca solo gli avvisi dell'albo sulla sua configurazione, come in
		 * T-06 senza l'albo: nessun avviso d'avvio dalla trasparenza o dal
		 * componente comune.
		 */
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$cartella_albo = wp_normalize_path( dirname( (string) \AlboPretorioPa\FILE_PRINCIPALE ) );
		$tolti         = array();

		foreach ( $GLOBALS['wp_filter']['admin_notices']->callbacks as $priorita => $agganci ) {
			foreach ( $agganci as $aggancio ) {
				$funzione = $aggancio['function'];
				$riflesso = is_array( $funzione ) ? new \ReflectionMethod( $funzione[0], $funzione[1] ) : ( is_string( $funzione ) && false !== strpos( $funzione, '::' ) ? new \ReflectionMethod( $funzione ) : new \ReflectionFunction( $funzione ) );

				if ( 0 === strpos( wp_normalize_path( (string) $riflesso->getFileName() ), $cartella_albo . '/' ) ) {
					$tolti[] = array( $funzione, $priorita );
				}
			}
		}

		$this->assertNotEmpty( $tolti, 'L\'albo ha i suoi avvisi: senza, la prova non distingue niente.' );

		foreach ( $tolti as $aggancio ) {
			remove_action( 'admin_notices', $aggancio[0], $aggancio[1] );
		}

		$this->assertSame( '', $this->avvisi_in_bacheca() );
	}

	/**
	 * T-149. Indicizzazione invertita, ciascuna sulle sue pagine.
	 */
	public function test_t149_indicizzazione_invertita() {
		$albo = $this->divieti( (string) get_permalink( $this->albo ) );
		$this->assertTrue( $albo['robots']['noindex'] ?? false, 'Pagina dell\'albo: divieto nel sorgente.' );
		$this->assertStringContainsString( 'noindex', $albo['intestazione'], 'Pagina dell\'albo: divieto nell\'intestazione.' );

		$sezione = $this->divieti( (string) get_permalink( $this->sezione ) );
		$this->assertArrayNotHasKey( 'noindex', $sezione['robots'] );
		$this->assertSame( '', $sezione['intestazione'] );

		$voce = $this->divieti( add_query_arg( PaginaPubblica::PARAMETRO_VOCE, PaginaPubblica::albero()->radice(), (string) get_permalink( $this->sezione ) ) );
		$this->assertArrayNotHasKey( 'noindex', $voce['robots'] );
		$this->assertSame( '', $voce['intestazione'] );

		// Tutte e due sulla stessa pagina: vince il divieto dell'albo.
		$insieme = $this->pagina( 'Insieme', '[' . PaginaPubblica::CODICE_BREVE . '] [' . \AlboPretorioPa\PaginaPubblica::CODICE_BREVE . ']' );
		\AlboPretorioPa\PaginaPubblica::azzera();
		$entrambe = $this->divieti( (string) get_permalink( $insieme ) );
		$this->assertTrue( $entrambe['robots']['noindex'] ?? false );
		$this->assertStringContainsString( 'noindex', $entrambe['intestazione'] );
	}

	/**
	 * T-151. Con l'albo registrato, un contenuto della trasparenza scade con la sua data.
	 */
	public function test_t151_cicli_di_vita() {
		$contenuto = (int) self::factory()->post->create(
			array(
				'post_type'   => \TrasparenzaPa\TIPO_CONTENUTO,
				'post_status' => 'publish',
				'post_title'  => 'Contenuto',
			)
		);
		$elenco    = static function (): array {
			return wp_list_pluck(
				get_posts(
					array(
						'post_type'        => \TrasparenzaPa\TIPO_CONTENUTO,
						'post_status'      => 'publish',
						'suppress_filters' => false,
						'posts_per_page'   => -1,
					)
				),
				'ID'
			);
		};

		$this->assertTrue( conformita_core_sezione_registrata( \AlboPretorioPa\SEZIONE ) );
		$this->assertTrue( conformita_core_imposta_fine_pubblicazione( $contenuto, '2099-12-31' ) );
		$this->assertFalse( conformita_core_scaduto( $contenuto ) );
		$this->assertContains( $contenuto, $elenco() );

		$ieri = ( new \DateTimeImmutable( 'yesterday', wp_timezone() ) )->format( 'Y-m-d' );
		$this->assertTrue( conformita_core_imposta_fine_pubblicazione( $contenuto, $ieri ) );
		$this->assertTrue( conformita_core_scaduto( $contenuto ) );
		$this->assertNotContains( $contenuto, $elenco() );

		// La politica della trasparenza resta la sua, con l'albo accanto.
		$this->assertSame( 'archivio', conformita_core_politica_tipo( \TrasparenzaPa\TIPO_CONTENUTO )->scadenza() );
		$this->assertTrue( conformita_core_sezione_registrata( \AlboPretorioPa\SEZIONE ) );
	}

	/**
	 * T-150. Mappa del sito: la sezione c'e, l'albo no.
	 */
	public function test_t150_mappa_del_sito() {
		$fornitore = wp_sitemaps_get_server()->registry->get_provider( 'posts' );
		$this->assertNotNull( $fornitore );

		$indirizzi = wp_list_pluck( $fornitore->get_url_list( 1, 'page' ), 'loc' );

		$this->assertContains( get_permalink( $this->sezione ), $indirizzi );
		$this->assertNotContains( get_permalink( $this->albo ), $indirizzi );
	}
}
