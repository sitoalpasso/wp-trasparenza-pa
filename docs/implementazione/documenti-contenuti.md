# Scheda di implementazione: contenuti delle voci, terzo passo

Terzo dei quattro passi descritti in `contenuti-voci.md`. Scheda scritta prima del codice e
completata a lavoro finito. Il passo permette di allegare documenti a un contenuto: il file
lo deposita il componente comune nella sua cartella protetta, e questo componente decide
soltanto chi può aggiungere e togliere, quando, e con quale traccia.

## In parole semplici

**Cosa costruiamo.** Nella schermata di un contenuto compare il riquadro "Documenti":
l'elenco dei file allegati, ciascuno con il collegamento per scaricarlo da amministratore,
un campo per caricarne uno nuovo e, finché il contenuto non è pubblicato, una casella per
togliere un documento. Il file si carica salvando il contenuto, uno per volta.

**Come la costruiamo.** Il file non lo scrive questo componente: lo deposita il
componente comune, che lo mette nella sua cartella protetta solo dopo aver verificato che
il server ne neghi l'accesso diretto, ne calcola l'impronta e lo consegna solo attraverso
il suo indirizzo controllato. I documenti di un contenuto sono gli allegati depositati in
quel modo con quel contenuto come padre: non esiste un secondo elenco da tenere allineato.
Aggiungere si può sempre, anche a un contenuto pubblicato, perché aggiornare la sezione è
normale. Togliere si può solo mentre il contenuto non è pubblicato: un documento già esposto
non sparisce con un clic. Chi deve togliere un documento pubblicato per errore, per esempio
perché contiene dati personali, rimette il contenuto in bozza, toglie il documento e lo
ripubblica: tre passaggi, tutti nel registro. Ogni aggiunta e ogni rimozione lasciano una
voce di registro con il nome del file e la sua impronta.

**Come si prova, e come si prova che la prova è vera.** Prove nuove nelle righe da T-67 in
poi, con file veri depositati dal componente comune e la verifica della protezione fatta
rispondere da un server finto; ogni regola ha una rottura voluta che fa diventare rossa
almeno una prova, elencata in fondo a lavoro finito.

## 1. File toccati

| File | Perché |
|---|---|
| `src/class-scheda-documenti.php` | Nuovo. Il riquadro "Documenti", il deposito, la rimozione, l'esito |
| `trasparenza-pa.php` | Caricamento e agganci |
| `tests/DocumentiTest.php`, `tests/deposito-di-prova.php` | Nuove prove e aiuti al deposito |
| `docs/` | Collaudo, requisiti, architettura, dati |

## 2. Requisito servito

TRASP-19 (art. 6 del d.lgs. 33/2013): ogni documento aggiunto o tolto è nel registro, con
utente, istante, nome e impronta. La protezione dei file scaduti (TRASP-09 per i documenti)
è del componente comune, che non consegna l'allegato di un contenuto scaduto.

## 3. Flusso per chi usa il sito

Chi può modificare il contenuto apre la sua schermata, sceglie un file nel riquadro
"Documenti" e salva. Torna sulla stessa schermata con l'esito: documento aggiunto, oppure il
motivo per cui non lo è, per esempio la protezione della cartella non verificata o un tipo di
file non ammesso dall'installazione. Su un contenuto non pubblicato, spunta "Togli" accanto a
un documento e salva: il documento e il suo file spariscono, la riga di registro resta.

## 4. Dati letti e scritti

| Dato | Lettura e scrittura |
|---|---|
| Allegati del contenuto | gli allegati con il contenuto come padre e la marca di deposito del componente comune, letti dalla banca dati con un'interrogazione che distingue "nessuno" da "lettura fallita" |
| File | depositato da `conformita_core_deposita_allegato()`; tolto con `wp_delete_attachment()`, file compreso |
| Registro di core | una riga per ogni aggiunta e ogni rimozione: azione `documento_contenuto`, dettagli con l'operazione, il documento, il nome del file e l'impronta |
| Dato temporaneo `trasparenza_pa_esito_documenti_<utente>_<contenuto>` | l'esito, per un minuto, solo sulla schermata di quel contenuto |
| Blocco della banca dati | lo stesso delle altre schermate |

## 5. Permessi

Chi può modificare quel contenuto, più il nonce legato al contenuto. Lo scaricamento da
amministratore passa dall'indirizzo amministrativo del componente comune, che pretende il
permesso del tipo.

## 6. Modi di guasto

| Caso | Esito |
|---|---|
| Nonce mancante o di un altro contenuto, utente senza permesso, altro tipo | niente depositato, niente tolto, niente nel registro |
| Caricamento non riuscito, file non arrivato da un caricamento HTTP, tipo non ammesso, protezione non verificata | niente depositato, messaggio con il motivo del componente comune |
| Registro che rifiuta l'aggiunta | il documento appena depositato si cancella, file compreso; messaggio. Se non si riesce a cancellarlo, il messaggio lo dice |
| Documento da togliere che non è di quel contenuto, o non depositato dal componente comune | niente tolto, niente nel registro |
| Contenuto pubblicato | togliere è rifiutato |
| Lettura fallita degli allegati o dello stato del contenuto | niente tolto, niente nel registro |
| Registro che rifiuta la rimozione | niente tolto |
| Documento non cancellato dopo la riga di registro | messaggio che dice della rimozione non avvenuta |
| Altro salvataggio in corso | attesa fino a cinque secondi, poi rinuncia |

## 7. Test

Righe da T-67 in poi di `docs/collaudo.md`, in `tests/DocumentiTest.php`.

## 8. Cosa deliberatamente NON fa

- **Non scarica al pubblico.** Il componente comune consegna l'allegato al pubblico solo se
  il contenuto padre è consultabile, e il tipo dei contenuti oggi non lo è: la pagina
  pubblica (quarto passo) dovrà decidere come i documenti arrivano al pubblico senza aprire
  il tipo.
- Non limita i tipi di file ai formati aperti (TRASP-07, TRASP-20) e non segnala i PDF fatti
  di scansioni (TRASP-15): oggi vale l'elenco dei tipi ammessi dall'installazione. Unità
  futura, con l'elenco dei formati come dato con fonte.
- Non rinomina i file secondo le specifiche ANAC (TRASP-21).
- Non toglie i documenti quando il contenuto si cancella definitivamente: restano sul
  disco, senza padre, e il componente comune non li consegna più. Unità futura.
- Non impedisce di cancellare un documento dalla libreria dei media: è un limite comune con
  l'albo, da chiudere nel componente comune.
- Non chiede un motivo per la rimozione.

## 9. Come si prova sul sito vero

1. Apro un contenuto in bozza, carico un PDF nel riquadro "Documenti" e salvo: l'esito dice
   aggiunto, il documento è nell'elenco e si scarica dal collegamento; nel registro c'è la
   riga con il nome e l'impronta.
2. Pubblico il contenuto: la casella "Togli" non c'è più.
3. Rimetto il contenuto in bozza, spunto "Togli" e salvo: il documento sparisce, nel
   registro c'è la riga della rimozione.
4. Ripristino: si cestina il contenuto di prova.

## Dubbi annotati

- Togliere un documento da un contenuto pubblicato richiede di rimetterlo in bozza. Se
  l'ente preferisce un ritiro diretto con motivazione obbligatoria, si aggiunge senza
  cambiare il dato.
