<?php
/**
 * I vecchi indirizzi delle pagine portate nella sezione.
 *
 * Righe di collaudo T-143..T-146.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Registra l'indirizzo di ogni pagina portata e, quando quella pagina non
 * risponde piu, ne fa un rinvio permanente alla voce attuale del contenuto
 * nella pagina della sezione.
 *
 * Il rinvio scatta solo al posto di "pagina non trovata": finche l'ente tiene
 * pubblicata la pagina vecchia, la pagina vecchia risponde. Porta alla voce e
 * mai al contenuto o ai suoi file, quindi un contenuto scaduto non torna
 * raggiungibile da un indirizzo vecchio. Scheda:
 * `docs/implementazione/importazione-da-pagine.md`, passo 3.
 */
final class VecchiIndirizzi {

	/**
	 * Metadato del contenuto con l'indirizzo della pagina da cui e stato portato.
	 */
	const META_INDIRIZZO = '_trasparenza_pa_indirizzo_origine';

	/**
	 * Opzione con la pagina della sezione, quella con il codice breve.
	 */
	const OPZIONE_SEZIONE = 'trasparenza_pa_pagina_sezione';

	/**
	 * Nome dell'operazione nel registro delle modifiche.
	 */
	const AZIONE_REGISTRO = 'importazione_indirizzo';

	/**
	 * Aggancia il rinvio, prima delle ipotesi di WordPress sugli indirizzi sbagliati.
	 */
	public static function aggancia(): void {
		add_action( 'template_redirect', array( self::class, 'da_template_redirect' ), 5 );
	}

	/**
	 * Aggancio a `template_redirect`: il rinvio, se c'e.
	 */
	public static function da_template_redirect(): void {
		$destinazione = self::rinvio();

		if ( null === $destinazione ) {
			return;
		}

		wp_safe_redirect( $destinazione, 301 );
		exit;
	}

	/**
	 * Il rinvio della richiesta corrente: solo se e finita in "pagina non trovata".
	 *
	 * @return string|null
	 */
	public static function rinvio(): ?string {
		if ( ! is_404() || ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return null;
		}

		/*
		 * Niente sanitize_text_field(): toglierebbe le sequenze %xx, con cui e
		 * scritto il permalink registrato di uno slug codificato. Il percorso va
		 * solo come parametro di un'interrogazione preparata e non si stampa.
		 */
		$chiesto = (string) wp_unslash( $_SERVER['REQUEST_URI'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- vedi sopra.

		return self::destinazione( self::percorso( $chiesto ) );
	}

	/**
	 * Il percorso di un indirizzo rispetto a quello del sito, senza barre ai lati, in minuscolo.
	 *
	 * @param string $indirizzo Indirizzo intero o solo percorso.
	 * @return string
	 */
	public static function percorso( string $indirizzo ): string {
		$percorso = (string) wp_parse_url( $indirizzo, PHP_URL_PATH );
		$sito     = trailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );

		if ( str_starts_with( trailingslashit( $percorso ), $sito ) ) {
			$percorso = substr( trailingslashit( $percorso ), strlen( $sito ) );
		}

		return strtolower( trim( $percorso, '/' ) );
	}

	/**
	 * La destinazione di un percorso registrato, o nulla.
	 *
	 * @param string $percorso Percorso chiesto, gia normalizzato.
	 * @return string|null
	 */
	public static function destinazione( string $percorso ): ?string {
		global $wpdb;

		if ( '' === $percorso ) {
			return null;
		}

		$sezione = self::sezione();

		if ( null === $sezione ) {
			return null;
		}

		$albero = PaginaPubblica::albero();

		if ( null === $albero ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- serve distinguere nessun indirizzo da lettura fallita.
		$contenuto = $wpdb->get_var( $wpdb->prepare( "SELECT m.post_id FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = %s AND m.meta_value = %s AND p.post_type = %s AND p.post_status <> %s ORDER BY m.post_id ASC LIMIT 1", self::META_INDIRIZZO, $percorso, TIPO_CONTENUTO, 'trash' ) );

		if ( '' !== $wpdb->last_error || null === $contenuto ) {
			return null;
		}

		/* Una voce non letta non e una voce assente: il 301 resterebbe nei browser. */
		$valori = Metadato::leggi( (int) $contenuto, SchedaContenuto::META_COLLOCAZIONE );

		if ( is_wp_error( $valori ) ) {
			return null;
		}

		$radice = $albero->radice();
		$voce   = $valori[0] ?? '';
		$voce   = $albero->esiste( $voce ) ? $voce : $radice;
		$verso  = PaginaPubblica::indirizzo( (string) get_permalink( $sezione ), $voce, $radice );

		return self::percorso( $verso ) === $percorso && $voce === $radice ? null : $verso;
	}

	/**
	 * La pagina della sezione conservata, se e ancora una pagina pubblicata con il codice breve.
	 *
	 * @return int|null
	 */
	public static function sezione(): ?int {
		$valore = get_option( self::OPZIONE_SEZIONE );

		if ( ! is_string( $valore ) || 1 !== preg_match( '/\A[1-9][0-9]{0,18}\z/', $valore ) ) {
			return null;
		}

		return self::sezione_valida( (int) $valore ) ? (int) $valore : null;
	}

	/**
	 * Una pagina pubblicata con il codice breve della sezione.
	 *
	 * @param int $pagina Pagina.
	 * @return bool
	 */
	public static function sezione_valida( int $pagina ): bool {
		$pagina = get_post( $pagina );

		return $pagina instanceof \WP_Post && 'page' === $pagina->post_type && 'publish' === $pagina->post_status && '' === $pagina->post_password && has_shortcode( $pagina->post_content, PaginaPubblica::CODICE_BREVE );
	}

	/**
	 * Le pagine che possono fare da pagina della sezione.
	 *
	 * @return array<int, int>|WP_Error
	 */
	public static function sezioni_possibili() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- ricerca del codice breve nel testo, che get_posts() non offre.
		$righe = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND post_content LIKE %s ORDER BY post_title ASC, ID ASC", 'page', 'publish', '%' . $wpdb->esc_like( '[' . PaginaPubblica::CODICE_BREVE ) . '%' ) );

		if ( ! is_array( $righe ) || '' !== $wpdb->last_error ) {
			return new WP_Error( 'trasparenza_importazione_sezioni', __( 'Le pagine con il codice breve non si sono potute leggere dalla banca dati.', 'trasparenza-pa' ) );
		}

		return array_values( array_filter( array_map( 'intval', $righe ), array( self::class, 'sezione_valida' ) ) );
	}

	/**
	 * Gli indirizzi registrati: indirizzo e stato per contenuto, cestino compreso.
	 *
	 * @return array<int, array{indirizzo: string, stato: string}>|WP_Error
	 */
	public static function registrati() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- serve distinguere nessun indirizzo da lettura fallita.
		$righe = $wpdb->get_results( $wpdb->prepare( "SELECT m.post_id, m.meta_value, p.post_status FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = %s AND p.post_type = %s ORDER BY m.post_id ASC, m.meta_id ASC", self::META_INDIRIZZO, TIPO_CONTENUTO ), ARRAY_A );

		if ( ! is_array( $righe ) || '' !== $wpdb->last_error ) {
			return new WP_Error( 'trasparenza_importazione_indirizzi', __( 'Gli indirizzi già registrati non si sono potuti leggere dalla banca dati.', 'trasparenza-pa' ) );
		}

		$registrati = array();

		foreach ( $righe as $riga ) {
			$registrati[ (int) $riga['post_id'] ] ??= array(
				'indirizzo' => (string) $riga['meta_value'],
				'stato'     => (string) $riga['post_status'],
			);
		}

		return $registrati;
	}

	/**
	 * Registra gli indirizzi delle pagine scelte e toglie quelli chiesti.
	 *
	 * Le scelte non si prendono per buone: con il blocco della sezione preso il
	 * rapporto si rifa adesso, e l'indirizzo si ricava dalla pagina, mai dal
	 * modulo. Il rapporto serve solo per registrare: togliere un indirizzo e
	 * cambiare la pagina della sezione si fanno anche dopo che la pagina
	 * d'ingresso vecchia e stata ritirata, quando i rinvii servono davvero.
	 * Chi chiama controlla permessi, nonce e pagina della sezione.
	 *
	 * @param int        $radice  Pagina d'ingresso.
	 * @param int        $sezione Pagina della sezione, gia controllata.
	 * @param array      $pagine  Pagine di cui registrare l'indirizzo.
	 * @param array      $togli   Contenuti da cui togliere l'indirizzo.
	 * @param Alberatura $albero  Albero in vigore.
	 * @param string     $oggi    Oggi, AAAA-MM-GG nel fuso del sito.
	 * @return array<int, array{pagina: int, contenuto: int, titolo: string, indirizzo: string, esito: string, motivo: string}>|WP_Error
	 */
	public static function registra( int $radice, int $sezione, array $pagine, array $togli, Alberatura $albero, string $oggi ) {
		if ( ! Blocco::prendi() ) {
			return new WP_Error( 'trasparenza_importazione_occupato', __( 'Un altro salvataggio della sezione è in corso. Riprovare fra qualche secondo.', 'trasparenza-pa' ) );
		}

		try {
			$rapporto = array( 'pagine' => array() );

			if ( array() !== $pagine ) {
				$rapporto = ImportazionePagine::rapporto( $radice, $albero, $oggi );

				if ( is_wp_error( $rapporto ) ) {
					return $rapporto;
				}
			}

			$registrati = self::registrati();

			if ( is_wp_error( $registrati ) ) {
				return $registrati;
			}

			/* Si rilegge: update_option() dice false sia se non scrive sia se il valore era gia quello. */
			update_option( self::OPZIONE_SEZIONE, (string) $sezione, false );

			if ( Opzione::leggi( self::OPZIONE_SEZIONE ) !== (string) $sezione ) {
				return new WP_Error( 'trasparenza_importazione_sezione_scritta', __( 'La pagina della sezione scelta non si è potuta salvare nella banca dati.', 'trasparenza-pa' ) );
			}

			$righe = array();

			foreach ( $togli as $valore ) {
				$contenuto = self::numero( $valore );
				$righe[]   = isset( $registrati[ $contenuto ] ) ? self::togli( $contenuto, $registrati[ $contenuto ]['indirizzo'] ) : self::riga( 0, $contenuto, '', '', 'scartato', __( 'Il contenuto non ha un indirizzo registrato.', 'trasparenza-pa' ) );

				if ( 'tolto' === end( $righe )['esito'] ) {
					unset( $registrati[ $contenuto ] );
				}
			}

			$per_pagina = array();

			foreach ( $rapporto['pagine'] as $pagina ) {
				$per_pagina[ $pagina['id'] ] = $pagina;
			}

			foreach ( $pagine as $valore ) {
				$id      = self::numero( $valore );
				$pagina  = $per_pagina[ $id ] ?? null;
				$riga    = self::controlla( $id, $pagina, $sezione, $registrati );
				$righe[] = $riga;

				if ( 'registrato' === $riga['esito'] ) {
					$registrati[ $riga['contenuto'] ] = array(
						'indirizzo' => $riga['indirizzo'],
						'stato'     => 'draft',
					);
				}
			}

			return $righe;
		} finally {
			Blocco::rilascia();
		}
	}

	/**
	 * Un identificativo da un valore del modulo, o zero.
	 *
	 * @param mixed $valore Valore.
	 * @return int
	 */
	private static function numero( $valore ): int {
		return is_string( $valore ) && 1 === preg_match( '/\A[1-9][0-9]{0,18}\z/', $valore ) ? (int) $valore : 0;
	}

	/**
	 * Controlla una pagina e, se tutto torna, ne registra l'indirizzo.
	 *
	 * @param int                                                 $id         Pagina chiesta.
	 * @param array<string, mixed>|null                           $pagina     Pagina del rapporto.
	 * @param int                                                 $sezione    Pagina della sezione.
	 * @param array<int, array{indirizzo: string, stato: string}> $registrati Indirizzi registrati.
	 * @return array{pagina: int, contenuto: int, titolo: string, indirizzo: string, esito: string, motivo: string}
	 */
	private static function controlla( int $id, ?array $pagina, int $sezione, array $registrati ): array {
		if ( null === $pagina ) {
			return self::riga( $id, 0, '', '', 'scartato', __( 'La pagina non è fra quelle del rapporto: non pubblicata, protetta da password, fuori dalla pagina d\'ingresso o inesistente.', 'trasparenza-pa' ) );
		}

		$titolo = (string) $pagina['titolo'];

		if ( null === $pagina['portata'] ) {
			return self::riga( $id, 0, $titolo, '', 'scartato', __( 'La pagina non è ancora stata portata in bozza.', 'trasparenza-pa' ) );
		}

		$contenuto = (int) $pagina['portata'];
		$indirizzo = self::percorso( (string) $pagina['indirizzo'] );

		if ( $id === $sezione ) {
			return self::riga( $id, $contenuto, $titolo, $indirizzo, 'scartato', __( 'È la pagina della sezione: un rinvio verso sé stessa girerebbe in tondo.', 'trasparenza-pa' ) );
		}

		if ( in_array( get_post_status( $contenuto ), array( false, 'trash' ), true ) ) {
			return self::riga( $id, $contenuto, $titolo, $indirizzo, 'scartato', __( 'Il contenuto che ha portato la pagina è nel cestino.', 'trasparenza-pa' ) );
		}

		if ( '' === $indirizzo ) {
			return self::riga( $id, $contenuto, $titolo, $indirizzo, 'scartato', __( 'La pagina non ha un indirizzo proprio: il sito usa indirizzi con il numero della pagina, che non cambiano.', 'trasparenza-pa' ) );
		}

		if ( isset( $registrati[ $contenuto ] ) ) {
			return $registrati[ $contenuto ]['indirizzo'] === $indirizzo
				? self::riga( $id, $contenuto, $titolo, $indirizzo, 'gia_registrato', __( 'L\'indirizzo è già registrato.', 'trasparenza-pa' ) )
				: self::riga( $id, $contenuto, $titolo, $indirizzo, 'scartato', __( 'Il contenuto ha già un altro indirizzo registrato: toglierlo prima.', 'trasparenza-pa' ) );
		}

		foreach ( $registrati as $altro ) {
			if ( $altro['indirizzo'] === $indirizzo ) {
				return self::riga( $id, $contenuto, $titolo, $indirizzo, 'scartato', __( 'Lo stesso indirizzo è già registrato per un altro contenuto.', 'trasparenza-pa' ) );
			}
		}

		return self::scrivi( $id, $contenuto, $titolo, $indirizzo );
	}

	/**
	 * Scrive l'indirizzo, dopo la voce di registro, e lo rilegge.
	 *
	 * @param int    $pagina    Pagina d'origine.
	 * @param int    $contenuto Contenuto.
	 * @param string $titolo    Titolo della pagina.
	 * @param string $indirizzo Indirizzo.
	 * @return array{pagina: int, contenuto: int, titolo: string, indirizzo: string, esito: string, motivo: string}
	 */
	private static function scrivi( int $pagina, int $contenuto, string $titolo, string $indirizzo ): array {
		$traccia = self::traccia( $contenuto, 'registrato', $pagina, $indirizzo );

		if ( is_wp_error( $traccia ) ) {
			/* translators: %s: motivo riportato dal registro. */
			return self::riga( $pagina, $contenuto, $titolo, $indirizzo, 'errore', sprintf( __( 'Non registrato: il registro delle modifiche non ha accettato la registrazione. Motivo: %s', 'trasparenza-pa' ), $traccia->get_error_message() ) );
		}

		add_post_meta( $contenuto, self::META_INDIRIZZO, wp_slash( $indirizzo ), true );

		if ( Metadato::leggi( $contenuto, self::META_INDIRIZZO ) !== array( $indirizzo ) ) {
			return self::riga( $pagina, $contenuto, $titolo, $indirizzo, 'errore', __( 'Non risulta che l\'indirizzo sia stato registrato. Il registro delle modifiche contiene una registrazione che potrebbe non essere avvenuta.', 'trasparenza-pa' ) );
		}

		return self::riga( $pagina, $contenuto, $titolo, $indirizzo, 'registrato', '' );
	}

	/**
	 * Toglie l'indirizzo di un contenuto, dopo la voce di registro, e controlla.
	 *
	 * @param int    $contenuto Contenuto.
	 * @param string $indirizzo Indirizzo registrato.
	 * @return array{pagina: int, contenuto: int, titolo: string, indirizzo: string, esito: string, motivo: string}
	 */
	private static function togli( int $contenuto, string $indirizzo ): array {
		$origine = get_post_meta( $contenuto, ImportazionePagine::META_ORIGINE, true );
		$pagina  = is_string( $origine ) && 1 === preg_match( '/\A[1-9][0-9]{0,18}\z/', $origine ) ? (int) $origine : 0;
		$titolo  = get_the_title( $contenuto );
		$traccia = self::traccia( $contenuto, 'tolto', $pagina, $indirizzo );

		if ( is_wp_error( $traccia ) ) {
			/* translators: %s: motivo riportato dal registro. */
			return self::riga( $pagina, $contenuto, $titolo, $indirizzo, 'errore', sprintf( __( 'Non tolto: il registro delle modifiche non ha accettato la rimozione. Motivo: %s', 'trasparenza-pa' ), $traccia->get_error_message() ) );
		}

		delete_post_meta( $contenuto, self::META_INDIRIZZO );

		if ( array() !== Metadato::leggi( $contenuto, self::META_INDIRIZZO ) ) {
			return self::riga( $pagina, $contenuto, $titolo, $indirizzo, 'errore', __( 'Non risulta che l\'indirizzo sia stato tolto. Il registro delle modifiche contiene una rimozione che potrebbe non essere avvenuta.', 'trasparenza-pa' ) );
		}

		return self::riga( $pagina, $contenuto, $titolo, $indirizzo, 'tolto', '' );
	}

	/**
	 * La voce di registro di una registrazione o di una rimozione.
	 *
	 * @param int    $contenuto  Contenuto.
	 * @param string $operazione `registrato` oppure `tolto`.
	 * @param int    $pagina     Pagina d'origine.
	 * @param string $indirizzo  Indirizzo.
	 * @return int|WP_Error
	 */
	private static function traccia( int $contenuto, string $operazione, int $pagina, string $indirizzo ) {
		return conformita_core_registra_voce(
			array(
				'sezione'   => SEZIONE,
				'contenuto' => $contenuto,
				'azione'    => self::AZIONE_REGISTRO,
				'dettagli'  => array(
					'operazione' => $operazione,
					'pagina'     => $pagina,
					'indirizzo'  => $indirizzo,
				),
			)
		);
	}

	/**
	 * Una riga dell'esito.
	 *
	 * @param int    $pagina    Pagina.
	 * @param int    $contenuto Contenuto.
	 * @param string $titolo    Titolo.
	 * @param string $indirizzo Indirizzo.
	 * @param string $esito     Esito.
	 * @param string $motivo    Motivo.
	 * @return array{pagina: int, contenuto: int, titolo: string, indirizzo: string, esito: string, motivo: string}
	 */
	private static function riga( int $pagina, int $contenuto, string $titolo, string $indirizzo, string $esito, string $motivo ): array {
		return array(
			'pagina'    => $pagina,
			'contenuto' => $contenuto,
			'titolo'    => $titolo,
			'indirizzo' => $indirizzo,
			'esito'     => $esito,
			'motivo'    => $motivo,
		);
	}
}
