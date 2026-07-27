#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

echo "Starting WordPress + MySQL..."
docker compose up -d db wordpress

echo "Waiting for WordPress..."
for i in $(seq 1 60); do
  if docker compose run --rm wpcli wp core is-installed --allow-root 2>/dev/null; then
    INSTALLED=1
    break
  fi
  sleep 2
done

if [ "${INSTALLED:-0}" != "1" ]; then
  echo "Installing WordPress..."
  docker compose run --rm wpcli wp core install \
    --url="http://127.0.0.1:9400" \
    --title="OminiFlow WooCommerce Dev" \
    --admin_user="admin" \
    --admin_password="admin123" \
    --admin_email="admin@example.com" \
    --skip-email \
    --allow-root
fi

echo "Installing WooCommerce..."
docker compose run --rm wpcli wp plugin install woocommerce --activate --allow-root

echo "Activating OminiFlow for WooCommerce plugin..."
docker compose run --rm wpcli wp plugin activate facebook-for-woocommerce --allow-root

echo "Running WooCommerce setup wizard..."
docker compose run --rm wpcli wp option update woocommerce_onboarding_profile '{"completed":true}' --format=json --allow-root 2>/dev/null || true
docker compose run --rm wpcli wp option update woocommerce_task_list_hidden "yes" --allow-root 2>/dev/null || true
docker compose run --rm wpcli wp option update woocommerce_default_country "US:CA" --allow-root
docker compose run --rm wpcli wp option update woocommerce_currency "USD" --allow-root
docker compose run --rm wpcli wp option update woocommerce_store_address "123 Test Street" --allow-root
docker compose run --rm wpcli wp option update woocommerce_store_city "San Francisco" --allow-root
docker compose run --rm wpcli wp option update woocommerce_store_postcode "94105" --allow-root

echo ""
echo "=========================================="
echo " Local WooCommerce setup is ready"
echo "=========================================="
echo " Store:     http://127.0.0.1:9400"
echo " Admin:     http://127.0.0.1:9400/wp-admin"
echo " User:      admin"
echo " Password:  admin123"
echo " OminiFlow: http://127.0.0.1:9400/wp-admin/admin.php?page=wc-facebook"
echo " WhatsApp:  http://127.0.0.1:9400/wp-admin/admin.php?page=wc-whatsapp"
echo "=========================================="
echo "Stop: docker compose down"
