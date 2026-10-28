<?php
/**
 * Il permesso di amministrare la sezione e la sua prima assegnazione.
 *
 * Righe di collaudo T-26 e T-27.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Chi puo dichiarare lo stato delle voci.
 *
 * Il permesso e del componente e non deriva da un tipo di contenuto, perche lo
 * stato di una voce non e un contenuto: e una dichiarazione dell'amministrazione
 * sulla sezione. Non e `manage_options`, cosi l'ente puo darlo al responsabile
 * della trasparenza senza dargli il resto delle impostazioni del sito.
 */
final class Permessi {

	/**
	 * Il permesso di dichiarare lo stato delle voci.
	 */
	const AMMINISTRAZIONE = 'trasparenza_pa_amministrazione';

	/**
	 * Opzione che ricorda la prima assegnazione, con la versione che l'ha fatta.
	 *
	 * Finche manca, l'assegnazione si ritenta a ogni richiesta; quando c'e, non
	 * si rifa piu.
	 */
	const OPZIONE_ASSEGNATO = 'trasparenza_pa_permessi_assegnati';

	/**
	 * Il ruolo che riceve il permesso alla prima installazione.
	 */
	const RUOLO_INIZIALE = 'administrator';

	/**
	 * Aggancio a `init`.
	 *
	 * Senza sezione registrata il componente e inerte e non tocca i ruoli.
	 */
	public static function da_init(): void {
		if ( ! Avvio::registrata() ) {
			return;
		}

		self::assegna();
	}

	/**
	 * Assegna il permesso all'amministratore, una volta sola.
	 *
	 * **Una volta sola, non a ogni aggiornamento.** Un ente che toglie il
	 * permesso all'amministratore per riservarlo al responsabile della
	 * trasparenza ha fatto una scelta: un aggiornamento che lo ridesse la
	 * cancellerebbe senza dirlo. L'opzione si scrive solo dopo aver visto il
	 * permesso nei ruoli conservati, cosi una scrittura persa si ritenta alla
	 * richiesta successiva invece di risultare fatta per sempre.
	 *
	 * @return true|WP_Error
	 */
	public static function assegna() {
		if ( false !== get_option( self::OPZIONE_ASSEGNATO ) ) {
			return true;
		}

		$ruolo = get_role( self::RUOLO_INIZIALE );

		if ( null === $ruolo ) {
			return new WP_Error( 'trasparenza_permessi_ruolo_assente', __( 'Il ruolo di amministratore non esiste, e il permesso di amministrare la sezione non è stato assegnato a nessuno.', 'trasparenza-pa' ) );
		}

		$ruolo->add_cap( self::AMMINISTRAZIONE );

		/*
		 * Si rilegge dai ruoli conservati e non dall'oggetto in memoria, che
		 * avrebbe il permesso anche se la scrittura fosse andata persa.
		 */
		$conservati = new \WP_Roles();
		$riletto    = $conservati->get_role( self::RUOLO_INIZIALE );

		if ( null === $riletto || ! $riletto->has_cap( self::AMMINISTRAZIONE ) ) {
			return new WP_Error( 'trasparenza_permessi_non_scritti', __( 'Il permesso di amministrare la sezione non risulta scritto nei ruoli: si ritenta alla prossima richiesta.', 'trasparenza-pa' ) );
		}

		update_option( self::OPZIONE_ASSEGNATO, VERSIONE, false );

		return true;
	}

	/**
	 * Nessun ruolo puo dichiarare lo stato delle voci.
	 *
	 * @return bool
	 */
	public static function nessun_ruolo_possiede(): bool {
		foreach ( wp_roles()->role_objects as $ruolo ) {
			if ( $ruolo->has_cap( self::AMMINISTRAZIONE ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Avviso quando nessuno puo dichiarare gli stati.
	 *
	 * Riservato a chi gestisce i componenti, che e chi puo rimediare. Senza
	 * questo avviso un ente che ha tolto il permesso a tutti vedrebbe sparire la
	 * schermata e non saprebbe perche.
	 */
	public static function mostra_avviso(): void {
		if ( ! current_user_can( 'activate_plugins' ) || ! Avvio::registrata() || ! self::nessun_ruolo_possiede() ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: nome del componente, 2: nome del permesso. */
					__( '%1$s: nessun ruolo può dichiarare lo stato delle voci della sezione. Assegnare il permesso %2$s al ruolo del responsabile della trasparenza.', 'trasparenza-pa' ),
					NOME,
					self::AMMINISTRAZIONE
				)
			)
		);
	}
}
