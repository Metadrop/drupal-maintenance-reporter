# Drupal maintenance reporter

This project contains tools to do maintenance reports.

It allows:

- Showing the updated packages in a specific period.
- Show the fixed securities in a specific period.

## Usage

This command will generate a report of the develop branch of February 2023:

```
cd my-project

./vendor/bin/drupal-maintenance-reporter report develop --from=2023-02-01 --to=2023-02-28
```

### Arguments

- **branch**: Branch to run composer-lock-diff
- **from**: Y-m-d date to start from.
- **to**: Y-m-d date to start to.

### Examples

- Generate a full report (packages updated + fixed securities):

```
cd my-project

./vendor/bin/drupal-maintenance-reporter report develop --from=2023-02-01 --to=2023-02-28
```

- Show packages updated given a specific period:

```
cd my-project

./vendor/bin/drupal-maintenance-reporter composer-lock-diff-period develop --from=2023-02-01 --to=2023-02-28
```

- Fixed securities in a specific period:

```
cd my-project

./vendor/bin/drupal-maintenance-reporter securities-fixed develop --from=2023-02-01 --to=2023-02-28
```
