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

**Cosa costruiamo.** Tre pezzi, uno per pull request. La scelta sul modo di presentare le
richieste è presa (30 ottobre 2026): la sezione dà istruzioni e recapiti, e le richieste
arrivano all'ente per i canali che già usa, posta elettronica, posta certificata o protocollo.
Nessun modulo da compilare sul sito: non c'è un dato personale in più da custodire.

1. **I dati dell'ente.** Una schermata "Accesso civico", aperta a chi amministra la sezione:
   l'ufficio a cui si presenta la richiesta generalizzata, con email e telefono; il titolare del
   potere sostitutivo, con email e telefono; facoltativo, l'indirizzo del modulo che l'ente
   mette a disposizione. Il file dell'albero dice in quale voce si mostrano, come già per il
   responsabile.
2. **La pagina pubblica.** Nella voce "Accesso civico" un riquadro dice, per ciascuno dei due
   tipi di accesso, a chi si scrive e come, a chi rivolgersi se la risposta non arriva, e che
   contro un rifiuto si chiede il riesame al responsabile. Il responsabile non si scrive due
   volte: il riquadro prende nome e recapiti dalla schermata "Responsabile".
3. **Il registro degli accessi.** Gli addetti annotano ogni richiesta con l'argomento, la data,
   l'esito e la data della decisione, senza i nomi di chi chiede, e la voce lo mostra, come
   chiede l'Allegato 1 alla riga "Registro degli accessi".

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
| 2 | `tests/AccessoCivicoPubblicoTest.php` | Nuovo. Righe T-103 e T-104 |
| 3 | `src/class-registro-accessi.php`, `src/class-schermata-registro-accessi.php` | Nuovi. Le righe del registro e la schermata |
| 3 | `tests/RegistroAccessiTest.php` | Nuovo. Righe T-105..T-109 |
| 4 | `src/class-registro-accessi.php` | Il registro nella voce, per cinque anni |
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

## 7-bis. La pagina pubblica (passo 2)

**Dove compare.** Nella voce che il file dell'albero indica per i dati dell'accesso civico,
dopo la dicitura dello stato e prima dei contenuti della voce, un riquadro con il titolo "Come
si chiede l'accesso civico". Compare solo se i dati della schermata "Accesso civico" sono
indicati e validi: senza, il riquadro direbbe a metà a chi scrivere, e chi amministra la
sezione vede già l'avviso.

**Cosa dice.**

| Parte | Contenuto |
|---|---|
| Accesso civico semplice | chiunque può chiedere i dati che la sezione dovrebbe pubblicare e non pubblica (art. 5, c. 1); la richiesta va al responsabile della prevenzione della corruzione e della trasparenza, con nome, email e, se indicato, telefono, presi dalla schermata "Responsabile"; se il responsabile non è indicato, la frase resta senza nome e recapiti |
| Accesso civico generalizzato | chiunque può chiedere dati e documenti ulteriori che l'ente detiene, nei limiti dell'art. 5-bis (art. 5, c. 2); la richiesta va all'ufficio indicato, con email e telefono |
| Per tutte e due | la richiesta non va motivata ed è gratuita, salvo il rimborso del costo di riproduzione su supporti materiali (art. 5, cc. 3 e 4); il collegamento al modulo dell'ente, se indicato |
| Se la risposta non arriva | il titolare del potere sostitutivo, con email e telefono (art. 2, c. 9-bis, l. 241/1990) |
| Contro il rifiuto | il riesame al responsabile della prevenzione della corruzione e della trasparenza (art. 5, c. 7) |

Le email sono collegamenti `mailto:`; i telefoni testo, come nel riquadro del responsabile.

## 7-ter. Modi di guasto (passo 2)

| Caso | Esito |
|---|---|
| Dati dell'accesso civico non indicati, o conservati non validi | nessun riquadro |
| Responsabile non indicato | il riquadro c'è; la frase dell'accesso semplice e del riesame resta senza nome e recapiti |
| Albero senza la collocazione | nessun riquadro in nessuna voce |
| Valori con codice | escaping di ogni valore; email e indirizzo del modulo passano da `esc_url` |

## 7-quater. Il registro degli accessi (passo 3)

**Cosa chiede la norma.** L'Allegato 1 della delibera ANAC 1310/2016, alla riga "Registro degli
accessi", chiede l'elenco delle richieste di accesso, agli atti, civico e generalizzato, con
l'oggetto e la data della richiesta e l'esito con la data della decisione, aggiornato ogni sei
mesi. Le linee guida ANAC sull'accesso generalizzato (delibera 1309/2016, § 9) raccomandano di
tenerlo e pubblicarlo senza dati personali.

**La schermata.** "Amministrazione trasparente > Registro degli accessi", aperta a chi
amministra la sezione. In alto il modulo per aggiungere una richiesta; sotto, l'elenco delle
richieste, ciascuna con il suo modulo per correggerla o toglierla. La schermata ricorda di non
scrivere nell'argomento nomi o altri dati di chi chiede.

**Le regole di una riga.**

| Campo | Regola |
|---|---|
| Tipo | uno fra accesso agli atti (l. 241/1990), accesso civico semplice, accesso civico generalizzato |
| Argomento | obbligatorio; una riga, senza codice, al massimo 300 caratteri |
| Data della richiesta | obbligatoria; AAAA-MM-GG, esistente e non dopo oggi nel fuso del sito |
| Esito | in corso, accolta, accolta in parte, rifiutata, differita |
| Data della decisione | vuota se l'esito è in corso, altrimenti obbligatoria; AAAA-MM-GG, esistente, non prima della richiesta e non dopo oggi |

**Il salvataggio.** Le regole delle altre schermate della sezione: permesso e nonce prima di
tutto; ogni campo controllato prima di scrivere; un salvataggio della sezione alla volta; prima
la voce di registro, poi il dato, che si rilegge. Ogni riga ha un numero che non si riusa,
neanche dopo che una riga è stata tolta: il numero dice al registro delle modifiche di quale
richiesta si parla. Il modulo di una riga porta la riga come la mostrava all'apertura: se nel
frattempo qualcun altro l'ha cambiata o tolta, è un conflitto e non si salva niente.

**Il registro delle modifiche.** Una voce `registro_accessi` per ogni aggiunta, correzione o
rimozione, con l'operazione, il numero della riga e i nomi dei campi cambiati. Perché a una
verifica si possa dire che cosa è stato tolto o cambiato, e da chi, la rimozione registra anche
tipo, data della richiesta, esito e data della decisione della riga tolta, e la correzione i
valori di prima dei campi cambiati fra questi (T-109). L'argomento invece non entra mai nel
registro: lo scrive una persona, e un nome scritto per sbaglio resterebbe nel registro dopo la
correzione, che non si può cancellare.

**Togliere una riga.** Accanto al pulsante "Togli dal registro" c'è una casella da spuntare;
senza la spunta il salvataggio si rifiuta e non tocca niente (T-108). Funziona senza JavaScript:
il controllo è nel salvataggio.

**Il dato conservato.** L'opzione `trasparenza_pa_registro_accessi`: l'ultimo numero dato e le
righe. Una riga conservata che non supera il controllo non si mostra al pubblico, ma la
schermata la elenca, con l'avviso, perché si possa correggere o togliere.

**Permesso.** Il permesso della sezione, `trasparenza_pa_amministrazione`. Un permesso
dedicato a chi gestisce le richieste è un'unità a parte, se l'ente lo chiederà.

## 7-quinquies. Modi di guasto (passo 3)

| Caso | Esito |
|---|---|
| Senza permesso, senza nonce o con nonce scaduto | rifiuto prima di leggere i campi |
| Operazione sconosciuta, numero di riga non valido | niente scritto, messaggio |
| Rimozione senza la casella di conferma spuntata | niente tolto, niente nel registro, messaggio |
| Campo non valido o non testo | niente scritto, niente nel registro, messaggio; il modulo rimostra quanto inviato |
| Riga cambiata o tolta da altri dopo l'apertura del modulo, partenza mancante | conflitto, niente scritto |
| Un altro salvataggio della sezione in corso | niente scritto, messaggio |
| Lettura fallita del registro conservato | niente scritto, niente nel registro delle modifiche, messaggio |
| Registro delle modifiche che rifiuta la voce | niente scritto, messaggio |
| Dato non scritto | messaggio che lo dice: il registro delle modifiche contiene un cambio non avvenuto |

## 8. Cosa deliberatamente NON fa

- Non riceve richieste dal sito: è la scelta del 30 ottobre 2026.
- Non tiene i nomi di chi chiede né i documenti della pratica: il registro degli accessi è un
  elenco pubblico; la pratica resta nel protocollo dell'ente.
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

- **Molte richieste.** Il registro sta in una sola opzione, non caricata a ogni pagina, e la
  schermata mostra tutte le righe con il loro modulo. Con qualche centinaio di richieste l'anno
  va bene per alcuni anni; oltre le mille righe la schermata passerà a pagine o le righe a un
  tipo di contenuto, in un'unità a parte.
- **Argomento nel registro delle modifiche.** Una rimozione per sbaglio perde l'argomento della
  riga tolta, che si ricostruisce dal protocollo dell'ente. È voluto: un nome scritto per
  sbaglio nell'argomento non deve restare nel registro, che non si cancella. Se l'ente preferisce
  poter ricostruire anche l'argomento, si aggiunge.

## Rotture volute, a lavoro finito

### Passo 1: i dati dell'ente

Quarantaquattro rotture, una per volta e poi tolte. Quarantatré fanno diventare rossa almeno una
prova. Nel controllo: campo non testo ammesso, spazi ai bordi non tolti, ufficio o titolare
vuoti ammessi, pulizia dei nomi tolta, nomi lunghi ammessi, nomi contati in byte, solo l'ufficio
o solo il titolare controllati, email non controllata, telefono libero, telefono facoltativo,
schema del modulo libero, modulo lungo ammesso, modulo di 2000 caratteri rifiutato, pulizia del
modulo tolta, modulo obbligatorio, lettura con `get_option` per chi riscrive, dati conservati
mostrati senza controllo, codice d'errore di un altro dato. Nel salvataggio: permesso tolto,
nonce tolto, nome del titolare nel registro, dato scritto prima del registro, rilettura tolta,
blocco tolto, conflitto tolto, campi applicati anche se non cambiati da chi salva, partenza
mancante accettata, partenze rilette dopo un rifiuto, dati uguali registrati. Nella schermata e
nell'avviso: avviso su ogni schermata del pannello, avviso a chi non ha il permesso, avviso con
i dati indicati, menu con un permesso generico, schermata aperta senza permesso, valore senza
escaping, collocazione del responsabile al posto di quella dell'accesso civico, rifiuto non
rimostrato, campo non testo rimostrato. Nel file dell'albero: versione in vigore riportata alla
2026-10-30, versione 2026-10-30 ritoccata, collocazione su un'altra voce, fonte della
collocazione tolta.

Il campo non testo rimostrato era verde al primo giro: una prova nuova manda il titolare come
elenco e guarda che la schermata lo rimostri vuoto, senza la parola "Array".

Una resta verde, ed è voluta: togliere il confronto dell'email con la sua versione ripulita non
cambia niente, perché `is_email()` e `sanitize_email()` di WordPress ammettono gli stessi
caratteri, e un indirizzo che la prima accetta la seconda lo lascia com'è. Il confronto resta,
come nella schermata del responsabile, per non dipendere da questa coincidenza.

### Passo 2: la pagina pubblica

Diciotto rotture, una per volta e poi tolte, e tutte fanno diventare rossa almeno una prova:
riquadro tolto, riquadro in ogni voce, collocazione del responsabile al posto di quella
dell'accesso civico, dati conservati mostrati senza controllo, responsabile ignorato,
responsabile conservato mostrato senza controllo, frase senza responsabile che promette
recapiti, modulo sempre, indirizzo del modulo senza `esc_url`, riga del telefono sempre, nome
senza escaping, email senza `esc_url` o senza escaping, titolare del potere sostitutivo con i
recapiti dell'ufficio, ufficio senza telefono, riesame tolto, gratuità tolta, titolare del
potere sostitutivo tolto.

Il responsabile conservato mostrato senza controllo era verde al primo giro: una prova nuova
conserva un responsabile con un nome che non supera il controllo e guarda che la frase resti
senza nome. Per l'email senza `esc_url` la prova usa un indirizzo con la e commerciale, che
`is_email()` ammette prima della chiocciola.

### Passo 3: il registro degli accessi

Quarantanove rotture, una per volta e poi tolte. Quarantotto fanno diventare rossa almeno una
prova. Nel controllo della riga: campo non testo ammesso, tipo o esito liberi, argomento vuoto
ammesso, pulizia dell'argomento tolta, argomento lungo ammesso, argomento contato in byte, data
della richiesta futura ammessa, calendario non controllato, data con un carattere davanti o con
un a capo in fondo, richiesta in corso con la data della decisione, decisione prima della
richiesta o futura, oggi in UTC al posto del fuso del sito. Nel dato conservato: lettura fallita
taciuta, ultimo numero ignorato o accettato anche se non è un intero positivo, righe scartate
non contate nel numero nuovo, numeri non positivi ammessi, campi non testo ammessi, righe per lo
schermo vuote. Nel salvataggio: permesso tolto, nonce tolto, operazione libera, numero libero o
con un a capo in fondo, controllo della riga tolto, partenza mancante ammessa, blocco tolto o
non rilasciato, lettura fallita ignorata, numero nuovo dal conteggio delle righe, partenza non
confrontata, correzione senza cambi registrata, campi cambiati invertiti, registro delle
modifiche ignorato, ultimo numero che scende dopo una rimozione, rilettura ignorata. Nella
schermata: aperta senza permesso, ordine per numero invece che per data, pari data senza ordine
per numero, avviso della riga non valida tolto, rifiuto non rimostrato, partenza nuova dopo un
rifiuto, valori e partenze senza escaping, esito del salvataggio non cancellato dopo averlo
mostrato.

Sette erano verdi al primo giro, e sono coperte da prove nuove: la data con un carattere davanti
(la prova usa uno zero, perché una lettera la scartava per caso il controllo sulle date
future), l'ultimo numero conservato che non è un intero positivo, l'operazione sconosciuta con
numero e partenza validi, la partenza mancante (ora si guarda che venga rifiutata prima di
chiedere il blocco), l'ordine per data e l'ordine per numero a pari data. Le rotture hanno anche
trovato che il simbolo `$` delle espressioni regolari accetta un a capo in fondo: date e numero
della riga ora si controllano con `\A` e `\z`. L'ordinamento delle righe prima di scriverle è
stato tolto: era verde perché i numeri nuovi arrivano già in ordine e la schermata ordina da sé.

Una resta verde, ed è voluta: togliere lo svuotamento della cache delle opzioni dopo la scrittura
non cambia niente nelle prove, che girano senza una cache persistente. Con una cache persistente
serve perché le letture successive vedano il dato nuovo, come nelle altre
schermate della sezione.

Dopo la rilettura, per la conferma e i valori di prima (T-108, T-109), nove rotture, tutte
rosse: conferma tolta, conferma con un valore qualunque, conferma chiesta anche per correggere,
valori di prima tolti, argomento fra i valori registrati, valori di prima anche dei campi non
cambiati, valori nuovi al posto di quelli di prima, casella tolta dalla schermata, registro
caricato a ogni pagina. I valori di prima vanno nel registro come `prima_tipo`,
`prima_richiesta`, `prima_esito`, `prima_decisione`: il registro di core accetta solo coppie
piatte di nome e valore.
