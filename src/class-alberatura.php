<?php
/**
 * L'albero delle voci della sezione, letto da un file di dati.
 *
 * Righe di collaudo T-11, T-12, T-92 e T-93.
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
	 * Anni di pubblicazione dichiarati dal file, o nullo se il file non li dice.
	 *
	 * @var int|null
	 */
	private $durata_anni;

	/**
	 * Voci coperte da un modulo: voce, identificativo e nome del modulo.
	 *
	 * @var array<string, array{obbligo: string, nome: string}>
	 */
	private $moduli;

	/**
	 * Voci la cui pubblicazione si conta dalla cessazione: voce, regola, nome, anni e anni dei dati patrimoniali.
	 *
	 * @var array<string, array{regola: string, nome: string, anni: int, patrimoniali: int|null}>
	 */
	private $cessazione;

	/**
	 * Banche dati a cui si rinvia, nell'ordine del file: identificativo, nome, titolare, limite, fonte e voci.
	 *
	 * @var array<string, array{banca: string, nome: string, titolare: string|null, limite: string|null, fonte: string, voci: array<int, string>}>
	 */
	private $rinvii;

	/**
	 * Costruito solo da `carica()`, a controlli superati.
	 *
	 * @param array<string, array{id: string, nome: string, padre: string|null, riferimento: string}> $voci     Voci.
	 * @param string                                                                                  $radice   Radice.
	 * @param string                                                                                  $versione Versione.
	 * @param array<string, string>                                                                   $diciture Diciture.
	 * @param array<string, string>                                                                   $collocazioni Collocazioni.
	 * @param int|null                                                                                $durata_anni  Anni di pubblicazione.
	 * @param array<string, array{obbligo: string, nome: string}>                                     $moduli       Voci coperte da un modulo.
	 * @param array<string, array{regola: string, nome: string, anni: int, patrimoniali: int|null}>   $cessazione   Voci legate alla cessazione.
	 * @param array<string, array<string, mixed>>                                                     $rinvii       Banche dati a cui si rinvia.
	 */
	private function __construct( array $voci, string $radice, string $versione, array $diciture, array $collocazioni, ?int $durata_anni, array $moduli, array $cessazione, array $rinvii ) {
		$this->voci         = $voci;
		$this->radice       = $radice;
		$this->versione     = $versione;
		$this->diciture     = $diciture;
		$this->collocazioni = $collocazioni;
		$this->durata_anni  = $durata_anni;
		$this->moduli       = $moduli;
		$this->cessazione   = $cessazione;
		$this->rinvii       = $rinvii;
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

		/*
		 * La durata della pubblicazione e facoltativa nel formato: le versioni
		 * dell'albero scritte prima non la hanno e restano leggibili. Quando c'e,
		 * e un numero intero di anni da uno in su e nient'altro: una durata
		 * sbagliata nasconderebbe o lascerebbe pubblici i contenuti, quindi un
		 * valore dubbio rende il file illeggibile invece di essere interpretato.
		 */
		$durata_anni = null;

		if ( array_key_exists( 'durata_pubblicazione', $dati ) ) {
			$durata = $dati['durata_pubblicazione'];

			if ( ! is_array( $durata ) || array( 'anni' ) !== array_keys( $durata ) || ! is_int( $durata['anni'] ) || $durata['anni'] < 1 ) {
				return self::errore( 'trasparenza_albero_durata', __( 'La durata della pubblicazione nel file dell\'albero deve essere un numero intero di anni, da uno in su.', 'trasparenza-pa' ) );
			}

			$durata_anni = $durata['anni'];
		}

		/*
		 * Le voci coperte da un modulo ANAC sono facoltative nel formato, come
		 * la durata. Ogni modulo ha un nome da mostrare e un elenco di voci
		 * senza sottovoci, le sole in cui si colloca un contenuto; una voce sta
		 * in un modulo solo. Un elenco dubbio rende il file illeggibile.
		 */
		$moduli = array();
		$padri  = array_flip( array_filter( array_column( $voci, 'padre' ), 'is_string' ) );

		if ( array_key_exists( 'moduli', $dati ) ) {
			if ( ! is_array( $dati['moduli'] ) ) {
				return self::errore_moduli();
			}

			foreach ( $dati['moduli'] as $obbligo => $modulo ) {
				if ( ! is_string( $obbligo ) || 1 !== preg_match( '/^[a-z0-9_]{1,80}$/', $obbligo ) || ! is_array( $modulo ) ) {
					return self::errore_moduli();
				}

				$chiavi = array_keys( $modulo );
				sort( $chiavi );

				if ( array( 'nome', 'voci' ) !== $chiavi || ! is_string( $modulo['nome'] ) || '' === trim( $modulo['nome'] ) || strlen( $modulo['nome'] ) > 200 || ! is_array( $modulo['voci'] ) || array() === $modulo['voci'] || ! array_is_list( $modulo['voci'] ) ) {
					return self::errore_moduli();
				}

				foreach ( $modulo['voci'] as $voce ) {
					if ( ! is_string( $voce ) || ! isset( $voci[ $voce ] ) || $voce === $radice || isset( $padri[ $voce ] ) || isset( $moduli[ $voce ] ) ) {
						return self::errore_moduli();
					}

					$moduli[ $voce ] = array(
						'obbligo' => $obbligo,
						'nome'    => trim( $modulo['nome'] ),
					);
				}
			}
		}

		/*
		 * Le voci legate alla cessazione sono facoltative nel formato, come i
		 * moduli. Ogni regola dice per quanti anni dopo la cessazione si
		 * pubblica, con un nome da mostrare e la fonte fra le fonti del file,
		 * perche il termine lo fissa la norma. Una regola puo dire anche un
		 * termine piu breve per i dati patrimoniali, da zero anni dopo la
		 * cessazione agli anni della regola. Le voci sono senza sottovoci, e
		 * ciascuna sta in una regola sola. Un elenco dubbio rende il file
		 * illeggibile: un termine sbagliato nasconderebbe o lascerebbe pubblici
		 * i dati delle persone.
		 */
		$cessazione = array();
		$fonti      = is_array( $dati['fonti'] ?? null ) ? array_filter( array_column( array_filter( $dati['fonti'], 'is_array' ), 'chiave' ), 'is_string' ) : array();

		if ( array_key_exists( 'cessazione', $dati ) ) {
			if ( ! is_array( $dati['cessazione'] ) ) {
				return self::errore_cessazione();
			}

			foreach ( $dati['cessazione'] as $regola => $termine ) {
				if ( ! is_string( $regola ) || 1 !== preg_match( '/^[a-z0-9_]{1,80}$/', $regola ) || ! is_array( $termine ) ) {
					return self::errore_cessazione();
				}

				$chiavi = array_keys( $termine );
				sort( $chiavi );

				$patrimoniali = $termine['anni_patrimoniali'] ?? null;

				if ( ( array( 'anni', 'fonte', 'nome', 'voci' ) !== $chiavi && array( 'anni', 'anni_patrimoniali', 'fonte', 'nome', 'voci' ) !== $chiavi ) || ( in_array( 'anni_patrimoniali', $chiavi, true ) && ( ! is_int( $patrimoniali ) || $patrimoniali < 0 || ! is_int( $termine['anni'] ) || $patrimoniali > $termine['anni'] ) ) || ! is_string( $termine['nome'] ) || '' === trim( $termine['nome'] ) || strlen( $termine['nome'] ) > 200 || ! is_int( $termine['anni'] ) || $termine['anni'] < 1 || ! is_string( $termine['fonte'] ) || ! in_array( $termine['fonte'], $fonti, true ) || ! is_array( $termine['voci'] ) || array() === $termine['voci'] || ! array_is_list( $termine['voci'] ) ) {
					return self::errore_cessazione();
				}

				foreach ( $termine['voci'] as $voce ) {
					if ( ! is_string( $voce ) || ! isset( $voci[ $voce ] ) || $voce === $radice || isset( $padri[ $voce ] ) || isset( $cessazione[ $voce ] ) ) {
						return self::errore_cessazione();
					}

					$cessazione[ $voce ] = array(
						'regola'       => $regola,
						'nome'         => trim( $termine['nome'] ),
						'anni'         => $termine['anni'],
						'patrimoniali' => $patrimoniali,
					);
				}
			}
		}

		$rinvii = self::rinvii_da_dati( $dati, $voci, $radice, $padri, $fonti );

		if ( is_wp_error( $rinvii ) ) {
			return $rinvii;
		}

		return new self( $voci, $radice, $versione, array_intersect_key( $diciture, array_flip( self::DICITURE ) ), $collocazioni, $durata_anni, $moduli, $cessazione, $rinvii );
	}

	/**
	 * Legge e controlla l'elenco delle banche dati a cui si rinvia.
	 *
	 * L'elenco e facoltativo nel formato, come i moduli. Ogni banca dati ha un
	 * nome, la fonte fra le fonti del file e le voci senza sottovoci che copre;
	 * una voce puo stare in piu banche dati. Il titolare c'e solo dove la norma
	 * manda a lui l'accesso civico quando il dato comunicato non compare nella
	 * banca dati; il limite e una frase che restringe chi puo rinviare. Un
	 * elenco dubbio rende il file illeggibile: un rinvio a una banca dati che
	 * non copre la voce lascerebbe un obbligo non assolto con l'aria di esserlo.
	 * Righe T-92 e T-93.
	 *
	 * @param array<string, mixed>                $dati   Dati del file.
	 * @param array<string, array<string, mixed>> $voci   Voci.
	 * @param string                              $radice Radice.
	 * @param array<string, int>                  $padri  Voci con sottovoci.
	 * @param array<int|string, string>           $fonti  Chiavi delle fonti.
	 * @return array<string, array<string, mixed>>|WP_Error
	 */
	private static function rinvii_da_dati( array $dati, array $voci, string $radice, array $padri, array $fonti ) {
		$rinvii = array();

		if ( ! array_key_exists( 'rinvii', $dati ) ) {
			return $rinvii;
		}

		if ( ! is_array( $dati['rinvii'] ) ) {
			return self::errore_rinvii();
		}

		foreach ( $dati['rinvii'] as $banca => $rinvio ) {
			if ( ! is_string( $banca ) || 1 !== preg_match( '/^[a-z0-9_]{1,80}$/', $banca ) || ! is_array( $rinvio ) ) {
				return self::errore_rinvii();
			}

			$chiavi = array_keys( $rinvio );

			if ( array() !== array_diff( array( 'fonte', 'nome', 'voci' ), $chiavi ) || array() !== array_diff( $chiavi, array( 'fonte', 'limite', 'nome', 'titolare', 'voci' ) ) ) {
				return self::errore_rinvii();
			}

			foreach ( array( 'nome', 'titolare', 'limite' ) as $testo ) {
				if ( array_key_exists( $testo, $rinvio ) && ( ! is_string( $rinvio[ $testo ] ) || '' === trim( $rinvio[ $testo ] ) || strlen( $rinvio[ $testo ] ) > 200 ) ) {
					return self::errore_rinvii();
				}
			}

			if ( ! is_string( $rinvio['fonte'] ) || ! in_array( $rinvio['fonte'], $fonti, true ) || ! is_array( $rinvio['voci'] ) || array() === $rinvio['voci'] || ! array_is_list( $rinvio['voci'] ) ) {
				return self::errore_rinvii();
			}

			foreach ( $rinvio['voci'] as $voce ) {
				if ( ! is_string( $voce ) || ! isset( $voci[ $voce ] ) || $voce === $radice || isset( $padri[ $voce ] ) ) {
					return self::errore_rinvii();
				}
			}

			if ( count( array_unique( $rinvio['voci'] ) ) !== count( $rinvio['voci'] ) ) {
				return self::errore_rinvii();
			}

			$rinvii[ $banca ] = array(
				'banca'    => $banca,
				'nome'     => trim( $rinvio['nome'] ),
				'titolare' => isset( $rinvio['titolare'] ) ? trim( $rinvio['titolare'] ) : null,
				'limite'   => isset( $rinvio['limite'] ) ? trim( $rinvio['limite'] ) : null,
				'fonte'    => $rinvio['fonte'],
				'voci'     => $rinvio['voci'],
			);
		}

		return $rinvii;
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
	 * Anni di pubblicazione dichiarati dal file, o nullo se il file non li dice.
	 *
	 * @return int|null
	 */
	public function durata_anni(): ?int {
		return $this->durata_anni;
	}

	/**
	 * Il modulo ANAC che copre una voce, o nullo se nessuno.
	 *
	 * @param string $voce Voce.
	 * @return array{obbligo: string, nome: string}|null
	 */
	public function modulo( string $voce ): ?array {
		return $this->moduli[ $voce ] ?? null;
	}

	/**
	 * La regola che conta la pubblicazione di una voce dalla cessazione, o nullo se nessuna.
	 *
	 * @param string $voce Voce.
	 * @return array{regola: string, nome: string, anni: int, patrimoniali: int|null}|null
	 */
	public function cessazione( string $voce ): ?array {
		return $this->cessazione[ $voce ] ?? null;
	}

	/**
	 * Le banche dati a cui una voce puo rinviare, nell'ordine del file; vuoto se nessuna.
	 *
	 * @param string $voce Voce.
	 * @return array<int, array{banca: string, nome: string, titolare: string|null, limite: string|null, fonte: string}>
	 */
	public function rinvii( string $voce ): array {
		$trovati = array();

		foreach ( $this->rinvii as $rinvio ) {
			if ( in_array( $voce, $rinvio['voci'], true ) ) {
				unset( $rinvio['voci'] );
				$trovati[] = $rinvio;
			}
		}

		return $trovati;
	}

	/**
	 * Una banca dati dell'elenco, senza le voci, o nullo se non c'e.
	 *
	 * @param string $banca Identificativo della banca dati.
	 * @return array{banca: string, nome: string, titolare: string|null, limite: string|null, fonte: string}|null
	 */
	public function banca_dati( string $banca ): ?array {
		if ( ! isset( $this->rinvii[ $banca ] ) ) {
			return null;
		}

		$rinvio = $this->rinvii[ $banca ];
		unset( $rinvio['voci'] );

		return $rinvio;
	}

	/**
	 * Errore dell'elenco delle banche dati a cui si rinvia.
	 *
	 * @return WP_Error
	 */
	private static function errore_rinvii(): WP_Error {
		return self::errore( 'trasparenza_albero_rinvii', __( 'L\'elenco delle banche dati nel file dell\'albero deve dare a ognuna un nome, una fonte fra le fonti del file e voci senza sottovoci, senza ripetizioni; titolare e limite, se ci sono, sono testi non vuoti fino a 200 caratteri.', 'trasparenza-pa' ) );
	}

	/**
	 * Errore dell'elenco delle voci legate alla cessazione.
	 *
	 * @return WP_Error
	 */
	private static function errore_cessazione(): WP_Error {
		return self::errore( 'trasparenza_albero_cessazione', __( 'L\'elenco delle voci legate alla cessazione nel file dell\'albero deve dare a ogni regola un nome, un numero intero di anni da uno in su, una fonte fra le fonti del file e voci senza sottovoci, ciascuna in una regola sola; per i dati patrimoniali, se c\'è, un numero intero di anni da zero a quelli della regola.', 'trasparenza-pa' ) );
	}

	/**
	 * Errore dell'elenco delle voci coperte da un modulo.
	 *
	 * @return WP_Error
	 */
	private static function errore_moduli(): WP_Error {
		return self::errore( 'trasparenza_albero_moduli', __( 'L\'elenco delle voci coperte da un modulo nel file dell\'albero deve dare a ogni modulo un nome e voci senza sottovoci, ciascuna in un modulo solo.', 'trasparenza-pa' ) );
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
