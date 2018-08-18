# Strichliste API

## API endpoints 

### GET /user


#### Description

Returns a list of user objects.

Note: This response is Pageable. See #Pagination

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

### POST /user

#### Description

Create a new user

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

* Returns 409 if the user already exists

### GET /user/{userId}

#### Description

Returns the user details for a given `userId`.

Note: You can access this resource using the `id` or `name` as userId

#### Response

Returns the `User-Object`

#### Errors

* 404 Not found, if user does not exist
* 400 If Request is not well-formed or fields are missing

### GET /user/{userId}/transaction

#### Description

Access the user's transactions for a given `userId`

Note: You can access this resource using the `id` or `name` as userId

#### Example

```json
{
  "transactions": [
    {
      "id": 3,
      "user": {
        "id": 1,
        "name": "schinken",
        "active": true,
        "email": "foo@bar.de",
        "balance": 233,
        "created": "2018-08-17 16:20:57",
        "updated": "2018-08-17 16:22:41"
      },
      "article": null,
      "comment": null,
      "amount": 33,
      "created": "2018-08-17 16:22:41"
    },
    { }, { }, { }
  ]
}
```

#### Response

Returns a list of transactions as a `Transaction-Object`

#### Errors

* 404 Not found, if user does not exist


### POST /user/{userId}/transaction

#### Description

Create a transaction for the given `userId`

Note: You can access this resource using the `id` or `name` as userId

#### Example

```json
{
  "amount": 123,
  "articleId": 1,
  "comment": "Foobar!"
}
```

|  field    | datatype | description                                         |
|-----------|----------|-----------------------------------------------------|
| amount    | integer  | amount in cents (optional if articleId is provided) |
| articleId | integer  | id of an article (optional)                         |
| comment   | string   | comment (optional)                                  |

If an articleId is provided, the amount parameter gets overwritten by the price of the article

#### Response

Returns a `Transaction-Object`

#### Errors

* 404 Not found, if user does not exist
* 400 If Request is not well-formed or fields are missing


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

|  field  | datatype       | description                   |
|---------|----------------|-------------------------------|
| id      | integer        | contains the user identifier  |
| name    | string         | username                      |
| active  | boolean        | If user is deactivated or not |
| email   | string or null | e-mail address (optional )    |
| balance | integer        | balance in cents              |
| created | datetime       | datetime of creating          |
| updated | datetime       | datetime of last transaction  |

### Transaction-Object

```json
{
  "id": 3,
  "user": {
    "id": 1,
    "name": "schinken",
    "active": true,
    "email": "foo@bar.de",
    "balance": 233,
    "created": "2018-08-17 16:20:57",
    "updated": "2018-08-17 16:22:41"
  },
  "article": null,
  "comment": null,
  "amount": 33,
  "created": "2018-08-17 16:22:41"
}
```

|  field  | datatype               | description                                                              |
|---------|------------------------|--------------------------------------------------------------------------|
| id      | integer                | contains the transaction identifier                                      |
| user    | User-Object            |                                                                          |
| article | Article-Object or null | Contains an article-object if the transaction is created with an article |
| comment | string or null         | comment (optional)                                                       |
| amount  | integer                | amount in cents                                                          |
| created | datetime               | datetime of creating       