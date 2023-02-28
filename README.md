# Maintenance reporting

This project contains tools to do maintenance reports.

## Scripts

### Composer lock diff period

Show the composer lock diff output for a specific period. TO use this command, composer lock diff must be installed in the environment.

#### Arguments

- **branch**: Branch to run composer-lock-diff
- **from**: Y-m-d date to start from.
- **to**: Y-m-d date to start to.


#### Usage

```
cd my-project

./maintenance-reporting/scripts/composer-lock-diff-month.sh dev 2023-02-01 2023-02-28
```

