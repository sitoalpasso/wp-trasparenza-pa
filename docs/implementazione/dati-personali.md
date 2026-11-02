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
