# Scheda di implementazione: contenuti delle voci, quarto passo

Quarto e ultimo dei passi descritti in `contenuti-voci.md`. Scheda scritta prima del codice e
completata a lavoro finito. Il passo porta i contenuti sulla pagina pubblica della sezione,
dentro la voce in cui sono collocati, con la data di ultimo aggiornamento (TRASP-10).

Per tenere le richieste di unione corte, il passo si fa in tre parti:

1. **questa**: testo dei contenuti nella loro voce e date di ultimo aggiornamento;
2. i documenti allegati scaricabili dal pubblico, che dipendono da una scelta aperta su
   come il componente comune consegna i file di contenuti che non hanno una pagina propria;
3. le voci coperte dai moduli ANAC (artt. 13, 4-bis, 31): l'avviso all'addetto che indica il
   modulo quando è usabile, secondo la scelta del 28 ottobre 2026 annotata in
   `contenuti-voci.md`.

## In parole semplici

**Cosa costruiamo.** Sulla pagina "Amministrazione trasparente", aprendo una voce, compaiono
i contenuti collocati lì: titolo, testo e la data in cui sono stati aggiornati l'ultima
volta. In cima alla voce, e in cima a ogni sottosezione che la contiene, compare la data di
ultimo aggiornamento: la più recente fra quelle dei contenuti che stanno dentro. I contenuti
più recenti vengono per primi.

**Come la costruiamo.** Un contenuto compare solo se è pubblicato, senza password, collocato
in una voce valida e con una fine della pubblicazione non ancora passata. La fine si
controlla su ogni contenuto mentre la pagina si disegna, senza contare sul compito
pianificato: un contenuto scaduto sparisce dalla pagina nel momento in cui scade, anche se
nessuno lo ha ancora spostato. Il testo passa per lo stesso filtro di sicurezza che
WordPress usa per gli articoli, e i codici brevi scritti dentro un contenuto non si eseguono.

**Come si prova, e come si prova che la prova è vera.** Prove nuove nelle righe da T-71 in
poi; ogni regola ha una rottura voluta che fa diventare rossa almeno una prova, elencata in
fondo a lavoro finito.

## 1. File toccati

| File | Perché |
|---|---|
| `src/class-contenuti-pubblici.php` | Nuovo. Quali contenuti compaiono in una voce, la data di ultimo aggiornamento, l'HTML |
| `src/class-pagina-pubblica.php` | La voce disegna i suoi contenuti e la sua data |
| `trasparenza-pa.php` | Caricamento |
| `tests/PaginaContenutiTest.php` | Nuove prove |
| `docs/` | Collaudo, requisiti, architettura |

## 2. Requisiti serviti

- TRASP-10 (art. 6 del d.lgs. 33/2013): data di ultimo aggiornamento per ogni contenuto,
  per ogni voce che ne ha e per ogni sottosezione sopra di essa.
- TRASP-09 (art. 8) per la pagina della sezione: un contenuto scaduto non compare, anche con
  il compito pianificato fermo.
- TRASP-02 (art. 9): la pagina si legge senza autenticazione.

## 3. Flusso per chi usa il sito

Chi visita la sezione apre una voce dall'elenco e trova i contenuti collocati lì, ciascuno
con la sua data. Le voci con sottovoci mostrano la data più recente fra quelle dei contenuti
sotto di loro.

## 4. Dati letti e scritti

Solo lettura, dalla banca dati con interrogazioni che distinguono "nessun contenuto" da "lettura fallita": contenuti pubblicati del tipo `trasparenza_testo`, la loro voce
(`_trasparenza_pa_collocazione`, una riga sola e ammessa dall'albero in vigore), la fine
della pubblicazione del componente comune, la data di ultima modifica di WordPress. Niente
si scrive.

## 5. Permessi

Lettura pubblica. Anche chi amministra vede sulla pagina pubblica solo quello che vede il
pubblico.

## 6. Modi di guasto

| Caso | Esito |
|---|---|
| Contenuto in bozza, in revisione, privato, pianificato, con password | non compare |
| Contenuto senza fine della pubblicazione, con la fine passata, con più fini | non compare |
| Contenuto senza voce, con più voci, con una voce che l'albero in vigore non ammette | non compare |
| Albero non disponibile | la pagina non disegna niente, come prima |
| Lettura fallita dell'elenco dei contenuti, di un contenuto, della sua voce o della sua fine | al posto di contenuti e date compare un avviso di indisponibilità, nella voce e in quelle sopra: una voce non deve sembrare vuota per un guasto |
| Testo con codice o con codici brevi | il codice si toglie, i codici brevi restano testo |

## 7. Test

Righe T-71..T-74 di `docs/collaudo.md`, in `tests/PaginaContenutiTest.php`.

## 8. Cosa deliberatamente NON fa

- Non mostra i documenti allegati: seconda parte.
- Non avvisa l'addetto sulle voci coperte dai moduli ANAC: terza parte.
- Non divide in pagine una voce con molti contenuti.
- La data di una voce è quella dei suoi contenuti: una voce senza contenuti non ha data, e i
  cambi dello stato dell'obbligo o del responsabile non la spostano.

## 9. Come si prova sul sito vero

1. Creo un contenuto, scelgo la voce "Bandi di concorso", scrivo una decorrenza di
   quest'anno e pubblico.
2. Da una finestra anonima apro la pagina della sezione, poi "Bandi di concorso": il
   contenuto c'è, con la data di oggi; la stessa data compare in cima alla voce e nella
   sottosezione sopra.
3. Cambio la decorrenza in una data di sei anni fa: il contenuto sparisce dalla pagina.
4. Ripristino: si cestina il contenuto di prova.

## Dubbi annotati

- TRASP-10 chiede la data "per ogni sottosezione e ogni voce". Qui la si dà dove c'è almeno
  un contenuto; per una voce vuota una data non avrebbe un significato vero.

## Rotture volute, a lavoro finito

Trentuno rotture, una per volta e poi tolte. Ventisette fanno diventare rossa almeno una
prova: errore dell'interrogazione ignorato, errore della voce ignorato, errore della fine
ignorato, contenuto non caricato ignorato, errore mostrato come voce vuota, ordine invertito,
spareggio invertito, voce non filtrata nell'interrogazione, voci ammesse non considerate, più
voci accettate, controllo sul singolo contenuto non chiamato, tipo, stato, password e scadenza
non ricontrollati sul singolo contenuto, sottovoci non guardate, voci sotto le sottovoci non
guardate, data più vecchia invece della più recente, data della voce senza le voci sotto, data
anche dove non c'è niente, contenuti delle voci sotto ripetuti, fuso cablato, testo senza
filtro, codici brevi eseguiti, titolo senza escaping, data del contenuto tolta, contenuti non
disegnati sulla pagina.

Quattro restano verdi, e sono volute:

- togliere dall'interrogazione la richiesta dello stato pubblicato o l'esclusione delle
  password non cambia niente, perché il controllo sul singolo contenuto lo ripete: la
  richiesta all'interrogazione serve a non caricare contenuti che poi si scartano;
- togliere il controllo che la voce letta sia fra quelle chieste non cambia niente, perché
  l'interrogazione ha già preteso una riga fra quelle voci e il contenuto ne ha una sola;
- leggere la data di modifica locale invece di quella in UTC dà lo stesso istante, perché
  WordPress la interpreta nel fuso del sito.

Limite dichiarato: la scadenza la decide il componente comune, che oggi tratta una propria
lettura fallita come data assente, cioè contenuto scaduto. Questo componente legge la stessa
riga subito prima e si ferma se la sua lettura fallisce, ma un guasto che colpisse solo la
lettura del componente comune nasconderebbe il contenuto senza avviso. Da chiudere nel
componente comune.
