# Scheda di implementazione: responsabile della trasparenza

Seconda unità della fase di gestione. Scheda compilata a lavoro finito. Serve TRASP-13:
la sezione indica il responsabile della prevenzione della corruzione e della trasparenza,
con nome e recapiti, dove la norma lo prevede. Serve anche TRASP-19 per la traccia di ogni
cambio.

## In parole semplici

**Cosa costruiamo.** Una seconda schermata nel pannello, "Amministrazione trasparente >
Responsabile", dove chi amministra la sezione scrive nome, email, telefono e indirizzo
dell'atto di nomina del responsabile. Nome ed email sono obbligatori, gli altri due no. La
pagina pubblica mostra questi dati in un riquadro, nella voce "Prevenzione della
Corruzione" sotto "Altri contenuti", che è dove la delibera ANAC 1310/2016 colloca la riga
del responsabile. Finché il responsabile non è indicato, chi può rimediare vede un avviso.

**Come la costruiamo.** La voce in cui compare il riquadro non è scritta nel codice: la
dice il file dell'albero, con una chiave nuova, `collocazioni`, accanto alla fonte che la
giustifica. La schermata usa lo stesso permesso e le stesse regole della schermata degli
stati: nonce, tutto controllato prima di scrivere, un salvataggio alla volta, prima il
registro e poi i dati, e i dati riletti dopo averli scritti. Il registro dice quali campi
sono cambiati, non i valori: nome e recapiti sono dati personali, e il registro li
conserverebbe anche dopo che l'amministrazione li ha cambiati. Un campo che la pulizia
cambierebbe, come un nome con un pezzo di codice, si rifiuta invece di salvarlo diverso da
come è stato scritto.

**Come si prova, e come si prova che la prova è vera.** Diciotto prove nuove (righe
T-34..T-38). Diciassette rotture volute, una per volta e poi tolte, le fanno diventare
rosse: collocazione verso la radice accettata, collocazione verso una voce inesistente
accettata, nome non confrontato con la sua versione ripulita, schema dell'atto di nomina
non controllato, telefono non controllato, nome stampato senza escaping, controllo del
permesso tolto, controllo del nonce tolto, valori scritti nel registro, dati scritti prima
del registro, rilettura tolta, blocco tolto, avviso su tutte le schermate, dati uguali
registrati come cambio, menu aperto con il permesso generico delle impostazioni, riquadro
in ogni voce, collocazione normativa spostata su un'altra voce.

## 1. File toccati

| File | Perché |
|---|---|
| `src/class-alberatura.php` | Legge e controlla la chiave facoltativa `collocazioni`; nuovo metodo `collocazione()` |
| `src/class-responsabile.php` | Nuovo. Dati, controllo, riquadro pubblico |
| `src/class-schermata-responsabile.php` | Nuovo. Sottovoce di menu, schermata, salvataggio, avviso |
| `src/class-blocco.php` | Nuovo. Il blocco della banca dati, prima dentro la schermata degli stati e ora condiviso dalle due schermate |
| `src/class-schermata-stati.php` | Usa il blocco condiviso; nessun cambio di comportamento |
| `src/class-pagina-pubblica.php` | Il riquadro nella voce indicata dall'albero |
| `trasparenza-pa.php` | Caricamento delle classi e aggancio della schermata |
| `dati/alberatura/corrente.json` | Collocazione del responsabile e sua fonte |
| `tests/dati/albero-finto.json` | Collocazione del responsabile nell'albero delle prove |
| `tests/AlberaturaTest.php`, `tests/AlberoNormativoTest.php`, `tests/ResponsabileTest.php` | Righe T-34..T-38 |
| `docs/collaudo.md`, `docs/requisiti.md`, `docs/dati.md`, `docs/architettura.md`, `docs/implementazione/alberatura.md` | Righe di collaudo, TRASP-13, dato e voce di registro, parti del plugin, formato del file |

## 2. Requisito servito

TRASP-13 (art. 43, c. 1, del d.lgs. 33/2013): il responsabile è indicato con nome e
recapiti, nella sottosezione prevista. TRASP-19: ogni cambio è una voce del registro di
core.

## 3. Flusso per chi usa il sito

Chi ha il permesso della sezione apre "Amministrazione trasparente > Responsabile", scrive
i dati e salva. Torna sulla stessa schermata con l'esito: salvato, nessun cambio, oppure il
motivo per cui non si è salvato niente, con il modulo ancora pieno di quello che aveva
scritto. Il visitatore apre la voce "Prevenzione della Corruzione" della pagina pubblica e
trova il riquadro con nome, email come collegamento, telefono e collegamento all'atto di
nomina, questi ultimi due solo se indicati.

## 4. Dati letti e scritti

| Dato | Lettura e scrittura |
|---|---|
| Opzione `trasparenza_pa_responsabile` | nuova; `nome`, `email`, `telefono`, `nomina`. Assente vuol dire non indicato. Si rilegge dalla banca dati prima e dopo ogni scrittura; dati conservati che non superano il controllo valgono come non indicati e non si mostrano |
| Chiave `collocazioni` del file dell'albero | nuova; `responsabile_trasparenza` porta a `prevenzione_della_corruzione`, con `fonte_collocazioni` che cita l'Allegato 1 della delibera ANAC 1310/2016 |
| Registro di core | una voce per salvataggio che cambia qualcosa: sezione `amministrazione_trasparente`, azione `responsabile_trasparenza`, dettagli `campi` con i soli nomi dei campi cambiati. Utente e istante li mette core |
| Dato temporaneo `trasparenza_pa_esito_responsabile_<utente>` | l'esito del salvataggio e, dopo un rifiuto, quanto inviato, per un minuto; letto e cancellato dalla schermata |
| Blocco della banca dati | lo stesso della schermata degli stati, preso per la durata del salvataggio |

## 5. Permessi

| Azione | Chi |
|---|---|
| Vedere la sottovoce e aprire la schermata | `trasparenza_pa_amministrazione`; senza, la schermata rifiuta |
| Salvare | `trasparenza_pa_amministrazione` più il nonce dell'azione, legato all'utente; senza uno dei due, rifiuto con codice 403 |
| Vedere l'avviso "responsabile non indicato" | `trasparenza_pa_amministrazione`, sulla bacheca e sulle schermate della sezione |
| Vedere il riquadro pubblico | tutti, senza autenticazione |

Il visitatore anonimo legge soltanto: il riquadro è costruito con escaping di ogni valore,
e i valori stessi sono stati rifiutati all'ingresso se contenevano codice.

## 6. Modi di guasto

| Caso | Esito |
|---|---|
| Campo sbagliato, campo non testuale, richiesta senza i dati | niente scritto, niente nel registro, messaggio; il modulo rimostra quanto inviato |
| Registro che rifiuta il cambio | niente scritto; il messaggio riporta il motivo |
| Dati non scritti dopo la voce di registro | l'esito è un errore che dice che il registro contiene un cambio non avvenuto; restano i dati di prima |
| Altro salvataggio in corso | attesa fino a cinque secondi, poi rinuncia senza scrivere |
| Albero senza collocazione del responsabile | i dati si salvano, la schermata avvisa che la pagina pubblica non li mostrerà, il riquadro non compare in nessuna voce |
| Albero non disponibile | come sopra; la pagina pubblica ha già il suo modo di guasto (T-19) |
| Collocazione verso una voce inesistente o la radice | il file dell'albero si rifiuta tutto, come per ogni altro difetto del file |
| Sezione non registrata (componente inerte) | nessuna sottovoce, nessun avviso |

**Limite dichiarato.** Come per gli stati, l'ordine è "prima il registro, poi i dati". Se la
banca dati accetta la voce di registro e poi non scrive i dati, la rilettura lo vede e
l'esito lo dice subito.

## 7. Test

Righe T-34..T-38 di `docs/collaudo.md`: T-34 in `tests/AlberaturaTest.php`, T-35 in
`tests/AlberoNormativoTest.php`, le altre in `tests/ResponsabileTest.php`. La prova del
blocco apre una seconda connessione alla banca dati, come quella degli stati, e dura cinque
secondi.

## 8. Cosa deliberatamente NON fa

Non permette di cancellare il responsabile: la norma lo vuole indicato, e lo si cambia
scrivendo i dati nuovi. Non conserva nel registro i valori precedenti. Non gestisce il
piano anticorruzione o il PIAO (TRASP-14), né il titolare del potere sostitutivo, che
arriva con l'accesso civico (TRASP-12). Non controlla che l'indirizzo dell'atto di nomina
risponda: è un collegamento dichiarato dall'amministrazione.

## 9. Come si prova sul sito vero

1. Da un utente con il permesso della sezione, sulla bacheca compare l'avviso "il
   responsabile ... non è indicato" con il collegamento "Indicarlo". Se non compare, il
   rilascio si annulla.
2. Nella schermata "Responsabile" provo a salvare un nome con `<b>` dentro: l'esito dice
   che non si è salvato niente, e il modulo ha ancora quello che avevo scritto.
3. Scrivo nome, email e telefono veri e salvo: esito "Dati del responsabile salvati",
   l'avviso sparisce. Nel registro delle modifiche c'è una voce `responsabile_trasparenza`
   con il mio utente e i nomi dei campi, senza i valori.
4. Da un browser senza accesso apro la voce "Prevenzione della Corruzione" della pagina
   pubblica: c'è il riquadro con i dati e l'email si apre come collegamento. Nella voce
   "Altri contenuti" il riquadro non c'è. Se manca o compare altrove, il rilascio si
   annulla.
5. Ripristino: si rimettono i dati di prima. La voce di registro resta, ed è giusto così.

## Dubbi annotati

- La collocazione segue l'Allegato 1 della delibera ANAC 1310/2016. Se una fonte successiva
  sposta la riga del responsabile, la collocazione cambia nel file, con la sua fonte, e non
  nel codice.
- L'avviso non compare su ogni schermata del pannello, per non diventare rumore: chi
  amministra la sezione lo vede sulla bacheca e sulle schermate della sezione.
