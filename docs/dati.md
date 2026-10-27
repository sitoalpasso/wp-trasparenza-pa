# Dati: cosa esiste, dove sta, chi può toccarlo

La mappa dei dati del plugin, con le risposte alle domande di controllo. I nomi tecnici
precisi vengono fissati dalle schede in `docs/implementazione/`; questo file tiene la
visione d'insieme e si aggiorna nello stesso commit che introduce un dato nuovo.

## Le tre famiglie di dati

**1. Le definizioni** (file di dati nel repository, non nel database):

| Cosa | Contenuto | Chi la cambia |
|---|---|---|
| Alberatura | l'albero delle sezioni conforme all'Allegato A: identificativo del nodo, denominazione esatta, articolo di riferimento, piu le diciture degli stati che ne richiedono una. File `dati/alberatura/corrente.json`, formato descritto in `docs/implementazione/alberatura.md` | solo un aggiornamento del plugin, mai l'utente |
| Schemi | una definizione per ogni schema ANAC e per ogni sua versione, con la data di approvazione ANAC e la fonte; accanto, i JSON Schema ufficiali usati per validare. Catalogo `schemas/catalogo.json`, formato descritto in `docs/implementazione/motore-schemi.md` | idem: una versione nuova si affianca, non sovrascrive |

**2. I contenuti** (nel database del sito):

| Dato | Dove sta | Chi lo scrive |
|---|---|---|
| Voce pubblicata (il dato compilato di uno schema) | contenuto di tipo dedicato più campi strutturati; porta con sé l'identificativo della versione di schema con cui è stato raccolto | redattore, tramite i moduli generati dallo schema |
| Nodo dell'albero: stato dell'obbligo (applicabile, non applicabile, sospeso) | opzione `trasparenza_pa_stati_obbligo`, una coppia identificativo-stato per nodo; un nodo assente e "non dichiarato", che non e uno dei tre e non ha valore predefinito | responsabile con permesso dedicato |
| Data di ultimo aggiornamento | per nodo e per voce, scritta dal sistema a ogni pubblicazione | solo il sistema |
| Stato editoriale (bozza, validato, pubblicabile provvisoriamente, non pubblicabile) | stato della voce, con esito della validazione | il flusso di validazione, mai a mano |
| Segnalazioni al RPCT | registro proprio: voce, difformità, data, destinatario | solo il sistema, nei due stati di difformità |
| Voce-rinvio | tipo dedicato: etichetta, URL, data, amministrazione titolare, destinatario dell'accesso civico, esito dell'ultima verifica | redattore la crea; l'esito lo scrive il verificatore di core |
| Richieste di accesso civico | registro proprio: data, oggetto, stato della risposta | il modulo pubblico la crea; il responsabile la aggiorna |
| Decorrenza dell'obbligo e scadenza quinquennale | metadato della voce; la scadenza si calcola dalla decorrenza, non dalla data di caricamento | il sistema, dalla decorrenza indicata dal redattore |

**3. I file generati**: gli export in formato aperto e l'`at_map`, prodotti dal sistema
con nomi che contengono date, versione e codice della sottosezione. Si rigenerano dai
dati: non sono mai l'unica copia di niente.

## Le risposte alle domande di controllo

**Dove sta la data di pubblicazione?** La decorrenza dell'obbligo è un metadato della
voce, indicato in compilazione; la data di ultimo aggiornamento la scrive il sistema. La
scadenza non si salva come dato indipendente che qualcuno potrebbe sbagliare: si calcola
dalla decorrenza (cinque anni dal 1° gennaio dell'anno successivo), e il calcolo ha i
suoi test, compresi i casi a cavallo d'anno.

**Cosa succede se cambio un dato già pubblicato?** L'aggiornamento è una nuova versione
della voce con la nuova data di aggiornamento: la storia resta nel registro di core, e i
file esportati nuovi hanno un nome con la data nuova. Nessuna sovrascrittura muta.

**Cosa succede ai documenti allegati?** Come nell'albo: cartella protetta, consegna solo
tramite endpoint. Finché la voce è pubblicata il download è pubblico; scaduta la voce, il
file resta ma si ottiene solo dall'archivio ad accesso controllato, per l'accesso civico.
Mai cancellato.

**Chi può cambiare lo stato di un obbligo o di una voce?** Solo chi ha la capability
dedicata, e lo stato editoriale segue solo le transizioni del flusso di validazione. La
dicitura del "non applicabile" la stampa il sistema, carattere per carattere: non è un
testo libero che un redattore può riscrivere.

## I permessi

Le capability dei tipi le **deriva core** dall'identificativo del tipo, ma core non le
assegna a nessun ruolo: **è questo plugin che le assegna**, e la corrispondenza è
configurabile dall'ente. Prima dell'assegnazione le voci non sono visibili a nessuno,
nemmeno all'amministratore: è voluto, perché l'errore che produce si vede subito invece di
lasciare tutto aperto in silenzio. Va detto nel README, altrimenti sembra un difetto.
Attenzione: riguarda l'amministrazione, non la consultazione pubblica, che resta senza
autenticazione perché è un requisito di legge.

| Azione | Capability dedicata | Note |
|---|---|---|
| Compilare bozze delle voci | gestione trasparenza | |
| Validare e pubblicare | pubblicazione trasparenza | passa dal controllo dati personali |
| Dichiarare lo stato di un obbligo | amministrazione trasparenza | il non applicabile stampa la dicitura obbligata |
| Creare e modificare voci-rinvio | gestione trasparenza | il caso senza banca dati (art. 19) non è configurabile come rinvio |
| Consultare l'archivio delle voci scadute | archivio trasparenza | il pubblico accede solo per accesso civico |
| Gestire le richieste di accesso civico | accesso civico | |
| Consultare il registro delle modifiche | lettura registro (di core) | |
| Vedere la sezione pubblicata | nessuna: pubblico, senza autenticazione | è un requisito, non una dimenticanza |

## Cosa NON viene salvato

Nessun tracciamento dei visitatori: l'accesso alla sezione è anonimo per legge. Nessuna
copia locale modificata dei JSON Schema ANAC: si valida contro i file ufficiali
versionati nel repository con la fonte citata. Nessun campo degli schemi dentro il
codice PHP: se un nome di campo compare nei sorgenti, un test statico lo blocca.
