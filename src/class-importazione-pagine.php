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
	 * Metadato del contenuto che ricorda la pagina da cui e stato portato.
	 */
	const META_ORIGINE = '_trasparenza_pa_pagina_origine';

	/**
	 * Nome dell'operazione nel registro delle modifiche.
	 */
	const AZIONE_REGISTRO = 'importazione_pagina';

	/**
	 * La funzione del componente comune che dice dov'e la sua cartella protetta.
	 *
	 * @var array{0: string, 1: string}
	 */
	private static $cartella_protetta = array( '\\Conformita_Core_Allegati', 'cartella' );

	/**
	 * Sceglie la funzione che dice dov'e la cartella protetta.
	 *
	 * @internal Le prove la puntano su una funzione assente, per il guasto di T-142.
	 *
	 * @param array{0: string, 1: string} $chiamata Classe e funzione.
	 */
	public static function fissa_cartella_protetta( array $chiamata ): void {
		self::$cartella_protetta = $chiamata;
	}

	/**
	 * Il rapporto.
	 *
	 * @param int        $radice Pagina d'ingresso.
	 * @param Alberatura $albero Albero in vigore.
	 * @param string     $oggi   Oggi, AAAA-MM-GG nel fuso del sito.
	 * @return array{radice: int, durata: int|null, pagine: array<int, array<string, mixed>>, non_lette: int, voci_senza_pagina: array<int, string>}|WP_Error
	 *         Ogni pagina porta anche `portata`: il contenuto che l'ha gia portata, o nullo.
	 */
	public static function rapporto( int $radice, Alberatura $albero, string $oggi ) {
		$pagine = self::pagine();

		if ( is_wp_error( $pagine ) ) {
			return $pagine;
		}

		if ( ! isset( $pagine[ $radice ] ) ) {
			return new WP_Error( 'trasparenza_importazione_radice', __( 'La pagina d\'ingresso indicata non è una pagina pubblicata senza password.', 'trasparenza-pa' ) );
		}

		$portate = self::portate();

		if ( is_wp_error( $portate ) ) {
			return $portate;
		}

		$nascoste = self::non_pubblicate();

		if ( is_wp_error( $nascoste ) ) {
			return $nascoste;
		}

		$allegati = self::allegati();

		if ( is_wp_error( $allegati ) ) {
			return $allegati;
		}

		$figli = array();

		foreach ( $pagine as $id => $pagina ) {
			$figli[ $pagina['padre'] ][] = $id;
		}

		$figli_nascosti = array();

		foreach ( $nascoste as $id => $padre ) {
			$figli_nascosti[ $padre ][] = $id;
		}

		$per_percorso = array();
		$per_padre    = array();

		foreach ( $allegati as $allegato ) {
			$per_percorso[ $allegato['percorso'] ] ??= $allegato;
			$per_padre[ $allegato['padre'] ][]       = $allegato;
		}

		$nomi = self::nomi( $albero );
		$base = self::base();

		$durata     = $albero->durata_anni();
		$righe      = array();
		$visti      = array();
		$proposte   = array();
		$da_fare    = array( array( $radice, 0 ) );
		$da_contare = array();

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
				'portata'    => $portate[ $id ] ?? null,
			);

			// In profondità: i figli subito dopo il genitore, nel loro ordine.
			$sotto = array();

			foreach ( $figli[ $id ] ?? array() as $figlio ) {
				$sotto[] = array( $figlio, $profondita + 1 );
			}

			$da_fare    = array_merge( $sotto, $da_fare );
			$da_contare = array_merge( $da_contare, $figli_nascosti[ $id ] ?? array() );
		}

		/*
		 * Sotto una pagina non pubblicata non si legge niente, nemmeno le
		 * pagine pubblicate: si contano, perche chi prepara il passaggio sappia
		 * che il rapporto non le copre.
		 */
		$non_lette = 0;

		while ( array() !== $da_contare ) {
			$id = array_shift( $da_contare );

			if ( isset( $visti[ $id ] ) ) {
				continue;
			}

			$visti[ $id ] = true;
			++$non_lette;
			$da_contare = array_merge( $da_contare, $figli[ $id ] ?? array(), $figli_nascosti[ $id ] ?? array() );
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
			'non_lette'         => $non_lette,
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
	 * Le pagine pubblicate senza password, per identificativo, nell'ordine delle pagine.
	 *
	 * @return array<int, array{padre: int, titolo: string, testo: string}>|WP_Error
	 */
	private static function pagine() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- serve distinguere nessuna pagina da lettura fallita, cosa che get_pages() non dice.
		$righe = $wpdb->get_results( $wpdb->prepare( "SELECT /* trasparenza-pa-importazione */ ID, post_parent, post_title, post_content FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND post_password = '' ORDER BY menu_order ASC, post_title ASC, ID ASC", 'page', 'publish' ), ARRAY_A );

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
	 * Porta in bozza le pagine scelte, ciascuna nella sua voce.
	 *
	 * Le scelte non si prendono per buone: il rapporto si rifa adesso, con il
	 * blocco della sezione preso, e si portano solo le sue pagine, nelle voci
	 * senza sottovoci dell'albero, una volta sola. Chi chiama controlla
	 * permessi e nonce.
	 *
	 * @param int                      $radice Pagina d'ingresso.
	 * @param array<int|string, mixed> $scelte Voce scelta per pagina; vuota vuol dire non portare.
	 * @param Alberatura               $albero Albero in vigore.
	 * @param string                   $oggi   Oggi, AAAA-MM-GG nel fuso del sito.
	 * @return array<int, array{pagina: int, titolo: string, esito: string, contenuto: int|null, motivo: string}>|WP_Error
	 */
	public static function porta( int $radice, array $scelte, Alberatura $albero, string $oggi ) {
		if ( ! Blocco::prendi() ) {
			return new WP_Error( 'trasparenza_importazione_occupato', __( 'Niente portato: un altro salvataggio della sezione è in corso. Riprovare fra qualche secondo.', 'trasparenza-pa' ) );
		}

		try {
			$rapporto = self::rapporto( $radice, $albero, $oggi );

			if ( is_wp_error( $rapporto ) ) {
				return $rapporto;
			}

			$pagine = array();

			foreach ( $rapporto['pagine'] as $pagina ) {
				$pagine[ $pagina['id'] ] = $pagina;
			}

			$ammesse = SchedaContenuto::voci_ammesse( $albero );
			$righe   = array();

			foreach ( $scelte as $chiave => $voce ) {
				if ( '' === $voce ) {
					continue;
				}

				$id     = 1 === preg_match( '/\A[1-9][0-9]{0,18}\z/', (string) $chiave ) ? (int) $chiave : 0;
				$pagina = $pagine[ $id ] ?? null;
				$titolo = null === $pagina ? '' : (string) $pagina['titolo'];

				if ( null === $pagina ) {
					$righe[] = self::riga( $id, $titolo, 'scartata', null, __( 'La pagina non è fra quelle del rapporto: non pubblicata, protetta da password, fuori dalla pagina d\'ingresso o inesistente.', 'trasparenza-pa' ) );
				} elseif ( ! is_string( $voce ) || ! in_array( $voce, $ammesse, true ) ) {
					$righe[] = self::riga( $id, $titolo, 'scartata', null, __( 'La voce scelta non è fra quelle in cui si può collocare un contenuto.', 'trasparenza-pa' ) );
				} elseif ( null !== $pagina['portata'] ) {
					$righe[] = self::riga( $id, $titolo, 'gia_portata', (int) $pagina['portata'], __( 'La pagina è già stata portata.', 'trasparenza-pa' ) );
				} else {
					$righe[] = self::crea( $id, $titolo, $voce );
				}
			}

			return $righe;
		} finally {
			Blocco::rilascia();
		}
	}

	/**
	 * Copia i file scelti fra i documenti dei contenuti in bozza.
	 *
	 * Le scelte non si prendono per buone: il rapporto si rifa adesso, con il
	 * blocco della sezione preso, e si copiano solo file presenti del rapporto,
	 * nei contenuti in bozza o in attesa che hanno portato la loro pagina, una
	 * volta sola per impronta. Un file oltre il termine ordinario o senza data
	 * si copia solo dalle scelte confermate. Chi chiama controlla permessi e
	 * nonce.
	 *
	 * @param int                      $radice     Pagina d'ingresso.
	 * @param array<int|string, mixed> $normali    Percorsi scelti per pagina.
	 * @param array<int|string, mixed> $confermati Percorsi scelti per pagina, con la conferma per quelli oltre il termine.
	 * @param Alberatura               $albero     Albero in vigore.
	 * @param string                   $oggi       Oggi, AAAA-MM-GG nel fuso del sito.
	 * @return array<int, array{pagina: int, titolo: string, percorso: string, esito: string, allegato: int|null, motivo: string}>|WP_Error
	 */
	public static function copia( int $radice, array $normali, array $confermati, Alberatura $albero, string $oggi ) {
		if ( ! Blocco::prendi() ) {
			return new WP_Error( 'trasparenza_importazione_occupato', __( 'Niente copiato: un altro salvataggio della sezione è in corso. Riprovare fra qualche secondo.', 'trasparenza-pa' ) );
		}

		try {
			if ( null === self::base()['protetta'] ) {
				return new WP_Error( 'trasparenza_importazione_protetta', __( 'Il componente comune non dice dov\'è la sua cartella protetta, quindi non si sa quali file sono già protetti per altri contenuti.', 'trasparenza-pa' ) );
			}

			$rapporto = self::rapporto( $radice, $albero, $oggi );

			if ( is_wp_error( $rapporto ) ) {
				return $rapporto;
			}

			$pagine = array();

			foreach ( $rapporto['pagine'] as $pagina ) {
				$pagine[ $pagina['id'] ] = $pagina;
			}

			$scelte = array();

			foreach ( array(
				false => $normali,
				true  => $confermati,
			) as $confermata => $elenco ) {
				foreach ( $elenco as $chiave => $percorsi ) {
					$id = 1 === preg_match( '/\A[1-9][0-9]{0,18}\z/', (string) $chiave ) ? (int) $chiave : 0;

					foreach ( is_array( $percorsi ) ? $percorsi : array( $percorsi ) as $percorso ) {
						$scelte[] = array( $id, is_string( $percorso ) ? $percorso : '', (bool) $confermata );
					}
				}
			}

			$righe   = array();
			$copiate = array();

			foreach ( $scelte as list( $id, $percorso, $confermata ) ) {
				$pagina = $pagine[ $id ] ?? null;
				$titolo = null === $pagina ? '' : (string) $pagina['titolo'];

				if ( null === $pagina ) {
					$righe[] = self::riga_file( $id, $titolo, $percorso, 'scartato', null, __( 'La pagina non è fra quelle del rapporto: non pubblicata, protetta da password, fuori dalla pagina d\'ingresso o inesistente.', 'trasparenza-pa' ) );
					continue;
				}

				if ( null === $pagina['portata'] ) {
					$righe[] = self::riga_file( $id, $titolo, $percorso, 'scartato', null, __( 'La pagina non è ancora stata portata in bozza.', 'trasparenza-pa' ) );
					continue;
				}

				$contenuto = (int) $pagina['portata'];

				if ( ! isset( $copiate[ $contenuto ] ) ) {
					$copiate[ $contenuto ] = self::impronte( $contenuto );
				}

				$file = null;

				foreach ( $pagina['file'] as $uno ) {
					if ( $uno['percorso'] === $percorso ) {
						$file = $uno;
						break;
					}
				}

				if ( is_string( $copiate[ $contenuto ] ) ) {
					$righe[] = self::riga_file( $id, $titolo, $percorso, 'stato' === $copiate[ $contenuto ] ? 'scartato' : 'errore', null, 'stato' === $copiate[ $contenuto ] ? __( 'Il contenuto che ha portato la pagina non è in bozza né in attesa di revisione: da qui non riceve file, perché in un contenuto pubblicato un allegato aggiunto è subito pubblico.', 'trasparenza-pa' ) : __( 'Lo stato o i documenti del contenuto non si sono potuti leggere dalla banca dati: non si sa se il file c\'è già. Riprovare fra qualche minuto.', 'trasparenza-pa' ) );
				} elseif ( null === $file ) {
					$righe[] = self::riga_file( $id, $titolo, $percorso, 'scartato', null, __( 'Il file non è fra quelli della pagina nel rapporto.', 'trasparenza-pa' ) );
				} elseif ( 'presente' !== $file['stato'] ) {
					$righe[] = self::riga_file( $id, $titolo, $percorso, 'scartato', null, __( 'Il file manca, o il suo percorso esce dalla cartella dei caricamenti.', 'trasparenza-pa' ) );
				} elseif ( $file['protetto'] ) {
					$righe[] = self::riga_file( $id, $titolo, $percorso, 'scartato', null, __( 'Il file è già protetto dal componente comune per un altro contenuto: una copia avrebbe una scadenza diversa.', 'trasparenza-pa' ) );
				} elseif ( false !== $file['finita'] && ! $confermata ) {
					$righe[] = self::riga_file( $id, $titolo, $percorso, 'scartato', null, __( 'Il file è oltre il termine ordinario di pubblicazione, o la sua data non si conosce: si copia solo con la conferma, dopo aver verificato che l\'atto produce ancora effetti.', 'trasparenza-pa' ) );
				} elseif ( in_array( $file['impronta'], $copiate[ $contenuto ], true ) ) {
					$righe[] = self::riga_file( $id, $titolo, $percorso, 'gia_copiato', null, __( 'Un allegato con gli stessi byte è già fra quelli del contenuto.', 'trasparenza-pa' ) );
				} else {
					$riga    = self::copia_file( $id, $titolo, $percorso, $contenuto );
					$righe[] = $riga;

					if ( 'copiato' === $riga['esito'] ) {
						$copiate[ $contenuto ][] = $file['impronta'];
					}
				}
			}

			return $righe;
		} finally {
			Blocco::rilascia();
		}
	}

	/**
	 * Le impronte dei documenti di un contenuto che puo ricevere file.
	 *
	 * @param int $contenuto Contenuto.
	 * @return array<int, string>|string Impronte; `stato` se il contenuto non e in bozza o in attesa; `lettura` se una lettura e fallita.
	 */
	private static function impronte( int $contenuto ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- lo stato decide se un file diventa pubblico: si legge dalla banca dati, non dalla memoria.
		$stato = $wpdb->get_var( $wpdb->prepare( "SELECT post_status FROM {$wpdb->posts} WHERE ID = %d", $contenuto ) );

		if ( '' !== $wpdb->last_error || ! is_string( $stato ) ) {
			return 'lettura';
		}

		if ( ! in_array( $stato, array( 'draft', 'pending' ), true ) ) {
			return 'stato';
		}

		$documenti = SchedaDocumenti::documenti( $contenuto );

		if ( is_wp_error( $documenti ) ) {
			return 'lettura';
		}

		$impronte = array();

		foreach ( $documenti as $allegato ) {
			$impronta = conformita_core_impronta_allegato( $allegato );

			if ( ! is_array( $impronta ) || 'sha256' !== $impronta['algoritmo'] ) {
				return 'lettura';
			}

			$impronte[] = (string) $impronta['valore'];
		}

		return $impronte;
	}

	/**
	 * Copia un file in un temporaneo e lo deposita fra i documenti del contenuto.
	 *
	 * Il deposito sposta il file che riceve: senza il temporaneo sposterebbe
	 * l'originale, e la pagina vecchia perderebbe il suo file.
	 *
	 * @param int    $pagina    Pagina d'origine.
	 * @param string $titolo    Titolo della pagina.
	 * @param string $percorso  Percorso del file, gia controllato sul rapporto.
	 * @param int    $contenuto Contenuto che lo riceve.
	 * @return array{pagina: int, titolo: string, percorso: string, esito: string, allegato: int|null, motivo: string}
	 */
	private static function copia_file( int $pagina, string $titolo, string $percorso, int $contenuto ): array {
		$reale = self::reale( $percorso, self::base()['cartella'] );

		if ( null === $reale || 'fuori' === $reale ) {
			return self::riga_file( $pagina, $titolo, $percorso, 'errore', null, __( 'Il file non si è potuto leggere.', 'trasparenza-pa' ) );
		}

		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$temporaneo = wp_tempnam( wp_basename( $percorso ) );

		try {
			if ( '' === $temporaneo || ! copy( $reale, $temporaneo ) ) {
				return self::riga_file( $pagina, $titolo, $percorso, 'errore', null, __( 'Il file non si è potuto copiare in un file temporaneo.', 'trasparenza-pa' ) );
			}

			$esito = SchedaDocumenti::deposita_nel_blocco(
				$contenuto,
				array(
					'name'     => wp_basename( $percorso ),
					'tmp_name' => $temporaneo,
					'type'     => '',
					'size'     => (int) filesize( $temporaneo ),
					'error'    => UPLOAD_ERR_OK,
				),
				'percorso_locale',
				array(
					'pagina'   => $pagina,
					'percorso' => $percorso,
				)
			);
		} finally {
			if ( '' !== $temporaneo && file_exists( $temporaneo ) ) {
				wp_delete_file( $temporaneo );
			}
		}

		if ( 'salvato' !== $esito['codice'] ) {
			return self::riga_file( $pagina, $titolo, $percorso, 'errore', null, $esito['messaggio'] );
		}

		return self::riga_file( $pagina, $titolo, $percorso, 'copiato', (int) $esito['allegato'], '' );
	}

	/**
	 * Una riga dell'esito della copia.
	 *
	 * @param int      $pagina   Pagina.
	 * @param string   $titolo   Titolo.
	 * @param string   $percorso Percorso.
	 * @param string   $esito    Esito.
	 * @param int|null $allegato Documento creato.
	 * @param string   $motivo   Motivo.
	 * @return array{pagina: int, titolo: string, percorso: string, esito: string, allegato: int|null, motivo: string}
	 */
	private static function riga_file( int $pagina, string $titolo, string $percorso, string $esito, ?int $allegato, string $motivo ): array {
		return array(
			'pagina'   => $pagina,
			'titolo'   => $titolo,
			'percorso' => $percorso,
			'esito'    => $esito,
			'allegato' => $allegato,
			'motivo'   => $motivo,
		);
	}

	/**
	 * Crea il contenuto in bozza di una pagina, con registro e metadati.
	 *
	 * @param int    $pagina Pagina d'origine.
	 * @param string $titolo Titolo.
	 * @param string $voce   Voce, gia controllata.
	 * @return array{pagina: int, titolo: string, esito: string, contenuto: int|null, motivo: string}
	 */
	private static function crea( int $pagina, string $titolo, string $voce ): array {
		// wp_insert_post() toglie le barre inverse: il titolo letto dalla banca dati non ne ha di aggiunte.
		$id = wp_insert_post(
			array(
				'post_type'    => TIPO_CONTENUTO,
				'post_status'  => 'draft',
				'post_title'   => wp_slash( $titolo ),
				'post_content' => '',
				'post_author'  => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $id ) || 0 === $id ) {
			return self::riga( $pagina, $titolo, 'errore', null, __( 'Il contenuto non si è potuto creare.', 'trasparenza-pa' ) );
		}

		$traccia = conformita_core_registra_voce(
			array(
				'sezione'   => SEZIONE,
				'contenuto' => $id,
				'azione'    => self::AZIONE_REGISTRO,
				'dettagli'  => array(
					'pagina' => $pagina,
					'voce'   => $voce,
				),
			)
		);

		if ( is_wp_error( $traccia ) ) {
			wp_delete_post( $id, true );

			/* translators: %s: motivo riportato dal registro. */
			return self::riga( $pagina, $titolo, 'errore', null, sprintf( __( 'Non portata: il registro delle modifiche non ha accettato il passaggio, e un contenuto senza traccia nel registro non resta. Motivo: %s', 'trasparenza-pa' ), $traccia->get_error_message() ) );
		}

		add_post_meta( $id, self::META_ORIGINE, (string) $pagina, true );
		add_post_meta( $id, SchedaContenuto::META_COLLOCAZIONE, $voce, true );

		if ( Metadato::leggi( $id, self::META_ORIGINE ) !== array( (string) $pagina ) || Metadato::leggi( $id, SchedaContenuto::META_COLLOCAZIONE ) !== array( $voce ) ) {
			wp_delete_post( $id, true );

			return self::riga( $pagina, $titolo, 'errore', null, __( 'Non portata: la voce o la pagina d\'origine non si sono potute scrivere, e il contenuto è stato tolto. Il registro contiene un passaggio che non è avvenuto.', 'trasparenza-pa' ) );
		}

		return self::riga( $pagina, $titolo, 'portata', $id, '' );
	}

	/**
	 * Una riga dell'esito.
	 *
	 * @param int      $pagina    Pagina.
	 * @param string   $titolo    Titolo.
	 * @param string   $esito     Esito.
	 * @param int|null $contenuto Contenuto.
	 * @param string   $motivo    Motivo.
	 * @return array{pagina: int, titolo: string, esito: string, contenuto: int|null, motivo: string}
	 */
	private static function riga( int $pagina, string $titolo, string $esito, ?int $contenuto, string $motivo ): array {
		return array(
			'pagina'    => $pagina,
			'titolo'    => $titolo,
			'esito'     => $esito,
			'contenuto' => $contenuto,
			'motivo'    => $motivo,
		);
	}

	/**
	 * Le pagine gia portate: contenuto per pagina d'origine, cestino compreso.
	 *
	 * @return array<int, int>|WP_Error
	 */
	private static function portate() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- serve distinguere nessuna pagina portata da lettura fallita.
		$righe = $wpdb->get_results( $wpdb->prepare( "SELECT /* trasparenza-pa-importazione */ m.post_id, m.meta_value FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = %s AND p.post_type = %s ORDER BY m.post_id ASC", self::META_ORIGINE, TIPO_CONTENUTO ), ARRAY_A );

		if ( ! is_array( $righe ) || '' !== $wpdb->last_error ) {
			return new WP_Error( 'trasparenza_importazione_portate', __( 'Il rapporto non si può fare: non si sa quali pagine sono già state portate, perché la lettura non è riuscita.', 'trasparenza-pa' ) );
		}

		$portate = array();

		foreach ( $righe as $riga ) {
			$portate[ (int) $riga['meta_value'] ] ??= (int) $riga['post_id'];
		}

		return $portate;
	}

	/**
	 * Le pagine non pubblicate o protette da password, fuori dal cestino: solo identificativo e genitore.
	 *
	 * Titolo e testo non si leggono: servono solo a contare cosa il rapporto non copre.
	 * Una pagina protetta da password e pubblicata, ma il suo testo non e pubblico.
	 *
	 * @return array<int, int>|WP_Error Genitore per identificativo.
	 */
	private static function non_pubblicate() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- serve distinguere nessuna pagina da lettura fallita.
		$righe = $wpdb->get_results( $wpdb->prepare( "SELECT /* trasparenza-pa-importazione */ ID, post_parent FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN (%s, %s) AND ( post_status <> %s OR post_password <> '' ) ORDER BY ID ASC", 'page', 'trash', 'auto-draft', 'publish' ), ARRAY_A );

		if ( ! is_array( $righe ) || '' !== $wpdb->last_error ) {
			return self::errore_lettura();
		}

		$nascoste = array();

		foreach ( $righe as $riga ) {
			$nascoste[ (int) $riga['ID'] ] = (int) $riga['post_parent'];
		}

		return $nascoste;
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
	 * Piu la cartella protetta del componente comune: risolta; vuota se non c'e
	 * ancora; nulla se il componente comune non dice dov'e.
	 *
	 * @return array{dominio: string, percorso: string, cartella: string, protetta: string|null}
	 */
	private static function base(): array {
		$caricamenti = wp_upload_dir( null, false );
		$indirizzo   = (string) $caricamenti['baseurl'];
		$dominio     = (string) wp_parse_url( $indirizzo, PHP_URL_HOST );
		$porta       = wp_parse_url( $indirizzo, PHP_URL_PORT );

		$protetta = is_callable( self::$cartella_protetta ) ? realpath( (string) call_user_func( self::$cartella_protetta ) ) : null;

		return array(
			'dominio'  => strtolower( $dominio . ( null !== $porta ? ':' . $porta : '' ) ),
			'percorso' => untrailingslashit( (string) wp_parse_url( $indirizzo, PHP_URL_PATH ) ),
			'cartella' => untrailingslashit( (string) $caricamenti['basedir'] ),
			'protetta' => false === $protetta ? '' : $protetta,
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
			$prefisso = $base['percorso'] . '/';

			/*
			 * Prima l'indirizzo intero, che comincia dopo un apice, un uguale,
			 * una parentesi, uno spazio o all'inizio del testo; poi si decide se
			 * e un file: stesso dominio della cartella dei caricamenti, o nessun
			 * dominio, e un percorso che comincia con quello della cartella.
			 */
			if ( preg_match_all( '#(?<![^\s"\'=\[\(>])((?:https?:)?//[^\s"\'<>\[\]]+|/[^\s"\'<>\[\]]+)#i', $testo, $trovati ) ) {
				foreach ( $trovati[1] as $indirizzo ) {
					$indirizzo = html_entity_decode( $indirizzo, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
					$percorso  = (string) preg_replace( '/[?#].*\z/s', '', $indirizzo );

					if ( str_starts_with( $percorso, '//' ) || 1 === preg_match( '#\Ahttps?://#i', $percorso ) ) {
						$dominio = (string) wp_parse_url( ( str_starts_with( $percorso, '//' ) ? 'http:' : '' ) . $percorso, PHP_URL_HOST );
						$porta   = wp_parse_url( ( str_starts_with( $percorso, '//' ) ? 'http:' : '' ) . $percorso, PHP_URL_PORT );

						if ( strtolower( $dominio . ( null !== $porta ? ':' . $porta : '' ) ) !== $base['dominio'] ) {
							continue;
						}

						$percorso = (string) preg_replace( '#\A(?:https?:)?//[^/]*#i', '', $percorso );
					}

					if ( ! str_starts_with( $percorso, $prefisso ) || strlen( $percorso ) === strlen( $prefisso ) ) {
						continue;
					}

					$percorsi[] = rawurldecode( substr( $percorso, strlen( $prefisso ) ) );
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
					'protetto' => ( null !== $allegato && conformita_core_allegato_protetto( (int) $allegato['id'] ) ) || self::nella_protetta( $percorso, $base ),
				),
				self::sul_disco( $percorso, $base['cartella'] )
			);
		}

		return $file;
	}

	/**
	 * Il file sta, una volta risolto, dentro la cartella protetta del componente comune.
	 *
	 * Ogni file che il componente comune deposita sta li dentro. Il confronto si
	 * fa sul percorso reale: un percorso scritto in un altro modo, con `./` o
	 * con una barra doppia, porta allo stesso file e non sfugge.
	 *
	 * @param string                $percorso Percorso relativo o pieno.
	 * @param array<string, string> $base     Cartella dei caricamenti e cartella protetta.
	 * @return bool
	 */
	private static function nella_protetta( string $percorso, array $base ): bool {
		$reale = self::reale( $percorso, $base['cartella'] );

		return is_string( $base['protetta'] ) && '' !== $base['protetta'] && null !== $reale && 'fuori' !== $reale && str_starts_with( $reale, trailingslashit( $base['protetta'] ) );
	}

	/**
	 * Stato, dimensione e impronta di un file, solo dentro la cartella dei caricamenti.
	 *
	 * @param string $percorso Percorso relativo o pieno.
	 * @param string $cartella Cartella dei caricamenti.
	 * @return array{stato: string, byte: int|null, impronta: string|null}
	 */
	private static function sul_disco( string $percorso, string $cartella ): array {
		$reale  = self::reale( $percorso, $cartella );
		$niente = array(
			'stato'    => 'fuori' === $reale ? 'fuori' : 'mancante',
			'byte'     => null,
			'impronta' => null,
		);

		if ( null === $reale || 'fuori' === $reale ) {
			return $niente;
		}

		$byte     = filesize( $reale );
		$impronta = hash_file( 'sha256', $reale );

		if ( false === $byte || false === $impronta ) {
			return array_merge( $niente, array( 'stato' => 'mancante' ) );
		}

		return array(
			'stato'    => 'presente',
			'byte'     => $byte,
			'impronta' => $impronta,
		);
	}

	/**
	 * Il percorso reale di un file dentro la cartella dei caricamenti.
	 *
	 * Un percorso con un segmento `..` e fuori senza chiedere niente al disco; gli
	 * altri si risolvono, collegamenti simbolici compresi, e valgono solo se
	 * restano dentro la cartella.
	 *
	 * @param string $percorso Percorso relativo o pieno.
	 * @param string $cartella Cartella dei caricamenti.
	 * @return string|null Il percorso reale; `fuori`; nullo se il file non c'e.
	 */
	private static function reale( string $percorso, string $cartella ): ?string {
		if ( '' === $percorso || str_contains( $percorso, "\0" ) ) {
			return null;
		}

		if ( in_array( '..', preg_split( '#[/\\\\]#', $percorso ), true ) ) {
			return 'fuori';
		}

		$radice = realpath( $cartella );
		$pieno  = path_is_absolute( $percorso ) ? $percorso : $cartella . '/' . ltrim( $percorso, '/' );
		$reale  = realpath( $pieno );

		if ( false === $radice || false === $reale ) {
			return null;
		}

		if ( ! str_starts_with( $reale, trailingslashit( $radice ) ) ) {
			return 'fuori';
		}

		return is_file( $reale ) ? $reale : null;
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
