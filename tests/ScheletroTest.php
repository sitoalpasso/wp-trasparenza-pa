<?php
/**
 * Test di scheletro: verificano che l'ambiente rispetti le versioni minime dichiarate
 * e che il componente non introduca assunzioni sul fuso orario.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

/**
 * Verifiche di base sull'ambiente di esecuzione.
 */
class Test_Scheletro extends WP_UnitTestCase {

	/**
	 * La versione di PHP non e inferiore a quella dichiarata nell'intestazione.
	 */
	public function test_versione_php_minima(): void {
		$this->assertTrue(
			version_compare( PHP_VERSION, \TrasparenzaPa\PHP_MINIMO, '>=' ),
			'PHP in esecuzione inferiore alla versione minima dichiarata.'
		);
	}

	/**
	 * La versione di WordPress non e inferiore a quella dichiarata nell'intestazione.
	 */
	public function test_versione_wordpress_minima(): void {
		$this->assertTrue(
			version_compare( get_bloginfo( 'version' ), \TrasparenzaPa\WORDPRESS_MINIMO, '>=' ),
			'WordPress in esecuzione inferiore alla versione minima dichiarata.'
		);
	}

	/**
	 * Il fuso orario si legge sempre dalla configurazione del sito.
	 *
	 * Il componente non deve mai cablare un fuso: questo test fissa la funzione da usare
	 * e fallisce se in futuro sparisse dal contratto di WordPress.
	 */
	public function test_fuso_orario_dal_sito(): void {
		$this->assertInstanceOf( DateTimeZone::class, wp_timezone() );
	}

	/**
	 * Il file principale del plugin e caricato.
	 */
	public function test_componente_caricato(): void {
		$this->assertTrue( defined( 'TrasparenzaPa\VERSIONE' ), 'Il file principale del componente non risulta caricato.' );
	}
}
