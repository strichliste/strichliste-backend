# Strichliste Commands

## User Status

Deactivates or activates a user account based on userid or name

```bash
php bin/console app:user:status <userId> <active>
```

| argument | description                             |
|----------|-----------------------------------------|
| userId   | username or id                          |
| active   | true or false to activate or deactivate |

## Retire Data

If you want to delete older transactions because of privacy or other reasons, this is the right command to do that

```bash
php bin/console app:retire-data --days=3 --months=10 --confirm
```

This command deletes all transactions before 10 month and 3 days and skips confirm question

| option  | description                    |
|---------|--------------------------------|
| days    | Interval in days               |
| months  | Interval in months             |
| years   | Interval in years              |
| confirm | Automatically confirm question |

## Cleanup Accounts

This command comes in handy if you want to deactivate older unused accounts to clean up your list of stale users.

```bash
php bin/console app:user:cleanup --days=3 --months=10 --maxBalance=300 --confirm
```

| option     | description                    |
|------------|--------------------------------|
| days       | Interval in days               |
| months     | Interval in months             |
| years      | Interval in years              |
| confirm    | Automatically confirm question |
| minBalance | Minimum balance                |
| maxBalance | Maximum balance                |