# Scheda di implementazione: motore dell'alberatura

Unita T1b. Scheda compilata a lavoro finito. Il motore e provato con un albero finto:
l'albero normativo e un file di dati che arriva con l'unita successiva e ha prove sue.

## In parole semplici

**Cosa costruiamo.** La sezione "Amministrazione trasparente" e un albero di voci fissato
dalla legge. Il componente legge l'albero da un file, lo controlla per intero e lo mostra
in una pagina del sito, una voce alla volta, con il percorso per tornare indietro e le voci
figlie. Per ogni voce l'amministrazione dichiara se l'obbligo si applica, non si applica o
e sospeso; quando non si applica, la pagina stampa la frase prevista, sempre uguale.

**Come la costruiamo.** L'albero non sta nel codice ma in un file versionato: quando cambia
la norma si cambia il file. Il codice conosce solo la forma del file e un nome, quello della
radice, che la legge fissa. Un file con anche un solo difetto si rifiuta intero, perche un
albero a meta pubblicherebbe una sezione incompleta senza che nessuno se ne accorga. La
pagina e una pagina normale del sito con un codice breve dentro, come per la pubblicita
legale; la voce si sceglie con un parametro dell'indirizzo che non cambia se l'albero cambia
forma. Una voce senza stato dichiarato resta "non dichiarata": non si presume applicabile.

**Come si prova, e come si prova che la prova e vera.** Quattordici prove nuove, tutte con
un albero finto in `tests/dati/`. Undici rotture volute, fatte una per volta e poi tolte,
rendono rosse le prove: figli ordinati alfabeticamente invece che come nel file, controllo
dei duplicati tolto, stato predefinito "applicabile", controllo del valore tolto, dicitura
passata dal correttore tipografico di WordPress (cambia l'apostrofo), indirizzi delle voci
tutti uguali, voce sconosciuta che mostra la radice, `noindex` aggiunto dal componente,
avviso del sito non indicizzabile tolto, avvisi mostrati a tutti, contenitore vuoto
disegnato senza albero.

## 1. File toccati

| File | Perche |
|---|---|
| `src/class-alberatura.php` | Nuovo. Lettura e controllo del file dell'albero, tutto o niente |
| `src/class-stati-obbligo.php` | Nuovo. I tre stati dichiarabili e il quarto, "non dichiarato" |
| `src/class-pagina-pubblica.php` | Nuovo. Codice breve, risposta 404 per voce sconosciuta, avvisi |
| `trasparenza-pa.php` | Costante del file dell'albero, caricamento delle classi, aggancio della pagina |
| `tests/dati/albero-finto.json` | Nuovo. L'albero delle prove, con ordine non alfabetico apposta |
| `tests/AlberaturaTest.php`, `tests/StatiObbligoTest.php`, `tests/PaginaPubblicaTest.php` | Nuovi. Righe T-11..T-19 |
| `tests/avvio-comune.php` | Le prove puntano il motore sull'albero finto |
| `docs/collaudo.md`, `docs/dati.md` | Righe T-11..T-19; file dell'albero e opzione degli stati |

## 2. Requisito servito

TRASP-02 (struttura della sezione) per la parte del motore: lo chiude solo l'albero vero.
TRASP-03 (nessun ostacolo all'indicizzazione) per la pagina. TRASP-27 (stato dell'obbligo e
dicitura) per lo stato e la dicitura; la schermata in cui si sceglie lo stato arriva con
le unita successive.

## 3. Flusso per chi usa il sito

Chi amministra crea una pagina e ci scrive il codice breve `[amministrazione_trasparente]`.
Il visitatore vede la radice con l'elenco delle voci; cliccando una voce vede il suo nome,
il riferimento normativo, il percorso e le voci figlie. Se l'albero manca o e difettoso la
pagina non mostra niente e chi puo attivare i componenti vede un avviso con il motivo.

## 4. Dati letti e scritti

Formato del file dell'albero, versione 1:

| Chiave | Contenuto |
|---|---|
| `formato` | il numero 1; un formato diverso si rifiuta |
| `versione` | la versione dell'albero, testo non vuoto |
| `diciture` | testo per `non_applicabile` e per `sospeso`, entrambi obbligatori |
| `nodi` | elenco di voci in ordine di visualizzazione: `id` (lettere, cifre e trattino basso, al massimo 80), `nome`, `padre` (nullo solo per la radice), `riferimento` |
| `fonti` | elenco delle fonti dell'albero: `chiave`, `titolo`, `url` o `estremi`, `consultata` (data), `uso`. Il motore non la legge; la controllano le prove dell'albero normativo (T-25) |
| `fonte_diciture` | per ogni dicitura, la chiave della fonte o la dichiarazione che e un testo del componente. Idem |
| `collocazioni` | facoltativa: per ogni dato della sezione che non e una voce, come il responsabile della trasparenza (`responsabile_trasparenza`), la voce in cui si mostra. Il nome del dato segue `[a-z0-9_]{1,80}`; la voce deve esistere e non essere la radice, altrimenti il file si rifiuta con `trasparenza_albero_collocazione` (T-34) |
| `fonte_collocazioni` | per ogni collocazione, la chiave della fonte. Il motore non la legge; la controllano le prove dell'albero normativo (T-35) |

Gli stati si scrivono nell'opzione `trasparenza_pa_stati_obbligo`. Un valore conservato che
non e fra i tre vale come non dichiarato.

## 5. Permessi

La pagina e pubblica e non scrive niente. La funzione che dichiara lo stato controlla il
dato e non il permesso: il permesso lo controllera la schermata che la usa. Gli avvisi
richiedono `activate_plugins`.

## 6. Modi di guasto

| Caso | Esito |
|---|---|
| File assente, non JSON, formato ignoto, incompleto | nessun albero, avviso con il motivo, codice d'errore dedicato |
| Dicitura mancante, identificativo non ammesso o doppio, voce senza nome | idem |
| Radice assente, doppia o con nome diverso; padre inesistente; ciclo | idem |
| Voce chiesta che non esiste, o parametro non testuale | risposta 404 |
| Sito impostato per non farsi indicizzare | avviso: la sezione deve restare indicizzabile |
| Sezione non registrata | il codice breve non si registra; il componente e gia inerte |

## 7. Test

Righe T-11..T-19 di `docs/collaudo.md`, nei file elencati sopra.

## 8. Cosa deliberatamente NON fa

Non contiene l'albero normativo. Non offre una schermata per gli stati. Non aggancia filtri
sui metatag dei motori di ricerca o sulle intestazioni: la pagina e una pagina ordinaria e
resta indicizzabile come il resto del sito. Non mostra i contenuti delle voci, che arrivano
con gli schemi.

## 9. Come si prova sul sito vero

Senza il file dell'albero la pagina con il codice breve resta vuota e chi amministra vede
l'avviso "l'albero delle voci non e disponibile": e l'esito atteso, e va verificato. Con
l'albero installato (unita T1c, scheda `albero-normativo.md`), da visitatore anonimo: la pagina mostra le voci di primo
livello nell'ordine dell'Allegato A; un indirizzo con `?voce=` seguito da un identificativo
inventato risponde 404; il codice della pagina non contiene `noindex`.

## Dubbi annotati

- Il testo della dicitura per lo stato sospeso viene dal file dell'albero e non da una
  formula di legge: il file dell'albero normativo lo dichiara in `fonte_diciture` come
  testo del componente (risolto con l'unita T1c).
- Il parametro `voce` e generico e potrebbe coincidere con quello di un altro componente
  sulla stessa pagina. Si cambia solo con un nuovo formato degli indirizzi, perche gli
  indirizzi delle voci finiscono nel file di mappatura della sezione.
