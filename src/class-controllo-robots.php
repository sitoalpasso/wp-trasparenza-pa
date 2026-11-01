<?php
/**
 * Il controllo sul file robots.txt.
 *
 * Righe di collaudo T-154 e T-155.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Dice a chi amministra il sito se il file robots.txt chiude ai motori di
 * ricerca la pagina della sezione o le sue voci (TRASP-03). Non corregge
 * niente: il file puo essere un file vero sul server, fuori dalla portata di
 * WordPress. Le righe si leggono secondo RFC 9309. Scheda:
 * `docs/implementazione/robots-txt.md`.
 */
final class ControlloRobots {

	/**
	 * Percorso del file vero, se fissato da una prova.
	 *
	 * @var string|null
	 */
	private static $file = null;

	/**
	 * Aggancia l'avviso.
	 */
	public static function aggancia(): void {
		add_action( 'admin_notices', array( self::class, 'mostra_avvisi' ) );
	}

	/**
	 * Fissa il percorso del file vero.
	 *
	 * @internal Per le prove.
	 *
	 * @param string|null $file Percorso, nullo per quello nella cartella di WordPress.
	 */
	public static function fissa_file( ?string $file ): void {
		self::$file = $file;
	}

	/**
	 * La riga che chiude il percorso, o nullo se il percorso resta aperto.
	 *
	 * Contano i gruppi per tutti i motori; vince la regola che corrisponde con
	 * piu caratteri, e a parita vince Allow.
	 *
	 * @param string $testo    Testo del file.
	 * @param string $percorso Percorso con i parametri, dalla radice del sito.
	 * @return string|null La riga, scritta come `Disallow: valore`.
	 */
	public static function riga_che_chiude( string $testo, string $percorso ): ?string {
		$migliore = null;

		foreach ( self::regole_per_tutti( $testo ) as $regola ) {
			if ( '' === $regola['valore'] || ! self::corrisponde( $regola['valore'], $percorso ) ) {
				continue;
			}

			$lunghezza = strlen( $regola['valore'] );

			if ( null === $migliore || $lunghezza > $migliore['lunghezza'] || ( $lunghezza === $migliore['lunghezza'] && 'allow' === $regola['tipo'] ) ) {
				$migliore = array(
					'lunghezza' => $lunghezza,
					'tipo'      => $regola['tipo'],
					'valore'    => $regola['valore'],
				);
			}
		}

		return null !== $migliore && 'disallow' === $migliore['tipo'] ? 'Disallow: ' . $migliore['valore'] : null;
	}

	/**
	 * Le regole dei gruppi `User-agent: *`.
	 *
	 * @param string $testo Testo del file.
	 * @return array<int, array{tipo: string, valore: string}>
	 */
	private static function regole_per_tutti( string $testo ): array {
		$regole      = array();
		$agenti      = array();
		$dopo_regole = false;
		$per_tutti   = false;

		foreach ( preg_split( '/\r\n|\r|\n/', $testo ) as $riga ) {
			$riga = trim( (string) preg_replace( '/#.*/', '', $riga ) );

			if ( 1 !== preg_match( '/\A([A-Za-z-]+)\s*:\s*(.*)\z/', $riga, $parti ) ) {
				continue;
			}

			$nome   = strtolower( $parti[1] );
			$valore = trim( $parti[2] );

			if ( 'user-agent' === $nome ) {
				if ( $dopo_regole ) {
					$agenti      = array();
					$dopo_regole = false;
				}

				$agenti[]  = strtolower( $valore );
				$per_tutti = in_array( '*', $agenti, true );
				continue;
			}

			if ( 'allow' !== $nome && 'disallow' !== $nome ) {
				continue;
			}

			$dopo_regole = true;

			if ( $per_tutti ) {
				$regole[] = array(
					'tipo'   => $nome,
					'valore' => $valore,
				);
			}
		}

		return $regole;
	}

	/**
	 * La regola corrisponde all'inizio del percorso.
	 *
	 * @param string $valore   Valore della regola, con `*` e `$` finale.
	 * @param string $percorso Percorso.
	 * @return bool
	 */
	private static function corrisponde( string $valore, string $percorso ): bool {
		$fine = '$' === substr( $valore, -1 );

		if ( $fine ) {
			$valore = substr( $valore, 0, -1 );
		}

		$espressione = implode( '.*', array_map( static fn( string $pezzo ): string => preg_quote( $pezzo, '/' ), explode( '*', $valore ) ) );

		return 1 === preg_match( '/\A' . $espressione . ( $fine ? '\z' : '' ) . '/', $percorso );
	}

	/**
	 * Il testo del file che il sito manda ai motori.
	 *
	 * Il file vero nella cartella di WordPress, se c'e: il server manda quello.
	 * Altrimenti il file che WordPress costruisce in `do_robots()`, con il suo
	 * filtro. L'azione `do_robotstxt` non si lancia: chi la usa lo fa per
	 * rispondere alla richiesta del file, e puo mandare intestazioni o chiudere
	 * la risposta, cosa che in bacheca romperebbe la pagina.
	 *
	 * @return string|WP_Error
	 */
	public static function testo() {
		$file = self::$file ?? ABSPATH . 'robots.txt';

		if ( file_exists( $file ) ) {
			$testo = is_file( $file ) && is_readable( $file ) ? file_get_contents( $file ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale.

			return false === $testo ? new WP_Error( 'trasparenza_robots_illeggibile', __( 'il file robots.txt nella cartella del sito non si è potuto leggere, e il controllo non è stato fatto.', 'trasparenza-pa' ) ) : $testo;
		}

		$indirizzo = wp_parse_url( site_url() );
		$cartella  = is_array( $indirizzo ) && ! empty( $indirizzo['path'] ) ? $indirizzo['path'] : '';
		$testo     = "User-agent: *\nDisallow: {$cartella}/wp-admin/\nAllow: {$cartella}/wp-admin/admin-ajax.php\n";

		return (string) apply_filters( 'robots_txt', $testo, get_option( 'blog_public' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- filtro di WordPress.
	}

	/**
	 * Le pagine della sezione chiuse dal file.
	 *
	 * @return array<int, array{titolo: string, cosa: string, riga: string}>|WP_Error
	 */
	public static function chiuse() {
		$albero = PaginaPubblica::albero();
		$pagine = IndicizzazioneVoci::pagine();

		if ( null === $albero || null === $pagine || array() === $pagine ) {
			return array();
		}

		$testo = self::testo();

		if ( is_wp_error( $testo ) ) {
			return $testo;
		}

		$voce   = self::una_voce( $albero );
		$chiuse = array();

		foreach ( $pagine as $pagina ) {
			$base = (string) get_permalink( $pagina );
			$riga = self::riga_che_chiude( $testo, self::percorso( $base ) );
			$cosa = 'pagina';

			if ( null === $riga && null !== $voce ) {
				$riga = self::riga_che_chiude( $testo, self::percorso( PaginaPubblica::indirizzo( $base, $voce, $albero->radice() ) ) );
				$cosa = 'voci';
			}

			if ( null !== $riga ) {
				$chiuse[] = array(
					'titolo' => get_the_title( $pagina ),
					'cosa'   => $cosa,
					'riga'   => $riga,
				);
			}
		}

		return $chiuse;
	}

	/**
	 * Una voce diversa dalla radice, per provare gli indirizzi con il parametro.
	 *
	 * @param Alberatura $albero Albero.
	 * @return string|null
	 */
	private static function una_voce( Alberatura $albero ): ?string {
		foreach ( $albero->identificativi() as $voce ) {
			if ( $voce !== $albero->radice() ) {
				return $voce;
			}
		}

		return null;
	}

	/**
	 * Percorso e parametri di un indirizzo, dalla radice del sito.
	 *
	 * @param string $indirizzo Indirizzo.
	 * @return string
	 */
	private static function percorso( string $indirizzo ): string {
		$parti = wp_parse_url( $indirizzo );

		if ( ! is_array( $parti ) ) {
			return '/';
		}

		return ( $parti['path'] ?? '/' ) . ( isset( $parti['query'] ) ? '?' . $parti['query'] : '' );
	}

	/**
	 * L'avviso nella bacheca iniziale, a chi puo attivare i componenti.
	 */
	public static function mostra_avvisi(): void {
		if ( ! current_user_can( 'activate_plugins' ) || ! Avvio::registrata() ) {
			return;
		}

		// Solo nella bacheca iniziale: il controllo legge file e pagine, e non serve a ogni schermata.
		$schermata = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( null === $schermata || 'dashboard' !== $schermata->id ) {
			return;
		}

		$chiuse = self::chiuse();

		if ( is_wp_error( $chiuse ) ) {
			printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html( NOME . ': ' . $chiuse->get_error_message() ) );

			return;
		}

		foreach ( $chiuse as $chiusa ) {
			$testo = 'voci' === $chiusa['cosa']
				/* translators: 1: nome del componente, 2: titolo della pagina, 3: riga del file. */
				? __( '%1$s: il file robots.txt chiede ai motori di ricerca di non visitare le voci della pagina "%2$s" (riga "%3$s"). La sezione Amministrazione trasparente per legge deve restare indicizzabile.', 'trasparenza-pa' )
				/* translators: 1: nome del componente, 2: titolo della pagina, 3: riga del file. */
				: __( '%1$s: il file robots.txt chiede ai motori di ricerca di non visitare la pagina "%2$s" (riga "%3$s"). La sezione Amministrazione trasparente per legge deve restare indicizzabile.', 'trasparenza-pa' );

			printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html( sprintf( $testo, NOME, $chiusa['titolo'], $chiusa['riga'] ) ) );
		}
	}
}
