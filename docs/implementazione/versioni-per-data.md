# Scheda di implementazione: versioni riconosciute anche dalla data

Aggiunta alle unita T2a e T2b, scritta prima del codice. Decisione di Andrea del 2026-10-27.

## In parole semplici

**Il problema.** Fra agosto e settembre 2026 l'Autorita ha ritoccato i suoi schemi lasciando
lo stesso numero di versione. Se lo rifa lasciando anche lo stesso identificativo interno, il
catalogo oggi rifiuta il file nuovo, perche due file con lo stesso identificativo non possono
convivere. Si potrebbe solo sostituire il vecchio, e i dati raccolti con il vecchio verrebbero
controllati con il nuovo.

**Cosa cambia.** Una versione si riconosce dal numero **e** dalla data di approvazione: la
versione si chiama, per il componente, `1.0@2026-06-10`. Due file con lo stesso identificativo
possono stare nel catalogo se appartengono a versioni diverse, e ogni versione si controlla
solo con i suoi file. Ogni versione sa anche dirsi a parole, per esempio "versione 1.0 del
10 giugno 2026": e la frase che il modulo di compilazione (T2c) mettera accanto a ogni dato
salvato.

**Come si prova.** Si aggiunge allo schema finto una seconda "1.0", con lo stesso
identificativo e una regola diversa, e una seconda copia del file comune, anch'essa con lo
stesso identificativo. Un dato deve passare con una e non con l'altra, e viceversa.

## 1. File toccati

| File | Perche |
|---|---|
| `src/class-catalogo-schemi.php` | Codice della versione, file comuni per versione, controlli fatti versione per versione |
| `src/class-motore-schemi.php` | Un validatore per versione; la versione si indica con il suo codice; frase con la data |
| `tests/CatalogoSchemiTest.php`, `tests/MotoreSchemiTest.php`, `tests/SchemiUfficialiTest.php` | Codici al posto dei soli numeri; righe T-28 e T-37 |
| `tests/dati/schemi/` | Seconda "1.0" finta e seconda copia del file comune finto |
| `docs/implementazione/motore-schemi.md`, `docs/collaudo.md` | Formato del catalogo e righe |

## 2. Requisito servito

TRASP-26 (versioni affiancate con la data dell'Autorita).

## 3. Flusso per chi usa il sito

Nessuno in questa unita. Con T2c, accanto a ogni dato salvato: "validato con lo schema
dell'art. 13, versione 1.0 del 10 giugno 2026".

## 4. Dati letti e scritti

Formato del catalogo sempre 1, con due regole nuove:

| Regola | Contenuto |
|---|---|
| Codice della versione | `versione@approvato_il`, unico per obbligo. Due voci con lo stesso numero e la stessa data sono una versione doppia |
| `comuni` nella voce di uno schema | facoltativo: elenco dei file comuni che quella versione usa. Senza elenco, la versione usa tutti i file comuni del catalogo |

Identificativo `$id` unico **dentro ogni versione** (il suo file piu i suoi file comuni), non
piu in tutto il catalogo. I rimandi di ogni versione si risolvono solo sui file di quella
versione.

## 5. Permessi

Nessuna superficie registrata.

## 6. Modi di guasto

| Caso | Esito |
|---|---|
| Due voci con stesso obbligo, numero e data | catalogo rifiutato, versione doppia |
| Due file con lo stesso identificativo nella stessa versione | catalogo rifiutato, identificativo doppio |
| Una voce elenca un file comune che non e nel catalogo | catalogo rifiutato, codice dedicato |
| Codice di versione sconosciuto, anche solo il numero senza data | errore, il dato non si dichiara valido |

## 7. Test

Riga T-28 con il caso nuovo, riga T-37 nuova; T-29..T-36 passano ai codici.

## 8. Cosa deliberatamente NON fa

Non salva i dati (T2c). Non decide da solo quale data dare a un file ritoccato senza atto:
la data la scrive chi aggiorna il catalogo, con la fonte.

## 9. Come si prova sul sito vero

Niente da vedere finche non c'e il modulo (T2c).
