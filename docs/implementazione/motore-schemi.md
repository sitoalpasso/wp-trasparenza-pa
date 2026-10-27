# Scheda di implementazione: motore degli schemi

Unita T2a. Scheda scritta prima del codice. Il motore si prova con schemi finti, scritti per
le prove nella stessa forma dei file ufficiali; gli schemi ufficiali entrano dopo come file
di dati (unita T2b) e il modulo di compilazione arriva con l'unita T2c.

## In parole semplici

**Cosa costruiamo.** Per tre obblighi (articoli 4-bis, 13 e 31 del decreto) l'Autorita
anticorruzione non chiede piu un documento qualunque ma dati in un formato preciso, descritto
da un file chiamato schema. Costruiamo il pezzo che prende un dato e dice se rispetta lo
schema, e se no dove sbaglia e perche. Ogni schema ha una versione e una data: quando
l'Autorita ne pubblica una nuova, la nuova si aggiunge accanto alla vecchia, e i dati gia
raccolti restano legati alla versione con cui sono nati.

**Come lo costruiamo.** Il controllo vero e proprio lo fa una libreria gia esistente e molto
usata, opis/json-schema, scelta da Andrea il 27 ottobre. Dentro il componente la libreria
viene rinominata, cosi non si scontra con altri componenti del sito che ne usano un'altra
versione. Gli schemi stanno in una cartella di dati con un elenco (il catalogo) che dice per
ciascuno l'obbligo, la versione, la data di approvazione, la data da cui vale e da dove viene.
I file ufficiali rimandano a un file comune con un indirizzo internet: il motore non va mai in
rete, risolve quei rimandi sui file che ha in cartella, e se un rimando non trova il suo file
rifiuta il catalogo invece di scoprirlo al primo dato.

**Come si prova, e come si prova che la prova e vera.** Otto righe di collaudo (T-26..T-33)
con schemi finti dai nomi di campo volutamente strani, cosi la prova statica che cerca i nomi
dei campi nel codice non scatta per caso su una parola italiana. Le rotture volute, una per
volta: una versione non ancora in vigore scelta lo stesso, un rimando risolto in rete, un
errore senza percorso del campo, un nome di campo scritto in un commento, un controllo del
catalogo tolto, la libreria non rinominata.

## 1. File toccati

| File | Perche |
|---|---|
| `composer.json`, `composer.lock` | La libreria fra le dipendenze; lo strumento che la rinomina (Strauss) fra quelle di sviluppo; il comando `composer prefissa` |
| `bin/avvisi-librerie.php` | Nuovo. Copia accanto alla libreria rinominata i file NOTICE che la licenza Apache-2.0 chiede di distribuire |
| `.gitignore` | `vendor-prefixed/` si genera, non si conserva nel repository |
| `src/class-catalogo-schemi.php` | Nuovo. Lettura e controllo del catalogo, tutto o niente |
| `src/class-motore-schemi.php` | Nuovo. Scelta della versione, validazione, errori con il percorso del campo |
| `trasparenza-pa.php` | Caricamento della libreria rinominata e delle due classi |
| `tests/dati/schemi/` | Nuovo. Catalogo e schemi finti |
| `tests/CatalogoSchemiTest.php`, `tests/MotoreSchemiTest.php`, `tests/NomiCampiTest.php` | Nuovi. Righe T-26..T-33 |
| `.github/workflows/ci.yml` | La libreria rinominata si genera prima delle prove |
| `docs/collaudo.md`, `docs/architettura.md`, `docs/dati.md`, `README.md`, `publiccode.yml` | Righe, classi, dati e attribuzione della libreria |

## 2. Requisito servito

TRASP-05 per la parte del motore (lo chiudono gli schemi ufficiali e le prove con gli esempi
ANAC, unita T2b). TRASP-06 (schema nuovo come solo file, nomi dei campi fuori dal PHP).
TRASP-26 (versioni affiancate con la data dell'Autorita).

## 3. Flusso per chi usa il sito

In questa unita nessuno: il motore non ha ancora una schermata. La usera il modulo di
compilazione (T2c), che mostrera gli errori del motore accanto ai campi.

## 4. Dati letti e scritti

Formato del catalogo, versione 1, file `schemas/catalogo.json`:

| Chiave | Contenuto |
|---|---|
| `formato` | il numero 1 |
| `comuni` | elenco dei file di definizioni condivise, ciascuno con `file` e `fonte` |
| `schemi` | elenco delle versioni: `obbligo` (stesso alfabeto degli identificativi dell'albero), `versione`, `approvato_il` e `in_vigore_dal` (date AAAA-MM-GG), `file`, `fonte` |
| `fonte` | `titolo`, `indirizzo`, `consultato_il` |

Ogni file di schema porta il proprio identificativo (`$id`), che deve essere unico. Il
motore non scrive niente: il dato da controllare arriva da chi lo chiama.

## 5. Permessi

Nessuna superficie registrata: niente pagine, niente REST, niente azioni. Il motore e una
funzione interna.

## 6. Modi di guasto

| Caso | Esito |
|---|---|
| Catalogo assente, illeggibile, formato ignoto, incompleto | nessun catalogo, codice d'errore dedicato |
| Versione doppia, identificativo di schema doppio, data non valida, fonte mancante | idem |
| File di schema assente, non JSON o senza identificativo; rimando senza file locale | idem |
| Libreria rinominata non generata | errore dedicato al primo uso, nessun errore fatale |
| Obbligo o versione sconosciuti | errore dedicato, il dato non si dichiara valido |
| Dato passato come elenco PHP invece che come testo JSON o oggetto | errore dedicato |
| Nessuna versione in vigore alla data | nessuna versione scelta: il dato nuovo non si raccoglie |

## 7. Test

Righe T-26..T-33 di `docs/collaudo.md`.

## 8. Cosa deliberatamente NON fa

Non contiene gli schemi ufficiali (T2b). Non genera moduli (T2c). Non legge CSV (T3). Non
scarica niente dalla rete, nemmeno per aggiornarsi: uno schema nuovo arriva con un
aggiornamento del componente. Non sceglie da solo una versione che non e ancora in vigore.

## 9. Come si prova sul sito vero

Niente da vedere in questa unita. La prova sul sito arriva con T2c: un dato sbagliato apposta
nel modulo deve mostrare l'errore accanto al campo giusto.

## Dubbi annotati

- La libreria rinominata non sta nel repository e si genera con `composer install`. Chi
  installa il componente scaricando il repository cosi com'e non la trova: per la prova in
  ambiente di collaudo serve un pacchetto costruito dalla verifica automatica, da preparare
  prima di M3.
- I nomi dei campi ufficiali sono in buona parte parole italiane comuni. La prova statica
  li cerca anche nei commenti: con gli schemi ufficiali (T2b) i commenti del PHP dovranno
  evitarle, e non solo il codice.
- Un elenco PHP vuoto non dice se e un oggetto vuoto o una lista vuota, e lo schema li
  distingue. Il motore accetta il dato solo come testo JSON o come oggetto, e rifiuta un
  elenco PHP con un errore dedicato: chi costruisce il dato deve dire quale dei due intende.
