#!/bin/sh
set -e

# Les migrations sont jouees au demarrage du conteneur : une mise en production
# qui livre du code sans son schema produit une application cassee.
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

php bin/console cache:warmup --env=prod

exec "$@"
