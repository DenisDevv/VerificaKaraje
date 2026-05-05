
--------------------------------------------------
QUERY USATE
--------------------------------------------------

1) Elenco istruttori
```sql
SELECT id_istruttore, nome, cognome
FROM Istruttori
ORDER BY cognome, nome
```
Uso: carica gli istruttori per i menu.

2) Elenco corsi con istruttore
```sql
SELECT c.id_corso, c.nome_corso, c.id_istruttore, i.nome AS nome_istruttore, i.cognome AS cognome_istruttore
FROM Corsi c
LEFT JOIN Istruttori i ON i.id_istruttore = c.id_istruttore
ORDER BY c.nome_corso
```
Uso: carica i corsi con nome istruttore.

--------------------------------------------------
STEP 1 - INSERIMENTO NUOVO ISCRITTO
--------------------------------------------------

3) Controllo corso/istruttore valido
```sql
SELECT COUNT(*)
FROM Corsi
WHERE id_corso = ? AND id_istruttore = ?
```
Uso: verifica che il corso appartenga all'istruttore scelto.

4) Inserimento membro
```sql
INSERT INTO Membri (nome, cognome, data_nascita, tipo_abbonamento, stato_pagamento)
VALUES (?, ?, ?, ?, ?)
```
Uso: crea il nuovo membro.

5) Inserimento iscrizione
```sql
INSERT INTO Iscrizioni_Corsi (id_corso, id_membro, data_iscrizione, orario_preferito)
VALUES (?, ?, CURDATE(), ?)
```
Uso: collega il membro al corso scelto.

--------------------------------------------------
STEP 2 - CORSO TOP PER ISTRUTTORE (MIN 5)
--------------------------------------------------

6) Corso con massimo iscritti per istruttore
```sql
SELECT i.nome AS nome_istruttore, i.cognome AS cognome_istruttore, c.nome_corso, t.totale
FROM Istruttori i
JOIN (
    SELECT c1.id_istruttore, c1.id_corso, COUNT(ic1.id_iscrizione) AS totale
    FROM Corsi c1
    LEFT JOIN Iscrizioni_Corsi ic1 ON ic1.id_corso = c1.id_corso
    GROUP BY c1.id_istruttore, c1.id_corso
) t ON t.id_istruttore = i.id_istruttore
JOIN (
    SELECT z.id_istruttore, MAX(z.totale) AS massimo
    FROM (
        SELECT c2.id_istruttore, c2.id_corso, COUNT(ic2.id_iscrizione) AS totale
        FROM Corsi c2
        LEFT JOIN Iscrizioni_Corsi ic2 ON ic2.id_corso = c2.id_corso
        GROUP BY c2.id_istruttore, c2.id_corso
    ) z
    GROUP BY z.id_istruttore
) m ON m.id_istruttore = t.id_istruttore AND m.massimo = t.totale
JOIN Corsi c ON c.id_corso = t.id_corso
WHERE t.totale >= 5
ORDER BY i.cognome, i.nome, c.nome_corso
```
Uso: per ogni istruttore mostra il corso con piu iscritti, solo se almeno 5.

--------------------------------------------------
STEP 3 - ELENCO ISCRITTI E CAMBIO CORSO
--------------------------------------------------

7) Lettura corso corrente dell'iscrizione
```sql
SELECT id_corso
FROM Iscrizioni_Corsi
WHERE id_iscrizione = ?
```
Uso: controlla il corso attuale prima del cambio.

8) Verifica esistenza nuovo corso
```sql
SELECT COUNT(*)
FROM Corsi
WHERE id_corso = ?
```
Uso: verifica che il nuovo corso esista.

9) Update corso iscrizione
```sql
UPDATE Iscrizioni_Corsi
SET id_corso = ?
WHERE id_iscrizione = ?
```
Uso: aggiorna il corso dell'iscrizione.

10) Elenco iscritti di un corso
```sql
SELECT ic.id_iscrizione, m.cognome, m.nome, m.tipo_abbonamento, ic.data_iscrizione, ic.orario_preferito
FROM Iscrizioni_Corsi ic
JOIN Membri m ON m.id_membro = ic.id_membro
WHERE ic.id_corso = ?
ORDER BY m.cognome, m.nome
```
Uso: mostra gli iscritti del corso selezionato.

--------------------------------------------------
STEP 4 - REPORT COMPLETO
--------------------------------------------------

11) Report istruttori, corsi, iscritti
```sql
SELECT
    i.cognome AS cognome_istruttore,
    i.nome AS nome_istruttore,
    c.nome_corso,
    c.livello_difficolta,
    c.durata_minuti,
    m.cognome AS cognome_membro,
    m.nome AS nome_membro,
    m.tipo_abbonamento,
    m.stato_pagamento,
    ic.data_iscrizione,
    ic.orario_preferito
FROM Istruttori i
JOIN Corsi c ON c.id_istruttore = i.id_istruttore
LEFT JOIN Iscrizioni_Corsi ic ON ic.id_corso = c.id_corso
LEFT JOIN Membri m ON m.id_membro = ic.id_membro
ORDER BY i.cognome, i.nome, c.nome_corso, m.cognome, m.nome
```
Uso: report finale completo ordinato per istruttore, corso, cognome e nome iscritto.

