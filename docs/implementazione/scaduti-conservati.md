# Scheda di implementazione: il contenuto scaduto non si cancella

Scheda scritta prima del codice e completata a lavoro finito. Serve TRASP-09 per la parte "mai
cancellato". La parte "fuori dalla sezione su ogni strada" è in
`docs/implementazione/scaduti-strade-di-lettura.md`.

## In parole semplici

**Cosa dice la norma.** L'art. 8, c. 3, del d.lgs. 33/2013 dice che, finito il periodo di
pubblicazione, i dati escono dalla sezione e restano ottenibili con l'accesso civico. Per
poterli dare a chi li chiede, bisogna averli ancora.

**Cosa succede oggi.** Un contenuto scaduto sparisce dal sito, ma chi può gestire i contenuti
lo può mettere nel cestino e cancellarlo, oppure rimetterlo in bozza e poi cancellarlo. Lo
stesso vale per i suoi documenti nella libreria dei file.

**Cosa costruiamo.** Un contenuto pubblicato e scaduto resta com'è: non va nel cestino, non si
cancella, non torna in bozza o in revisione, non diventa privato; i suoi documenti non si
cancellano e non vanno nel cestino. Chi ci prova vede il motivo. I collegamenti "Cestina" ed
"Elimina" non compaiono. Un contenuto pubblicato e non scaduto, e ogni bozza, restano come
oggi.

**Come si prova.** Righe T-157 (cestino e cancellazione) e T-158 (stato).

## 1. File toccati

- `src/class-conservazione-scaduti.php` (nuovo), agganciato da `trasparenza-pa.php`.
- `tests/ConservazioneScadutiTest.php` (nuovo).
- `docs/collaudo.md`, `docs/requisiti.md`, `docs/architettura.md`, questa scheda.

## 2. Requisiti serviti

| Requisito | Cosa copre questa unità |
|---|---|
| TRASP-09 | il contenuto scaduto e i suoi documenti non si cancellano |

## 3. Le regole

**Quale contenuto è fermo.** Un contenuto della sezione con stato pubblicato nella banca dati e
la fine della pubblicazione passata, secondo il componente comune. Lo stato si legge dalla
banca dati; se la lettura fallisce, il contenuto vale come fermo: una cancellazione rifiutata si
riprova, una fatta non si disfa.

**Cestino e cancellazione (T-157).** Per un contenuto fermo e per ogni allegato il cui contenuto
padre è fermo:

- il permesso di cancellare (`delete_post`) è negato a chiunque, così i collegamenti spariscono
  dalle schermate;
- `wp_trash_post()` e `wp_delete_post()`, che non guardano i permessi, si fermano lo stesso
  (agganci `pre_trash_post` e `pre_delete_post`);
- cancellare l'utente autore non porta via il contenuto (il tipo è registrato con
  `delete_with_user` falso; la prova lo tiene fermo).

**Stato (T-158).** Una scrittura che chiede per un contenuto fermo uno stato diverso da
pubblicato lo lascia pubblicato (aggancio `wp_insert_post_data`); il resto della scrittura
passa, quindi una correzione del testo si salva. Il motivo si mostra una volta a chi ha tentato.

## 4. Modi di guasto

| Guasto | Comportamento |
|---|---|
| Lettura dello stato fallita | il contenuto vale come fermo |
| Fine della pubblicazione assente o illeggibile su un contenuto pubblicato | il componente comune lo considera scaduto: il contenuto è fermo |

## 5. Cosa deliberatamente non fa

- Non ferma chi cambia prima la data di fine, rendendo il contenuto di nuovo in vigore, e poi lo
  toglie: ogni passo resta nel registro delle modifiche.
- Non ferma le scritture fatte direttamente sulla banca dati, né la disattivazione del
  componente.
- Non toglie dal contenuto scaduto un documento caricato per errore: il contenuto non è più
  visibile, e una procedura motivata per toglierlo è un'unità a parte, se serve.
- Non tocca i contenuti pubblicati e non scaduti: se fermarli anche loro è una scelta aperta.

## Com'è andata

Costruito come scritto sopra, con due aggiunte venute dalle prove:

- WordPress cancella i file della libreria con un aggancio suo (`pre_delete_attachment`), non con
  quello dei contenuti: il fermo è agganciato anche lì.
- Una programmazione chiesta su un contenuto fermo lo lascia pubblicato e gli lascia anche la
  data scritta nella banca dati; senza, il contenuto restava pubblicato con una data futura.

Gli articoli e gli altri tipi del sito non sono toccati: la prova lo verifica cestinando un
articolo pubblicato.

TRASP-09 resta in corso: la scelta se fermare anche i contenuti pubblicati e non scaduti è
aperta.

**Rotture volute**, una per volta, con le prove di `ConservazioneScadutiTest`. Tutte rosse.

| Rottura | Prova rossa |
|---|---|
| Permesso di cancellare non negato | T-157 |
| Cestino non fermato | T-157 |
| Cancellazione non fermata | T-157 |
| Cancellazione del file dalla libreria non fermata | T-157 |
| Allegati non guardati | T-157 |
| Lettura dello stato fallita trattata come contenuto non fermo | T-157 |
| Fermo anche sulle bozze | T-157 |
| Fermo anche sui contenuti in vigore | T-157 |
| Fermo anche sugli altri tipi | T-157 |
| Stato non trattenuto | T-158 |
| Data non trattenuta | T-158 |
| Motivo non ricordato | T-157, T-158 |
| Motivo mostrato più di una volta | T-157 |
| Fermo non agganciato | T-157, T-158 |

## Norme e fonti

| Regola | Norma | Fonte, e se è verificata |
|---|---|---|
| Alla scadenza i dati escono dalla sezione e restano ottenibili con l'accesso civico, quindi si conservano | d.lgs. 33/2013, art. 8, c. 3, e art. 5 (TRASP-09) | `docs/requisiti.md`; testo citato a memoria |
| Agganci di cestino e cancellazione | funzioni `wp_trash_post()`, `wp_delete_post()`, `wp_delete_attachment()` di WordPress | lette nel codice dell'ambiente di prova |
