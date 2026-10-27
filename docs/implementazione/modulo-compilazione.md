# Scheda di implementazione: voce di trasparenza e modulo di compilazione

Unita T2c. Scheda scritta prima del codice. Si fa in due passi, ciascuno con le sue prove:
T2c-1 (il tipo di contenuto, i permessi, la pubblicazione chiusa) e T2c-2 (il modulo
generato dallo schema, il salvataggio, gli errori accanto ai campi).

## In parole semplici

**Cosa costruiamo.** Nel menu dell'amministrazione compare la voce **Trasparenza**. Chi ha il
permesso crea una voce, sceglie a quale obbligo appartiene (per ora gli articoli 4-bis, 13 e
31) e compila un modulo. Il modulo non e scritto a mano: il componente lo costruisce leggendo
lo schema dell'Autorita, quindi quando lo schema cambia cambia anche il modulo, senza
toccare il codice. Al salvataggio il dato si controlla contro lo schema: se e giusto lo si
dice, se e sbagliato ogni errore compare accanto al campo che lo ha causato, in italiano.

**La versione resta attaccata al dato.** Nel momento in cui si raccoglie il primo dato, la
voce si lega alla versione dello schema in vigore quel giorno, e la scheda lo dice: "dato
raccolto con lo schema dell'obbligo art. 13, versione 1.0 del 10/06/2026". Se poi l'Autorita
pubblica una versione nuova, la voce vecchia continua a controllarsi con la sua.

**Cosa non facciamo ancora.** La voce non si pubblica: ogni tentativo resta in bozza con un
messaggio. La pubblicazione ha bisogno degli stati editoriali e della segnalazione al
responsabile della trasparenza (unita T5), e aprirla prima vorrebbe dire mettere online dati
non controllati. I dati arrivano solo dal modulo; il caricamento da file CSV e l'unita T3.

**Come si prova, e come si prova che la prova e vera.** Il giro completo: si prende l'esempio
ufficiale dell'art. 13, lo si inserisce nel modulo campo per campo come farebbe una persona,
si salva, e il dato salvato deve essere identico all'esempio. Poi si rompe un campo e
l'errore deve comparire accanto a quel campo. Un quarto schema finto, aggiunto solo come
file, deve produrre il suo modulo. Ogni prova ha la sua rottura voluta.

## 1. File toccati

| File | Perche |
|---|---|
| `trasparenza-pa.php` | Costanti del tipo e agganci |
| `src/class-tipo-voce.php` | Nuovo (T2c-1). Registrazione del tipo attraverso il componente comune, chiuso al pubblico |
| `src/class-permessi.php` | Nuovo (T2c-1). Permessi chiesti al componente comune, assegnati all'amministratore |
| `src/class-installazione.php` | Nuovo (T2c-1). Assegnazione ripetuta a ogni cambio di versione installata |
| `src/class-chiusura-pubblicazione.php` | Nuovo (T2c-1). Ogni tentativo di pubblicare lascia la voce in bozza, con il motivo |
| `src/class-modulo-schema.php` | Nuovo (T2c-2). Dallo schema ai campi del modulo, e dai valori inviati al dato |
| `src/class-scheda-voce.php` | Nuovo (T2c-2). Il riquadro nella schermata della voce, il salvataggio, gli errori |
| `tests/` | Nuove prove, righe T-38..T-45; un quarto schema finto |
| `docs/` | Collaudo, architettura, dati, requisiti |

## 2. Requisito servito

TRASP-05 (i tre obblighi si compilano solo con i campi dello schema), TRASP-06 (il modulo
nasce dallo schema, nessun campo nel codice), TRASP-26 (la versione resta legata al dato).

## 3. Flusso per chi usa il sito

Amministratore: menu **Trasparenza**, **Aggiungi voce**, titolo, scelta dell'obbligo,
**Salva bozza**. Al ricaricamento compare il modulo con la frase della versione. Compila,
salva bozza: in cima al riquadro "il dato rispetta lo schema" oppure il numero di errori, e
ogni errore accanto al suo campo. **Pubblica** non pubblica: la voce resta in bozza e un
avviso dice perche. Un utente con il ruolo Autore o Redattore di WordPress non vede il menu.

## 4. Dati letti e scritti

| Dato | Dove | Chi lo scrive |
|---|---|---|
| Voce | contenuto di tipo `trasparenza_voce`, sempre in bozza in questa unita | redattore |
| Obbligo e codice di versione | metadati della voce, scritti al primo salvataggio con un obbligo e poi mai piu cambiati dal modulo | il sistema, alla scelta dell'obbligo |
| Dato compilato | metadato della voce, in JSON, anche quando non e conforme | il sistema, dai valori del modulo |
| Esito dell'ultimo controllo | metadato della voce: conforme, oppure l'elenco dei difetti con percorso e regola | il sistema |
| Versione installata | opzione, per riassegnare i permessi a ogni aggiornamento | il sistema |

Regole del passaggio dai valori al dato: un campo lasciato vuoto non entra nel dato (non
diventa un testo vuoto); un gruppo con tutti i campi vuoti non entra; un elenco tiene solo
gli elementi compilati; un numero intero si converte solo se il valore e un intero scritto
in cifre, altrimenti resta testo e lo schema lo segnala. Il campo `$schema` del dato si
riempie da solo con l'identificativo dello schema della versione, se lo schema lo prevede.

Regole del passaggio dallo schema al modulo: un oggetto diventa un gruppo di campi; un
elenco diventa una serie di gruppi, quelli gia compilati piu uno vuoto; un elenco chiuso di
valori diventa una scelta; un numero diventa un campo numerico; il resto un campo di testo.
L'etichetta e il commento della proprieta, oppure la descrizione della definizione a cui
rimanda, oppure il nome della proprieta. Le varianti alternative di uno schema (come i tre
tipi di soggetto dell'art. 13) si mostrano tutte: si compila quella che serve e lo schema
controlla che sia una sola.

## 5. Permessi

I nomi dei permessi non sono scritti nel codice: si chiedono al componente comune, che li
ricava dall'identificativo del tipo. In questa unita li riceve solo il ruolo Amministratore,
con l'insieme completo; un ruolo proprio della trasparenza (redattore e responsabile) e una
scelta da fare con gli stati editoriali (T5). Salvataggio del modulo: nonce dedicato e
controllo del permesso di modificare quella voce; i valori ripuliti in ingresso e protetti in
uscita. Il tipo non e esposto all'interfaccia REST (`show_in_rest` falso, dichiarato) e non e
interrogabile dal pubblico: la consultazione pubblica passera dalla pagina della sezione e
dallo scarico (T4, T8).

## 6. Modi di guasto

| Caso | Esito |
|---|---|
| Avvio non riuscito o catalogo degli schemi non caricabile | il tipo non nasce oppure il modulo non compare, con un avviso; nessun dato scritto |
| Obbligo della voce non piu nel catalogo | il modulo dice che lo schema manca; il dato salvato resta com'e |
| Salvataggio senza nonce valido o senza permesso | niente scritto |
| Dato difforme | salvato come bozza con i difetti |
| Tentativo di pubblicare | la voce resta in bozza, avviso con il motivo |

## 7. Test

Righe T-38..T-45 di `docs/collaudo.md`.

## 8. Cosa deliberatamente NON fa

Non pubblica (T5). Non legge CSV (T3). Non genera i file da scaricare ne l'`at_map` (T4). Non
riempie da solo le date dell'intestazione: sono campi dello schema e il codice non li
conosce per nome. Non disegna moduli speciali per singoli obblighi: un modulo uguale per
tutti, semplice, come raccomandato dal parere indipendente.

## 9. Come si prova sul sito vero

Creare una voce per l'art. 13, compilare i dati dell'esempio ufficiale e salvare: "il dato
rispetta lo schema". Cambiare una data in 2026-05-22: l'errore compare accanto a quella data.
Premere Pubblica: la voce resta in bozza.

## Dubbi annotati

- **Ruolo proprio.** L'albo crea un ruolo suo; qui per ora i permessi vanno solo
  all'amministratore. Quali ruoli servono (chi compila, chi valida e pubblica) si decide con
  gli stati editoriali.
- **Messaggi della libreria.** La libreria di validazione scrive in inglese. Il modulo mostra
  un messaggio italiano per ogni tipo di regola violata, con il dettaglio originale fra
  parentesi.
- **Elenchi descritti per posizione.** Nell'art. 13 gli uffici di un organo sono descritti
  con un elenco di schemi, uno per posizione, e ce n'e uno solo: per le regole di JSON
  Schema (draft-07) lo schema controlla il primo ufficio e lascia libero il secondo e i
  successivi. Il modulo offre a tutti gli uffici gli stessi campi del primo, ma il controllo
  resta quello dello schema: un errore nel secondo ufficio non viene segnalato. Il
  componente non corregge lo schema dell'Autorita; il limite va dichiarato.
