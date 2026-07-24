#!/bin/sh
set -e

cd /var/www/html

if [ -n "$DB_HOST" ]; then
  i=0
  until php -r "try { new PDO('mysql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT')?:3306).';dbname='.getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); exit(0);} catch (Exception \$e) { exit(1); }" 2>/dev/null; do
    i=$((i + 1))
    if [ "$i" -ge 60 ]; then
      echo "DB not ready after 60s"
      exit 1
    fi
    sleep 1
  done
fi

mkdir -p storage/app/public \
  storage/framework/cache \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

if [ -x /opt/venv/bin/python ]; then
  /opt/venv/bin/python -c "import pandas, sklearn, pymysql" 2>/dev/null \
    || echo "WARN: Python pipeline deps missing"
fi

# skip cache/migrate on queue worker
case " $* " in
  *"queue:work"*)
    ;;
  *)
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan storage:link --force 2>/dev/null || true
    php artisan migrate --force
    ;;
esac

exec "$@"
