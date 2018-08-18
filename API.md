# Strichliste API

## API endpoints 

### GET /user

Returns a list of user objects

```json
{
  "users": [
    {
      "id": 1,
      "name": "schinken",
      "active": true,
      "email": "foo@bar.de",
      "balance": 233,
      "created": "2018-08-17 16:20:57",
      "updated": "2018-08-17 16:22:41"
    },
    { }, { }, { }
  ]
}
```

This response is Pageable. See #Pagination

### POST /user

#### Example

```json
{
  "name": "Username",
  "email": "foo@bar.de"
}
```

#### Request-Parameters

|  field  | datatype    | description                   |
|---------|-------------|-------------------------------|
| name    | string      | username                      |
| email   | string      | e-mail address (optional )    |

#### Response

Returns the created `User-Object`

#### Errors

* TODO

## Misc

### Pagination

With these two parameters, you can page through the result set:

```json
{
  "offset": 0,
  "limit": 25
}
```

### User-Object

```json
{
  "id": 1,
  "name": "schinken",
  "active": true,
  "email": "foo@bar.de",
  "balance": 233,
  "created": "2018-08-17 16:20:57",
  "updated": "2018-08-17 16:22:41"
}
```

|  field  | datatype    | description                   |
|---------|-------------|-------------------------------|
| id      | integer     | contains the user identifier  |
| name    | string      | username                      |
| active  | boolean     | If user is deactivated or not |
| email   | string|null | e-mail address (optional )    |
| balance | integer     | balance in cents              |
| created | datetime    | datetime of creating          |
| updated | datetime    | datetime of last transaction  |