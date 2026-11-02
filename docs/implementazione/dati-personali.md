# Scheda di implementazione: la conferma sui dati personali prima di pubblicare

Scheda scritta prima del codice e completata a lavoro finito. Serve TRASP-16 per i contenuti
della sezione.

## In parole semplici

**Cosa dice la norma.** L'obbligo di trasparenza non autorizza a pubblicare dati personali che
non servono: il GDPR, art. 5, vuole dati adeguati, pertinenti e limitati a quanto necessario;
l'art. 7-bis, c. 4, del d.lgs. 33/2013 chiede di rendere non intelligibili i dati personali non
pertinenti o eccedenti; i dati relativi alla salute non si diffondono mai (art. 2-septies, c. 8,
del d.lgs. 196/2003).

**Cosa succede oggi.** Un contenuto si pubblica con il pulsante "Pubblica" di WordPress, come un
articolo. Nessun passaggio chiede di guardare i dati personali prima.

**Cosa costruiamo.** Come nell'albo pretorio: un riquadro "Dati personali" nella schermata del
contenuto, con i casi tipici in cui un contenuto della sezione rivela dati che non dovrebbe, e una
casella di conferma. Senza la conferma il contenuto non si pubblica e non si programma: resta in
bozza e chi ci ha provato vede il motivo. La conferma, con chi l'ha data, va nel registro delle
modifiche. La valutazione resta dell'ente: il componente fornisce il passaggio obbligato, non la
decisione.

**Come si prova.** Righe T-159 (il passaggio obbligato su ogni strada) e T-160 (il riquadro).

## 1. File toccati

- `src/class-controllo-preventivo.php` (nuovo), agganciato da `trasparenza-pa.php`.
- `tests/ControlloPreventivoTest.php` (nuovo); `tests/avvio-comune.php`, per le prove che
  pubblicano contenuti senza occuparsi della conferma.
- `docs/collaudo.md`, `docs/requisiti.md`, `docs/architettura.md`, questa scheda.

## 2. Requisiti serviti

| Requisito | Cosa copre questa unità |
|---|---|
| TRASP-16 | passaggio obbligato sui dati personali prima della pubblicazione di un contenuto |

## 3. Le regole

**Quando serve la conferma (T-159).** Quando un contenuto della sezione entra in uno stato
visibile o che lo diventa da solo (pubblicato, programmato) da uno stato che non lo è. Lo stato di
partenza si legge dalla banca dati; se la lettura fallisce, la conferma serve. Un contenuto già
pubblicato che si modifica, o un programmato che arriva alla sua data, non la chiede di nuovo.

**Le strade.** La scrittura del contenuto (`wp_insert_post_data`: modulo, `wp_update_post()`,
modifica rapida e in blocco) e il cambio di stato fatto direttamente (`transition_post_status`,
come in `wp_publish_post()`), con lo stesso schema delle voci (T-40): senza conferma lo stato
torna bozza.

**La conferma.** Data nel riquadro, con la casella, nella stessa richiesta della pubblicazione:
gettone legato al contenuto e permesso di pubblicarlo. Vale per quel contenuto e per quella
richiesta. Prima dello stato si scrive la voce di registro con chi ha confermato; se il registro
rifiuta, il contenuto resta in bozza.

**Il riquadro (T-160).** Solo sui contenuti non ancora pubblicati né programmati, a chi può
pubblicarli. Elenca i casi tipici:

- curriculum con recapiti personali, codice fiscale, firma o fotografia che non servono;
- dati patrimoniali e reddituali di coniuge e parenti che non hanno dato il consenso;
- beneficiari di contributi e sussidi da cui si capisce lo stato di salute o il disagio economico
  e sociale (art. 26, c. 4, del d.lgs. 33/2013);
- coordinate bancarie, codici fiscali e indirizzi di residenza negli atti di liquidazione;
- graduatorie con punteggi legati a condizioni sociali o familiari.

Poi la frase sui dati relativi alla salute, e la casella.

## 4. Modi di guasto

| Guasto | Comportamento |
|---|---|
| Lettura dello stato di partenza fallita | serve la conferma |
| Registro che rifiuta la conferma | il contenuto resta in bozza, con il motivo |

## 5. Cosa deliberatamente non fa

- Non chiede la conferma a ogni modifica di un contenuto già pubblicato: il passaggio è quello
  della pubblicazione, come nell'albo.
- Non legge i documenti per cercare dati personali.
- Non riguarda le voci compilate sugli schemi, che oggi non si pubblicano (T-40): quando si
  apriranno con gli stati editoriali, la conferma andrà nel loro passaggio.

## Com'è andata

Costruito come scritto sopra, con tre cose venute dalle prove.

- Il cambio di stato fatto direttamente riporta in bozza, ma gli agganci di WordPress che
  seguono l'annuncio della pubblicazione partono lo stesso, come per le voci (T-40). Per questo la
  scrittura del contenuto ha il suo sbarramento: da lì il contenuto non passa neppure un istante da
  pubblicato, e la prova conta gli annunci di pubblicazione.
- La casella non spuntata manda comunque il gettone, perché il gettone sta nel modulo: la prova
  lo verifica.
- Con la lettura dello stato fallita, la conservazione degli scaduti (T-158) trattava una bozza
  come contenuto fermo e la forzava a pubblicata. Corretto lì, con una prova sua: la forzatura
  chiede ora uno stato letto e pubblicato, mentre il fermo sulla cancellazione resta.

Le altre prove della suite pubblicano contenuti senza occuparsi della conferma: l'avvio delle
prove la considera data, e solo `ControlloPreventivoTest` la chiede davvero.

**Rotture volute**, una per volta, con le prove di `ControlloPreventivoTest`.

| Rottura | Prova rossa |
|---|---|
| La scrittura non riporta in bozza | T-159 (annunci di pubblicazione) |
| Cambio di stato diretto non fermato | T-159 |
| Stato di partenza sempre considerato già visibile | T-159 |
| Lettura dello stato fallita considerata già visibile | T-159 |
| Casella non guardata | T-159 |
| Gettone non verificato | T-159 |
| Permesso di pubblicare non verificato | T-159 |
| Registro che rifiuta ignorato | T-159 |
| Conferma che resta valida per un secondo cambio di stato | T-159 |
| Programmato che arriva alla data fermato | T-159 |
| Motivo non ricordato, o mostrato più di una volta | T-159 |
| Riquadro sul contenuto pubblicato o programmato | T-160 |
| Riquadro a chi non può pubblicare | T-160 |
| Riquadro senza gettone | T-160 |
| Riquadro non agganciato | T-160 |
| Anche gli altri tipi del sito fermati | T-159 |
| Conferma delle prove non considerata | T-156, nelle altre prove |
| Conservazione che forza a pubblicato con la lettura fallita | T-158 |

Una rottura resta verde, per equivalenza dichiarata: accettare una casella con il numero di un
altro contenuto. Il gettone è legato al contenuto, quindi una casella diversa arriva solo con il
gettone di quell'altro contenuto, che la verifica rifiuta (prova "conferma per un altro
contenuto").

TRASP-16 è fatto: ogni strada di pubblicazione di un contenuto passa dalla conferma, e le voci non
si pubblicano (T-40). Quando le voci si apriranno, la conferma andrà nel loro passaggio.

## Norme e fonti

| Regola | Norma | Fonte, e se è verificata |
|---|---|---|
| Dati personali adeguati, pertinenti e limitati a quanto necessario | Regolamento (UE) 2016/679 (GDPR), art. 5, par. 1, lett. c | `docs/requisiti.md`; testo citato a memoria |
| Non intelligibili i dati personali non pertinenti o eccedenti | d.lgs. 33/2013, art. 7-bis, c. 4 | citato a memoria |
| Niente dati da cui ricavare salute o disagio economico e sociale dei destinatari di contributi | d.lgs. 33/2013, art. 26, c. 4 | citato a memoria |
| I dati relativi alla salute non si diffondono | d.lgs. 196/2003, art. 2-septies, c. 8 | citato a memoria |
| Casi tipici del riquadro | linee guida del Garante in materia di trasparenza (provvedimento del 15 maggio 2014) | citate a memoria, non verificate: elenco orientativo, la valutazione resta dell'ente |
| Agganci di scrittura e di cambio di stato | funzioni `wp_insert_post()`, `wp_publish_post()`, `wp_transition_post_status()` di WordPress | lette nel codice dell'ambiente di prova |
