# Scheda di implementazione: albero normativo

Unita T1c. Il file `dati/alberatura/corrente.json`, letto dal motore descritto in
`alberatura.md`. Nessuna riga di PHP cambia: cambia solo il dato.

## In parole semplici

**Cosa costruiamo.** L'elenco delle voci della sezione "Amministrazione trasparente" come lo
fissano la legge e l'Autorita anticorruzione: 23 voci principali e, sotto, 65 voci di
secondo livello, con i nomi esatti e il riferimento all'articolo che le prevede. Al posto
della vecchia voce "Controlli sulle imprese" c'e "Controlli sulle attività economiche",
introdotta nel 2024.

**Come lo costruiamo.** I nomi non si scrivono a memoria: si copiano dai documenti
ufficiali scaricati dai siti di Normattiva e dell'Autorita, e il file dice da dove viene
ogni cosa, con l'indirizzo e la data in cui e stato letto. Le voci principali e il loro
ordine vengono dall'Allegato A del decreto; i nomi delle voci di secondo livello dalla
tabella che l'Autorita ha pubblicato nel 2016, che per sua dichiarazione modifica la
struttura della sezione. Dove l'Autorita ha gia dato un codice a una voce, per il file che
descrive la sezione ai suoi controlli, il file usa lo stesso codice.

**Come si prova, e come si prova che la prova e vera.** Sei prove confrontano il file con
gli elenchi attesi, scritti a mano nella prova e non letti dal file. Otto rotture volute
del file, fatte una per volta e poi tolte, le rendono rosse: il nome vecchio della voce del
2024, le voci sui pagamenti spostate sotto "Servizi erogati", una maiuscola cambiata, un
codice dell'Autorita cambiato, l'apostrofo tipografico nella frase del non applicabile,
l'ordine delle voci principali, una voce tolta, una fonte tolta.

## 1. File toccati

| File | Perche |
|---|---|
| `dati/alberatura/corrente.json` | Nuovo. L'albero, versione `2026-10-24` |
| `tests/AlberoNormativoTest.php` | Nuovo. Righe T-20..T-25 |
| `docs/collaudo.md` | Righe T-20..T-25; TRASP-04 fatto |
| `docs/implementazione/alberatura.md` | Chiavi `fonti` e `fonte_diciture` del formato |

## 2. Requisito servito

TRASP-04 (struttura conforme all'Allegato A e alle linee guida ANAC, compresa la voce
dell'art. 23-bis).

## 3. Fonti, lette il 2026-10-24

| Fonte | Da dove | Cosa se ne prende |
|---|---|---|
| d.lgs. 33/2013, Allegato A, Tabella 1, testo vigente dal 2 agosto 2024 | Normattiva | le voci di primo livello e il loro ordine |
| Delibera ANAC 1310/2016, Allegato 1, file `Del.1310.2016.All_new.ods` | sito ANAC, pagina dell'atto; impronta SHA-256 nel file | i nomi del secondo livello e i riferimenti |
| d.lgs. 33/2013, art. 23-bis, inserito dal d.lgs. 103/2024 | Normattiva | il nome "Controlli sulle attività economiche", scritto nel comma 1 |
| Modello `at_map` ANAC per i soggetti dell'art. 2-bis, c. 1 (15/07/2025) | Guida online ANAC | gli identificativi delle voci |

La delibera 1310/2016 dichiara che il suo Allegato 1 "introduce le conseguenti modifiche
alla struttura della sezione": per questo, dove i nomi del secondo livello differiscono fra
Allegato A e Allegato 1, vale l'Allegato 1.

## 4. Scelte di trascrizione

- **Spazi**: a capo e spazi doppi delle celle sono ridotti a uno spazio. Nessun'altra
  modifica, maiuscole comprese ("Prevenzione della Corruzione", "Piano della Performance").
- **Pagamenti**: nel file ANAC le quattro voci sui pagamenti hanno la cella del primo
  livello vuota (una cella a se, non la continuazione di "Servizi erogati"). L'Allegato A
  le colloca sotto "Pagamenti dell'amministrazione", che e una delle 23 voci: si seguono
  l'Allegato A e il modello `at_map`.
- **Voci in grigio**: l'Allegato 1 segna in grigio le sottosezioni con soli dati non piu
  obbligatori dopo il d.lgs. 97/2016 (Burocrazia zero, Benessere organizzativo, Dati
  aggregati attività amministrativa, Monitoraggio tempi procedimentali) e ne chiede la
  conservazione fino alla scadenza. Restano nell'albero: sono parte della struttura
  pubblicata, e l'amministrazione ne dichiara lo stato come per le altre.
- **Voce ripetuta**: "Sanzioni per mancata comunicazione dei dati" compare sotto
  "Organizzazione" e sotto "Personale". La prima ha l'identificativo del modello `at_map`,
  la seconda `personale_sanzioni_mancata_comunicazione_dati`.
- **Riferimenti**: tutti quelli delle righe della voce, senza ripetizioni, separati da
  punto e virgola. Dove la cella e unita a quella della voce precedente, la voce eredita
  l'ultimo riferimento di quella.
- **Identificativi**: 68 del modello `at_map`; 21 nostri, in minuscolo con trattino basso,
  per le voci che il modello non ha. I tre identificativi del modello senza voce nelle
  fonti (`budget`, `piano_integrato_attivita_organizzazione`,
  `rischi_corruttivi_e_trasparenza`) restano fuori: la loro collocazione e materia
  dell'unita dell'`at_map`.
- **Diciture**: il non applicabile e quella di TRASP-27; il sospeso non ha una formula di
  legge e il file lo dichiara come testo del componente.

## 5. Permessi, dati scritti, modi di guasto

Nessuna novita rispetto a `alberatura.md`: il file si legge, non si scrive. Un file
guasto si rifiuta intero con il suo codice d'errore.

## 6. Cosa deliberatamente NON fa

Non aggiunge voci di terzo livello (i singoli obblighi), che arrivano con il catalogo degli
obblighi e gli schemi. Non distingue le voci in grigio nella pagina: il formato 1 non ha una
chiave per farlo. Non segue le denominazioni del verificatore automatico dell'Autorita,
che e uno strumento di controllo e non una fonte.

## 7. Come si prova sul sito vero

Da visitatore anonimo, la pagina della sezione mostra 23 voci nell'ordine della tabella;
"Controlli sulle attività economiche" e la decima; "Pagamenti dell'amministrazione" ha
quattro figli; `?voce=controlli_sulle_imprese` risponde 404.

## Dubbi annotati

- Il verificatore automatico dell'Autorita usa in quattro voci il nome dell'Allegato A e non
  quello dell'Allegato 1: va controllato con una scansione vera prima della messa online.
- Per "Bandi di gara e contratti" le voci di secondo livello del 2016 precedono il codice
  dei contratti del 2023 e le delibere ANAC successive: da rileggere con la sorveglianza
  normativa.
