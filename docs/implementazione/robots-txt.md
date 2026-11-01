# Scheda di implementazione: il file robots.txt non deve chiudere la sezione

Scheda scritta prima del codice e completata a lavoro finito. Serve TRASP-03 per la parte del
file robots.txt.

## In parole semplici

**Cosa dice la norma.** L'art. 9, c. 1, del d.lgs. 33/2013 vieta di usare filtri o soluzioni
tecniche che impediscano ai motori di ricerca di indicizzare la sezione.

**Il problema.** Il file `robots.txt` dice ai motori di ricerca quali indirizzi del sito non
visitare. Una sola riga sbagliata (`Disallow: /amministrazione-trasparente/`, oppure
`Disallow: /*?`, che chiude tutti gli indirizzi con parametri e quindi tutte le voci) toglie la
sezione dai motori senza che nessuno se ne accorga guardando il sito. La riga può arrivare da un
file messo sul server, da un altro componente o dal tema.

**Cosa costruiamo.** Un controllo, non una correzione: il file può essere un file vero sul server,
fuori dalla portata di WordPress, e la scelta di cosa farne resta dell'ente. Quando il file chiude
la pagina della sezione o le sue voci, chi amministra il sito vede un avviso in bacheca con la
pagina e la riga che la chiude.

**Come si prova.** Righe T-154 (le regole di lettura del file) e T-155 (l'avviso).

## 1. File toccati

- `src/class-controllo-robots.php` (nuovo), agganciato da `trasparenza-pa.php`.
- `src/class-indicizzazione-voci.php`: le pagine della sezione in una funzione propria, usata
  anche dal controllo.
- `tests/ControlloRobotsTest.php` (nuovo).
- `docs/collaudo.md`, `docs/requisiti.md`, `docs/architettura.md`, questa scheda.

## 2. Requisiti serviti

| Requisito | Cosa copre questa unità |
|---|---|
| TRASP-03 | nessun blocco della sezione nel file robots.txt, o almeno un avviso a chi può rimediare |

## 3. Le regole di lettura (T-154)

Si segue lo standard del file robots.txt (RFC 9309):

- contano i gruppi `User-agent: *`; righe di più `User-agent` consecutive aprono un gruppo
  solo; maiuscole e minuscole non contano nei nomi delle righe;
- dentro il gruppo, `Disallow` e `Allow` si confrontano con l'inizio del percorso, parametri
  compresi; `*` vale qualunque sequenza e `$` in fondo vale la fine dell'indirizzo;
- vince la regola che corrisponde con più caratteri; a parità vince `Allow`; un `Disallow`
  vuoto non chiude niente;
- i commenti (`#`) si ignorano.

Si controllano due indirizzi per ogni pagina della sezione: la pagina e la pagina aperta su una
voce (con il parametro `voce`), perché una regola sui parametri chiude solo le voci.

**Quale file.** Se nella cartella di WordPress c'è un file `robots.txt`, il server manda quello e
WordPress non entra in gioco: si legge il file. Altrimenti si ricostruisce il file che WordPress
manda: le righe predefinite, quello che i componenti scrivono sull'azione `do_robotstxt` e il
filtro `robots_txt`.

## 4. L'avviso (T-155)

In bacheca, a chi può attivare i componenti, come l'avviso di T-18 sul sito che chiede di non
farsi indicizzare: per ogni pagina chiusa, il titolo della pagina, se è chiusa la pagina o solo
le sue voci, e la riga che la chiude. Nessun avviso se nessuna pagina è chiusa, se l'albero non è
disponibile o se non ci sono pagine della sezione.

## 5. Modi di guasto

| Guasto | Comportamento |
|---|---|
| File `robots.txt` presente ma non leggibile | avviso: il file non si è potuto leggere e il controllo non è stato fatto |
| Lettura delle pagine della sezione fallita | nessun avviso del controllo: le pagine non si conoscono |

## 6. Cosa deliberatamente non fa

- Non corregge il file: se è un file vero sul server, WordPress non può cambiarlo, e cambiarlo
  quando è virtuale vorrebbe dire scavalcare un altro componente senza che l'ente lo sappia.
- Non controlla i gruppi dedicati a un motore preciso (`User-agent: Googlebot`): il controllo
  guarda la regola valida per tutti.
- Non scarica il file dal sito: lo legge dove sta, così funziona anche su una copia di prova non
  raggiungibile da fuori.

## Norme e fonti

| Regola | Norma | Fonte, e se è verificata |
|---|---|---|
| Nessuna soluzione tecnica che impedisca di indicizzare la sezione | d.lgs. 33/2013, art. 9, c. 1 (TRASP-03) | `docs/requisiti.md`; testo dell'articolo citato a memoria |
| Come si leggono le righe del file robots.txt | RFC 9309 (Robots Exclusion Protocol), sezione 2.2 | citato a memoria, non riletto |
| Come WordPress costruisce il file virtuale | funzione `do_robots()` di WordPress | letta nel codice di WordPress dell'ambiente di prova |
