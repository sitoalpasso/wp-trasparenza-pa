<?php
/**
 * I permessi del componente: sulle voci e sulla sezione.
 *
 * Righe di collaudo T-39, T-46 e T-47.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Chi puo fare cosa sulle voci e sulla sezione.
 *
 * Due famiglie di permessi, con due regole di assegnazione diverse, volute.
 *
 * **I permessi della voce e del contenuto** sono quelli del lavoro di
 * redazione, gli stessi nomi generici per i due tipi. I nomi non
 * sono scritti qui: si chiedono al componente comune, che li ricava
 * dall'identificativo del tipo. Qui stanno solo i nomi generici di WordPress,
 * cioe quali permessi si vogliono. Per ora li riceve il solo amministratore, a
 * ogni cambio della versione installata (vedi `Installazione`), cosi una
 * versione nuova porta con se i permessi nuovi; i ruoli della redazione
 * arrivano con gli stati editoriali.
 *
 * **Il permesso di amministrare la sezione** (`AMMINISTRAZIONE`) e quello di
 * chi dichiara lo stato delle voci e indica il responsabile della
 * trasparenza: dichiarazioni dell'ente, non contenuti. Non deriva
 * da un tipo e non e `manage_options`, cosi l'ente puo darlo al responsabile
 * della trasparenza senza dargli il resto delle impostazioni del sito. Va
 * all'amministratore **una volta sola**, alla prima installazione, e non a
 * ogni versione: un ente che lo toglie all'amministratore per riservarlo al
 * responsabile ha fatto una scelta, e un aggiornamento che lo ridesse la
 * cancellerebbe senza dirlo.
 */
final class Permessi {

	/**
	 * Nomi generici concessi all'amministratore.
	 */
	const CHIAVI = array(
		'edit_posts',
		'edit_others_posts',
		'edit_private_posts',
		'edit_published_posts',
		'publish_posts',
		'read_private_posts',
		'delete_posts',
		'delete_private_posts',
		'delete_published_posts',
		'delete_others_posts',
		'create_posts',
	);

	/**
	 * Il permesso di amministrare la sezione.
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
	 * L'esito dell'ultima assegnazione tentata in questa richiesta.
	 *
	 * @var true|WP_Error|null
	 */
	private static $esito = null;

	/**
	 * Ruoli e nomi generici che ricevono.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function assegnazioni(): array {
		return array( 'administrator' => self::CHIAVI );
	}

	/**
	 * I tipi a cui si applicano i nomi generici.
	 */
	const TIPI = array( TIPO_VOCE, TIPO_CONTENUTO );

	/**
	 * Corrispondenza fra nomi generici e permessi di un tipo.
	 *
	 * @param string $tipo Tipo, la voce se non indicato.
	 * @return array<string, string>|WP_Error
	 */
	public static function mappa( string $tipo = TIPO_VOCE ) {
		if ( ! function_exists( 'conformita_core_capacita_tipo' ) ) {
			return new WP_Error( 'trasparenza_meccanismo_comune_assente', __( 'I permessi della voce si chiedono al componente comune, che non è caricato.', 'trasparenza-pa' ) );
		}

		return conformita_core_capacita_tipo( $tipo );
	}

	/**
	 * Assegna i permessi e rilegge i ruoli per verificare che siano scritti.
	 *
	 * @return true|WP_Error
	 */
	public static function applica() {
		foreach ( self::TIPI as $tipo ) {
			$esito = self::applica_tipo( $tipo );

			if ( is_wp_error( $esito ) ) {
				return $esito;
			}
		}

		return true;
	}

	/**
	 * Assegna i permessi di un tipo e rilegge i ruoli per verificare che siano scritti.
	 *
	 * @param string $tipo Tipo.
	 * @return true|WP_Error
	 */
	private static function applica_tipo( string $tipo ) {
		$mappa = self::mappa( $tipo );

		if ( is_wp_error( $mappa ) ) {
			return $mappa;
		}

		foreach ( self::assegnazioni() as $nome => $chiavi ) {
			$ruolo = get_role( $nome );

			if ( null === $ruolo ) {
				continue;
			}

			foreach ( $chiavi as $chiave ) {
				if ( isset( $mappa[ $chiave ] ) ) {
					$ruolo->add_cap( $mappa[ $chiave ] );
				}
			}
		}

		$riletti = new \WP_Roles();

		foreach ( self::assegnazioni() as $nome => $chiavi ) {
			$ruolo = $riletti->get_role( $nome );

			if ( null === $ruolo ) {
				continue;
			}

			foreach ( $chiavi as $chiave ) {
				if ( isset( $mappa[ $chiave ] ) && ! $ruolo->has_cap( $mappa[ $chiave ] ) ) {
					/* translators: %s: identificativo del ruolo. */
					return new WP_Error( 'trasparenza_permessi_non_scritti', sprintf( __( 'I permessi della voce non risultano scritti sul ruolo %s.', 'trasparenza-pa' ), $nome ) );
				}
			}
		}

		return true;
	}

	/**
	 * Aggancio a `init`.
	 *
	 * Senza sezione registrata il componente e inerte e non tocca i ruoli.
	 */
	public static function da_init(): void {
		if ( ! Avvio::registrata() ) {
			return;
		}

		self::$esito = self::assegna();
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
	 * L'opzione si legge con `Opzione::leggi()` e non con `get_option()`, che
	 * restituisce "assente" anche quando la lettura fallisce: una lettura
	 * fallita ridarebbe all'amministratore il permesso che l'ente gli ha tolto.
	 * Se la lettura fallisce i ruoli non si toccano.
	 *
	 * @return true|WP_Error
	 */
	public static function assegna() {
		$assegnato = Opzione::leggi( self::OPZIONE_ASSEGNATO );

		if ( is_wp_error( $assegnato ) ) {
			return new WP_Error( 'trasparenza_permessi_lettura_fallita', __( 'Non si è potuto leggere se il permesso di amministrare la sezione è già stato assegnato: i ruoli non sono stati toccati, si ritenta alla prossima richiesta.', 'trasparenza-pa' ) );
		}

		if ( null !== $assegnato ) {
			return true;
		}

		$ruolo = get_role( self::RUOLO_INIZIALE );

		if ( null === $ruolo ) {
			return new WP_Error( 'trasparenza_permessi_ruolo_assente', __( 'Il ruolo di amministratore non esiste, e il permesso di amministrare la sezione non è stato assegnato a nessuno.', 'trasparenza-pa' ) );
		}

		$ruolo->add_cap( self::AMMINISTRAZIONE );

		/*
		 * Si rilegge dai ruoli conservati e non da quelli in memoria, che
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
	 * Avviso quando l'assegnazione non e riuscita o nessuno puo dichiarare gli stati.
	 *
	 * Riservato a chi gestisce i componenti, che e chi puo rimediare. Senza
	 * questo avviso un ente che ha tolto il permesso a tutti vedrebbe sparire la
	 * schermata e non saprebbe perche.
	 */
	public static function mostra_avviso(): void {
		if ( ! current_user_can( 'activate_plugins' ) || ! Avvio::registrata() ) {
			return;
		}

		if ( is_wp_error( self::$esito ) ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html( NOME . ': ' . self::$esito->get_error_message() )
			);
		}

		if ( ! self::nessun_ruolo_possiede() ) {
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
