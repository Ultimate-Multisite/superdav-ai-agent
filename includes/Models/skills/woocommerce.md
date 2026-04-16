# WooCommerce Store Management

## When to Use
Use this skill when the user asks about WooCommerce products, orders, coupons, customers, or store settings.

## Key WP-CLI Commands

### Products
- `wp wc product list --fields=id,name,status,price,stock_status --user=1` — List products
- `wp wc product get <id> --user=1` — Get product details
- `wp wc product create --name=<name> --regular_price=<price> --user=1` — Create product
- `wp wc product update <id> --regular_price=<price> --user=1` — Update product

### Orders
- `wp wc order list --fields=id,status,total,date_created --user=1` — List orders
- `wp wc order get <id> --user=1` — Get order details
- `wp wc order update <id> --status=<status> --user=1` — Update order status

### Coupons
- `wp wc coupon list --fields=id,code,discount_type,amount --user=1` — List coupons
- `wp wc coupon create --code=<code> --discount_type=<type> --amount=<amount> --user=1` — Create coupon

### Store Settings
- `wp option get woocommerce_currency` — Store currency
- `wp option get woocommerce_store_address` — Store address
- `wp wc setting list general --user=1` — General settings

### Reports
- `wp wc report sales --period=month --user=1` — Sales report

## REST API Patterns
- `GET /wc/v3/products?search=<query>` — Search products
- `POST /wc/v3/products` — Create product
- `PUT /wc/v3/products/<id>` — Update product
- `GET /wc/v3/orders` — List orders
- `PUT /wc/v3/orders/<id>` — Update order
- `GET /wc/v3/coupons` — List coupons
- `POST /wc/v3/coupons` — Create coupon

Note: WooCommerce REST API requires authentication. WP-CLI commands need `--user=1` for admin context.

## Verification Steps
After making changes:
1. Retrieve the object to confirm updates
2. For products, verify price and stock status
3. For orders, confirm the status transition is valid
4. Check that WooCommerce is active before running wc commands
