#!/bin/sh
set -e

DB=/var/www/html/database/database.sqlite

# Persistent disk starts empty on first deploy — seed if no database file yet.
if [ ! -f "$DB" ]; then
    sqlite3 "$DB" < /var/www/html/schema.sql
    chown www-data:www-data "$DB"
fi

exec apache2-foreground
