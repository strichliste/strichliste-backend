# Strichliste Config

The configuration can be found in `config/strichliste.yaml`. This is also reachable by the `/api/settings` endpoint and used by the frontend.

*Hint*: You can overwrite the import path in `config/services.yaml` if you want to put your config file into another place like `/etc/strichliste.yml`

*Hint*: You have to clear the cache with the corresponding command to apply config changes.

## common

| field      | datatype | default    | description          |
|------------|----------|------------|----------------------|
| idleTimer  | int      | 30000      | Time in milliseconds |

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
| enabled | bool     | true    | Enable/Disable sending money            |
| custom  | book     | true    | Enable/Disable custom amounts           |
| steps   | money[]  |         | Available payment steps in unit "money" |

#### dispense:

| field   | datatype | default | description                             |
|---------|----------|---------|-----------------------------------------|
| enabled | bool     | true    | Enable/Disable sending money            |
| custom  | book     | true    | Enable/Disable custom amounts           |
| steps   | money[]  |         | Available payment steps in unit "money" |

## Datatypes

#### timeperiod

For format see http://de.php.net/manual/en/datetime.formats.relative.php

#### money

Money is always formatted in cents. So if you want to limit your transactions to 10 €, you have to use 1000 as value.
