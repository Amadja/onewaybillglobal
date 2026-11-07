START TRANSACTION;

SET @ares_tracking = 'GRC-UG-271215221';
SET @ares_name = 'Ares Group Sarl - RDC';
SET @ares_phone = '+243904429804';
SET @ares_existing_shipment_id = (SELECT id FROM shipments WHERE BINARY tracking_number = BINARY @ares_tracking LIMIT 1);
SET @ares_new_shipment = @ares_existing_shipment_id IS NULL;
SET @ares_customer_id = (SELECT id FROM customers WHERE BINARY name = BINARY @ares_name AND BINARY phone = BINARY @ares_phone ORDER BY id LIMIT 1);
INSERT INTO customers (name, company_name, phone, address, import_permit, website)
SELECT @ares_name, NULL, @ares_phone, '144 AVENUE DU 04 JANVIER, KINDU, MANIEMA RDC', 'A1310723W', 'https://aresgroup.sarl.fr/'
WHERE @ares_customer_id IS NULL AND @ares_new_shipment = 1;
SET @ares_customer_id = COALESCE(@ares_customer_id, LAST_INSERT_ID());
INSERT INTO shipments (tracking_number, customer_id, origin_country, destination_country, origin_location, destination_location, service_type, package_description, package_weight_kg, current_status)
SELECT @ares_tracking, @ares_customer_id, 'DRC', 'Warehouse', 'DRC', 'Entebbe, Uganda', 'Warehouse Storage', 'Precious Metal', 168.00, 'Goods received at warehouse'
WHERE @ares_new_shipment = 1;
SET @ares_shipment_id = COALESCE(@ares_existing_shipment_id, LAST_INSERT_ID());
INSERT INTO warehouse_entries (shipment_id, warehouse_name, warehouse_location, entry_date, entry_time, free_storage_days, daily_rate_per_kg_usd, formula_text)
SELECT @ares_shipment_id, 'Garamba Warehouse', 'Entebbe, Uganda', '2026-09-28', '09:00:00', 5, 3.00, 'Charge = max((full_elapsed_days - free_days), 0) * weight_kg * daily_rate_usd_per_kg_per_day; days are 24-hour periods from entry time'
WHERE @ares_new_shipment = 1;
INSERT INTO shipment_events (shipment_id, title, location, event_date, event_time, is_current, is_alert, alert_level, description)
SELECT @ares_shipment_id, 'Goods received at warehouse', 'Garamba Warehouse, Entebbe, Uganda', '2026-09-28', '09:00:00', 1, 0, 'none', ''
WHERE @ares_new_shipment = 1;
INSERT INTO shipment_events (shipment_id, title, location, event_date, event_time, is_current, is_alert, alert_level, description)
SELECT @ares_shipment_id, 'Storage fee accrual starts', 'Garamba Warehouse, Entebbe, Uganda', '2026-10-03', '09:00:00', 0, 0, 'none', 'Warehouse charges begin after 5 free days.'
WHERE @ares_new_shipment = 1;

SET @roger_tracking = 'GRC-UG-260915231';
SET @roger_name = 'ROGER NGANDU';
SET @roger_phone = '+243990480832';
SET @roger_existing_shipment_id = (SELECT id FROM shipments WHERE BINARY tracking_number = BINARY @roger_tracking LIMIT 1);
SET @roger_new_shipment = @roger_existing_shipment_id IS NULL;
SET @roger_customer_id = (SELECT id FROM customers WHERE BINARY name = BINARY @roger_name AND BINARY phone = BINARY @roger_phone ORDER BY id LIMIT 1);
INSERT INTO customers (name, company_name, phone, address, import_permit, website)
SELECT @roger_name, NULL, @roger_phone, NULL, NULL, NULL
WHERE @roger_customer_id IS NULL AND @roger_new_shipment = 1;
SET @roger_customer_id = COALESCE(@roger_customer_id, LAST_INSERT_ID());
INSERT INTO shipments (tracking_number, customer_id, origin_country, destination_country, origin_location, destination_location, service_type, package_description, package_weight_kg, current_status)
SELECT @roger_tracking, @roger_customer_id, 'DRC', 'Warehouse', 'DRC', 'Entebbe, Uganda', 'Warehouse Storage', 'Precious Metal', 1000.00, 'Goods received at warehouse'
WHERE @roger_new_shipment = 1;
SET @roger_shipment_id = COALESCE(@roger_existing_shipment_id, LAST_INSERT_ID());
INSERT INTO warehouse_entries (shipment_id, warehouse_name, warehouse_location, entry_date, entry_time, free_storage_days, daily_rate_per_kg_usd, formula_text)
SELECT @roger_shipment_id, 'Garamba Warehouse', 'Entebbe, Uganda', '2026-09-15', '09:00:00', 5, 3.00, 'Charge = max((full_elapsed_days - free_days), 0) * weight_kg * daily_rate_usd_per_kg_per_day; days are 24-hour periods from entry time'
WHERE @roger_new_shipment = 1;
INSERT INTO shipment_events (shipment_id, title, location, event_date, event_time, is_current, is_alert, alert_level, description)
SELECT @roger_shipment_id, 'Goods received at warehouse', 'Garamba Warehouse, Entebbe, Uganda', '2026-09-15', '09:00:00', 1, 0, 'none', ''
WHERE @roger_new_shipment = 1;
INSERT INTO shipment_events (shipment_id, title, location, event_date, event_time, is_current, is_alert, alert_level, description)
SELECT @roger_shipment_id, 'Storage fee accrual starts', 'Garamba Warehouse, Entebbe, Uganda', '2026-09-20', '09:00:00', 0, 0, 'none', 'Warehouse charges begin after 5 free days.'
WHERE @roger_new_shipment = 1;

COMMIT;

SELECT tracking_number, current_status
FROM shipments
WHERE BINARY tracking_number IN (BINARY 'GRC-UG-271215221', BINARY 'GRC-UG-260915231')
ORDER BY tracking_number;
