# Scheda di implementazione: schemi ufficiali ANAC

Unita T2b. Scheda scritta prima delle prove. Il motore degli schemi (T2a) e gia provato con
schemi finti; qui entrano i file veri pubblicati dall'Autorita, come dati, e le prove
contro gli esempi compilati che l'Autorita stessa pubblica.

## In parole semplici

**Cosa facciamo.** Prendiamo dal sito dell'Autorita anticorruzione i tre schemi oggi
obbligatori (articoli 4-bis, 13 e 31 del decreto) e il file comune a cui rimandano, e li
mettiamo nel componente cosi come sono, senza riscriverli. Nel catalogo scriviamo per
ciascuno da dove viene, quando l'abbiamo preso, da quando vale e un'impronta del file, cioe
un codice calcolato dal contenuto che cambia se cambia anche una sola lettera.

**Perche l'impronta.** Fra agosto e settembre 2026 l'Autorita ha cambiato il contenuto dei
tre schemi lasciando lo stesso numero di versione, 1.0. Il numero da solo quindi non basta a
dire quale file abbiamo: l'impronta si, e la prova la controlla.

**Come si prova, e come si prova che la prova e vera.** L'Autorita pubblica sette esempi
compilati: devono passare tutti, cosi come sono scaricati. Poi si rompono apposta, un
difetto alla volta, e ogni rottura deve essere respinta con l'indicazione del campo
sbagliato. Infine si cambia un byte di uno schema e la prova sull'impronta deve diventare
rossa.

## 1. File toccati

| File | Perche |
|---|---|
| `schemas/catalogo.json` | Nuovo. Le tre versioni ufficiali e il file comune, con fonte, date e impronta |
| `schemas/*.schema.json` | Nuovi. I quattro file ufficiali, identici byte per byte a quelli scaricati |
| `tests/dati/esempi-anac/` | Nuovo. I sette esempi compilati pubblicati dalla guida ANAC |
| `tests/SchemiUfficialiTest.php` | Nuovo. Righe T-34..T-36 |
| `src/class-motore-schemi.php` | Il dato passato come testo puo cominciare con il segno BOM, come gli esempi ufficiali |
| `src/*.php`, `trasparenza-pa.php` | Commenti e un nome di funzione riscritti: i nomi dei campi ufficiali sono parole comuni (vedi T-32) |
| `docs/collaudo.md`, `docs/dati.md`, `docs/requisiti.md`, `README.md` | Righe, dati, stato dei requisiti, fonte degli schemi |

## 2. Requisito servito

TRASP-05 (validazione contro gli schemi ufficiali, chiusa per i tre obbligatori dagli esempi
ANAC). TRASP-26 (versioni con la data dell'Autorita, ora sui file veri).

## 3. Flusso per chi usa il sito

Nessuno in questa unita: gli schemi sono dati letti dal motore. Il modulo di compilazione
arriva con T2c.

## 4. Dati letti e scritti

Il formato del catalogo e quello di T2a (`docs/implementazione/motore-schemi.md`, sezione 4).
La fonte di ogni voce porta in piu, senza cambiare il formato:

| Chiave | Contenuto |
|---|---|
| `pagina` | la pagina della guida da cui il file si scarica |
| `sha256` | l'impronta del file come scaricato |
| `pagina_aggiornata_il` | la data di aggiornamento dichiarata dalla pagina, per gli schemi |

Identificativi degli obblighi: `art_4_bis`, `art_13`, `art_31`. Nome dei file: quello
ufficiale, che coincide con l'ultima parte del loro `$id`.

Date: `approvato_il` e `in_vigore_dal` sono il 2026-06-10, data dell'approvazione dei
modelli aggiornati da parte del Consiglio ANAC (vedi i dubbi).

## 5. Permessi

Nessuna superficie registrata.

## 6. Modi di guasto

| Caso | Esito |
|---|---|
| Uno schema ritoccato a mano nel repository | la prova sull'impronta diventa rossa |
| L'Autorita ripubblica lo stesso numero di versione con un contenuto nuovo | l'impronta cambia: il file nuovo si aggiunge come versione nuova, vedi i dubbi |
| Dato in testo che comincia con il segno BOM | si toglie il segno e si valida |

## 7. Test

Righe T-34..T-36 di `docs/collaudo.md`; la riga T-32 ora gira anche sui nomi ufficiali.

## 8. Cosa deliberatamente NON fa

Non legge i modelli CSV (T3). Non genera moduli (T2c). Non aggiorna gli schemi da solo
dalla rete: uno schema nuovo arriva con un aggiornamento del componente. Non include le
copie degli schemi precedenti a quelle scaricate il 2026-10-27, perche non sono piu
scaricabili dalla fonte ufficiale.

## 9. Come si prova sul sito vero

Niente da vedere in questa unita. Con T2c: incollare l'esempio ufficiale dell'art. 13 nel
modulo e vederlo accettato.

## Dubbi annotati

- **Data di approvazione.** Le pagine della guida citano la delibera 495/2024 (per 4-bis e
  31 come modificata dalla 481/2025) e risultano aggiornate il 17/09/2026 (13 e 4-bis) e il
  28/08/2026 (31), senza un atto nuovo trovato dopo il 10 giugno 2026. Si e scelto il 10
  giugno 2026, l'ultima approvazione nota. Se l'Autorita ha approvato i ritocchi di
  settembre con un atto proprio, la data va corretta.
- **Stesso numero, contenuto diverso.** Risolto il 2026-10-27 su decisione di Andrea: una
  versione si riconosce da numero e data, e due versioni possono avere file con lo stesso
  `$id` (`docs/implementazione/versioni-per-data.md`, riga T-37). La versione in vigore dei
  tre schemi e quindi `1.0@2026-06-10`.
- **Nomi dei campi nei commenti.** La prova T-32 cerca i nomi ufficiali anche nei commenti,
  e molti sono parole comuni (amministrazione, documento, oggetto, intestazione, anno). Per
  rispettarla sono stati riscritti alcuni commenti. Uno schema futuro con un campo come
  "stato" o "voce" renderebbe rossa la prova su parole che il codice usa per altro.
