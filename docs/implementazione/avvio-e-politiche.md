# Scheda di implementazione: avvio del componente e dichiarazione delle politiche

Unita T1a. Scheda compilata a lavoro finito. Lo schema dell'avvio e lo stesso del
componente della pubblicita legale, con le politiche opposte.

## In parole semplici

**Cosa costruiamo.** Quando WordPress carica il componente, il componente controlla che il
meccanismo comune ci sia e sia della versione giusta, poi gli dichiara le due regole della
sezione: i motori di ricerca devono poterla leggere tutta, e un dato scaduto esce dalla
sezione ma resta in archivio. Se qualcosa manca, il componente resta fermo e lo dice in
bacheca, invece di pubblicare senza regole.

**Come la costruiamo.** Nessun dato salvato: la dichiarazione si rifa a ogni caricamento.
Il controllo di versione lo fa il meccanismo comune, che e anche l'unico a poter disattivare
il componente. Tutti gli altri rifiuti lasciano il componente attivo e inerte, perche un
componente disattivato non puo piu segnalare niente.

**Come si prova, e come si prova che la prova e vera.** Ventitre prove in tre avvii della
suite. Sei rotture volute, ciascuna fatta in una copia e poi ripristinata, rendono rosse le
prove: indicizzazione cambiata in `vietata`, scadenza cambiata in `irraggiungibile`,
idempotenza tolta, avvisi mostrati a tutti, chiamata al meccanismo comune al caricamento del
file, avviso vuoto senza meccanismo comune.

## 1. File toccati

| File | Perche |
|---|---|
| `trasparenza-pa.php` | Costanti dell'identita verso il meccanismo comune (versione di interfaccia, nomi, sezione, due politiche) e il solo aggancio dell'avvio |
| `src/class-avvio.php` | Nuovo. La procedura di avvio, separata perche il file principale deve potersi caricare senza eseguire niente |
| `bin/installa-core.sh`, `bin/installa-suite-test.sh` | Nuovi. Procurano il meccanismo comune a una revisione fissata e la suite di test di WordPress |
| `tests/avvio-comune.php`, `tests/bootstrap*.php` | Tre avvii della suite: la versione di interfaccia e una costante, e una costante si definisce una volta per processo |
| `tests/AvvioTest.php`, `tests/SenzaCoreTest.php`, `tests/CoreIncompatibileTest.php`, `tests/ambiente-trasparenza.php` | Nuovi. Righe T-01..T-10 |
| `tests/ScheletroTest.php` | Rinominato per la convenzione dei nomi delle prove |
| `phpunit*.xml.dist`, `composer.json` | Una configurazione e un comando per avvio |
| `.github/workflows/ci.yml` | Suite di WordPress installata direttamente, meccanismo comune procurato, tre esecuzioni di PHPUnit |
| `.wp-env.json` | Il meccanismo comune entra nell'ambiente locale alla stessa revisione |
| `phpcs.xml.dist` | Esclusioni mirate ai soli test |
| `CLAUDE.md` | La versione si controlla all'avvio, non all'attivazione |
| `docs/collaudo.md` | Righe T-01..T-10 |

## 2. Requisito servito

Nessun TRASP-xx si chiude qui. T1a e la base di TRASP-03 (indicizzazione consentita) e
TRASP-17 (politiche opposte a quelle della pubblicita legale): entrambi discendono dalla
politica della sezione, che esiste solo se qualcuno la dichiara.

## 3. Flusso per chi usa il sito

Nessuno se ne accorge quando funziona. Si vede solo quando qualcosa non va, con un avviso
in bacheca riservato a chi puo attivare i componenti.

## 4. Dati letti e scritti

Nessuno nella banca dati. Il registro delle sezioni vive in memoria per la durata della
richiesta.

## 5. Permessi

L'avvio non ha superficie invocabile dall'esterno. Gli avvisi richiedono `activate_plugins`.

## 6. Modi di guasto

| Caso | Esito |
|---|---|
| Meccanismo comune assente | attivo e inerte, avviso con la versione richiesta |
| Versione incompatibile | disattivato dal meccanismo comune, avviso suo |
| Sezione gia registrata da altri | conflitto, attivo e inerte, politiche altrui intatte |
| Filtro di scadenza non avviato | errore dedicato, nessuna sezione, avviso |

## 7. Test

Righe T-01..T-10 di `docs/collaudo.md`, nei file elencati sopra.

## 8. Cosa deliberatamente NON fa

Non registra tipi di contenuto, pagine, ruoli o permessi: arrivano con T1b e con le unita
successive. Non offre un'opzione per l'indicizzazione: la norma la impone.

## 9. Come si prova sul sito vero

Da riga di comando, con entrambi i componenti attivi, si rileggono le sezioni registrate e la
politica della sezione: deve comparire `amministrazione_trasparente` con indicizzazione
`consentita` e scadenza `archivio`, altrimenti il rilascio si annulla. Su un ambiente
controllato si toglie poi la cartella del componente comune (dall'elenco dei componenti non
si puo disattivare, perche altri ne dipendono) e da amministratore si deve vedere l'avviso
"Trasparenza PA resta attivo ma inerte". Le prove complete stanno nel collaudo di rilascio
del progetto, unita T1a.
