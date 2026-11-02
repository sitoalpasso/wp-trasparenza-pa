# Scheda di implementazione: le letture fallite nel riquadro della voce e nell'installazione

Scheda scritta prima del codice e completata a lavoro finito. Chiude un residuo della rilettura
del 28/10: due punti leggono dati con `get_post_meta()` e `get_option()`, che danno lo stesso
valore quando il dato non c'è e quando la lettura fallisce.

## In parole semplici

**Cosa succede oggi.** Il riquadro "Dati della voce" legge dalla banca dati l'obbligo, la versione
dello schema e il dato compilato. Se una di queste letture fallisce, il riquadro crede che il dato
non ci sia: mostra il modulo vuoto, o la scelta dell'obbligo, o dice che lo schema non è più nel
catalogo. Un addetto che salva quel modulo vuoto cancella il dato che c'era. Allo stesso modo
l'installazione, se non riesce a leggere la versione installata, crede di non essere mai stata
installata e riassegna i permessi a ogni richiesta, anche quelli che l'ente avesse tolto.

**Cosa costruiamo.** Una lettura fallita non vale come "vuoto" (AGENTS.md, punto 7): l'operazione si
ferma e lo dice. Il riquadro non mostra il modulo e spiega che i dati non si sono letti; il
salvataggio non scrive niente e lo dice; l'installazione non riassegna i permessi e lo dice, e
riprova alla richiesta successiva.

**Come si prova.** Righe T-161 (riquadro e salvataggio della voce) e T-162 (installazione).

## 1. File toccati

- `src/class-scheda-voce.php`, `src/class-installazione.php`.
- `tests/LettureFalliteTest.php` (nuovo).
- `docs/collaudo.md`, questa scheda.

## 2. Requisiti serviti

| Requisito | Cosa copre questa unità |
|---|---|
| TRASP-05, TRASP-26 | il dato della voce e la versione a cui è legato non si perdono per una lettura fallita |
| T-39 (permessi) | i permessi si assegnano al cambio di versione, non a ogni lettura fallita |

## 3. Le regole

**Riquadro (T-161).** Obbligo, versione e dato si leggono con `Metadato::leggi()`. Se una lettura
fallisce, o un metadato ha più di un valore, il riquadro mostra solo l'avviso, senza modulo e
senza scelta dell'obbligo, e un campo nascosto che dice al salvataggio che il modulo non c'era.

**Salvataggio (T-161).** Obbligo e versione si leggono con `Metadato::leggi()`. Se una lettura
fallisce, o un metadato ha più di un valore, o arriva il campo nascosto del riquadro senza modulo,
non si scrive niente e chi ha salvato vede il motivo una volta.

**Installazione (T-162).** Se la versione in memoria è quella del componente, niente da fare,
come oggi. Altrimenti la versione si rilegge dalla banca dati con `Opzione::leggi()`: se la
lettura fallisce, i permessi non si toccano e in bacheca compare l'avviso; se la versione è già
quella del componente, niente da fare.

## 4. Modi di guasto

| Guasto | Comportamento |
|---|---|
| Lettura di obbligo, versione o dato fallita nel riquadro | avviso, nessun modulo |
| Lettura di obbligo o versione fallita nel salvataggio | nessuna scrittura, motivo |
| Lettura della versione installata fallita | permessi non toccati, avviso, si riprova |

## 5. Cosa deliberatamente non fa

- Non cambia la lettura dell'esito dell'ultimo controllo: serve solo a mostrare il riassunto, e
  una lettura fallita lo fa vedere come "non controllato"; il salvataggio successivo lo rifà.
- Non cambia le letture usate solo per mostrare dati sulla pagina pubblica.
