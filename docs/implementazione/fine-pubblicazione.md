# Scheda di implementazione: contenuti delle voci, secondo passo

Secondo dei quattro passi descritti in `contenuti-voci.md`. Scheda scritta prima del codice e
completata a lavoro finito. Il passo dà a ogni contenuto la data da cui decorre l'obbligo di
pubblicazione e, da quella, la fine della pubblicazione calcolata secondo l'art. 8, c. 3, del
d.lgs. 33/2013. Senza la fine il componente comune non mostra il contenuto al pubblico: è
questo passo che rende un contenuto pubblicabile.

## In parole semplici

**Cosa costruiamo.** Nella schermata di un contenuto compare il riquadro "Pubblicazione",
con una sola data da scrivere: quella da cui decorre l'obbligo di pubblicare il documento, per
esempio la data di approvazione del bilancio. Sotto, il riquadro mostra quando finisce la
pubblicazione: il 31 dicembre del quinto anno successivo, perché la legge conta cinque anni a
partire dal 1° gennaio dell'anno dopo. Una decorrenza del 10 marzo 2026 dà fine al 31
dicembre 2031. Finché la data non c'è, il riquadro avvisa che il contenuto non compare al
pubblico.

**Come la costruiamo.** I cinque anni non stanno nel codice: stanno nel file dell'albero,
insieme alla fonte da cui vengono, come le voci e le diciture. Per aggiungerli serve una
versione nuova del file, perché una versione già distribuita non si cambia. La data di
decorrenza è un dato del contenuto; la fine la scrive il componente comune, che la usa per
decidere cosa nascondere. Il salvataggio segue le regole delle altre schermate: nonce,
permesso, data controllata, lettura dalla banca dati che distingue "assente" da "lettura
fallita", stato di partenza nel modulo, un salvataggio alla volta, prima il registro e poi il
dato, e il dato riletto.

**Come si prova, e come si prova che la prova è vera.** Prove nuove nelle righe T-63..T-66;
ogni regola ha una rottura voluta che fa diventare rossa almeno una prova, elencata in fondo a
lavoro finito.

## Come si chiama la versione nuova dell'albero, e perché

La versione nuova si chiama `2026-10-28-2`, nel file `dati/alberatura/2026-10-28-2.json`.

- Finora una versione dell'albero ha il nome del giorno in cui è stata scritta, e il nome del
  file coincide con la versione (riga T-55). Il 28 ottobre 2026 esiste già, ed è distribuita
  in `main`: non si riscrive, perché uno stesso nome non può indicare due alberi diversi. Il
  numero dopo la data distingue la seconda versione dello stesso giorno, e l'ordine dei nomi
  resta quello del tempo.
- La 2026-10-28 resta accanto, byte per byte, con l'impronta attesa scritta nella prova, come
  la 2026-10-24. La versione nuova è uguale alla precedente salvo la versione, la durata, la
  sua fonte e la voce di quella fonte nell'elenco delle fonti.
- La revisione 4 di TRASP-26 chiede che la versione di uno **schema** ANAC sia la data più
  l'impronta del file. Qui non la si applica all'albero e non la si risolve: l'albero non è uno
  schema ANAC, la sua impronta è già scritta nelle prove, e il residuo resta aperto per gli
  schemi, accanto alle righe T-28 e T-37. Se la stessa regola si estenderà all'albero, il nome
  `2026-10-28-2` resta valido come versione storica: le versioni non si rinominano.

## 1. File toccati

| File | Perché |
|---|---|
| `dati/alberatura/2026-10-28-2.json` | Nuovo. Versione dell'albero con la durata della pubblicazione e la sua fonte |
| `src/class-alberatura.php` | Legge e controlla la durata, facoltativa nel formato |
| `src/class-scheda-pubblicazione.php` | Nuovo. Il riquadro "Pubblicazione", il calcolo della fine, il salvataggio, l'esito |
| `trasparenza-pa.php` | Albero in vigore, caricamento e agganci |
| `tests/AlberoNormativoTest.php` | T-55 punta alla sua versione per nome; T-63 |
| `tests/PubblicazioneTest.php` | Righe T-64..T-66 |
| `docs/` | Collaudo, requisiti, architettura, dati, formato dell'albero |

## 2. Requisito servito

TRASP-08 (art. 8, c. 3, d.lgs. 33/2013): la fine della pubblicazione è il 31 dicembre
dell'anno di decorrenza più cinque, ed è il componente comune a nasconderla da quel momento
(TRASP-09, già coperto dal filtro in lettura di core). TRASP-19 per la decorrenza: ogni
cambio è nel registro.

## 3. Flusso per chi usa il sito

Chi può modificare il contenuto apre la sua schermata, scrive la data nel riquadro
"Pubblicazione" e salva. Torna sulla stessa schermata con l'esito: data salvata e fine
calcolata, nessun cambio, oppure il motivo per cui non è stata salvata. Il riquadro mostra
sempre la fine registrata; se non coincide con quella che la decorrenza darebbe con l'albero
in vigore, lo dice, e un salvataggio la riallinea.

## 4. Dati letti e scritti

| Dato | Lettura e scrittura |
|---|---|
| File dell'albero | `durata_pubblicazione.anni`, intero da uno in su, con `fonte_durata` fra le fonti. Facoltativo nel formato: senza, la fine non si calcola e la decorrenza non si salva |
| Metadato `_trasparenza_pa_decorrenza` | nuovo; la data AAAA-MM-GG. Letto dalla banca dati prima e dopo ogni scrittura. Vale una riga sola: con più righe il riquadro lo dice, e una data salvata da lì le sostituisce con una, come per la voce |
| Fine della pubblicazione di core | scritta solo con `conformita_core_imposta_fine_pubblicazione()`, riletta con `conformita_core_fine_pubblicazione()` |
| Registro di core | una riga per ogni cambio: azione `pubblicazione_contenuto`, dettagli con decorrenza e fine di prima e di dopo |
| Dato temporaneo `trasparenza_pa_esito_pubblicazione_<utente>_<contenuto>` | l'esito, per un minuto, solo sulla schermata di quel contenuto |
| Blocco della banca dati | lo stesso delle altre schermate |

## 5. Permessi

Gli stessi della voce: chi può modificare quel contenuto, più il nonce legato al contenuto.

## 6. Modi di guasto

| Caso | Esito |
|---|---|
| Data non nel formato, inesistente nel calendario, non testuale | niente scritto, niente nel registro, messaggio |
| Data cancellata dopo essere stata salvata | rifiuto: un contenuto con la decorrenza non torna senza, e la fine resta |
| Nonce mancante o di un altro contenuto, utente senza permesso, altro tipo | niente scritto, niente nel registro |
| Albero non disponibile, o senza durata | niente scritto, messaggio |
| Fine calcolata oltre l'anno 9999 | niente scritto, messaggio |
| Lettura fallita della decorrenza o della fine | niente scritto, niente nel registro, messaggio |
| Decorrenza cambiata da un altro dopo l'apertura del modulo | conflitto, niente scritto |
| Registro che rifiuta | niente scritto, messaggio con il motivo |
| Decorrenza scritta e fine rifiutata da core | messaggio che lo dice: il riquadro mostra la differenza e un nuovo salvataggio la riallinea |
| Altro salvataggio in corso | attesa fino a cinque secondi, poi rinuncia |

## 7. Test

Righe T-63..T-66 di `docs/collaudo.md`.

## 8. Cosa deliberatamente NON fa

- Non prolunga la pubblicazione oltre i cinque anni per gli atti che producono ancora effetti
  ("e comunque fino a che gli atti pubblicati producono i loro effetti"): serve una seconda
  data, più lontana, scelta da chi pubblica. Primo contenuto a scadere con decorrenza di
  quest'anno: fine 2031. Costruito dopo, in `effetti-oltre-cinque-anni.md`.
- Non applica i termini diversi richiamati dallo stesso comma (artt. 14, c. 2, e 15, c. 4, e
  la normativa sui dati personali) né quelli che ANAC può fissare con il c. 3-bis: oggi la
  durata è una sola per tutta la sezione. Se serviranno durate per voce, si aggiungono al file
  dell'albero accanto a quella generale.
- Non ricalcola da solo le fini già scritte se una versione nuova dell'albero cambia la
  durata: il riquadro mostra la differenza, e chi pubblica la riallinea salvando.
- Non mostra niente sulla pagina pubblica (quarto passo).

## 9. Come si prova sul sito vero

1. Apro un contenuto senza data: il riquadro "Pubblicazione" dice che non compare al
   pubblico.
2. Scrivo 10/03/2026 e salvo: l'esito dice salvato, il riquadro mostra fine al 31/12/2031,
   nel registro c'è la riga con decorrenza e fine.
3. Scrivo una data del 2015 e salvo: fine al 31/12/2020, e da un'altra finestra anonima il
   contenuto non è raggiungibile.
4. Ripristino: si cestina il contenuto di prova.

## Dubbi annotati

- Il c. 3-bis permette ad ANAC di ridurre la durata per alcuni obblighi; il componente non
  lo fa ancora (vedi sopra).
- "Anno successivo a quello da cui decorre l'obbligo": si legge come anno solare della data
  di decorrenza, come scritta da chi pubblica. È la lettura letterale del testo.

## Rotture volute, a lavoro finito

Quarantuno rotture, una per volta e poi tolte. Quaranta fanno diventare rossa almeno una
prova: durata non controllata, durata zero ammessa, chiavi in più ammesse, durata ignorata,
albero in vigore rimesso alla versione di prima, fine senza l'anno dopo, fine con il giorno
della decorrenza, limite del 9999 tolto, espressione della data senza ancore, giro del
calendario tolto, nonce non controllato, nonce non legato al contenuto, permesso non
controllato, tipo non controllato, cancellazione ammessa, conflitto non controllato, stessa
data con la fine giusta trattata come cambio, stessa data senza guardare la fine, data letta
con `get_post_meta()`, fine letta con `get_post_meta()`, dato scritto prima del registro,
rilettura della data tolta, rilettura della fine tolta, fine non scritta, blocco tolto, albero
non controllato, partenza non controllata con più date, date trovate non registrate, righe
vecchie lasciate, avviso delle più date tolto, fine di prima non registrata, partenza tolta
dal riquadro, escaping della partenza tolto, valore non valido mostrato come data, avviso del
riallineamento tolto, avviso della fine mancante tolto, esito legato al solo utente, esito
mostrato su ogni schermata, esito mostrato a ogni richiesta, salvataggio senza controllare
che la richiesta venga dal riquadro.

Una resta verde, ed è voluta: sostituire le ancore `\A` e `\z` con `^` e `$` ammetterebbe un
a capo in coda, ma il giro del calendario che segue lo rifiuta comunque. Le due difese si
coprono a vicenda; resta la più stretta.
