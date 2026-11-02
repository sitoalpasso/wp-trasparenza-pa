<?php
/**
 * Il confronto con la versione installata.
 *
 * Righe di collaudo T-39 e T-162.
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
	 * La lettura della versione installata e fallita in questa richiesta.
	 *
	 * @var \WP_Error|null
	 */
	private static $lettura_fallita = null;

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
		self::$lettura_fallita = null;

		if ( ! TipoVoce::registrato() || VERSIONE === self::versione_installata() ) {
			return false;
		}

		/*
		 * La versione in memoria dice che serve: si conferma sulla banca dati.
		 * Una lettura fallita non vale come "mai installato", perche
		 * riassegnerebbe a ogni richiesta i permessi che l'ente avesse tolto.
		 */
		$scritta = Opzione::leggi( OPZIONE_VERSIONE );

		if ( is_wp_error( $scritta ) ) {
			self::$lettura_fallita = $scritta;

			return false;
		}

		if ( VERSIONE === $scritta ) {
			return false;
		}

		if ( is_wp_error( Permessi::applica() ) ) {
			return false;
		}

		update_option( OPZIONE_VERSIONE, VERSIONE, true );

		return VERSIONE === self::versione_installata();
	}

	/**
	 * Mostra a chi amministra che la versione installata non si e letta.
	 */
	public static function mostra_avviso(): void {
		if ( null === self::$lettura_fallita || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html( NOME . ': ' . __( 'la versione installata non si è potuta leggere dalla banca dati, quindi i permessi non sono stati controllati né riassegnati. Si riprova alla prossima richiesta; se l\'avviso torna, serve un controllo della banca dati.', 'trasparenza-pa' ) )
		);
	}
}
