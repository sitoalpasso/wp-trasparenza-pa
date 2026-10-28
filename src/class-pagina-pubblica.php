<?php
/**
 * La pagina pubblica della sezione: dove sta, cosa disegna, cosa risponde.
 *
 * Righe di collaudo T-15..T-19.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

defined( 'ABSPATH' ) || exit;

/**
 * Il codice breve della sezione e le regole della sua risposta.
 *
 * Come per la pubblicita legale, la pagina e quella che l'ente
 * crea e in cui scrive il codice breve: nessuna impostazione, nessun tipo di
 * contenuto proprio per le pagine. La voce si sceglie con un parametro
 * dell'indirizzo, che resta stabile anche se l'albero cambia forma: e
 * l'indirizzo che finira nel file di mappatura della sezione.
 */
final class PaginaPubblica {

	/**
	 * Il codice breve.
	 */
	const CODICE_BREVE = 'amministrazione_trasparente';

	/**
	 * Il parametro dell'indirizzo che sceglie la voce.
	 */
	const PARAMETRO_VOCE = 'voce';

	/**
	 * File dell'albero da caricare.
	 *
	 * @var string
	 */
	private static $file = '';

	/**
	 * Albero caricato, o nullo se non disponibile.
	 *
	 * @var Alberatura|null
	 */
	private static $albero = null;

	/**
	 * Motivo per cui l'albero non e disponibile, se non lo e.
	 *
	 * @var string
	 */
	private static $guasto = '';

	/**
	 * Aggancia la pagina a WordPress.
	 */
	public static function aggancia(): void {
		add_action( 'init', array( self::class, 'da_init' ) );
		add_action( 'template_redirect', array( self::class, 'da_template_redirect' ), 0 );
		add_action( 'admin_notices', array( self::class, 'mostra_avvisi' ) );
	}

	/**
	 * Sceglie il file dell'albero.
	 *
	 * @internal Le prove lo puntano su un albero finto: l'albero vero e un
	 *           dato normativo che le prove del motore non devono conoscere.
	 *
	 * @param string $file Percorso del file.
	 */
	public static function fissa_file( string $file ): void {
		self::$file = $file;
	}

	/**
	 * Riporta la classe allo stato iniziale.
	 *
	 * @internal Serve alle prove.
	 */
	public static function azzera(): void {
		self::$albero = null;
		self::$guasto = '';
	}

	/**
	 * Carica l'albero e registra il codice breve, se la sezione e nostra.
	 *
	 * Senza sezione registrata non si disegna niente: le regole della sezione
	 * non sarebbero dichiarate.
	 */
	public static function da_init(): void {
		self::azzera();

		if ( ! Avvio::registrata() ) {
			return;
		}

		$albero = Alberatura::carica( '' !== self::$file ? self::$file : FILE_ALBERATURA );

		if ( is_wp_error( $albero ) ) {
			self::$guasto = $albero->get_error_message();
		} else {
			self::$albero = $albero;
		}

		add_shortcode( self::CODICE_BREVE, array( self::class, 'da_codice_breve' ) );
	}

	/**
	 * L'albero caricato, o nullo.
	 *
	 * @return Alberatura|null
	 */
	public static function albero(): ?Alberatura {
		return self::$albero;
	}

	/**
	 * La voce chiesta dall'indirizzo: la radice se non se ne chiede nessuna.
	 *
	 * Nullo se se ne chiede una che non esiste.
	 *
	 * @param array<string, mixed> $richiesta Parametri della richiesta.
	 * @return string|null
	 */
	public static function voce_chiesta( array $richiesta ): ?string {
		if ( null === self::$albero ) {
			return null;
		}

		if ( ! array_key_exists( self::PARAMETRO_VOCE, $richiesta ) ) {
			return self::$albero->radice();
		}

		$voce = $richiesta[ self::PARAMETRO_VOCE ];

		return is_string( $voce ) && self::$albero->esiste( $voce ) ? $voce : null;
	}

	/**
	 * Una voce sconosciuta risponde 404, non la radice.
	 *
	 * Un indirizzo sbagliato che mostra la radice sembra funzionare, e nel file
	 * di mappatura un indirizzo che risponde 200 vale come voce presente.
	 */
	public static function da_template_redirect(): void {
		if ( null === self::$albero || ! is_singular() ) {
			return;
		}

		$pagina = get_queried_object();

		if ( ! $pagina instanceof \WP_Post || ! has_shortcode( $pagina->post_content, self::CODICE_BREVE ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lettura pubblica, nessuna modifica.
		if ( null !== self::voce_chiesta( wp_unslash( $_GET ) ) ) {
			return;
		}

		global $wp_query;

		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Disegna la voce chiesta.
	 *
	 * @param mixed $attributi Attributi, ignorati.
	 * @return string
	 */
	public static function da_codice_breve( $attributi = array() ): string {
		unset( $attributi );

		if ( null === self::$albero ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lettura pubblica, nessuna modifica.
		$voce = self::voce_chiesta( wp_unslash( $_GET ) );

		return null === $voce ? '' : self::disegna( self::$albero, $voce, (string) get_permalink() );
	}

	/**
	 * Indirizzo stabile di una voce.
	 *
	 * @param string $base Indirizzo della pagina della sezione.
	 * @param string $voce Identificativo della voce.
	 * @param string $radice Identificativo della radice.
	 * @return string
	 */
	public static function indirizzo( string $base, string $voce, string $radice ): string {
		return $voce === $radice ? $base : add_query_arg( self::PARAMETRO_VOCE, $voce, $base );
	}

	/**
	 * HTML di una voce: percorso, nome, riferimento, dicitura, figli.
	 *
	 * @param Alberatura $albero Albero.
	 * @param string     $voce   Voce esistente.
	 * @param string     $base   Indirizzo della pagina della sezione.
	 * @return string
	 */
	public static function disegna( Alberatura $albero, string $voce, string $base ): string {
		$radice = $albero->radice();
		$dati   = (array) $albero->voce( $voce );
		$html   = '<div class="trasparenza-pa">';

		if ( $voce !== $radice ) {
			$html .= '<nav class="trasparenza-pa-percorso" aria-label="' . esc_attr__( 'Percorso nella sezione', 'trasparenza-pa' ) . '"><ol>';

			foreach ( $albero->percorso( $voce ) as $passo ) {
				$nome = (string) ( $albero->voce( $passo )['nome'] ?? '' );

				$html .= $passo === $voce
					? '<li><span aria-current="page">' . esc_html( $nome ) . '</span></li>'
					: '<li><a href="' . esc_url( self::indirizzo( $base, $passo, $radice ) ) . '">' . esc_html( $nome ) . '</a></li>';
			}

			$html .= '</ol></nav>';
			$html .= '<h2>' . esc_html( (string) $dati['nome'] ) . '</h2>';
		}

		if ( '' !== (string) $dati['riferimento'] ) {
			/* translators: %s: riferimento normativo della voce. */
			$html .= '<p class="trasparenza-pa-riferimento">' . esc_html( sprintf( __( 'Riferimento: %s', 'trasparenza-pa' ), (string) $dati['riferimento'] ) ) . '</p>';
		}

		$stato = StatiObbligo::stato( $voce );

		if ( StatiObbligo::NON_APPLICABILE === $stato || StatiObbligo::SOSPESO === $stato ) {
			$html .= '<p class="trasparenza-pa-dicitura">' . esc_html( $albero->dicitura( $stato ) ) . '</p>';
		}

		// Il responsabile si mostra nella voce che il file dell'albero indica, qualunque ne sia lo stato.
		if ( $albero->collocazione( Responsabile::DATO ) === $voce ) {
			$html .= Responsabile::html();
		}

		$figli = $albero->figli( $voce );

		if ( array() !== $figli ) {
			$html .= '<ul class="trasparenza-pa-voci">';

			foreach ( $figli as $figlio ) {
				$html .= '<li><a href="' . esc_url( self::indirizzo( $base, $figlio, $radice ) ) . '">' . esc_html( (string) ( $albero->voce( $figlio )['nome'] ?? '' ) ) . '</a></li>';
			}

			$html .= '</ul>';
		}

		return $html . '</div>';
	}

	/**
	 * Avvisi sullo stato della sezione, per chi puo rimediare.
	 *
	 * Due condizioni: l'albero non e disponibile, oppure il sito intero chiede
	 * ai motori di ricerca di non indicizzarlo. La seconda non dipende dal
	 * componente, ma nasconde anche questa sezione, e l'art. 9 del d.lgs.
	 * 33/2013 vieta di ostacolarne l'indicizzazione.
	 */
	public static function mostra_avvisi(): void {
		if ( ! current_user_can( 'activate_plugins' ) || ! Avvio::registrata() ) {
			return;
		}

		if ( '' !== self::$guasto ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: nome del componente, 2: motivo. */
						__( '%1$s non mostra la sezione: l\'albero delle voci non è disponibile. %2$s', 'trasparenza-pa' ),
						NOME,
						self::$guasto
					)
				)
			);
		}

		if ( '0' === (string) get_option( 'blog_public' ) ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: nome del componente. */
						__( '%s: il sito chiede ai motori di ricerca di non indicizzarlo, e così nasconde anche la sezione Amministrazione trasparente, che per legge deve restare indicizzabile.', 'trasparenza-pa' ),
						NOME
					)
				)
			);
		}
	}
}
