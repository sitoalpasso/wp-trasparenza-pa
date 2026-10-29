# Scheda di implementazione: pubblicazione finché l'atto produce effetti

Completa il passo descritto in `fine-pubblicazione.md`, che lo lasciava fuori. Scheda scritta
prima del codice e completata a lavoro finito. L'art. 8, c. 3, del d.lgs. 33/2013 vuole la
pubblicazione per cinque anni "e comunque fino a che gli atti pubblicati producono i loro
effetti": un atto che produce effetti oltre i cinque anni resta pubblicato fino a quando li
produce.

## In parole semplici

**Cosa costruiamo.** Nel riquadro "Pubblicazione" compare una seconda data, facoltativa:
"L'atto produce effetti fino al". Chi pubblica la scrive quando sa che l'atto vale più a lungo
dei cinque anni di legge, per esempio un contratto di otto anni o un regolamento che resta in
vigore. La pubblicazione finisce nella più lontana delle due date: il 31 dicembre di legge
oppure il giorno in cui finiscono gli effetti. Se gli effetti finiscono prima del 31 dicembre
di legge, la fine resta quella di legge: la seconda data non accorcia mai.

**Come la costruiamo.** La seconda data è un dato del contenuto accanto alla decorrenza, con
le stesse regole di salvataggio: stesso nonce, stesso permesso, stato di partenza nel modulo,
un salvataggio alla volta, prima il registro e poi i dati, dati riletti. Si può cambiare e si
può togliere, per esempio quando l'atto viene revocato: ogni cambio è nel registro. Senza la
decorrenza non si salva, perché senza decorrenza non c'è una fine da prolungare.

**Come si prova, e come si prova che la prova è vera.** Prove nuove nelle righe T-81..T-83, e
le prove T-65 aggiornate perché il registro porta ora anche la seconda data; ogni regola ha una
rottura voluta che fa diventare rossa almeno una prova, elencata in fondo a lavoro finito.

## 1. File toccati

| File | Perché |
|---|---|
| `src/class-scheda-pubblicazione.php` | La seconda data nel riquadro, nel calcolo della fine, nel salvataggio e nel registro |
| `tests/PubblicazioneTest.php` | La richiesta di prova porta la seconda data; il registro la riporta |
| `tests/EffettiTest.php` | Nuovo. Righe T-81..T-83 |
| `docs/` | Collaudo, requisiti, architettura |

## 2. Requisito servito

TRASP-08 (art. 8, c. 3, d.lgs. 33/2013), seconda parte: "e comunque finché l'atto produce
effetti". Con questo passo TRASP-08 è coperto per la durata generale; restano i termini diversi
per voce (sotto).

## 3. Flusso per chi usa il sito

Chi può modificare il contenuto apre la sua schermata, scrive la decorrenza e, se serve, la
data di fine degli effetti, e salva. L'esito dice la fine della pubblicazione e se viene dalla
legge o dagli effetti. Il riquadro mostra sempre la fine registrata e, se gli effetti la
prolungano, lo dice.

## 4. Dati letti e scritti

| Dato | Lettura e scrittura |
|---|---|
| Metadato `_trasparenza_pa_effetti` | nuovo; la data AAAA-MM-GG di fine degli effetti, facoltativa. Letto dalla banca dati prima e dopo ogni scrittura. Vale una riga sola: con più righe il riquadro lo dice, e il salvataggio da lì le sostituisce con una, o le toglie, come per la decorrenza |
| Metadato `_trasparenza_pa_decorrenza` | come oggi |
| Fine della pubblicazione di core | la più lontana fra la fine di legge e la data degli effetti |
| Registro di core | la stessa riga `pubblicazione_contenuto`, con in più gli effetti di prima e di dopo; con più righe degli effetti, anche quelle trovate |

## 5. Permessi

Gli stessi della decorrenza: chi può modificare quel contenuto, più il nonce legato al
contenuto.

## 6. Modi di guasto

| Caso | Esito |
|---|---|
| Data degli effetti non nel formato, inesistente nel calendario, non testuale | niente scritto, niente nel registro, messaggio |
| Data degli effetti senza decorrenza | niente scritto, messaggio |
| Data degli effetti prima della decorrenza | niente scritto, messaggio: un atto non smette di produrre effetti prima che decorra l'obbligo di pubblicarlo |
| Data degli effetti prima della fine di legge | si salva, la fine resta quella di legge, e il riquadro lo dice |
| Campo degli effetti o sua partenza mancanti o non testuali | niente scritto, messaggio |
| Data degli effetti cambiata da un altro dopo l'apertura del modulo | conflitto, niente scritto |
| Lettura fallita degli effetti | niente scritto, niente nel registro, messaggio |
| Data degli effetti non scritta o non tolta | messaggio che lo dice, la fine non si tocca |
| Data degli effetti tolta | si toglie dopo la riga di registro; la fine torna quella di legge, e se è già passata il contenuto non compare più al pubblico |

## 7. Test

Righe T-81..T-83 di `docs/collaudo.md`, in `tests/EffettiTest.php`; le prove T-65 di
`tests/PubblicazioneTest.php` guardano la riga di registro con la seconda data.

## 8. Cosa deliberatamente NON fa

- **Non applica i termini diversi per voce.** Gli artt. 14, c. 2, e 15, c. 4, contano tre anni
  dalla cessazione del mandato o dell'incarico, non cinque dal 1° gennaio: è un'altra regola,
  con un'altra data di partenza, e arriva in un passo suo con le durate per voce nel file
  dell'albero.
- **Non ha una casella "fino a revoca".** Il componente comune vuole sempre una data di fine:
  per un atto senza termine noto chi pubblica scrive la data che conosce, anche lontana, e la
  aggiorna quando l'atto cessa. Ogni cambio resta nel registro.
- Non controlla che l'atto produca davvero effetti fino a quella data: lo dichiara chi
  pubblica, ed è nel registro con il suo nome.
- Non avvisa prima che gli effetti finiscano.

## 9. Come si prova sul sito vero

1. Apro un contenuto con decorrenza 10/03/2019: il riquadro dice fine al 31/12/2024, e il
   contenuto non compare al pubblico.
2. Scrivo come fine degli effetti il 30/06/2027 e salvo: l'esito dice fine al 30/06/2027,
   prolungata dagli effetti; da una finestra anonima il contenuto compare.
3. Tolgo la data degli effetti e salvo: la fine torna al 31/12/2024 e il contenuto sparisce.
   Nel registro ci sono le due righe.
4. Ripristino: si cestina il contenuto di prova.

## Dubbi annotati

- "Fino a che producono i loro effetti": si legge come l'ultimo giorno in cui l'atto produce
  effetti, compreso. È la stessa regola della fine di legge, che il componente comune tratta
  come giorno compreso.

## Rotture volute, a lavoro finito

Ventotto rotture, una per volta e poi tolte. Ventisette fanno diventare rossa almeno una
prova: effetti ignorati nella fine, effetti che accorciano, limite del 9999 tolto, partenza
degli effetti non testuale ammessa, effetti senza decorrenza ammessi, effetti non controllati
nel calendario, effetti prima della decorrenza ammessi, lettura fallita degli effetti
ignorata, cambio degli effetti trattato come nessun cambio quando la fine resta la stessa,
conflitto sugli effetti tolto, effetti di prima non registrati, effetti di dopo non
registrati, effetti trovati non registrati, righe in più non tolte, rimozione non fatta,
prima scrittura tolta, cambio non scritto, rilettura degli effetti tolta, esito che non dice
il prolungamento, campo del riquadro senza valore, partenza del riquadro vuota, escaping della
partenza tolto, valore non valido mostrato come data, avviso delle più date tolto, riquadro che
calcola la fine senza effetti, riquadro che dice sempre "non prolunga", salvataggio che scrive
la sola fine di legge.

Una resta verde, ed è voluta: togliere il controllo che la data degli effetti sia un testo
non cambia niente, perché il controllo del calendario che segue rifiuta ogni valore non
testuale, e senza decorrenza lo rifiuta il confronto con il vuoto. Il controllo resta, perché
dice che cosa si aspetta il modulo.
