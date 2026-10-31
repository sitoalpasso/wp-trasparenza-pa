# Scheda di implementazione: i valori dei moduli tornano identici

Scheda scritta prima del codice e completata a lavoro finito. Corregge un difetto delle schermate
"Responsabile", "Accesso civico" e "Rinvii alle banche dati", trovato rileggendo la schermata del
registro degli accessi. Serve TRASP-13, TRASP-12 e TRASP-25, che quelle schermate realizzano, e
TRASP-19 per la traccia dei cambi.

## In parole semplici

**Il difetto.** Ogni modulo della sezione porta, nascosti, i valori che mostrava quando è stato
aperto: al salvataggio servono a capire se nel frattempo qualcun altro ha cambiato qualcosa. Se
in un campo di testo si scrive una sequenza come `&amp;`, la si salva così com'è, ma la funzione
di WordPress che prepara i valori per il modulo, `esc_attr()`, non la ricodifica: il browser la
legge come `&` e rimanda `&`. Al salvataggio il valore di partenza non è più uguale a quello
conservato e il salvataggio si rifiuta come conflitto, anche ricaricando la schermata. Il dato
resta bloccato finché qualcuno non lo cambia dalla banca dati.

**La correzione.** Una funzione sola, `Testo::esatto()`, prepara i valori dei moduli e i testi
liberi scritti dagli addetti ricodificando ogni carattere speciale, anche dentro una sequenza già
scritta: il browser rimanda esattamente il testo conservato. La usano le quattro schermate con i
valori di partenza e la tabella pubblica del registro degli accessi, che già la applicavano in
proprio.

**Insieme.** La data del rinvio si controllava con un'espressione che accetta un a capo in fondo
e, per una coincidenza, scartava un carattere davanti solo se era una lettera. Si controlla dal
primo all'ultimo carattere, come la data del registro degli accessi.

**Come si prova.** Righe T-115..T-118: per ciascuna schermata, un valore con `&amp;` e `&lt;`
scritti come testo, la schermata letta come la leggerebbe il browser, il modulo rimandato
com'è: il salvataggio riesce e il dato resta identico. Per la data, i due casi che passavano.

## 1. File toccati

| File | Perché |
|---|---|
| `src/class-testo.php` | Nuovo. `Testo::esatto()` |
| `src/class-schermata-responsabile.php`, `src/class-schermata-accesso-civico.php`, `src/class-schermata-rinvii.php` | Valori dei campi e delle partenze con `Testo::esatto()` |
| `src/class-schermata-registro-accessi.php`, `src/class-registro-accessi.php` | La funzione propria lascia il posto a quella comune |
| `src/class-rinvii.php` | Data controllata dal primo all'ultimo carattere |
| `trasparenza-pa.php` | Il file nuovo |
| `tests/ResponsabileTest.php`, `tests/AccessoCivicoTest.php`, `tests/RinviiDichiaratiTest.php` | Righe T-115..T-118 |

## 2. Cosa deliberatamente NON fa

- Non cambia come la pagina pubblica mostra nome del responsabile, dati dell'accesso civico ed
  etichetta del rinvio: lì `esc_html()` mostra `&amp;` scritto come testo come `&`. Non blocca
  niente e non è un rischio, perché il testo esce comunque con escaping; se l'ente lo vorrà
  identico anche lì, si cambia con la stessa funzione.
- Non rifiuta le sequenze come `&amp;` al salvataggio: sono testo lecito, e rifiutarle
  costringerebbe gli addetti a indovinare perché.
