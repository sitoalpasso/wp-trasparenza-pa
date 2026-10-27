<?php
/**
 * Avvio del componente e dichiarazione delle politiche della sezione.
 *
 * Righe di collaudo T-01..T-10.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Porta il componente da "caricato" a "operativo", oppure spiega perche non ci arriva.
 *
 * Tre passaggi in ordine fisso: il meccanismo comune deve esserci, la sua
 * interfaccia deve essere compatibile, la sezione deve risultare registrata con
 * le due politiche dichiarate per intero. Il primo che non riesce ferma gli
 * altri, perche ciascuno e la precondizione del successivo.
 */
final class Avvio {

	/**
	 * L'avvio e gia stato tentato in questa richiesta.
	 *
	 * L'idempotenza poggia su questo stato interno e non sul confronto fra le
	 * politiche gia registrate e quelle attese: l'uguaglianza dei valori direbbe
	 * che qualcuno ha registrato la sezione con quelle politiche, non che sia
	 * stato questo componente. Righe T-08 e T-09.
	 *
	 * @var bool
	 */
	private static $eseguito = false;

	/**
	 * Esito del primo tentativo, restituito ai successivi.
	 *
	 * @var true|false|WP_Error
	 */
	private static $esito = false;

	/**
	 * La sezione e stata registrata da questo componente.
	 *
	 * @var bool
	 */
	private static $registrata = false;

	/**
	 * Avvisi da mostrare nella bacheca, in ordine di emissione.
	 *
	 * @var array<int, string>
	 */
	private static $avvisi = array();

	/**
	 * Aggancio a `plugins_loaded`.
	 *
	 * Il valore di ritorno si ignora: a componenti caricati non c'e piu nessuno
	 * a cui restituirlo, e l'esito e gia in un avviso leggibile da chi puo
	 * rimediare.
	 */
	public static function da_plugins_loaded(): void {
		self::esegui();
	}

	/**
	 * Esegue l'avvio, una volta sola per richiesta.
	 *
	 * Valore restituito: `true` se l'avvio e arrivato in fondo; il WP_Error
	 * del meccanismo comune quando e quello a rifiutare, cosi il codice
	 * dell'errore non si perde; `false` quando e questo componente a fermarsi da
	 * se, cioe quando il meccanismo comune non e caricato o quando la sua
	 * guardia di versione lo ha disattivato, casi in cui nessun WP_Error
	 * esiste.
	 *
	 * @return true|false|WP_Error
	 */
	public static function esegui() {
		if ( self::$eseguito ) {
			return self::$esito;
		}

		self::$eseguito = true;
		self::$esito    = self::tenta();

		return self::$esito;
	}

	/**
	 * La sezione risulta registrata da questo componente.
	 *
	 * Distinta da `conformita_core_sezione_registrata()`, che dice se la sezione
	 * esiste nel registro comune senza dire chi ce l'ha messa.
	 *
	 * @return bool
	 */
	public static function registrata(): bool {
		return self::$registrata;
	}

	/**
	 * Riporta la classe allo stato che ha al caricamento del file.
	 *
	 * @internal Serve alle prove, che girano tutte nello stesso processo: uno
	 *           stato lasciato dietro renderebbe verde o rossa la prova
	 *           successiva per un motivo che non c'entra.
	 */
	public static function azzera(): void {
		self::$eseguito   = false;
		self::$esito      = false;
		self::$registrata = false;
		self::$avvisi     = array();
	}

	/**
	 * Mostra gli avvisi accumulati.
	 *
	 * Riservati a chi puo attivare i componenti: si rimedia a tutte queste
	 * condizioni installando, aggiornando o disattivando un componente, e chi
	 * non puo farlo leggerebbe un guasto che non e in grado di riparare.
	 */
	public static function mostra_avvisi(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		foreach ( self::$avvisi as $messaggio ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html( $messaggio )
			);
		}
	}

	/**
	 * I tre passaggi dell'avvio.
	 *
	 * @return true|false|WP_Error
	 */
	private static function tenta() {
		if ( ! function_exists( 'conformita_core_avvia_componente' ) ) {
			self::avvisa(
				sprintf(
					/* translators: 1: nome del componente, 2: versione di interfaccia richiesta, 3: nome del componente comune. */
					__( '%1$s resta attivo ma inerte: richiede la versione %2$s dell\'interfaccia di %3$s, e nessuna versione risulta disponibile perché %3$s non è caricato. Installare e attivare %3$s, poi ricaricare la pagina.', 'trasparenza-pa' ),
					NOME,
					CORE_API_RICHIESTA,
					CORE_NOME
				)
			);

			return false;
		}

		/*
		 * La guardia di versione appartiene al meccanismo comune, ed e l'unico
		 * punto in cui questo componente puo essere disattivato: se la versione
		 * non e compatibile lo disattiva quel componente e scrive lui l'avviso,
		 * con la versione richiesta e quella trovata.
		 */
		if ( ! conformita_core_avvia_componente( NOME, plugin_basename( FILE_PRINCIPALE ), CORE_API_RICHIESTA ) ) {
			return false;
		}

		$esito = conformita_core_registra_sezione(
			SEZIONE,
			array(
				'indicizzazione' => INDICIZZAZIONE,
				'scadenza'       => SCADENZA,
			)
		);

		if ( true === $esito ) {
			self::$registrata = true;

			return true;
		}

		/*
		 * Registrazione rifiutata: il componente resta attivo e inerte. Non si
		 * disattiva da se, perche un componente disattivato non viene piu
		 * caricato e non puo piu segnalare la condizione. Il motivo arriva dal
		 * meccanismo comune e si riporta com'e.
		 */
		self::avvisa(
			sprintf(
				/* translators: 1: nome del componente, 2: identificativo della sezione, 3: motivo riportato dal meccanismo comune. */
				__( '%1$s resta attivo ma inerte: la sezione %2$s non è stata registrata. %3$s', 'trasparenza-pa' ),
				NOME,
				SEZIONE,
				$esito->get_error_message()
			)
		);

		return $esito;
	}

	/**
	 * Accoda un avviso e assicura l'aggancio che lo mostra.
	 *
	 * L'aggancio si registra una volta sola e legge la coda al momento di
	 * stampare: azzerare la coda basta a togliere gli avvisi.
	 *
	 * @param string $messaggio Testo dell'avviso.
	 */
	private static function avvisa( string $messaggio ): void {
		self::$avvisi[] = $messaggio;

		if ( ! has_action( 'admin_notices', array( self::class, 'mostra_avvisi' ) ) ) {
			add_action( 'admin_notices', array( self::class, 'mostra_avvisi' ) );
		}
	}
}
