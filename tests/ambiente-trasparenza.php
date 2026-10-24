<?php
/**
 * Aiuti comuni alle prove.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

/**
 * Lettura della bacheca come la vede l'utente corrente.
 */
trait AmbienteTrasparenza {

	/**
	 * Il testo che la bacheca stamperebbe adesso.
	 *
	 * @return string
	 */
	protected function avvisi_in_bacheca(): string {
		ob_start();
		do_action( 'admin_notices' );

		return (string) ob_get_clean();
	}
}
