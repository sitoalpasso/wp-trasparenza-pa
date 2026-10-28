<?php
/**
 * L'albero delle voci della sezione, letto da un file di dati.
 *
 * Righe di collaudo T-11 e T-12.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Un albero di voci controllato per intero, oppure nessun albero.
 *
 * L'albero non sta nel codice: sta in un file di dati con la sua versione e le
 * sue fonti, perche l'Allegato A del d.lgs. 33/2013 cambia nel tempo. Questa
 * classe conosce la forma del file e non il suo contenuto: non sa come si
 * chiama nessuna voce, tranne la radice, il cui nome e imposto dall'art. 9 del
 * decreto ed e il solo controllo che non si delega al file.
 *
 * Il file si accetta tutto o niente. Un albero parziale pubblicherebbe una
 * sezione a cui mancano voci senza che nessuno se ne accorga.
 */
final class Alberatura {

	/**
	 * Versione del formato del file che questa classe sa leggere.
	 */
	const FORMATO = 1;

	/**
	 * Nome della sezione, art. 9 c. 1 del d.lgs. 33/2013.
	 */
	const NOME_SEZIONE = 'Amministrazione trasparente';

	/**
	 * Diciture che il file deve fornire, una per stato che ne richiede una.
	 */
	const DICITURE = array( 'non_applicabile', 'sospeso' );

	/**
	 * Voci per identificativo, nell'ordine del file.
	 *
	 * @var array<string, array{id: string, nome: string, padre: string|null, riferimento: string}>
	 */
	private $voci;

	/**
	 * Identificativo della radice.
	 *
	 * @var string
	 */
	private $radice;

	/**
	 * Versione dell'albero dichiarata dal file.
	 *
	 * @var string
	 */
	private $versione;

	/**
	 * Diciture per stato.
	 *
	 * @var array<string, string>
	 */
	private $diciture;

	/**
	 * Dove si mostrano i dati della sezione che non sono voci: dato e voce.
	 *
	 * @var array<string, string>
	 */
	private $collocazioni;

	/**
	 * Costruito solo da `carica()`, a controlli superati.
	 *
	 * @param array<string, array{id: string, nome: string, padre: string|null, riferimento: string}> $voci     Voci.
	 * @param string                                                                                  $radice   Radice.
	 * @param string                                                                                  $versione Versione.
	 * @param array<string, string>                                                                   $diciture Diciture.
	 * @param array<string, string>                                                                   $collocazioni Collocazioni.
	 */
	private function __construct( array $voci, string $radice, string $versione, array $diciture, array $collocazioni ) {
		$this->voci         = $voci;
		$this->radice       = $radice;
		$this->versione     = $versione;
		$this->diciture     = $diciture;
		$this->collocazioni = $collocazioni;
	}

	/**
	 * Legge e controlla un file dell'albero.
	 *
	 * @param string $file Percorso del file.
	 * @return Alberatura|WP_Error
	 */
	public static function carica( string $file ) {
		if ( ! is_readable( $file ) ) {
			return self::errore( 'trasparenza_albero_assente', __( 'Il file dell\'albero della sezione non esiste o non è leggibile.', 'trasparenza-pa' ) );
		}

		$dati = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale del componente.

		if ( ! is_array( $dati ) ) {
			return self::errore( 'trasparenza_albero_illeggibile', __( 'Il file dell\'albero della sezione non è un JSON valido.', 'trasparenza-pa' ) );
		}

		return self::da_dati( $dati );
	}

	/**
	 * Controlla i dati gia decodificati e costruisce l'albero.
	 *
	 * @param array<mixed> $dati Contenuto del file.
	 * @return Alberatura|WP_Error
	 */
	public static function da_dati( array $dati ) {
		if ( ( $dati['formato'] ?? null ) !== self::FORMATO ) {
			return self::errore( 'trasparenza_albero_formato', __( 'Il file dell\'albero usa un formato che questa versione del componente non sa leggere.', 'trasparenza-pa' ) );
		}

		$versione = $dati['versione'] ?? null;
		$nodi     = $dati['nodi'] ?? null;
		$diciture = $dati['diciture'] ?? null;

		if ( ! is_string( $versione ) || '' === $versione || ! is_array( $nodi ) || array() === $nodi || ! is_array( $diciture ) ) {
			return self::errore( 'trasparenza_albero_incompleto', __( 'Il file dell\'albero non dichiara versione, voci e diciture.', 'trasparenza-pa' ) );
		}

		foreach ( self::DICITURE as $stato ) {
			if ( ! isset( $diciture[ $stato ] ) || ! is_string( $diciture[ $stato ] ) || '' === trim( $diciture[ $stato ] ) ) {
				/* translators: %s: stato dell'obbligo. */
				return self::errore( 'trasparenza_albero_dicitura', sprintf( __( 'Il file dell\'albero non fornisce la dicitura per lo stato %s.', 'trasparenza-pa' ), $stato ) );
			}
		}

		$voci   = array();
		$radici = array();

		foreach ( $nodi as $nodo ) {
			$id    = is_array( $nodo ) ? ( $nodo['id'] ?? null ) : null;
			$nome  = is_array( $nodo ) ? ( $nodo['nome'] ?? null ) : null;
			$padre = is_array( $nodo ) ? ( $nodo['padre'] ?? null ) : null;
			$rif   = is_array( $nodo ) ? ( $nodo['riferimento'] ?? '' ) : '';

			if ( ! is_string( $id ) || 1 !== preg_match( '/^[A-Za-z0-9_]{1,80}$/', $id ) ) {
				return self::errore( 'trasparenza_albero_identificativo', __( 'Una voce dell\'albero ha un identificativo mancante o con caratteri non ammessi.', 'trasparenza-pa' ) );
			}

			if ( isset( $voci[ $id ] ) ) {
				/* translators: %s: identificativo della voce. */
				return self::errore( 'trasparenza_albero_duplicato', sprintf( __( 'La voce %s compare due volte nell\'albero.', 'trasparenza-pa' ), $id ) );
			}

			if ( ! is_string( $nome ) || '' === trim( $nome ) || ( null !== $padre && ! is_string( $padre ) ) || ! is_string( $rif ) ) {
				/* translators: %s: identificativo della voce. */
				return self::errore( 'trasparenza_albero_voce', sprintf( __( 'La voce %s non ha un nome, o ha un padre o un riferimento non validi.', 'trasparenza-pa' ), $id ) );
			}

			$voci[ $id ] = array(
				'id'          => $id,
				'nome'        => $nome,
				'padre'       => $padre,
				'riferimento' => $rif,
			);

			if ( null === $padre ) {
				$radici[] = $id;
			}
		}

		if ( 1 !== count( $radici ) ) {
			return self::errore( 'trasparenza_albero_radice', __( 'L\'albero deve avere una e una sola radice.', 'trasparenza-pa' ) );
		}

		$radice = $radici[0];

		if ( self::NOME_SEZIONE !== $voci[ $radice ]['nome'] ) {
			return self::errore( 'trasparenza_albero_nome_sezione', __( 'La radice dell\'albero deve chiamarsi esattamente "Amministrazione trasparente".', 'trasparenza-pa' ) );
		}

		foreach ( $voci as $id => $voce ) {
			if ( null !== $voce['padre'] && ! isset( $voci[ $voce['padre'] ] ) ) {
				/* translators: 1: identificativo della voce, 2: identificativo del padre. */
				return self::errore( 'trasparenza_albero_padre', sprintf( __( 'La voce %1$s dichiara come padre %2$s, che non esiste.', 'trasparenza-pa' ), $id, $voce['padre'] ) );
			}
		}

		/*
		 * Con una sola radice e ogni padre esistente, una voce che non arriva
		 * alla radice sta in un ciclo: risalendo, prima o poi ripassa da una voce
		 * gia vista.
		 */
		foreach ( array_keys( $voci ) as $id ) {
			$visti    = array();
			$corrente = $id;

			while ( null !== $corrente ) {
				if ( isset( $visti[ $corrente ] ) ) {
					/* translators: %s: identificativo della voce. */
					return self::errore( 'trasparenza_albero_ciclo', sprintf( __( 'La voce %s sta in un ciclo e non arriva alla radice.', 'trasparenza-pa' ), $id ) );
				}

				$visti[ $corrente ] = true;
				$corrente           = $voci[ $corrente ]['padre'];
			}
		}

		/*
		 * Le collocazioni sono facoltative: dicono in quale voce si mostra un
		 * dato della sezione che non e una voce, come il responsabile della
		 * trasparenza. Stanno nel file e non nel codice perche le decide la
		 * norma, come le voci. Una collocazione verso una voce che non esiste
		 * e un difetto del file, come un padre inesistente.
		 */
		$collocazioni = $dati['collocazioni'] ?? array();

		if ( ! is_array( $collocazioni ) ) {
			return self::errore( 'trasparenza_albero_collocazione', __( 'Le collocazioni del file dell\'albero devono essere coppie di dato e voce.', 'trasparenza-pa' ) );
		}

		foreach ( $collocazioni as $dato => $voce ) {
			if ( ! is_string( $dato ) || 1 !== preg_match( '/^[a-z0-9_]{1,80}$/', $dato ) || ! is_string( $voce ) || ! isset( $voci[ $voce ] ) || $voce === $radice ) {
				return self::errore( 'trasparenza_albero_collocazione', __( 'Una collocazione del file dell\'albero non indica un dato valido o porta a una voce che non esiste.', 'trasparenza-pa' ) );
			}
		}

		return new self( $voci, $radice, $versione, array_intersect_key( $diciture, array_flip( self::DICITURE ) ), $collocazioni );
	}

	/**
	 * Identificativo della radice.
	 *
	 * @return string
	 */
	public function radice(): string {
		return $this->radice;
	}

	/**
	 * Versione dichiarata dal file.
	 *
	 * @return string
	 */
	public function versione(): string {
		return $this->versione;
	}

	/**
	 * La voce esiste.
	 *
	 * @param string $id Identificativo.
	 * @return bool
	 */
	public function esiste( string $id ): bool {
		return isset( $this->voci[ $id ] );
	}

	/**
	 * Una voce, o nullo se non esiste.
	 *
	 * @param string $id Identificativo.
	 * @return array{id: string, nome: string, padre: string|null, riferimento: string}|null
	 */
	public function voce( string $id ): ?array {
		return $this->voci[ $id ] ?? null;
	}

	/**
	 * Identificativi di tutte le voci, nell'ordine del file.
	 *
	 * @return array<int, string>
	 */
	public function identificativi(): array {
		return array_keys( $this->voci );
	}

	/**
	 * Figli diretti di una voce, nell'ordine del file.
	 *
	 * @param string $id Identificativo.
	 * @return array<int, string>
	 */
	public function figli( string $id ): array {
		$figli = array();

		foreach ( $this->voci as $voce ) {
			if ( $voce['padre'] === $id ) {
				$figli[] = $voce['id'];
			}
		}

		return $figli;
	}

	/**
	 * Percorso dalla radice alla voce, radice compresa.
	 *
	 * @param string $id Identificativo.
	 * @return array<int, string>
	 */
	public function percorso( string $id ): array {
		$percorso = array();
		$corrente = $this->esiste( $id ) ? $id : null;

		while ( null !== $corrente ) {
			array_unshift( $percorso, $corrente );
			$corrente = $this->voci[ $corrente ]['padre'];
		}

		return $percorso;
	}

	/**
	 * Dicitura che il file fornisce per uno stato.
	 *
	 * @param string $stato Stato dell'obbligo.
	 * @return string
	 */
	public function dicitura( string $stato ): string {
		return $this->diciture[ $stato ] ?? '';
	}

	/**
	 * La voce in cui si mostra un dato della sezione, o nullo se il file non la dice.
	 *
	 * @param string $dato Nome del dato.
	 * @return string|null
	 */
	public function collocazione( string $dato ): ?string {
		return $this->collocazioni[ $dato ] ?? null;
	}

	/**
	 * Errore del caricamento.
	 *
	 * @param string $codice    Codice.
	 * @param string $messaggio Messaggio.
	 * @return WP_Error
	 */
	private static function errore( string $codice, string $messaggio ): WP_Error {
		return new WP_Error( $codice, $messaggio );
	}
}
