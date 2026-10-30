# Scheda di implementazione: accesso civico

Scheda scritta prima del codice e completata a lavoro finito, passo per passo. Serve TRASP-12:
dalla sezione si sa a chi chiedere un dato, un documento o un'informazione, e a chi rivolgersi
se la risposta non arriva.

## In parole semplici

**Cosa dice la norma.** Chiunque può chiedere all'ente i dati che dovrebbe pubblicare e non ha
pubblicato (accesso civico "semplice", art. 5, c. 1, del d.lgs. 33/2013) e, con i limiti
dell'art. 5-bis, qualunque altro dato o documento che l'ente detiene (accesso civico
"generalizzato", art. 5, c. 2). La richiesta semplice va al responsabile della prevenzione della
corruzione e della trasparenza; quella generalizzata all'ufficio che detiene i dati,
all'ufficio relazioni con il pubblico o a un altro ufficio che l'ente indica nella sezione
(art. 5, c. 3). Se la risposta non arriva nei termini si può chiedere al titolare del potere
sostitutivo (art. 2, c. 9-bis, della l. 241/1990), e contro un rifiuto si può chiedere il
riesame al responsabile della trasparenza (art. 5, c. 7). L'Allegato 1 della delibera ANAC
1310/2016, alla voce "Accesso civico", chiede di pubblicare per l'accesso semplice il nome del
responsabile e del titolare del potere sostitutivo, e per quello generalizzato gli uffici a cui
si presenta la richiesta, in tutti e due i casi con telefono ed email istituzionale e con le
modalità per presentarla.

**Cosa costruiamo.** Due pezzi, uno per pull request; un terzo, il registro degli accessi,
dipende da una scelta ancora aperta.

1. **I dati dell'ente.** Una schermata "Accesso civico", aperta a chi amministra la sezione:
   l'ufficio a cui si presenta la richiesta generalizzata, con email e telefono; il titolare del
   potere sostitutivo, con email e telefono; facoltativo, l'indirizzo del modulo che l'ente
   mette a disposizione. Il file dell'albero dice in quale voce si mostrano, come già per il
   responsabile.
2. **La pagina pubblica.** Nella voce "Accesso civico" un riquadro dice, per ciascuno dei due
   tipi di accesso, a chi si scrive e come, a chi rivolgersi se la risposta non arriva, e che
   contro un rifiuto si chiede il riesame al responsabile. Il responsabile non si scrive due
   volte: il riquadro prende nome e recapiti dalla schermata "Responsabile".

**Come si prova, e come si prova che la prova è vera.** Righe di collaudo da T-99 in avanti,
assegnate passo per passo. Ogni regola ha una rottura voluta che fa diventare rossa almeno una
prova, elencata in fondo a lavoro finito.

## 1. File toccati

| Passo | File | Perché |
|---|---|---|
| 1 | `dati/alberatura/2026-10-30-2.json` | Nuova versione dell'albero: la collocazione dei dati dell'accesso civico |
| 1 | `trasparenza-pa.php` | Il file dell'albero in vigore; i file nuovi |
| 1 | `src/class-accesso-civico.php`, `src/class-schermata-accesso-civico.php` | Nuovi. I dati e la schermata |
| 1 | `tests/AccessoCivicoTest.php` | Nuovo |
| 1 | `tests/RinviiTest.php` | La riga T-92 guarda la versione 2026-10-30 per nome, non come versione in vigore |
| 2 | `src/class-accesso-civico.php`, `src/class-pagina-pubblica.php` | Il riquadro nella voce |
| tutti | `docs/` | Collaudo, requisiti, dati, architettura |

## 2. Requisito servito

TRASP-12 (art. 5, d.lgs. 33/2013; art. 2, c. 9-bis, l. 241/1990; delibera ANAC 1310/2016,
Allegato 1, voce "Accesso civico"). La parte del registro delle richieste resta aperta finché
la scelta sul modo di presentarle non è presa: fino ad allora TRASP-12 resta "in corso".

## 3. Flusso per chi usa il sito

Chi amministra la sezione apre "Amministrazione trasparente > Accesso civico", scrive l'ufficio
e il titolare del potere sostitutivo con i loro recapiti e, se c'è, l'indirizzo del modulo, e
salva. Finché i dati mancano, chi può rimediare vede un avviso sulla bacheca e sulle schermate
della sezione, come per il responsabile. Chi consulta la sezione apre la voce "Accesso civico"
e trova il riquadro.

## 4. Dati letti e scritti

| Dato | Lettura e scrittura |
|---|---|
| Opzione `trasparenza_pa_accesso_civico` | ufficio, email e telefono dell'ufficio; titolare del potere sostitutivo, email e telefono; indirizzo del modulo. Nessun valore predefinito: assente vuol dire non indicato |
| Registro di core | una voce `accesso_civico` per ogni salvataggio che cambia qualcosa, con i soli nomi dei campi cambiati: il nome del titolare del potere sostitutivo è un dato personale |
| Opzione `trasparenza_pa_responsabile` | solo letta, per il riquadro pubblico |
| Collocazione `accesso_civico` del file dell'albero | la voce in cui si mostra il riquadro |

## 5. Permessi

Il permesso della sezione, `trasparenza_pa_amministrazione`, come per gli stati, il
responsabile e i rinvii. Nessun permesso nuovo.

## 6. Le regole del dato (passo 1)

| Campo | Regola |
|---|---|
| Ufficio | obbligatorio; una riga, senza codice, al massimo 200 caratteri |
| Email dell'ufficio | obbligatoria; un indirizzo valido, anche di posta certificata |
| Telefono dell'ufficio | obbligatorio; cifre, spazi, punti, barre, trattini e un + iniziale |
| Titolare del potere sostitutivo | obbligatorio; una riga, senza codice, al massimo 200 caratteri: nome, o nome e incarico |
| Email del titolare | obbligatoria; un indirizzo valido |
| Telefono del titolare | obbligatorio; come il telefono dell'ufficio |
| Indirizzo del modulo | facoltativo; indirizzo web completo che inizia con `https://` o `http://`, al massimo 2000 caratteri |

Telefono ed email sono obbligatori tutti e due perché l'Allegato 1 li chiede tutti e due. Il
salvataggio segue le regole della schermata del responsabile: permesso e nonce prima di tutto;
ogni campo controllato prima di scrivere; un salvataggio della sezione alla volta; prima il
registro, poi il dato, che si rilegge; si applicano solo i campi cambiati rispetto a quelli che
il modulo mostrava all'apertura, e un campo cambiato anche da altri nel frattempo è un
conflitto. Un campo che la pulizia cambierebbe si rifiuta.

## 7. Modi di guasto (passo 1)

| Caso | Esito |
|---|---|
| Senza permesso, senza nonce o con nonce scaduto | rifiuto prima di leggere i campi |
| Campo obbligatorio vuoto, campo non valido o non testo | niente scritto, niente nel registro, messaggio; il modulo rimostra quanto inviato |
| Partenza mancante o non valida | niente scritto, messaggio che chiede di ricaricare |
| Campo cambiato da altri dopo l'apertura del modulo | conflitto, niente scritto |
| Un altro salvataggio della sezione in corso | niente scritto, messaggio |
| Lettura fallita dei dati conservati | niente scritto, niente nel registro, messaggio |
| Registro che rifiuta la voce | niente scritto, messaggio |
| Dato non scritto | messaggio che lo dice: il registro contiene un cambio non avvenuto |
| Albero senza la collocazione | i dati si salvano; la schermata dice che la pagina pubblica non li mostra |

## 8. Cosa deliberatamente NON fa

- Non riceve richieste dal sito e non tiene il registro degli accessi: dipende dalla scelta
  aperta, e sarà un passo a parte.
- Non decide quale ufficio risponde: lo scrive l'ente. Un solo ufficio: l'art. 5, c. 3, lett.
  c, ammette che l'ente indichi un ufficio a cui presentare tutte le richieste generalizzate.
- Non scrive i termini di risposta: li dice la norma, e il riquadro la cita.

## Dubbi annotati

- **Telefono del responsabile.** La schermata del responsabile lo lascia facoltativo, mentre
  l'Allegato 1 chiede i recapiti telefonici anche per l'accesso semplice. Il riquadro del passo
  2 mostra il telefono del responsabile se c'è; renderlo obbligatorio è un cambio della
  schermata del responsabile, da valutare a parte.
- **Più uffici.** Un ente che vuole indicare un ufficio per ciascun tipo di dato non lo può fare
  in questo passo. La lettura scelta è quella dell'art. 5, c. 3, lett. c: un ufficio indicato
  nella sezione basta.
