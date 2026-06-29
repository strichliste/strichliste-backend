# Strichliste Config

The configuration can be found in `config/strichliste.yaml`. This is also reachable by the `/api/settings` endpoint and used by the frontend.

*Hint*: You can overwrite the import path in `config/services.yaml` if you want to put your config file into another place like `/etc/strichliste.yml`

*Hint*: You have to clear the cache with the corresponding command to apply config changes.

## common

| field        | datatype | default    | description          |
|--------------|----------|------------|----------------------|
| idleTimeout  | int      | 30000      | Time in milliseconds |

## paypal

You can enable the users to pay their debt or charge the account with paypal. All you need
is a valid paypal account.

| field      | datatype | default  | description                                    |
|------------|----------|----------|------------------------------------------------|
| enabled    | bool     | true     | Enable/Disable paypal feature                  |
| recipient  | string   | -        | Recipient Mail Address (paypal account)        |
| fee        | int      | 0        | Fee in percent (is added to the users balance) |


## user

| field       | datatype   | default | description                                       |
|-------------|------------|---------|---------------------------------------------------|
| stalePeriod | timeperiod | 10 day  | Determines, when a user is considered "inactive". |

## i18n

| field      | datatype | default    | description |
|------------|----------|------------|-------------|
| dateFormat | string   | YYYY-MM-DD | Date format |
| timezone   | string   | auto       | Timezone    |
| language   | string   | en         | Language    |

####  currency:

| field  | datatype | default | description                |
|--------|----------|---------|----------------------------|
| name   | string   | Euro    | Currency                   |
| symbol | string   | €       | Currency Symbol            |
| alpha3 | string   | EUR     | Alpha3 format for currency |

## account

#### boundary

| field | datatype | default | description         |
|-------|----------|---------|---------------------|
| upper | money    | 200000  | Upper account limit |
| lower | money    | -200000 | Lower account limit |

## payment

#### undo

The undo operation is a new feature from strichliste2, which allows to revert your last (accidental) transaction.

| field   | datatype   | default  | description                                         |
|---------|------------|----------|-----------------------------------------------------|
| enabled | bool       | true     | Enable/Disable undo feature                         |
| delete  | bool       | false    | Delete or mark transaction as deleted on undo       |
| timeout | timeperiod | 5 minute | Period how long you're able to undo the transaction |

####  boundary

You can limit your transaction to prevent accidental payments (like adding a unwanted zero).

| field | datatype | default | description             |
|-------|----------|---------|-------------------------|
| upper | money    | 15000   | Upper transaction limit |
| lower | money    | -2000   | Lower transaction limit |

#### transactions

A new strichliste2 feature is transferring money to other accounts with an optional comment.

| field   | datatype | default | description                  |
|---------|----------|---------|------------------------------|
| enabled | bool     | true    | Enable/Disable sending money |

#### deposit

| field   | datatype | default | description                             |
|---------|----------|---------|-----------------------------------------|
| enabled | bool     | true    | Enable/Disable quick money pay in       |
| custom  | book     | true    | Enable/Disable deposit custom amounts   |
| steps   | money[]  |         | Available payment steps in unit "money" |

#### dispense

| field   | datatype | default | description                             |
|---------|----------|---------|-----------------------------------------|
| enabled | bool     | true    | Enable/Disable quick expend             |
| custom  | book     | true    | Enable/Disable dispense custom amounts  |
| steps   | money[]  |         | Available payment steps in unit "money" |

## MQTT

Optional integration that publishes a JSON message for every user, transaction
and article action so external systems (dashboards, home automation, …) can
react to them.

Unlike the settings above, MQTT is **not** configured in `config/strichliste.yaml`
and is **not** exposed via `/api/settings` — that endpoint is unauthenticated and
serves the whole settings tree, which would leak the broker credentials. It is
configured with `MQTT_*` environment variables instead (defaults in
`config/packages/mqtt.yaml`; set real values in `.env.local` or the container
environment). Disabled by default.

| variable        | datatype | default     | description                                              |
|-----------------|----------|-------------|----------------------------------------------------------|
| MQTT_ENABLED    | bool     | false       | Master switch for the integration                        |
| MQTT_HOST       | string   | 127.0.0.1   | Broker host                                              |
| MQTT_PORT       | int      | 1883        | Broker port                                             |
| MQTT_USERNAME   | string   | (empty)     | Broker username (omit for anonymous)                     |
| MQTT_PASSWORD   | string   | (empty)     | Broker password                                          |
| MQTT_CLIENT_ID  | string   | strichliste | MQTT client id                                          |
| MQTT_BASE_TOPIC | string   | strichliste | Prefix for every topic                                   |
| MQTT_QOS        | int      | 0           | Quality of service (0, 1 or 2)                           |
| MQTT_RETAIN     | bool     | false       | Publish messages as retained                             |
| MQTT_TLS        | bool     | false       | Connect over TLS                                         |

Publishing is fire-and-forget: a broker outage is logged and never breaks the
action that triggered it.

#### Topics

Each action publishes the same resource representation the `/api` returns,
JSON-encoded, to `{MQTT_BASE_TOPIC}/{entity}/{action}`:

| topic                            | published when                                |
|----------------------------------|-----------------------------------------------|
| `<base>/user/created`            | a user is created                             |
| `<base>/user/updated`            | a user's profile is updated                   |
| `<base>/transaction/created`     | a deposit, dispense, transfer or purchase     |
| `<base>/transaction/deleted`     | a transaction is reverted (undone)            |
| `<base>/article/created`         | an article is created                         |
| `<base>/article/updated`         | an article is updated (new revision)          |
| `<base>/article/deleted`         | an article is deactivated                     |

Bulk administrative CLI commands (imports, LDAP sync, cleanup) intentionally do
not publish.

## Datatypes

#### timeperiod

For format see: https://www.php.net/manual/en/datetime.formats.php#datetime.formats.relative

#### money

Money is always formatted in cents. So if you want to limit your transactions to 10 €, you have to use 1000 as value.
