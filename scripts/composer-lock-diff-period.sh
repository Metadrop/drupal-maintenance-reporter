#!/bin/bash
BRANCH=$1
FROM=$2
TO=$3


git fetch

composer_lock_from_filename='composer-lock-from.json'
composer_lock_to_filename='composer-lock-to.json'

first_commit=$(git log origin/$BRANCH --after=$FROM --pretty=format:"%h" | tail -n1)
first_commit_data=$(git show $first_commit:composer.lock)
echo $first_commit_data > $composer_lock_from_filename


last_commit=$(git log origin/$BRANCH --until=$TO --pretty=format:"%h" | head -n1)
last_commit_data=$(git show $last_commit:composer.lock)
echo $last_commit_data > $composer_lock_to_filename

composer-lock-diff --from $composer_lock_from_filename --to $composer_lock_to_filename

rm $composer_lock_from_filename $composer_lock_to_filename
