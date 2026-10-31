<?php
/**
 * Il rapporto che precede il passaggio di una sezione tenuta come pagine WordPress.
 *
 * Righe di collaudo T-122..T-126.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Legge le pagine sotto una pagina d'ingresso e dice cosa si porterebbe, e dove.
 *
 * Non scrive niente. Legge i file solo dal disco e solo dentro la cartella dei
 * caricamenti: un indirizzo scritto in una pagina non fa mai chiamare un altro
 * server. Scheda: `docs/implementazione/importazione-da-pagine.md`.
 */
final class ImportazionePagine {

	/**
	 * Il rapporto.
	 *
	 * @param int        $radice Pagina d'ingresso.
	 * @param Alberatura $albero Albero in vigore.
	 * @param string     $oggi   Oggi, AAAA-MM-GG nel fuso del sito.
	 * @return array{radice: int, durata: int|null, pagine: array<int, array<string, mixed>>, voci_senza_pagina: array<int, string>}|WP_Error
	 */
	public static function rapporto( int $radice, Alberatura $albero, string $oggi ) {
		$pagine = self::pagine();

		if ( is_wp_error( $pagine ) ) {
			return $pagine;
		}

		if ( ! isset( $pagine[ $radice ] ) ) {
			return new WP_Error( 'trasparenza_importazione_radice', __( 'La pagina d\'ingresso indicata non è una pagina pubblicata.', 'trasparenza-pa' ) );
		}

		$allegati = self::allegati();

		if ( is_wp_error( $allegati ) ) {
			return $allegati;
		}

		$figli = array();

		foreach ( $pagine as $id => $pagina ) {
			$figli[ $pagina['padre'] ][] = $id;
		}

		$per_percorso = array();
		$per_padre    = array();

		foreach ( $allegati as $allegato ) {
			$per_percorso[ $allegato['percorso'] ] ??= $allegato;
			$per_padre[ $allegato['padre'] ][]       = $allegato;
		}

		$nomi = self::nomi( $albero );
		$base = self::base();

		$durata   = $albero->durata_anni();
		$righe    = array();
		$visti    = array();
		$proposte = array();
		$da_fare  = array( array( $radice, 0 ) );

		while ( array() !== $da_fare ) {
			list( $id, $profondita ) = array_shift( $da_fare );

			if ( isset( $visti[ $id ] ) ) {
				continue;
			}

			$visti[ $id ] = true;
			$pagina       = $pagine[ $id ];
			$voce         = self::voce( $pagina['titolo'], $nomi, $albero );
			$file         = self::file( $pagina['testo'], $per_padre[ $id ] ?? array(), $per_percorso, $base, $durata, $oggi );
			$esterni      = self::esterni( $pagina['testo'] );
			$caratteri    = self::caratteri( $pagina['testo'] );

			if ( 'proposta' === $voce[0] ) {
				$proposte[ $voce[1] ] = true;
			}

			$righe[] = array(
				'id'         => $id,
				'titolo'     => $pagina['titolo'],
				'indirizzo'  => (string) get_permalink( $id ),
				'profondita' => $profondita,
				'esito_voce' => $voce[0],
				'voce'       => $voce[1],
				'caratteri'  => $caratteri,
				'file'       => $file,
				'esterni'    => $esterni,
				'vuota'      => 0 === $caratteri && array() === $file && array() === $esterni,
			);

			// In profondità: i figli subito dopo il genitore, nel loro ordine.
			$sotto = array();

			foreach ( $figli[ $id ] ?? array() as $figlio ) {
				$sotto[] = array( $figlio, $profondita + 1 );
			}

			$da_fare = array_merge( $sotto, $da_fare );
		}

		$senza = array();

		foreach ( SchedaContenuto::voci_ammesse( $albero ) as $voce ) {
			if ( ! isset( $proposte[ $voce ] ) ) {
				$senza[] = $voce;
			}
		}

		return array(
			'radice'            => $radice,
			'durata'            => $durata,
			'pagine'            => $righe,
			'voci_senza_pagina' => $senza,
		);
	}

	/**
	 * La pubblicazione di un file e finita di sicuro.
	 *
	 * L'obbligo nasce prima del caricamento, quindi la fine non cade dopo il 31
	 * dicembre che chiude la durata contata dalla data di caricamento.
	 *
	 * @param string|null $data   AAAA-MM-GG o AAAA-MM, o nulla.
	 * @param int|null    $durata Anni di pubblicazione dall'albero.
	 * @param string      $oggi   AAAA-MM-GG.
	 * @return bool|null Nullo se non si sa.
	 */
	public static function finita( ?string $data, ?int $durata, string $oggi ): ?bool {
		if ( null === $data || null === $durata || 1 !== preg_match( '/\A(\d{4})-\d{2}/', $data, $da ) || 1 !== preg_match( '/\A(\d{4})-/', $oggi, $fino ) ) {
			return null;
		}

		return (int) $da[1] + $durata < (int) $fino[1];
	}

	/**
	 * Un testo scritto per il confronto: minuscolo, senza accenti, simboli come spazi.
	 *
	 * @param string $testo Testo.
	 * @return string
	 */
	public static function normalizza( string $testo ): string {
		$testo = strtolower( remove_accents( html_entity_decode( $testo, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );

		return trim( (string) preg_replace( '/[^a-z0-9]+/', ' ', $testo ) );
	}

	/**
	 * Le pagine pubblicate, per identificativo, nell'ordine delle pagine.
	 *
	 * @return array<int, array{padre: int, titolo: string, testo: string}>|WP_Error
	 */
	private static function pagine() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- serve distinguere nessuna pagina da lettura fallita, cosa che get_pages() non dice.
		$righe = $wpdb->get_results( $wpdb->prepare( "SELECT /* trasparenza-pa-importazione */ ID, post_parent, post_title, post_content FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s ORDER BY menu_order ASC, post_title ASC, ID ASC", 'page', 'publish' ), ARRAY_A );

		if ( ! is_array( $righe ) || '' !== $wpdb->last_error ) {
			return self::errore_lettura();
		}

		$pagine = array();

		foreach ( $righe as $riga ) {
			$pagine[ (int) $riga['ID'] ] = array(
				'padre'  => (int) $riga['post_parent'],
				'titolo' => (string) $riga['post_title'],
				'testo'  => (string) $riga['post_content'],
			);
		}

		return $pagine;
	}

	/**
	 * Gli allegati della libreria con il percorso registrato.
	 *
	 * @return array<int, array{id: int, padre: int, data: string, percorso: string}>|WP_Error
	 */
	private static function allegati() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- serve distinguere nessun allegato da lettura fallita.
		$righe = $wpdb->get_results( $wpdb->prepare( "SELECT /* trasparenza-pa-importazione */ p.ID, p.post_parent, p.post_date, m.meta_value FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s WHERE p.post_type = 'attachment' ORDER BY p.ID ASC, m.meta_id ASC", '_wp_attached_file' ), ARRAY_A );

		if ( ! is_array( $righe ) || '' !== $wpdb->last_error ) {
			return self::errore_lettura();
		}

		$allegati = array();
		$visti    = array();

		foreach ( $righe as $riga ) {
			$id = (int) $riga['ID'];

			if ( isset( $visti[ $id ] ) ) {
				continue;
			}

			$visti[ $id ] = true;
			$allegati[]   = array(
				'id'       => $id,
				'padre'    => (int) $riga['post_parent'],
				'data'     => substr( (string) $riga['post_date'], 0, 10 ),
				'percorso' => (string) $riga['meta_value'],
			);
		}

		return $allegati;
	}

	/**
	 * Errore di lettura.
	 *
	 * @return WP_Error
	 */
	private static function errore_lettura(): WP_Error {
		return new WP_Error( 'trasparenza_importazione_lettura', __( 'Il rapporto non si può fare: la lettura delle pagine non è riuscita.', 'trasparenza-pa' ) );
	}

	/**
	 * Le voci dell'albero per nome scritto per il confronto, radice esclusa.
	 *
	 * @param Alberatura $albero Albero.
	 * @return array<string, array<int, string>>
	 */
	private static function nomi( Alberatura $albero ): array {
		$nomi = array();

		foreach ( $albero->identificativi() as $id ) {
			$voce = $albero->voce( $id );

			if ( $id !== $albero->radice() && null !== $voce ) {
				$nomi[ self::normalizza( (string) $voce['nome'] ) ][] = $id;
			}
		}

		return $nomi;
	}

	/**
	 * L'esito della voce per un titolo.
	 *
	 * @param string                            $titolo Titolo della pagina.
	 * @param array<string, array<int, string>> $nomi   Voci per nome.
	 * @param Alberatura                        $albero Albero.
	 * @return array{0: string, 1: string|null}
	 */
	private static function voce( string $titolo, array $nomi, Alberatura $albero ): array {
		$trovate = $nomi[ self::normalizza( $titolo ) ] ?? array();

		if ( array() === $trovate ) {
			return array( 'nessuna', null );
		}

		if ( count( $trovate ) > 1 ) {
			return array( 'piu_voci', null );
		}

		return array() === $albero->figli( $trovate[0] ) ? array( 'proposta', $trovate[0] ) : array( 'con_sottovoci', $trovate[0] );
	}

	/**
	 * La cartella dei caricamenti: dominio, percorso nell'indirizzo, cartella sul disco.
	 *
	 * @return array{dominio: string, percorso: string, cartella: string}
	 */
	private static function base(): array {
		$caricamenti = wp_upload_dir( null, false );
		$indirizzo   = (string) $caricamenti['baseurl'];
		$dominio     = (string) wp_parse_url( $indirizzo, PHP_URL_HOST );
		$porta       = wp_parse_url( $indirizzo, PHP_URL_PORT );

		return array(
			'dominio'  => strtolower( $dominio . ( null !== $porta ? ':' . $porta : '' ) ),
			'percorso' => untrailingslashit( (string) wp_parse_url( $indirizzo, PHP_URL_PATH ) ),
			'cartella' => untrailingslashit( (string) $caricamenti['basedir'] ),
		);
	}

	/**
	 * I file di una pagina, senza doppioni per percorso.
	 *
	 * @param string                              $testo        Testo della pagina.
	 * @param array<int, array<string, mixed>>    $suoi         Allegati della pagina.
	 * @param array<string, array<string, mixed>> $per_percorso Allegati per percorso.
	 * @param array<string, string>               $base         Cartella dei caricamenti.
	 * @param int|null                            $durata       Durata dall'albero.
	 * @param string                              $oggi         Oggi.
	 * @return array<int, array<string, mixed>>
	 */
	private static function file( string $testo, array $suoi, array $per_percorso, array $base, ?int $durata, string $oggi ): array {
		$percorsi = array();

		if ( '' !== $base['percorso'] ) {
			$schema = '#(?:(?:https?:)?//([A-Za-z0-9.\-]+(?::\d+)?))?' . preg_quote( $base['percorso'], '#' ) . '/([^\s"\'<>\[\]?\#]+)#i';

			if ( preg_match_all( $schema, $testo, $trovati, PREG_SET_ORDER ) ) {
				foreach ( $trovati as $trovato ) {
					if ( '' !== $trovato[1] && strtolower( $trovato[1] ) !== $base['dominio'] ) {
						continue;
					}

					$percorsi[] = rawurldecode( html_entity_decode( $trovato[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
				}
			}
		}

		foreach ( $suoi as $allegato ) {
			$percorsi[] = (string) $allegato['percorso'];
		}

		$file = array();

		foreach ( array_values( array_unique( $percorsi ) ) as $percorso ) {
			$allegato = $per_percorso[ $percorso ] ?? null;
			$data     = null;

			if ( null !== $allegato && 1 === preg_match( '/\A\d{4}-\d{2}-\d{2}\z/', (string) $allegato['data'] ) ) {
				$data = (string) $allegato['data'];
			} elseif ( 1 === preg_match( '#\A(\d{4})/(\d{2})/#', $percorso, $cartella ) ) {
				$data = $cartella[1] . '-' . $cartella[2];
			}

			$file[] = array_merge(
				array(
					'percorso' => $percorso,
					'allegato' => null === $allegato ? null : (int) $allegato['id'],
					'data'     => $data,
					'finita'   => self::finita( $data, $durata, $oggi ),
				),
				self::sul_disco( $percorso, $base['cartella'] )
			);
		}

		return $file;
	}

	/**
	 * Stato, dimensione e impronta di un file, solo dentro la cartella dei caricamenti.
	 *
	 * Un percorso con un segmento `..` e fuori senza chiedere niente al disco; gli
	 * altri si risolvono, collegamenti simbolici compresi, e si aprono solo se
	 * restano dentro la cartella.
	 *
	 * @param string $percorso Percorso relativo o pieno.
	 * @param string $cartella Cartella dei caricamenti.
	 * @return array{stato: string, byte: int|null, impronta: string|null}
	 */
	private static function sul_disco( string $percorso, string $cartella ): array {
		$fuori    = array(
			'stato'    => 'fuori',
			'byte'     => null,
			'impronta' => null,
		);
		$mancante = array_merge( $fuori, array( 'stato' => 'mancante' ) );

		if ( '' === $percorso || str_contains( $percorso, "\0" ) ) {
			return $mancante;
		}

		if ( in_array( '..', preg_split( '#[/\\\\]#', $percorso ), true ) ) {
			return $fuori;
		}

		$radice = realpath( $cartella );
		$pieno  = path_is_absolute( $percorso ) ? $percorso : $cartella . '/' . ltrim( $percorso, '/' );
		$reale  = realpath( $pieno );

		if ( false === $radice || false === $reale ) {
			return $mancante;
		}

		if ( ! str_starts_with( $reale, trailingslashit( $radice ) ) ) {
			return $fuori;
		}

		if ( ! is_file( $reale ) ) {
			return $mancante;
		}

		$byte     = filesize( $reale );
		$impronta = hash_file( 'sha256', $reale );

		if ( false === $byte || false === $impronta ) {
			return $mancante;
		}

		return array(
			'stato'    => 'presente',
			'byte'     => $byte,
			'impronta' => $impronta,
		);
	}

	/**
	 * I collegamenti verso altri domini, una volta ciascuno.
	 *
	 * @param string $testo Testo della pagina.
	 * @return array<int, string>
	 */
	private static function esterni( string $testo ): array {
		$sito    = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$esterni = array();

		if ( preg_match_all( '#https?://[^\s"\'<>\[\]]+#i', $testo, $trovati ) ) {
			foreach ( $trovati[0] as $indirizzo ) {
				$indirizzo = rtrim( html_entity_decode( $indirizzo, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), '.,;:)' );
				$dominio   = strtolower( (string) wp_parse_url( $indirizzo, PHP_URL_HOST ) );

				if ( '' !== $dominio && $dominio !== $sito && ! in_array( $indirizzo, $esterni, true ) ) {
					$esterni[] = $indirizzo;
				}
			}
		}

		return $esterni;
	}

	/**
	 * I caratteri del testo, senza tag e senza cio che sta fra parentesi quadre.
	 *
	 * @param string $testo Testo della pagina.
	 * @return int
	 */
	private static function caratteri( string $testo ): int {
		$testo = (string) preg_replace( '/\[[^\]]*\]/', ' ', $testo );
		$testo = html_entity_decode( wp_strip_all_tags( $testo ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$testo = trim( (string) preg_replace( '/[\s\x{00A0}]+/u', ' ', $testo ) );

		return mb_strlen( $testo );
	}
}
