#!/bin/sh
set -e

php /var/www/html/bin/install.php --wait=60 --seed

exec apache2-foreground
