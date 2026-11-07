# One Way Bill Global

PHP/MySQL cargo tracking site. The root `index.php` serves the Garamba Sky Cargo storefront; shipment management is available under `/admin/` and public tracking uses the database.

## Local setup

1. Start Apache and MySQL in XAMPP.
2. Set the local database values in `config.php`; its base URL defaults to the XAMPP path.
3. Open `/setup-database.php` once to create the schema and initial admin account, then visit the site root.
4. Sign in at `/admin/login.php` with username `admin` and password `admin`.
5. Manage clients at `/admin/customers.php`, create shipments in the dashboard, and use a tracking number on the storefront.
6. Print a shipment receipt or accrued warehouse invoice from the shipment detail page. Change the starter password at `/admin/account.php` before exposing the site publicly.

## Upload to Apache hosting

1. Create a MySQL database and user in the hosting control panel and grant the user access to that database.
2. Upload the backend files/folders (`admin`, `db`, `includes`, `public`, `config.php`, `index.php`, and setup files) plus the root `.htaccess` into `public_html`. Enable “show hidden files” so `.htaccess` is included. Copy the image, CSS, JavaScript, and barcode-library files from `onewaybillglobal_website` directly into `public_html` too. Do not copy its prototype `index.html` or `shipments.json`; `index.php` serves the database-backed site and the JSON file is only legacy demo data. The asset resolver also supports leaving the files in `onewaybillglobal_website`.
3. `config.php` automatically uses `https://garambacargo.com` for `garambacargo.com`/`www.garambacargo.com` and retains the XAMPP localhost URL locally. Set `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` to the exact Namecheap database values; names are often prefixed with the cPanel account name.
4. Select the new database in phpMyAdmin and import `db/schema.sql`, or run `/setup-database.php` if the hosting database user is allowed to create tables.
5. To migrate the two legacy GRC warehouse records, select the database and import `db/import-legacy-grc.sql`.
6. To add the separate OWB legacy shipment, import `db/import-legacy-owb.sql`. It skips the record if that tracking number already exists. The source has no client identity, so it uses “Client details pending - OWB-UG-260927-001” for later editing.
7. Remove `setup-database.php` after setup. Test the homepage, admin workflows, and a real tracking number over HTTPS.

The client directory supports creating, searching, editing, and deleting clients. Deleting a client permanently cascades to that client's shipments, tracking events, charges, and warehouse entries; the admin UI displays the linked shipment count and asks for confirmation first.

The schema intentionally does not create or select a database, so it can be imported after selecting a host-created database. Keep `db/schema.sql` and configuration backups private.

## Warehouse charges

Enter the package weight when creating the shipment. The destination can be left undecided. On warehouse receipt, the counter operator enters the USD rate per kg per day and confirms the free-day allowance (5 by default). Days are complete 24-hour periods from the recorded entry time. The system calculates `max(full_elapsed_days - free_days, 0) * package_weight_kg * daily_rate_usd_per_kg_per_day` and updates the charge as days pass.

The accrual milestone is scheduled at the warehouse entry date/time plus the free-day allowance. If the shipment departs or the customer collects it before that point, no warehouse fee is charged and the accrual milestone is omitted from public tracking. After accrual begins, charges stop at the recorded departure or collection time.

When a warehouse fee has accrued, the shipment detail page offers a printable invoice showing the client, tracking reference, billing period, chargeable days, weight, daily rate, and total due. The invoice is calculated from current database records and is not a payment receipt.

After warehouse receipt, choose whether the shipment continues to a destination or stays at the warehouse for customer collection. A later destination can be added or changed from the shipment page. Standard transit milestones are suggested in the event form; custom updates are also accepted.

When recording `Departed origin facility`, operators can optionally enter the operating airline and flight number. Those details appear beside the departure in public tracking and can be added or corrected later from the shipment page.

Customer alerts are managed on the shipment page's preparation-stage alert editor. Info, attention, warning, and critical levels are color-coded in the public tracking timeline; each alert requires a customer-facing message. Alerts are shown on the tracking page only; outbound email/SMS delivery requires a configured provider.

## Before going public

The initial `admin` / `admin` credentials are intended for local setup only. Change the password at `/admin/account.php` before deployment. Use a dedicated production database user, HTTPS, and non-test data only. The legacy `shipments.json` file is not used by tracking and is blocked from direct downloads on Apache.