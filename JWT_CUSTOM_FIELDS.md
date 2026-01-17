# Configurazione JWT Payload Personalizzato

## Descrizione

Questa funzionalità ti permette di aggiungere campi personalizzati al payload JWT in modo dinamico attraverso l'interfaccia web, senza dover modificare manualmente il codice.

## Come Usare

### 1. Accedi alla Configurazione JWT

Vai su **Configurazione JWT** dal menu di navigazione del Generator:
```
http://tuosito.com/generator/jwt
```

### 2. Aggiungi Campi Personalizzati

Clicca su **"Aggiungi Campo"** per aggiungere un nuovo campo al payload JWT.

Per ogni campo devi specificare:
- **Nome Campo Database**: Il nome della colonna nella tabella `user_auth` (es: `azienda_id`, `department_id`, `company_name`)
- **Tipo Dato**: Il tipo di dato del campo
  - `String` - Testo
  - `Integer` - Numero intero
  - `Boolean` - Vero/Falso
  - `Float` - Numero decimale
- **Valore Default**: Valore da usare se il campo è vuoto/nullo nel database

### 3. Esempio di Configurazione

Supponiamo di voler aggiungere questi campi al payload JWT:

| Campo Database | Tipo | Valore Default |
|----------------|------|----------------|
| azienda_id | Integer | 0 |
| department_name | String | general |
| is_premium | Boolean | false |

### 4. Modifica Database

**IMPORTANTE**: Devi aggiungere questi campi alla tabella `user_auth` nel database:

```sql
ALTER TABLE user_auth 
ADD COLUMN azienda_id INT DEFAULT 0,
ADD COLUMN department_name VARCHAR(100) DEFAULT 'general',
ADD COLUMN is_premium BOOLEAN DEFAULT FALSE;
```

### 5. Salva e Genera API

1. Clicca su **"Salva Configurazione JWT"**
2. Vai su **"Genera API"**
3. Clicca su **"Genera API Complete"**

### 6. Payload JWT Risultante

Il token JWT generato conterrà:

```json
{
  "sub": "1",
  "email": "user@example.com",
  "role": "user",
  "name": "Mario Rossi",
  "azienda_id": 5,
  "department_name": "sales",
  "is_premium": true,
  "iat": 1234567890,
  "nbf": 1234567890,
  "exp": 1234571490
}
```

## Campi Standard (Non Modificabili)

Questi campi sono sempre inclusi nel payload JWT:

- `sub` - User ID
- `email` - Email utente
- `role` - Ruolo utente (user/admin/manager)
- `name` - Nome utente
- `iat` - Issued At (timestamp creazione)
- `nbf` - Not Before (timestamp validità)
- `exp` - Expiration (timestamp scadenza)

## Note Importanti

⚠️ **I campi personalizzati devono esistere nella tabella `user_auth`**
- Se aggiungi un campo `azienda_id` nella configurazione JWT, devi prima creare la colonna `azienda_id` nella tabella `user_auth`

⚠️ **Rigenera le API dopo ogni modifica**
- Le modifiche alla configurazione JWT vengono applicate solo quando rigeneri le API
- Vai su "Genera API" → "Genera API Complete" per applicare le modifiche

⚠️ **I valori default sono importanti**
- Se un campo non esiste per un utente, verrà usato il valore default specificato
- Assicurati che i valori default siano appropriati per il tipo di dato

## Vantaggi

✅ **Nessun codice da scrivere**: Configurazione completamente visuale
✅ **Flessibilità**: Aggiungi/rimuovi campi senza toccare il codice
✅ **Sicurezza**: I campi vengono validati e tipizzati automaticamente
✅ **Anteprima Live**: Vedi in tempo reale come sarà il payload JWT

## Troubleshooting

### Problema: Il campo non appare nel token JWT
**Soluzione**: 
1. Verifica che il campo esista nella tabella `user_auth`
2. Verifica che l'utente abbia un valore per quel campo
3. Rigenera le API

### Problema: Errore durante l'autenticazione
**Soluzione**:
1. Controlla che i tipi di dato configurati corrispondano al database
2. Verifica i valori default
3. Controlla i log del server per errori specifici

## File Modificati

Durante la generazione delle API, vengono modificati questi file:
- `/generated-api/config/jwt.php` - Handler JWT con i campi personalizzati
- La configurazione viene salvata in `/config/api_config.json`
