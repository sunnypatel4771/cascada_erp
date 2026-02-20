#!/bin/bash
set -e

host="${DB_HOST:-db}"
user="${DB_USER:-user}"
password="${DB_PASSWORD:-password}"
database="${DB_NAME:-erp_db}"

echo "Waiting for MySQL at $host:3306..."

until MYSQL_PWD="$password" mysql -h "$host" -u "$user" -e "SELECT 1" &> /dev/null; do
  echo "MySQL is unavailable - sleeping..."
  sleep 2
done

echo "MySQL is up and running!"
exec apache2-foreground