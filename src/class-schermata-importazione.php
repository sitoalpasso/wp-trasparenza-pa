<?php
/**
 * La schermata del rapporto di importazione dalle pagine del sito.
 *
 * Righe di collaudo T-121, T-124 e T-126.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

defined( 'ABSPATH' ) || exit;

/**
 * Chiede la pagina d'ingresso della sezione vecchia e mostra il rapporto.
 *
 * Il rapporto non scrive niente, quindi il modulo va per GET; il nonce serve
 * comunque, perche il rapporto legge testi e file che chi apre un indirizzo
 * preparato da altri non ha chiesto di leggere.
 */
final class SchermataImportazione {

	/**
	 * Identificativo della schermata.
	 */
	const PAGINA = 'trasparenza-pa-importazione';

	/**
	 * Azione del nonce.
	 */
	const AZIONE = 'trasparenza_pa_importazione';

	/**
	 * Nome del campo con la pagina d'ingresso.
	 */
	const CAMPO_RADICE = 'radice';

	/**
	 * Aggancia la schermata a WordPress.
	 */
	public static function aggancia(): void {
		// Dopo il registro degli accessi.
		add_action( 'admin_menu', array( self::class, 'da_admin_menu' ), 15 );
	}

	/**
	 * Sottovoce di menu, solo se la sezione e nostra.
	 */
	public static function da_admin_menu(): void {
		if ( ! Avvio::registrata() ) {
			return;
		}

		add_submenu_page(
			SchermataStati::PAGINA,
			__( 'Importazione dalle pagine', 'trasparenza-pa' ),
			__( 'Importazione dalle pagine', 'trasparenza-pa' ),
			Permessi::AMMINISTRAZIONE,
			self::PAGINA,
			array( self::class, 'mostra' )
		);
	}

	/**
	 * Stampa la schermata.
	 */
	public static function mostra(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- il nonce lo verifica html(), prima di leggere qualunque pagina.
		$richiesta = wp_unslash( $_GET );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- html() fa escaping di ogni valore.
		echo self::html( is_array( $richiesta ) ? $richiesta : array() );
	}

	/**
	 * La schermata, per la richiesta data.
	 *
	 * @param array<string, mixed> $richiesta Parametri della richiesta, gia senza barre inverse.
	 * @return string
	 */
	public static function html( array $richiesta ): string {
		$html = '<div class="wrap"><h1>' . esc_html__( 'Importazione dalle pagine', 'trasparenza-pa' ) . '</h1>';

		if ( ! current_user_can( Permessi::AMMINISTRAZIONE ) ) {
			return $html . self::avviso( __( 'Non avete il permesso di amministrare la sezione.', 'trasparenza-pa' ) ) . '</div>';
		}

		if ( ! array_key_exists( self::CAMPO_RADICE, $richiesta ) ) {
			return $html . self::spiegazione() . self::modulo( 0 ) . '</div>';
		}

		$nonce = $richiesta['_wpnonce'] ?? '';

		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, self::AZIONE ) ) {
			return $html . self::avviso( __( 'La richiesta è scaduta o non viene da questa schermata: nessuna pagina letta.', 'trasparenza-pa' ) )
				. '<p><a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGINA ) ) . '">' . esc_html__( 'Ricominciare', 'trasparenza-pa' ) . '</a></p></div>';
		}

		$valore = $richiesta[ self::CAMPO_RADICE ];

		if ( ! is_string( $valore ) || 1 !== preg_match( '/\A[1-9][0-9]{0,18}\z/', $valore ) ) {
			return $html . self::avviso( __( 'La pagina d\'ingresso indicata non è una pagina pubblicata.', 'trasparenza-pa' ) ) . self::modulo( 0 ) . '</div>';
		}

		$albero = PaginaPubblica::albero();

		if ( null === $albero ) {
			return $html . self::avviso( __( 'Il rapporto non si può fare: l\'albero della sezione non è caricato.', 'trasparenza-pa' ) ) . '</div>';
		}

		$radice   = (int) $valore;
		$oggi     = RegistroAccessi::oggi();
		$rapporto = ImportazionePagine::rapporto( $radice, $albero, $oggi );

		if ( is_wp_error( $rapporto ) ) {
			$modulo = 'trasparenza_importazione_radice' === $rapporto->get_error_code() ? self::modulo( 0 ) : '';

			return $html . self::avviso( $rapporto->get_error_message() ) . $modulo . '</div>';
		}

		return $html . self::modulo( $radice ) . self::rapporto( $rapporto, $albero, $oggi ) . '</div>';
	}

	/**
	 * Un avviso d'errore.
	 *
	 * @param string $testo Testo.
	 * @return string
	 */
	private static function avviso( string $testo ): string {
		return '<div class="notice notice-error"><p>' . esc_html( $testo ) . '</p></div>';
	}

	/**
	 * Cosa fa la schermata.
	 *
	 * @return string
	 */
	private static function spiegazione(): string {
		return '<p>' . esc_html__( 'Per un sito che oggi tiene la sezione come pagine: scegliere la pagina che fa da ingresso alla sezione. Il rapporto dice, per ogni pagina pubblicata che sta sotto, a quale voce corrisponde, quanto testo ha, quali file collega e quali collegamenti portano fuori dal sito. Non scrive e non cambia niente.', 'trasparenza-pa' ) . '</p>';
	}

	/**
	 * Il modulo che chiede la pagina d'ingresso.
	 *
	 * @param int $scelta Pagina gia scelta.
	 * @return string
	 */
	private static function modulo( int $scelta ): string {
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- con echo a zero restituisce l'elenco gia con escaping, non stampa.
		$elenco = wp_dropdown_pages(
			array(
				'echo'              => 0,
				'name'              => self::CAMPO_RADICE,
				'id'                => 'trasparenza-pa-radice',
				'selected'          => $scelta,
				'post_status'       => 'publish',
				'sort_column'       => 'menu_order,post_title',
				'show_option_none'  => __( 'Scegliere una pagina', 'trasparenza-pa' ),
				'option_none_value' => '',
			)
		);
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( '' === $elenco ) {
			return '<p>' . esc_html__( 'Il sito non ha pagine pubblicate.', 'trasparenza-pa' ) . '</p>';
		}

		return '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">'
			. '<input type="hidden" name="page" value="' . esc_attr( self::PAGINA ) . '">'
			. wp_nonce_field( self::AZIONE, '_wpnonce', false, false )
			. '<p><label for="trasparenza-pa-radice">' . esc_html__( 'Pagina d\'ingresso della sezione', 'trasparenza-pa' ) . '</label> '
			. $elenco
			. ' <button type="submit" class="button button-primary">' . esc_html__( 'Prepara il rapporto', 'trasparenza-pa' ) . '</button></p>'
			. '</form>';
	}

	/**
	 * Il rapporto.
	 *
	 * @param array<string, mixed> $rapporto Rapporto.
	 * @param Alberatura           $albero   Albero.
	 * @param string               $oggi     Il giorno da cui si e contato, AAAA-MM-GG.
	 * @return string
	 */
	private static function rapporto( array $rapporto, Alberatura $albero, string $oggi ): string {
		$giorno = \DateTimeImmutable::createFromFormat( '!Y-m-d', $oggi, wp_timezone() );

		$conti = array(
			'presente' => 0,
			'mancante' => 0,
			'fuori'    => 0,
			'finita'   => 0,
			'vuote'    => 0,
		);

		foreach ( $rapporto['pagine'] as $pagina ) {
			$conti['vuote'] += $pagina['vuota'] ? 1 : 0;

			foreach ( $pagina['file'] as $file ) {
				++$conti[ $file['stato'] ];
				$conti['finita'] += true === $file['finita'] ? 1 : 0;
			}
		}

		$html  = '<div id="trasparenza-pa-rapporto">';
		$html .= '<h2>' . esc_html__( 'Rapporto', 'trasparenza-pa' ) . '</h2>';
		/* translators: %s: data di oggi. */
		$html .= '<p>' . esc_html( sprintf( __( 'Calcolato al %s. Non è stato scritto niente.', 'trasparenza-pa' ), false === $giorno ? $oggi : ContenutiPubblici::giorno( $giorno->getTimestamp() ) ) ) . '</p>';
		$html .= '<ul>';
		/* translators: %d: numero di pagine. */
		$html .= '<li>' . esc_html( sprintf( _n( '%d pagina', '%d pagine', count( $rapporto['pagine'] ), 'trasparenza-pa' ), count( $rapporto['pagine'] ) ) ) . '</li>';
		/* translators: 1: file presenti, 2: file mancanti, 3: file fuori dalla cartella. */
		$html .= '<li>' . esc_html( sprintf( __( 'File presenti: %1$d; mancanti: %2$d; fuori dalla cartella dei caricamenti, non aperti: %3$d', 'trasparenza-pa' ), $conti['presente'], $conti['mancante'], $conti['fuori'] ) ) . '</li>';
		/* translators: %d: numero di file. */
		$html .= '<li>' . esc_html( sprintf( __( 'File con la pubblicazione finita di sicuro: %d. Il conto non sa degli atti che producono ancora effetti né delle durate legate alla cessazione di un incarico: va rivisto prima di decidere.', 'trasparenza-pa' ), $conti['finita'] ) ) . '</li>';
		/* translators: %d: numero di pagine. */
		$html .= '<li>' . esc_html( sprintf( __( 'Pagine vuote: %d', 'trasparenza-pa' ), $conti['vuote'] ) ) . '</li>';
		/* translators: %d: numero di voci. */
		$html .= '<li>' . esc_html( sprintf( __( 'Voci senza pagina: %d', 'trasparenza-pa' ), count( $rapporto['voci_senza_pagina'] ) ) ) . '</li>';
		$html .= '</ul>';

		if ( null === $rapporto['durata'] ) {
			$html .= '<p>' . esc_html__( 'L\'albero in vigore non dichiara la durata della pubblicazione: per nessun file si sa se la pubblicazione è finita.', 'trasparenza-pa' ) . '</p>';
		}

		$html .= '<table class="widefat striped"><thead><tr>'
			. '<th scope="col">' . esc_html__( 'Pagina', 'trasparenza-pa' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Voce', 'trasparenza-pa' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Testo', 'trasparenza-pa' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'File', 'trasparenza-pa' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Collegamenti esterni', 'trasparenza-pa' ) . '</th>'
			. '</tr></thead><tbody>';

		foreach ( $rapporto['pagine'] as $pagina ) {
			$html .= '<tr>'
				. '<td>' . str_repeat( '&mdash; ', (int) $pagina['profondita'] ) . '<a href="' . esc_url( $pagina['indirizzo'] ) . '">' . esc_html( $pagina['titolo'] ) . '</a>'
				. ( $pagina['vuota'] ? ' <strong>' . esc_html__( '(vuota)', 'trasparenza-pa' ) . '</strong>' : '' ) . '</td>'
				. '<td>' . esc_html( self::voce( $pagina, $albero ) ) . '</td>'
				/* translators: %d: numero di caratteri. */
				. '<td>' . esc_html( sprintf( _n( '%d carattere', '%d caratteri', (int) $pagina['caratteri'], 'trasparenza-pa' ), (int) $pagina['caratteri'] ) ) . '</td>'
				. '<td>' . self::file( $pagina['file'] ) . '</td>'
				. '<td>' . self::esterni( $pagina['esterni'] ) . '</td>'
				. '</tr>';
		}

		$html .= '</tbody></table>';

		$html .= '<h3>' . esc_html__( 'Voci senza pagina', 'trasparenza-pa' ) . '</h3>';

		if ( array() === $rapporto['voci_senza_pagina'] ) {
			$html .= '<p>' . esc_html__( 'Ogni voce ha una pagina proposta.', 'trasparenza-pa' ) . '</p>';
		} else {
			$html .= '<ul>';

			foreach ( $rapporto['voci_senza_pagina'] as $voce ) {
				$html .= '<li>' . esc_html( implode( ' / ', self::percorso( $voce, $albero ) ) ) . '</li>';
			}

			$html .= '</ul>';
		}

		return $html . '</div>';
	}

	/**
	 * La voce proposta, o perche non c'e.
	 *
	 * @param array<string, mixed> $pagina Pagina del rapporto.
	 * @param Alberatura           $albero Albero.
	 * @return string
	 */
	private static function voce( array $pagina, Alberatura $albero ): string {
		switch ( $pagina['esito_voce'] ) {
			case 'proposta':
				return implode( ' / ', self::percorso( (string) $pagina['voce'], $albero ) );
			case 'con_sottovoci':
				/* translators: %s: nome della voce. */
				return sprintf( __( '%s: ha sottovoci, sceglierne una', 'trasparenza-pa' ), implode( ' / ', self::percorso( (string) $pagina['voce'], $albero ) ) );
			case 'piu_voci':
				return __( 'Più voci hanno questo nome: sceglierne una', 'trasparenza-pa' );
			default:
				return __( 'Nessuna voce con questo nome', 'trasparenza-pa' );
		}
	}

	/**
	 * I nomi delle voci dalla prima sotto la radice fino a quella data.
	 *
	 * @param string     $voce   Voce.
	 * @param Alberatura $albero Albero.
	 * @return array<int, string>
	 */
	private static function percorso( string $voce, Alberatura $albero ): array {
		$nomi = array();

		foreach ( $albero->percorso( $voce ) as $id ) {
			$dati = $albero->voce( $id );

			if ( $id !== $albero->radice() && null !== $dati ) {
				$nomi[] = (string) $dati['nome'];
			}
		}

		return $nomi;
	}

	/**
	 * L'elenco dei file di una pagina.
	 *
	 * @param array<int, array<string, mixed>> $file File.
	 * @return string
	 */
	private static function file( array $file ): string {
		if ( array() === $file ) {
			return '';
		}

		$html = '<ul>';

		foreach ( $file as $uno ) {
			$html .= '<li><code>' . esc_html( $uno['percorso'] ) . '</code> ';

			if ( 'presente' === $uno['stato'] ) {
				/* translators: 1: dimensione in byte, 2: impronta SHA-256. */
				$html .= esc_html( sprintf( __( '%1$d byte, impronta %2$s', 'trasparenza-pa' ), $uno['byte'], $uno['impronta'] ) );
			} elseif ( 'mancante' === $uno['stato'] ) {
				$html .= '<strong>' . esc_html__( 'file mancante', 'trasparenza-pa' ) . '</strong>';
			} else {
				$html .= '<strong>' . esc_html__( 'fuori dalla cartella dei caricamenti: non aperto', 'trasparenza-pa' ) . '</strong>';
			}

			if ( true === $uno['finita'] ) {
				$html .= '; ' . esc_html__( 'pubblicazione finita di sicuro', 'trasparenza-pa' );
			} elseif ( null === $uno['finita'] ) {
				$html .= '; ' . esc_html__( 'non si sa se la pubblicazione è finita', 'trasparenza-pa' );
			}

			$html .= '</li>';
		}

		return $html . '</ul>';
	}

	/**
	 * L'elenco dei collegamenti esterni di una pagina.
	 *
	 * @param array<int, string> $esterni Collegamenti.
	 * @return string
	 */
	private static function esterni( array $esterni ): string {
		if ( array() === $esterni ) {
			return '';
		}

		$html = '<ul>';

		foreach ( $esterni as $indirizzo ) {
			$html .= '<li><code>' . esc_html( $indirizzo ) . '</code></li>';
		}

		return $html . '</ul>';
	}
}
