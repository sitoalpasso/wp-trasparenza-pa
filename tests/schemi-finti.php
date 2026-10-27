<?php
/**
 * Aiuti comuni alle prove del motore degli schemi.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\CatalogoSchemi;

/**
 * Il catalogo finto delle prove e le sue copie guaste.
 */
trait SchemiFinti {

	/**
	 * Indirizzo di base degli identificativi degli schemi finti.
	 *
	 * Un dominio riservato, che non risolve: se la libreria andasse in rete per
	 * un rimando, non troverebbe niente.
	 *
	 * @var string
	 */
	protected static $base_finta = 'https://schemi.esempio.invalid/json';

	/**
	 * Cartelle temporanee da togliere a fine prova.
	 *
	 * @var array<int, string>
	 */
	private $cartelle_temporanee = array();

	/**
	 * Toglie le cartelle temporanee.
	 */
	public function tear_down(): void {
		foreach ( $this->cartelle_temporanee as $cartella ) {
			foreach ( (array) glob( $cartella . '/*' ) as $file ) {
				unlink( $file );
			}

			rmdir( $cartella );
		}

		parent::tear_down();
	}

	/**
	 * Percorso del catalogo finto.
	 *
	 * @return string
	 */
	protected function catalogo_finto(): string {
		return __DIR__ . '/dati/schemi/catalogo.json';
	}

	/**
	 * Copia gli schemi finti in una cartella temporanea e ne restituisce il percorso.
	 *
	 * @return string
	 */
	protected function copia_schemi(): string {
		$cartella = get_temp_dir() . 'schemi-' . wp_generate_password( 12, false );

		mkdir( $cartella );

		foreach ( glob( __DIR__ . '/dati/schemi/*.json' ) as $file ) {
			copy( $file, $cartella . '/' . basename( $file ) );
		}

		$this->cartelle_temporanee[] = $cartella;

		return $cartella;
	}

	/**
	 * Un catalogo con un contenuto qualunque, accanto agli schemi finti.
	 *
	 * @param string $testo Contenuto del catalogo.
	 * @return string
	 */
	protected function catalogo_guasto( string $testo ): string {
		$file = $this->copia_schemi() . '/catalogo.json';

		file_put_contents( $file, $testo );

		return $file;
	}

	/**
	 * Carica una copia del catalogo finto modificata da una funzione.
	 *
	 * @param callable|null         $guasta Modifica del catalogo, o nulla.
	 * @param array<string, string> $altri  File da aggiungere nella cartella.
	 * @return CatalogoSchemi|\WP_Error
	 */
	protected function guasta( ?callable $guasta, array $altri = array() ) {
		$catalogo = json_decode( (string) file_get_contents( $this->catalogo_finto() ), true );

		if ( null !== $guasta ) {
			$catalogo = $guasta( $catalogo );
		}

		$file = $this->catalogo_guasto( (string) wp_json_encode( $catalogo ) );

		foreach ( $altri as $nome => $contenuto ) {
			file_put_contents( dirname( $file ) . '/' . $nome, $contenuto );
		}

		return CatalogoSchemi::carica( $file );
	}
}
