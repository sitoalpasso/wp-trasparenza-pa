# Scheda di implementazione: tre anni dalla cessazione per titolari e consulenti

Completa il passo descritto in `fine-pubblicazione.md`, che lo lasciava fuori. Scheda scritta
prima del codice e completata a lavoro finito. L'art. 8, c. 3, del d.lgs. 33/2013 fa salvi i
termini diversi degli artt. 14, c. 2, e 15, c. 4: i dati dei titolari di incarichi politici,
di amministrazione, di direzione o di governo, dei dirigenti e dei consulenti si pubblicano
"per i tre anni successivi" alla cessazione del mandato o dell'incarico, e non per cinque anni
dal 1° gennaio.

## In parole semplici

**Cosa costruiamo.** Nelle voci che la legge lega alla cessazione, per esempio "Titolari di
incarichi di collaborazione o consulenza", il riquadro "Pubblicazione" ha un campo in più:
"Data di cessazione del mandato o dell'incarico". Chi pubblica la scrive, e la pubblicazione
finisce tre anni dopo quel giorno: una cessazione del 10 marzo 2026 dà fine al 10 marzo 2029.
Se l'incarico è in corso, si scrive la fine prevista dall'atto di nomina, che la legge chiede
di indicare, e la si aggiorna se l'incarico finisce prima o viene prorogato. Senza data di
cessazione vale la regola generale dei cinque anni, e il riquadro avvisa che per i dati di una
persona serve la cessazione.

**Come la costruiamo.** Quali voci seguono la cessazione, e per quanti anni, è un dato del
file dell'albero, con la fonte, in una versione nuova (2026-10-29-2) che aggiunge solo
l'elenco e le due fonti. Il codice non conosce né gli articoli né le voci. La data di
cessazione è un dato del contenuto, con le stesse regole di salvataggio della decorrenza e
della fine degli effetti, e ogni cambio va nel registro.

**I dati patrimoniali finiscono alla cessazione.** Lo stesso comma fa un'eccezione: la
situazione patrimoniale e, se consentite, le dichiarazioni del coniuge non separato e dei
parenti entro il secondo grado si pubblicano solo fino alla cessazione, non per i tre anni
dopo. Nelle voci dell'art. 14 il riquadro ha una casella, "Il contenuto porta la situazione
patrimoniale o le dichiarazioni di coniuge e parenti": spuntata, la fine è il giorno della
cessazione, e la data di cessazione diventa obbligatoria. Anche questo termine sta nel file
dell'albero, nella regola dell'art. 14, come zero anni dopo la cessazione.

**Come si prova, e come si prova che la prova è vera.** Prove nuove nelle righe T-84..T-89;
le prove T-65 e T-82 aggiornate perché il registro porta anche la cessazione; ogni regola ha
una rottura voluta che fa diventare rossa almeno una prova, elencata in fondo a lavoro finito.

## Perché il campo è facoltativo anche nelle voci della cessazione

La voce "Titolari di incarichi politici, di amministrazione, di direzione o di governo" porta
insieme i dati dell'art. 13, c. 1, lett. a), sugli organi, e quelli dell'art. 14 sui singoli
titolari. Un contenuto sulla composizione di un organo segue i cinque anni; un contenuto sui
dati di una persona segue la cessazione. Per questo la cessazione è disponibile nella voce, e
la sceglie chi pubblica, contenuto per contenuto. Il riquadro lo spiega.

## 1. File toccati

| File | Perché |
|---|---|
| `dati/alberatura/2026-10-29-2.json` | Nuovo. L'albero 2026-10-29 più l'elenco delle voci legate alla cessazione e le due fonti |
| `trasparenza-pa.php` | L'albero in vigore diventa la versione 2026-10-29-2 |
| `src/class-alberatura.php` | Legge e controlla l'elenco: dice di una voce se segue la cessazione e per quanti anni |
| `src/class-scheda-pubblicazione.php` | Il campo della cessazione, il calcolo della fine, il salvataggio, il registro |
| `tests/` | Nuove prove; la prova della versione 2026-10-29 guarda il suo file per nome |
| `docs/` | Collaudo, requisiti, architettura |

## 2. Requisito servito

TRASP-08, per i termini diversi richiamati dall'art. 8, c. 3: artt. 14, c. 2, e 15, c. 4, del
d.lgs. 33/2013.

## 3. Flusso per chi usa il sito

Chi può modificare il contenuto sceglie la voce e salva. Se la voce segue la cessazione, il
riquadro "Pubblicazione" mostra il campo; chi pubblica scrive la decorrenza e la cessazione, e
salva. L'esito dice la fine e da quale regola viene.

## 4. Dati letti e scritti

| Dato | Lettura e scrittura |
|---|---|
| File dell'albero | `cessazione`: per ogni regola un nome, gli anni dopo la cessazione, la fonte fra le fonti del file, le voci senza sottovoci, ciascuna in una regola sola; facoltativi gli anni dopo la cessazione per i dati patrimoniali, da zero agli anni della regola. Facoltativo nel formato |
| Metadato `_trasparenza_pa_patrimoniale` | nuovo; `1` se il contenuto porta i dati patrimoniali, assente altrimenti. Letto dalla banca dati prima e dopo ogni scrittura; un valore diverso, o più righe, si sostituisce al salvataggio, con quelle trovate nel registro |
| Metadato `_trasparenza_pa_cessazione` | nuovo; la data AAAA-MM-GG. Letto dalla banca dati prima e dopo ogni scrittura. Una riga sola: con più righe il riquadro lo dice e il salvataggio da lì le sostituisce con una, o le toglie |
| Voce del contenuto | letta dalla banca dati per decidere se la cessazione è ammessa |
| Fine della pubblicazione di core | con la cessazione: tanti anni dopo la cessazione quanti ne dice l'albero, stesso giorno; con i dati patrimoniali, gli anni dei dati patrimoniali, cioè il giorno della cessazione per l'art. 14; senza cessazione: come oggi |
| Registro di core | la stessa riga `pubblicazione_contenuto`, con in più la cessazione e la casella dei dati patrimoniali di prima e di dopo |

## 5. Permessi

Gli stessi della decorrenza.

## 6. Modi di guasto

| Caso | Esito |
|---|---|
| Elenco malformato nel file: non un oggetto, chiavi diverse, nome vuoto o lungo, anni non interi o sotto uno, fonte che non è fra le fonti, voce che non esiste, radice, con sottovoci, in due regole | l'albero non si carica, come per gli altri errori del file |
| Cessazione non nel formato, inesistente, non testuale, prima della decorrenza | niente scritto, messaggio |
| Cessazione in una voce che non la prevede, o in un contenuto senza voce | niente scritto, messaggio |
| Cessazione e fine degli effetti scritte insieme | niente scritto, messaggio: con la cessazione la fine viene dalla legge sui titolari, e la fine degli effetti non si usa |
| Fine oltre il 9999 | niente scritto, messaggio |
| Lettura fallita della cessazione o della voce | niente scritto, niente nel registro, messaggio |
| Cessazione cambiata da un altro dopo l'apertura del modulo | conflitto |
| Cessazione non scritta o non tolta | messaggio, la fine non si tocca |
| Voce cambiata dopo la cessazione verso una voce che non la prevede | il riquadro mostra il campo con la data e chiede di toglierla; finché resta, il salvataggio si rifiuta, anche quello di una decorrenza nuova, e la fine resta quella registrata. È un limite voluto: la fine non cambia finché l'addetto non decide, e il riquadro lo dice |
| Casella dei dati patrimoniali senza data di cessazione | niente scritto, messaggio: senza la cessazione questi dati resterebbero pubblici per la regola generale |
| Casella dei dati patrimoniali in una voce la cui regola non ha un termine per quei dati, o fuori dalle voci della cessazione | niente scritto, messaggio |
| Valore della casella diverso da spuntata o vuota | niente scritto, messaggio |

Nello stesso salvataggio la voce si scrive prima della pubblicazione: l'aggancio della
pubblicazione ha una priorità dopo quello della voce, dichiarata e provata.

## 7. Test

Righe T-84..T-89 di `docs/collaudo.md`, in `tests/AlberoNormativoTest.php` e
`tests/CessazioneTest.php`.

## 8. Cosa deliberatamente NON fa

- Non riconosce da solo un contenuto con i dati patrimoniali: lo dichiara chi pubblica con la
  casella, ed è nel registro. I dati patrimoniali vanno in un contenuto loro, separato dal resto
  dei dati del titolare, perché finiscono prima.
- Non applica gli artt. 15-bis e 15-ter, che hanno termini propri, né i termini che ANAC può
  fissare con il c. 3-bis dell'art. 8.
- Non controlla che la cessazione scritta sia quella dell'atto di nomina.
- Non mostra il campo prima di salvare la voce: si sceglie la voce, si salva, e il campo
  compare.

## 9. Come si prova sul sito vero

1. Apro un contenuto nella voce "Titolari di incarichi di collaborazione o consulenza", con
   decorrenza 01/02/2025: il riquadro mostra il campo della cessazione e l'avviso.
2. Scrivo cessazione 31/01/2026 e salvo: l'esito dice fine al 31/01/2029.
3. Sposto il contenuto in "Bandi di concorso" e salvo: il riquadro chiede di togliere la data
   di cessazione; la tolgo e salvo: la fine torna quella dei cinque anni.
4. Ripristino: si cestina il contenuto di prova.

## Dubbi annotati

- "Per i tre anni successivi alla cessazione": si legge come fine nello stesso giorno, tre
  anni dopo, compreso. Una cessazione del 29 febbraio dà fine al 1° marzo: fra le due letture
  possibili è quella che non accorcia la pubblicazione, perché qui c'è un obbligo di
  pubblicare. Lo storico dell'albo fa la scelta opposta, il 28 febbraio, perché lì i dati sono
  da togliere. Per i dati patrimoniali la questione non si pone: la fine è il giorno stesso
  della cessazione.
- La Corte costituzionale, sentenza 20/2019, ha tolto per i dirigenti l'obbligo dei dati
  patrimoniali dell'art. 14, c. 1, lett. f): riguarda il passo sulla situazione patrimoniale,
  non questo.

## Rotture volute, a lavoro finito

Cinquantuno rotture, una per volta e poi tolte. Cinquanta fanno diventare rossa almeno una
prova.

- Nel file dell'albero: elenco non oggetto accettato, identificativo non controllato, chiavi
  non controllate, nome vuoto accettato, nome lungo accettato, anni non interi accettati, anni
  zero accettati, fonte non controllata, voci vuote accettate, voci con nomi accettate, voce
  inesistente accettata, voce con sottovoci accettata, voce ripetuta accettata, nome non
  ripulito, regola mai trovata, versione nuova dell'albero non in vigore.
- Nel calcolo: un giorno in meno, limite del 9999 tolto.
- Nel salvataggio: aggancio prima della voce, partenza non testuale ammessa, cessazione senza
  decorrenza ammessa, cessazione non controllata nel calendario, prima della decorrenza
  ammessa, insieme alla fine degli effetti ammessa, lettura fallita della cessazione ignorata,
  lettura fallita della voce ignorata, più voci ammesse, voce non controllata, limite del 9999
  ignorato, cambio della cessazione trattato come nessun cambio quando la fine resta la
  stessa, conflitto tolto, cessazione di prima non registrata, cessazione di dopo non
  registrata, cessazioni trovate non registrate, righe in più non tolte, rimozione non fatta,
  prima scrittura tolta, cambio non scritto, rilettura tolta, esito che non dice la regola,
  fine che non usa la cessazione.
- Nel riquadro: regola mai trovata, campo sempre nascosto, campo nascosto con una data già
  scritta in una voce senza regola, valore non valido mostrato come data, partenza vuota,
  avviso della voce senza regola tolto, richiesta della data tolta, avviso delle più date
  tolto, fine attesa calcolata senza cessazione.

Una resta verde, ed è voluta: togliere il controllo che la cessazione sia un testo non cambia
niente, perché il controllo del calendario che segue rifiuta ogni valore non testuale, come
per la fine degli effetti. Il controllo resta, perché dice che cosa si aspetta il modulo.

Una rottura rimasta verde al primo giro, il cambio trattato come nessun cambio, ha portato una
prova in più: togliere una cessazione che dà la stessa fine della regola generale è un cambio,
e si registra.
