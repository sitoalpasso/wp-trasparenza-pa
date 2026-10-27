<?php
/**
 * Il catalogo degli schemi: quali versioni esistono, da quando valgono, da dove vengono.
 *
 * Righe di collaudo T-27 e T-28.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Un catalogo controllato per intero, oppure nessun catalogo.
 *
 * Il catalogo elenca i file degli schemi e i file di definizioni comuni a cui
 * gli schemi rimandano. Questa classe conosce la forma del catalogo e la forma
 * minima di un file di schema (un JSON con il proprio identificativo), e non
 * il contenuto di nessuno schema: i campi stanno solo nei file.
 *
 * Come per l'albero, il catalogo si accetta tutto o niente. Un rimando che non
 * trova il suo file si scopre qui, al caricamento, e non al primo dato.
 */
final class CatalogoSchemi {

	/**
	 * Versione del formato del catalogo che questa classe sa leggere.
	 */
	const FORMATO = 1;

	/**
	 * Versioni per obbligo, in ordine di entrata in vigore.
	 *
	 * @var array<string, array<int, array{obbligo: string, versione: string, approvato_il: string, in_vigore_dal: string, file: string, fonte: array<string, string>, id: string}>>
	 */
	private $versioni;

	/**
	 * Contenuto decodificato di ogni file, per identificativo dello schema.
	 *
	 * @var array<string, object>
	 */
	private $contenuti;

	/**
	 * Costruito solo da `carica()`, a controlli superati.
	 *
	 * @param array<string, array<int, array<string, mixed>>> $versioni  Versioni per obbligo.
	 * @param array<string, object>                           $contenuti Contenuti per identificativo.
	 */
	private function __construct( array $versioni, array $contenuti ) {
		$this->versioni  = $versioni;
		$this->contenuti = $contenuti;
	}

	/**
	 * Legge e controlla un catalogo e tutti i file che elenca.
	 *
	 * @param string $file Percorso del catalogo.
	 * @return CatalogoSchemi|WP_Error
	 */
	public static function carica( string $file ) {
		if ( ! is_readable( $file ) ) {
			return new WP_Error( 'trasparenza_catalogo_assente', __( 'Il catalogo degli schemi non esiste o non è leggibile.', 'trasparenza-pa' ) );
		}

		$dati = self::decodifica( $file );

		if ( ! is_array( $dati ) ) {
			return new WP_Error( 'trasparenza_catalogo_illeggibile', __( 'Il catalogo degli schemi non è un JSON valido.', 'trasparenza-pa' ) );
		}

		if ( ( $dati['formato'] ?? null ) !== self::FORMATO ) {
			return new WP_Error( 'trasparenza_catalogo_formato', __( 'Il catalogo degli schemi usa un formato che questa versione del componente non sa leggere.', 'trasparenza-pa' ) );
		}

		$comuni = $dati['comuni'] ?? null;
		$schemi = $dati['schemi'] ?? null;

		if ( ! is_array( $comuni ) || ! is_array( $schemi ) || array() === $schemi ) {
			return new WP_Error( 'trasparenza_catalogo_incompleto', __( 'Il catalogo degli schemi non elenca le definizioni comuni e almeno uno schema.', 'trasparenza-pa' ) );
		}

		$cartella  = dirname( $file );
		$contenuti = array();
		$versioni  = array();

		foreach ( $comuni as $voce ) {
			$esito = self::leggi_voce( $voce, $cartella, false, $contenuti );

			if ( is_wp_error( $esito ) ) {
				return $esito;
			}
		}

		foreach ( $schemi as $voce ) {
			$esito = self::leggi_voce( $voce, $cartella, true, $contenuti );

			if ( is_wp_error( $esito ) ) {
				return $esito;
			}

			foreach ( $versioni[ $esito['obbligo'] ] ?? array() as $altra ) {
				if ( $altra['versione'] === $esito['versione'] ) {
					return new WP_Error(
						'trasparenza_catalogo_versione_doppia',
						/* translators: 1: versione, 2: obbligo. */
						sprintf( __( 'La versione %1$s dell\'obbligo %2$s compare due volte nel catalogo.', 'trasparenza-pa' ), $esito['versione'], $esito['obbligo'] )
					);
				}
			}

			$versioni[ $esito['obbligo'] ][] = $esito;
		}

		foreach ( $contenuti as $id => $contenuto ) {
			foreach ( self::rimandi( $contenuto ) as $rimando ) {
				$file_rimando = self::senza_frammento( $rimando );

				if ( '' !== $file_rimando && ! isset( $contenuti[ $file_rimando ] ) ) {
					return new WP_Error(
						'trasparenza_catalogo_rimando',
						/* translators: 1: identificativo dello schema, 2: rimando. */
						sprintf( __( 'Lo schema %1$s rimanda a %2$s, che non è fra i file del catalogo.', 'trasparenza-pa' ), $id, $rimando )
					);
				}
			}
		}

		foreach ( $versioni as $obbligo => $elenco ) {
			usort(
				$elenco,
				static function ( array $a, array $b ): int {
					return strcmp( $a['in_vigore_dal'], $b['in_vigore_dal'] );
				}
			);

			$versioni[ $obbligo ] = $elenco;
		}

		return new self( $versioni, $contenuti );
	}

	/**
	 * Obblighi presenti nel catalogo.
	 *
	 * @return array<int, string>
	 */
	public function obblighi(): array {
		return array_keys( $this->versioni );
	}

	/**
	 * Versioni di un obbligo, in ordine di entrata in vigore.
	 *
	 * @param string $obbligo Identificativo dell'obbligo.
	 * @return array<int, array<string, mixed>>
	 */
	public function versioni( string $obbligo ): array {
		return $this->versioni[ $obbligo ] ?? array();
	}

	/**
	 * Una versione di un obbligo, o nullo se non esiste.
	 *
	 * @param string $obbligo  Identificativo dell'obbligo.
	 * @param string $versione Versione.
	 * @return array<string, mixed>|null
	 */
	public function versione( string $obbligo, string $versione ): ?array {
		foreach ( $this->versioni( $obbligo ) as $voce ) {
			if ( $voce['versione'] === $versione ) {
				return $voce;
			}
		}

		return null;
	}

	/**
	 * Contenuto decodificato di ogni file, per identificativo dello schema.
	 *
	 * @return array<string, object>
	 */
	public function contenuti(): array {
		return $this->contenuti;
	}

	/**
	 * Controlla una voce del catalogo e legge il suo file.
	 *
	 * @param mixed                 $voce      Voce del catalogo.
	 * @param string                $cartella  Cartella del catalogo.
	 * @param bool                  $schema    La voce e una versione di un obbligo, non un file comune.
	 * @param array<string, object> $contenuti Contenuti gia letti, aggiornati qui.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function leggi_voce( $voce, string $cartella, bool $schema, array &$contenuti ) {
		if ( ! is_array( $voce ) || ! is_string( $voce['file'] ?? null ) || '' === $voce['file'] ) {
			return new WP_Error( 'trasparenza_catalogo_incompleto', __( 'Una voce del catalogo non indica il suo file.', 'trasparenza-pa' ) );
		}

		$nome = $voce['file'];

		if ( $schema ) {
			$obbligo  = $voce['obbligo'] ?? null;
			$versione = $voce['versione'] ?? null;

			if ( ! is_string( $obbligo ) || 1 !== preg_match( '/^[A-Za-z0-9_]{1,80}$/', $obbligo ) || ! is_string( $versione ) || '' === $versione ) {
				/* translators: %s: nome del file. */
				return new WP_Error( 'trasparenza_catalogo_incompleto', sprintf( __( 'La voce del catalogo per %s non indica obbligo e versione validi.', 'trasparenza-pa' ), $nome ) );
			}

			foreach ( array( 'approvato_il', 'in_vigore_dal' ) as $chiave ) {
				if ( ! self::data_valida( $voce[ $chiave ] ?? null ) ) {
					/* translators: 1: nome del campo, 2: nome del file. */
					return new WP_Error( 'trasparenza_catalogo_data', sprintf( __( 'La data %1$s della voce per %2$s non è nella forma AAAA-MM-GG.', 'trasparenza-pa' ), $chiave, $nome ) );
				}
			}
		}

		$fonte = $voce['fonte'] ?? null;

		foreach ( array( 'titolo', 'indirizzo' ) as $chiave ) {
			if ( ! is_array( $fonte ) || ! is_string( $fonte[ $chiave ] ?? null ) || '' === trim( $fonte[ $chiave ] ) ) {
				/* translators: %s: nome del file. */
				return new WP_Error( 'trasparenza_catalogo_fonte', sprintf( __( 'La voce del catalogo per %s non dichiara la fonte con titolo e indirizzo.', 'trasparenza-pa' ), $nome ) );
			}
		}

		if ( ! self::data_valida( $fonte['consultato_il'] ?? null ) ) {
			/* translators: %s: nome del file. */
			return new WP_Error( 'trasparenza_catalogo_fonte', sprintf( __( 'La fonte della voce per %s non dice quando è stata consultata.', 'trasparenza-pa' ), $nome ) );
		}

		$percorso = $cartella . '/' . $nome;

		if ( str_contains( $nome, '..' ) || ! is_readable( $percorso ) ) {
			/* translators: %s: nome del file. */
			return new WP_Error( 'trasparenza_catalogo_file_assente', sprintf( __( 'Il file %s elencato nel catalogo non esiste o non è leggibile.', 'trasparenza-pa' ), $nome ) );
		}

		$contenuto = self::decodifica( $percorso, false );
		$id        = is_object( $contenuto ) ? self::senza_frammento( (string) ( $contenuto->{'$id'} ?? '' ) ) : '';

		if ( '' === $id ) {
			/* translators: %s: nome del file. */
			return new WP_Error( 'trasparenza_catalogo_schema_illeggibile', sprintf( __( 'Il file %s non è uno schema JSON con il proprio identificativo.', 'trasparenza-pa' ), $nome ) );
		}

		if ( isset( $contenuti[ $id ] ) ) {
			/* translators: %s: identificativo dello schema. */
			return new WP_Error( 'trasparenza_catalogo_id_doppio', sprintf( __( 'Due file del catalogo dichiarano lo stesso identificativo %s.', 'trasparenza-pa' ), $id ) );
		}

		$contenuti[ $id ] = $contenuto;

		if ( ! $schema ) {
			return array();
		}

		return array(
			'obbligo'       => $obbligo,
			'versione'      => $versione,
			'approvato_il'  => $voce['approvato_il'],
			'in_vigore_dal' => $voce['in_vigore_dal'],
			'file'          => $nome,
			'fonte'         => $fonte,
			'id'            => $id,
		);
	}

	/**
	 * Decodifica un file JSON, togliendo il segno BOM iniziale se c'e.
	 *
	 * I file ufficiali cominciano con quel segno, e il decodificatore di PHP
	 * li rifiuterebbe.
	 *
	 * @param string $file        Percorso.
	 * @param bool   $associativo Restituisce elenchi associativi invece di oggetti.
	 * @return mixed
	 */
	private static function decodifica( string $file, bool $associativo = true ) {
		$testo = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale del componente.

		if ( str_starts_with( $testo, "\xEF\xBB\xBF" ) ) {
			$testo = substr( $testo, 3 );
		}

		return json_decode( $testo, $associativo );
	}

	/**
	 * La data e nella forma AAAA-MM-GG ed esiste nel calendario.
	 *
	 * @param mixed $data Valore.
	 * @return bool
	 */
	private static function data_valida( $data ): bool {
		if ( ! is_string( $data ) ) {
			return false;
		}

		$letta = \DateTimeImmutable::createFromFormat( '!Y-m-d', $data );

		return false !== $letta && $letta->format( 'Y-m-d' ) === $data;
	}

	/**
	 * Tutti i rimandi di uno schema, a qualunque profondita.
	 *
	 * @param mixed $nodo Parte dello schema.
	 * @return array<int, string>
	 */
	private static function rimandi( $nodo ): array {
		$trovati = array();

		if ( is_object( $nodo ) || is_array( $nodo ) ) {
			foreach ( (array) $nodo as $chiave => $valore ) {
				if ( '$ref' === $chiave && is_string( $valore ) ) {
					$trovati[] = $valore;
				} else {
					$trovati = array_merge( $trovati, self::rimandi( $valore ) );
				}
			}
		}

		return $trovati;
	}

	/**
	 * La parte di un indirizzo che indica il file, senza il frammento.
	 *
	 * Vuota per un rimando interno allo stesso schema.
	 *
	 * @param string $indirizzo Indirizzo o rimando.
	 * @return string
	 */
	private static function senza_frammento( string $indirizzo ): string {
		$posizione = strpos( $indirizzo, '#' );

		return false === $posizione ? $indirizzo : substr( $indirizzo, 0, $posizione );
	}
}
