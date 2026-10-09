#!/usr/bin/env bash
                                                                                                             
set -e
cd "$(dirname "$0")/.."
DB=/tmp/ptl_e2e.sqlite; rm -f $DB storage/logs/mail.log
cp -n secrets/.env secrets/.env.bak 2>/dev/null || true
cat > secrets/.env <<EOF
APP_ENV=test
BASE_DOMAIN=plugthelist.test
URL_SCHEME=http
APP_KEY=$(php -r 'echo base64_encode(random_bytes(32));')
DB_DSN=sqlite:$DB
PAYSTACK_MOCK=1
PAYSTACK_SECRET=sk_test_e2e_secret
MAIL_DRIVER=log
CRON_TOKEN=cron_token_for_tests_1234567890
URL_WWW=http://127.0.0.1:8081
URL_AUTH=http://127.0.0.1:8082
URL_CURATOR=http://127.0.0.1:8083
URL_APP=http://127.0.0.1:8084
URL_ADMIN=http://127.0.0.1:8085
URL_API=http://127.0.0.1:8086
EOF
php bin/migrate.php >/dev/null
PIDS=""
i=8081
for d in www auth curator app admin api; do
  php -S 127.0.0.1:$i -t $d tests/router.php >/tmp/ptl_srv_$d.log 2>&1 &
  PIDS="$PIDS $!"; i=$((i+1))
done
trap 'kill $PIDS 2>/dev/null; rm -f secrets/.env; [ -f secrets/.env.bak ] && mv secrets/.env.bak secrets/.env; true' EXIT
sleep 1
php tests/e2e.php
