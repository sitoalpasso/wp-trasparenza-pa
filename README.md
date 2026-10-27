# Trasparenza PA

Plugin WordPress per la sezione "Amministrazione trasparente": gestisce gli obblighi di
pubblicazione come dati strutturati conformi agli schemi standard, non come pagine libere
con documenti allegati.

**Stato: in sviluppo.** Non ancora utilizzabile in esercizio. Le interfacce e i formati
possono cambiare senza preavviso fino alla prima versione stabile.

## Cosa fa

Alla prima versione stabile il componente fornira:

- l'alberatura della sezione conforme all'Allegato A del d.lgs. 33/2013, con stato
  dell'obbligo a tre valori (applicabile, non applicabile, sospeso) e dicitura obbligata
  per le sottosezioni non applicabili;
- la compilazione degli obblighi tramite campi strutturati derivati dagli schemi di
  pubblicazione, con validazione del dato prodotto contro i JSON Schema ufficiali;
- l'export in formato aperto, con denominazione dei file conforme alle specifiche
  tecniche di pubblicazione e condizioni di riutilizzo emesse come parametro;
- la generazione del file di mappatura fra nodi dell'alberatura e URL;
- la durata quinquennale dei dati come proprieta del dato letto, con uscita dalla sezione
  senza cancellazione;
- l'adempimento per collegamento come tipo di dato tracciato e verificato
  periodicamente, con indicazione dell'amministrazione titolare e del destinatario
  dell'accesso civico;
- la politica di indicizzazione della sezione, che qui e obbligatoria e opposta a quella
  della pubblicita legale.

## Requisiti normativi di riferimento

Il componente implementa requisiti derivati dalla normativa applicabile ai soggetti
dell'art. 2-bis del d.lgs. 33/2013, indipendentemente dallo specifico ente. Le fonti,
citate per estremi:

- d.lgs. 14 marzo 2013, n. 33, come modificato dal d.lgs. 97/2016: art. 5 (accesso
  civico), art. 6 (qualita delle informazioni), art. 7 (formati aperti), art. 8 (durata
  dell'obbligo), art. 9 (accesso alla sezione e divieto di ostacolare l'indicizzazione),
  art. 9-bis e Allegato B (adempimento per collegamento alle banche dati nazionali),
  art. 43 (Responsabile della prevenzione della corruzione e della trasparenza), art. 48
  (schemi, standard e modelli di pubblicazione), Allegato A (struttura della sezione);
- ANAC, delibera n. 1310 del 28 dicembre 2016 (prime linee guida sull'attuazione degli
  obblighi di pubblicita dopo il d.lgs. 97/2016);
- ANAC, delibera n. 495 del 25 settembre 2024, avviso in Gazzetta Ufficiale n. 16 del
  21 gennaio 2025: schemi di pubblicazione ex art. 48 e Istruzioni operative;
- ANAC, delibera n. 481 del 3 dicembre 2025, in Gazzetta Ufficiale Serie Generale n. 300
  del 29 dicembre 2025: modifica di schemi e integrazione delle Istruzioni operative;
- ANAC, delibera n. 497 del 3 dicembre 2025: ulteriori schemi in sperimentazione
  volontaria;
- ANAC, Guida Online del Portale Servizi, sezione Amministrazione trasparente: specifiche
  tecniche di pubblicazione, denominazione dei file, file di mappatura degli URL, HTTPS;
  da qui vengono i JSON Schema degli artt. 4-bis, 13 e 31 e il file di definizioni comuni,
  distribuiti senza modifiche nella cartella `schemas/` con la fonte e l'impronta di
  ciascun file nel catalogo;
- d.l. 19 febbraio 2026, n. 19, art. 8, convertito con legge 20 aprile 2026, n. 50:
  adempimento per collegamento;
- d.l. 9 giugno 2021, n. 80, art. 6: il PIAO assorbe il piano triennale di prevenzione
  della corruzione e della trasparenza;
- Garante per la protezione dei dati personali, deliberazione 15 maggio 2014, n. 243,
  in Gazzetta Ufficiale n. 134 del 12 giugno 2014;
- regolamento (UE) 2016/679, art. 5, per la minimizzazione dei dati personali.

L'elenco completo dei requisiti implementati, con fonte e criterio di verifica per
ciascuno, sara pubblicato in `docs/requisiti.md`.

## Requisiti tecnici

- WordPress 6.5 o superiore
- PHP 8.1 o superiore
- sito singolo: multisite non supportato
- plugin `conformita-core` attivo; la compatibilita di versione e verificata
  all'avvio

## Licenza

GPL-3.0-or-later. Il testo integrale e in [LICENSE](LICENSE).

## Componenti di terze parti

Il pacchetto distribuito contiene la libreria di validazione
[opis/json-schema](https://github.com/opis/json-schema) e le sue dipendenze
`opis/string` e `opis/uri`, rilasciate in licenza Apache-2.0, compatibile con la
GPL-3.0. Sono incluse con i nomi prefissati (`TrasparenzaPa\Vendor\`) nella cartella
`vendor-prefixed/`, che si genera con `composer install`; ogni libreria vi porta il
proprio testo di licenza e, dove esiste, il proprio file NOTICE.

Il componente e disponibile in licenza aperta, senza costo di licenza ne canone
ricorrente.
