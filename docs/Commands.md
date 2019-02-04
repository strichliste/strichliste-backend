# Strichliste Commands

# Import strichliste 1 database

To import your old strichliste 1 database, copy your `database.sqlite` file, copy it to the strichliste2 directory and run:

```bash
php bin/console app:import <filename>
```

| argument   | description                             |
|------------|-----------------------------------------|
| filename   | database to import                      |

After import the terminal outputs "Import done!"

# User Status

Deactivates or activates a user account based on userid or name

```bash
php bin/console app:user:status <userId> <active>
```

| argument | description                             |
|----------|-----------------------------------------|
| userId   | username or id                          |
| active   | true or false to activate or deactivate |

# Retire Data

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

# Cleanup Accounts

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

# Import from LDAP

**Attention:** If you want to use that feature, you have to install another composer component, which is not included by default!

Just run this command inside your installation:
```bash
composer require symfony/ldap
```

Bare minimum example command:
```bash
php bin/console app:ldapimport --host=ldap.company.tld --bindDn="cn=reader,ou=ldapuser,dc=company" --password="yourpass" --baseDn="ou=employee,dc=company"
```

| argument   | description                                                         |
|------------|---------------------------------------------------------------------|
| host       | hostname or IP                                                      |
| port       | port or your LDAP server (default: 636)                             |
| ssl        | Enable/Disable SSL (default: ssl, options: none, ssl, tls)          |
| bindDn     | LDAP user                                                           |
| password   | Password                                                            |
| baseDN     | LDAP base DN                                                        |
| query      | LDAP filter query (default: ($userField=*))                         |
| userField  | Username field (default: uid)                                       |
| emailField | Mailaddress field (default: false)                                  |
| update     | Enable/Disable updating Mailaddress if user exists (default: false) |

You can optionally run this commands as a cronjob, to adapt changed in your LDAP directory data.