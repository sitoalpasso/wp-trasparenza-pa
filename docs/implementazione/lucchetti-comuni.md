# Scheda di implementazione: il blocco dei salvataggi sui lucchetti del componente comune

Scheda scritta prima del codice e completata a lavoro finito.

## In parole semplici

**Cosa succede oggi.** Le schermate di gestione della sezione salvano una alla volta: prima di
scrivere prendono un blocco della banca dati, e chi arriva mentre è preso aspetta qualche secondo
o rinuncia. Il blocco lo chiede questo componente da solo. Su alcune banche dati più vecchie,
ancora ammesse da WordPress, una connessione tiene un solo blocco alla volta: se durante un
salvataggio un altro componente installato sullo stesso sito prende il suo blocco, quello della
trasparenza si apre senza avviso, e un altro salvataggio può entrare in mezzo.

**Cosa costruiamo.** Il blocco si chiede al componente comune, che tiene il conto per tutti i
componenti dello stesso sito: dove la banca dati tiene un solo blocco per connessione, i blocchi
di tutti diventano lo stesso, e si apre solo quando l'ultimo l'ha lasciato. Dove la banca dati ne
tiene più d'uno, non cambia niente.

**Come si prova.** Riga T-167, con l'imitazione della banca dati vecchia che il componente comune
usa per le sue prove.

## 1. File toccati

- `src/class-blocco.php`: presa e rilascio con `conformita_core_prendi_lucchetto()` e
  `conformita_core_lascia_lucchetto()`; nome e attesa invariati.
- `trasparenza-pa.php`: interfaccia richiesta del componente comune `1.14.0`, la prima con i
  lucchetti comuni.
- `bin/installa-core.sh`: revisione del componente comune con i lucchetti.
- `tests/BloccoComuneTest.php` (nuovo); `tests/AvvioTest.php`, valori della compatibilità
  intorno alla versione nuova.
- `docs/`: collaudo, architettura, `gestione-stati.md`, questa scheda.

## 2. Requisiti serviti

| Requisito | Cosa copre questa unità |
|---|---|
| TRASP-19 | i salvataggi della gestione restano in fila anche dove la banca dati tiene un solo blocco per connessione, e il registro non riceve un cambio che un altro salvataggio ha coperto |

## 3. Le regole

- Il blocco ha lo stesso nome di prima, distinto per banca dati e prefisso; è il componente comune
  a decidere con che nome arriva alla banca dati.
- Attesa invariata: cinque secondi, poi si rinuncia con il messaggio di oggi.
- Una versione della banca dati che non si legge fa rifiutare la presa: il salvataggio rinuncia
  come con il blocco occupato. Il sistema si chiude, non si apre.

## 4. Modi di guasto

| Guasto | Comportamento |
|---|---|
| Blocco tenuto da un'altra richiesta | si aspetta al più cinque secondi, poi niente salvato, come oggi |
| Versione della banca dati che non si legge | presa rifiutata, niente salvato |
| Blocco di un altro componente preso dentro un salvataggio, su banca dati a blocco unico | il blocco resta chiuso per le altre richieste fino al rilascio del salvataggio |

## 5. Cosa deliberatamente non fa

- Non cambia le prove che prendono il blocco da una seconda connessione con il nome di sempre: la
  verifica continua gira su una banca dati che tiene più blocchi per connessione, dove il nome è
  quello. Il caso della banca dati vecchia lo prova T-167 con l'imitazione.

## Norme e fonti

| Regola | Fonte |
|---|---|
| Salvataggi in fila, registro coerente con il dato | TRASP-19 in `docs/requisiti.md` |
| Comportamento dei blocchi con nome nelle versioni di MySQL prima della 5.7.5 e di MariaDB prima della 10.0.2 | documentazione del componente comune (`class-conformita-core-lucchetti.php`), letta; non verificato sulla documentazione ufficiale di MySQL e MariaDB |
