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

**Come si prova, e come si prova che la prova è vera.** Prove nuove nelle righe T-84..T-88;
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
| File dell'albero | `cessazione`: per ogni regola un nome, gli anni dopo la cessazione, la fonte fra le fonti del file, le voci senza sottovoci, ciascuna in una regola sola. Facoltativo nel formato |
| Metadato `_trasparenza_pa_cessazione` | nuovo; la data AAAA-MM-GG. Letto dalla banca dati prima e dopo ogni scrittura. Una riga sola: con più righe il riquadro lo dice e il salvataggio da lì le sostituisce con una, o le toglie |
| Voce del contenuto | letta dalla banca dati per decidere se la cessazione è ammessa |
| Fine della pubblicazione di core | con la cessazione: tanti anni dopo la cessazione quanti ne dice l'albero, stesso giorno; senza: come oggi |
| Registro di core | la stessa riga `pubblicazione_contenuto`, con in più la cessazione di prima e di dopo |

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
| Voce cambiata dopo la cessazione verso una voce che non la prevede | il riquadro mostra il campo con la data e chiede di toglierla; finché resta, il salvataggio si rifiuta |

Nello stesso salvataggio la voce si scrive prima della pubblicazione: l'aggancio della
pubblicazione ha una priorità dopo quello della voce, dichiarata e provata.

## 7. Test

Righe T-84..T-88 di `docs/collaudo.md`, in `tests/AlberoNormativoTest.php` e
`tests/CessazioneTest.php`.

## 8. Cosa deliberatamente NON fa

- **Non separa la situazione patrimoniale.** L'art. 14, c. 2, vuole i dati patrimoniali e le
  dichiarazioni di coniuge e parenti pubblicati solo fino alla cessazione, non per i tre anni
  dopo. Arriva nel passo successivo, con una scelta nel riquadro per i contenuti che li
  portano. Fino ad allora chi pubblica deve mettere quei dati in un contenuto a parte e
  toglierlo alla cessazione.
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
  possibili è quella che non accorcia la pubblicazione.
- La Corte costituzionale, sentenza 20/2019, ha tolto per i dirigenti l'obbligo dei dati
  patrimoniali dell'art. 14, c. 1, lett. f): riguarda il passo sulla situazione patrimoniale,
  non questo.
