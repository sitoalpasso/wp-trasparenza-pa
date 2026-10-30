# Scheda di implementazione: rinvii alle banche dati nazionali

Scheda scritta prima del codice e completata a lavoro finito, passo per passo. Serve TRASP-25,
TRASP-29 e TRASP-11: un obbligo che si assolve con il collegamento a una banca dati nazionale.

## In parole semplici

**Cosa dice la norma.** Per alcuni obblighi l'ente non deve ripubblicare sul proprio sito dati
che ha già mandato a una banca dati nazionale: basta mettere nella sezione il collegamento a
quella banca dati (art. 9-bis, c. 2, del d.lgs. 33/2013). Le banche dati sono dieci, elencate
nell'Allegato B del decreto con l'amministrazione che le tiene e gli articoli che coprono. Il
d.l. 19/2026, art. 8, c. 3, lo ripete per gli artt. 15, 16, 17, 18, 19, 21, 22, 28, 29 e 30, e
al c. 2 aggiunge il sito "Soldi pubblici" per i pagamenti dell'art. 4-bis. Chi rinvia sposta
anche la risposta all'accesso civico: se il dato comunicato non compare nella banca dati, la
richiesta va al responsabile della trasparenza dell'amministrazione che la tiene (c. 3), salvo
che la mancanza dipenda dall'ente, e allora va al responsabile dell'ente (c. 4).

**Cosa costruiamo.** Tre pezzi, uno per pull request.

1. **L'elenco nel file dell'albero.** Quali banche dati esistono, chi le tiene, quali voci
   coprono, con la fonte. È un dato della norma, quindi sta nel file dell'albero e non nel
   codice, e si aggiorna con una versione nuova del file.
2. **La dichiarazione dell'ente.** Una schermata "Rinvii alle banche dati", aperta a chi
   amministra la sezione: per una voce l'ente sceglie la banca dati fra quelle che coprono
   quella voce, scrive l'indirizzo preciso dove si trova il suo dato, un'etichetta che dice
   cosa si trova dall'altra parte e la data da cui rinvia. Ogni cambio va nel registro.
3. **La pagina pubblica.** Sotto la voce compare il collegamento con l'etichetta, il nome della
   banca dati, l'amministrazione che la tiene, la data e la frase su chi risponde all'accesso
   civico.

**Come si prova, e come si prova che la prova è vera.** Righe di collaudo da T-92 in avanti,
assegnate passo per passo. Ogni regola ha una rottura voluta che fa diventare rossa almeno una
prova, elencata in fondo a lavoro finito.

## 1. File toccati

| Passo | File | Perché |
|---|---|---|
| 1 | `dati/alberatura/2026-10-30.json` | Nuova versione dell'albero: elenco dei rinvii e tre fonti nuove |
| 1 | `src/class-alberatura.php` | Lettura e controllo dell'elenco; le banche dati di una voce |
| 1 | `trasparenza-pa.php` | Il file dell'albero in vigore |
| 1 | `tests/RinviiTest.php` | Nuovo |
| 2 | `src/class-rinvii.php`, `src/class-schermata-rinvii.php` | Nuovi. Dichiarazioni dell'ente e schermata |
| 3 | `src/class-pagina-pubblica.php` | Il riquadro del rinvio sotto la voce |
| tutti | `docs/` | Collaudo, requisiti, dati, architettura, formato dell'albero |

## 2. Requisiti serviti

- TRASP-25 (art. 9-bis; d.l. 19/2026, art. 8, cc. 2 e 3): il rinvio è un dato, con data ed
  etichetta. La **verifica periodica automatica** che il collegamento risponda non è in questa
  unità: è un meccanismo del componente comune, come previsto dal piano, e arriva dopo. Fino ad
  allora TRASP-25 resta aperto per quella parte, e la scheda lo dice.
- TRASP-29 (art. 9-bis, cc. 3 e 4): ogni rinvio a una banca dati dell'Allegato B porta con sé
  l'amministrazione titolare e la frase su chi risponde all'accesso civico. Il caso dell'art. 19,
  che nessuna banca dati dell'Allegato B copre, non si può dichiarare come rinvio.
- TRASP-11 (art. 9): il collegamento è ammesso se porta al dato vero. Il componente non può
  saperlo da solo: chiede un indirizzo preciso e un'etichetta che dica cosa si trova, e li mette
  nel registro con il nome di chi li ha scritti.

## 3. Il formato dell'elenco (passo 1)

Chiave facoltativa `rinvii` del file dell'albero: un oggetto che a ogni banca dati, con un
identificativo di lettere minuscole, cifre e trattini bassi, fa corrispondere un oggetto con:

| Chiave | Obbligatoria | Contenuto |
|---|---|---|
| `nome` | sì | il nome della banca dati come lo scrive la fonte, fino a 200 caratteri |
| `fonte` | sì | la chiave di una fonte del file |
| `voci` | sì | voci senza sottovoci, non vuoto; una voce può stare in più banche dati |
| `titolare` | no | l'amministrazione che tiene la banca dati, come la scrive l'Allegato B. C'è solo dove vale l'art. 9-bis, cc. 3 e 4: la sua presenza è ciò che fa dire alla pagina pubblica che l'accesso civico va al titolare |
| `limite` | no | una frase che restringe chi può rinviare, mostrata nella schermata, per esempio "solo enti locali" |

Un elenco dubbio rende il file illeggibile, come per la cessazione: un rinvio a una banca dati
che non copre quella voce lascerebbe un obbligo non assolto con l'aria di esserlo.

## 4. L'elenco della versione 2026-10-30

Dall'Allegato B, testo vigente dal 23 giugno 2016, letto su Normattiva il 30 ottobre 2026, e dal
d.l. 19/2026, art. 8, c. 2. Le voci sono quelle dell'albero il cui riferimento cita l'articolo,
e il comma quando l'Allegato B lo indica.

| Banca dati | Titolare | Articoli dell'Allegato B | Voci |
|---|---|---|---|
| Perla PA | PCM-DFP | 15, 17, 18 | titolari di incarichi di collaborazione o consulenza; personale non a tempo indeterminato; incarichi conferiti e autorizzati ai dipendenti |
| SICO | MEF-RGS (IGOP) | 16, cc. 1-2; 17; 21, cc. 1 e 2 | dotazione organica; personale non a tempo indeterminato; contrattazione collettiva; contrattazione integrativa |
| Archivio contratti del settore pubblico | ARAN CNEL | 21, cc. 1 e 2 | contrattazione collettiva; contrattazione integrativa |
| SIQuEL | Corte dei conti | 22, soli enti locali | enti pubblici vigilati; società partecipate; enti di diritto privato controllati. Limite: solo enti locali |
| Patrimonio della PA | MEF-DT | 22, cc. 1 e 2; 30 | enti pubblici vigilati; società partecipate; enti di diritto privato controllati; patrimonio immobiliare; canoni di locazione o affitto |
| Rendiconti dei gruppi consiliari regionali | Corte dei conti | 28, c. 1 | rendiconti gruppi consiliari |
| BDAP | MEF-RGS | 29, c. 1; 37, c. 1, lett. a), b), c); 38 | bilancio preventivo e consuntivo; informazioni sulle singole procedure; atti delle amministrazioni aggiudicatrici; le tre voci delle opere pubbliche dell'art. 38 |
| REMS | Demanio | 30 | patrimonio immobiliare; canoni di locazione o affitto. Limite: solo immobili di proprietà statale |
| BDNCP | ANAC | 37, c. 1 | informazioni sulle singole procedure; atti delle amministrazioni aggiudicatrici |
| Servizio Contratti Pubblici | MIT | 37, c. 1 | informazioni sulle singole procedure; atti delle amministrazioni aggiudicatrici |
| Soldi pubblici | nessuno | 4-bis (d.l. 19/2026, art. 8, c. 2) | dati sui pagamenti |

## 5. Dati letti e scritti

| Dato | Passo | Lettura e scrittura |
|---|---|---|
| File dell'albero | 1 | letto e controllato; le banche dati di una voce |
| Opzione dei rinvii | 2 | nuova; per voce: banca dati, indirizzo, etichetta, data. Letta dalla banca dati prima e dopo ogni scrittura |
| Registro di core | 2 | una riga per ogni rinvio dichiarato, cambiato o tolto, con prima e dopo |

## 6. Permessi

La schermata dei rinvii la apre chi ha il permesso di amministrare la sezione, lo stesso degli
stati delle voci e del responsabile: il rinvio è una dichiarazione dell'ente, non un contenuto.

## 7. Modi di guasto (passo 1)

| Caso | Esito |
|---|---|
| Elenco non oggetto, banca dati con identificativo strano, chiavi mancanti o in più, nome vuoto o lungo, fonte non fra le fonti, voci vuote, inesistenti, radice, con sottovoci, ripetute nella stessa banca dati, titolare o limite vuoti o lunghi | il file si rifiuta |
| Nessun elenco | il file si legge, nessuna voce si assolve per rinvio |

## 7-bis. La dichiarazione dell'ente (passo 2)

**La schermata.** "Amministrazione trasparente > Rinvii alle banche dati" elenca le sole voci
che il file dell'albero lega ad almeno una banca dati, con il percorso della voce e il rinvio
dichiarato. Per ogni voce un modulo suo, salvato da solo: banca dati, scelta fra quelle della
voce, con il titolare e l'eventuale limite accanto al nome; indirizzo; etichetta; data. La
schermata ricorda che il collegamento assolve l'obbligo solo nei limiti dei dati che si trovano
davvero nella banca dati (art. 9-bis, c. 2) e che l'indirizzo deve portare al dato dell'ente,
non alla pagina iniziale della banca dati (art. 9).

**Le regole del dato.**

| Campo | Regola |
|---|---|
| Banca dati | fra quelle che il file dell'albero in vigore lega alla voce; vuota vuol dire nessun rinvio, e allora anche gli altri campi devono essere vuoti |
| Indirizzo | obbligatorio con la banca dati; indirizzo web completo che inizia con `https://` o `http://`, al massimo 2000 caratteri, che la pulizia non cambia |
| Etichetta | obbligatoria con la banca dati; una riga, senza codice, al massimo 200 caratteri: dice cosa si trova dall'altra parte |
| Data | obbligatoria con la banca dati; AAAA-MM-GG, esistente nel calendario e non dopo oggi nel fuso del sito: è il giorno da cui l'ente rinvia, non una promessa |

**Il salvataggio.** Le regole della schermata del responsabile: permesso e nonce prima di
tutto; ogni campo controllato prima di scrivere; un salvataggio della sezione alla volta;
prima la riga di registro, poi il dato, che si rilegge dopo averlo scritto. Il modulo porta il
rinvio che mostrava all'apertura: se nel frattempo qualcun altro ha cambiato il rinvio di
quella voce, è un conflitto e non si salva niente. Il dato si salva così come è stato scritto,
o si rifiuta: la pulizia non lo cambia mai in silenzio.

**Il registro.** Una riga `rinvio_banca_dati` per ogni rinvio dichiarato, cambiato o tolto, con
la voce e il rinvio di prima e di dopo, valori compresi: indirizzo ed etichetta non sono dati
personali, e tenerli nel registro è ciò che lega il collegamento a chi lo ha scritto
(TRASP-11). Un rinvio uguale a quello conservato non si registra.

**Il dato conservato.** L'opzione `trasparenza_pa_rinvii`: per voce, banca dati, indirizzo,
etichetta e data. Un rinvio conservato che non supera più il controllo, per esempio perché una
versione nuova dell'albero non lega più quella banca dati alla voce, non vale come rinvio: la
schermata lo dice accanto alla voce e la pagina pubblica non lo mostra.

## 7-ter. Modi di guasto (passo 2)

| Caso | Esito |
|---|---|
| Senza permesso, senza nonce o con nonce scaduto | rifiuto prima di leggere i campi |
| Voce inesistente, radice, voce senza banche dati, banca dati non della voce, campi non testuali o mancanti, indirizzo, etichetta o data non validi, data nel futuro, campi pieni senza banca dati | niente scritto, niente nel registro, messaggio; il modulo rimostra quanto inviato |
| Partenza mancante o non valida | niente scritto, messaggio che chiede di ricaricare |
| Rinvio della voce cambiato da altri dopo l'apertura del modulo | conflitto, niente scritto |
| Albero non disponibile | niente scritto, messaggio |
| Un altro salvataggio della sezione in corso | niente scritto, messaggio |
| Lettura fallita dei rinvii conservati | niente scritto, niente nel registro, messaggio |
| Registro che rifiuta la riga | niente scritto, messaggio |
| Rinvio non scritto | messaggio che lo dice: il registro contiene un cambio non avvenuto |

## 8. Cosa deliberatamente NON fa

- Non verifica che il collegamento risponda: è la verifica periodica del componente comune,
  in un'unità successiva.
- Non scrive gli indirizzi delle banche dati: l'indirizzo che porta al dato dell'ente cambia da
  ente a ente, e lo scrive l'ente.
- Non toglie dalla sezione i contenuti di una voce assolta per rinvio: l'ente può continuare a
  pubblicare i dati, purché identici a quelli comunicati (art. 9-bis, c. 2, ultima parte).
- Non decide se l'ente rientra nel limite di una banca dati: lo mostra nella schermata.

## Dubbi annotati

- **Rappresentazione grafica (art. 22, c. 1, lett. d).** L'Allegato B copre l'art. 22, cc. 1 e
  2, con Patrimonio della PA, ma "nei limiti dei dati effettivamente contenuti" (art. 9-bis,
  c. 2): la banca dati contiene i dati sulle partecipazioni, non una rappresentazione grafica.
  Si sceglie la lettura più prudente: la voce non è nell'elenco e resta da popolare sul sito.
- **REMS e gli immobili non statali.** L'Allegato B lega REMS all'art. 30 senza restrizioni,
  ma il nome della banca dati dice che gestisce gli immobili di proprietà statale: il patrimonio
  di un ente non statale lì non c'è, e il rinvio lascerebbe l'obbligo non assolto. Lettura
  prudente, come per SIQuEL: REMS porta il limite "solo immobili di proprietà statale", e per
  l'art. 30 resta Patrimonio della PA, senza limite.
- **Art. 37 con il codice dei contratti del 2023.** L'Allegato B cita le norme istitutive del
  codice del 2006. Le voci dell'art. 37 si lasciano nell'elenco perché l'allegato è vigente;
  l'aggiornamento alle banche dati di oggi è un cambio della versione dell'albero, quando la
  fonte lo dirà.
- **Soldi pubblici e il modulo dell'art. 4-bis.** La voce dei pagamenti ha anche il modulo ANAC.
  Il d.l. 19/2026 ammette il collegamento "per i soggetti i cui dati sui pagamenti sono già
  inseriti" nella banca dati: lo dichiara l'ente, e la schermata lo dice.

## Rotture volute, a lavoro finito

### Passo 1: l'elenco nel file dell'albero

Ventisette rotture, una per volta e poi tolte. Ventisei fanno diventare rossa almeno una prova:
elenco ignorato, elenco non oggetto ammesso, identificativo libero, identificativo fino a 90
caratteri, banca dati non oggetto ammessa, chiavi obbligatorie non controllate, chiavi in più
ammesse, testo vuoto ammesso, testo lungo ammesso, testo non stringa ammesso, solo il nome
controllato fra i testi, fonte fuori dalle fonti, voci vuote, voci con nomi, voce non testo,
radice ammessa, voci con sottovoci ammesse, voci ripetute ammesse, nome, titolare e limite non
ripuliti dagli spazi, errore dell'elenco ignorato, rinvii dati a tutte le voci, voci lasciate
nel rinvio restituito, banca dati inesistente non nulla, versione dell'albero in vigore lasciata
alla 2026-10-29-2.

Voce non testo e radice ammessa erano verdi al primo giro: un numero passava per una voce che
non c'era, e la radice dell'albero vero ha sempre sottovoci. Due prove nuove le fanno rosse:
un albero con una voce di sole cifre, dove il numero 15 passerebbe per la voce `15`, e un albero
fatto della sola radice.

Una resta verde, ed è voluta: togliere il controllo che la fonte sia un testo non cambia niente,
perché il confronto stretto con le chiavi delle fonti, che sono testi, rifiuta ogni altro valore.
