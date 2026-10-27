<?php
/**
 * Il confronto con la versione installata.
 *
 * Riga di collaudo T-39.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

defined( 'ABSPATH' ) || exit;

/**
 * I permessi si assegnano a ogni cambio di versione, non all'attivazione.
 *
 * Un aggiornamento del componente non passa dall'attivazione: agganciarsi al
 * numero di versione memorizzato e l'unico modo di non perdere i permessi
 * nuovi di una versione nuova.
 */
final class Installazione {

	/**
	 * Aggancio a `init`, dopo la registrazione del tipo.
	 */
	public static function da_init(): void {
		self::aggiorna_se_serve();
	}

	/**
	 * La versione memorizzata sul sito, vuota se il componente non e mai stato installato.
	 *
	 * @return string
	 */
	public static function versione_installata(): string {
		return (string) get_option( OPZIONE_VERSIONE, '' );
	}

	/**
	 * Assegna i permessi e memorizza la versione, se la versione e cambiata.
	 *
	 * La versione si scrive solo a permessi verificati: se qualcosa non va, la
	 * richiesta successiva riprova.
	 *
	 * @return bool Vero se l'aggiornamento e stato eseguito ora.
	 */
	public static function aggiorna_se_serve(): bool {
		if ( ! TipoVoce::registrato() || VERSIONE === self::versione_installata() ) {
			return false;
		}

		if ( is_wp_error( Permessi::applica() ) ) {
			return false;
		}

		update_option( OPZIONE_VERSIONE, VERSIONE, true );

		return VERSIONE === self::versione_installata();
	}
}
