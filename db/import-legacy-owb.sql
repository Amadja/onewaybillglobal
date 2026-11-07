START TRANSACTION;

SET @owb_tracking = 'OWB-UG-260927-001';
SET @owb_name = 'Client details pending - OWB-UG-260927-001';
SET @owb_existing_shipment_id = (SELECT id FROM shipments WHERE BINARY tracking_number = BINARY @owb_tracking LIMIT 1);
SET @owb_new_shipment = @owb_existing_shipment_id IS NULL;
SET @owb_customer_id = (SELECT id FROM customers WHERE BINARY name = BINARY @owb_name AND phone IS NULL ORDER BY id LIMIT 1);

INSERT INTO customers (name, company_name, phone, address, import_permit, website)
SELECT @owb_name, NULL, NULL, NULL, NULL, NULL
WHERE @owb_customer_id IS NULL AND @owb_new_shipment = 1;
SET @owb_customer_id = COALESCE(@owb_customer_id, LAST_INSERT_ID());

INSERT INTO shipments (tracking_number, customer_id, origin_country, destination_country, origin_location, destination_location, service_type, package_description, package_weight_kg, current_status)
SELECT @owb_tracking, @owb_customer_id, 'Uganda', 'UAE', 'Kampala', 'Dubai', 'International Air Freight', 'Precious material', 25.00, 'Preparing for international departure'
WHERE @owb_new_shipment = 1;
SET @owb_shipment_id = COALESCE(@owb_existing_shipment_id, LAST_INSERT_ID());

INSERT INTO warehouse_entries (shipment_id, warehouse_name, warehouse_location, entry_date, entry_time, free_storage_days, daily_rate_per_kg_usd, formula_text)
SELECT @owb_shipment_id, 'Garamba Warehouse', 'Entebbe, Uganda', '2026-09-28', '09:11:00', 5, 3.00, 'Charge = max((full_elapsed_days - free_days), 0) * weight_kg * daily_rate_usd_per_kg_per_day; days are 24-hour periods from entry time'
WHERE @owb_new_shipment = 1;

INSERT INTO shipment_events (shipment_id, title, location, event_date, event_time, is_current, is_alert, alert_level, description)
SELECT @owb_shipment_id, 'Shipment received', 'Warehouse, Entebbe, Uganda', '2026-09-28', '09:11:00', 0, 0, 'none', ''
WHERE @owb_new_shipment = 1;

INSERT INTO shipment_events (shipment_id, title, location, carrier_name, flight_number, event_date, event_time, is_current, is_alert, alert_level, description)
SELECT @owb_shipment_id, 'Departed origin facility', 'EBB Airport, Uganda', 'Kenya Airways Cargo', NULL, '2026-09-28', '15:05:00', 0, 0, 'none', ''
WHERE @owb_new_shipment = 1;

INSERT INTO shipment_events (shipment_id, title, location, event_date, event_time, is_current, is_alert, alert_level, description)
SELECT @owb_shipment_id, 'Preparing for international departure', 'Nairobi, Kenya', '2026-09-28', '16:13:00', 1, 1, 'warning', 'Pending completion and verification of the required documentation.'
WHERE @owb_new_shipment = 1;

INSERT INTO shipment_events (shipment_id, title, location, event_date, event_time, is_current, is_alert, alert_level, description)
SELECT @owb_shipment_id, 'Arrived at destination', 'Dubai, UAE', NULL, NULL, 0, 0, 'none', ''
WHERE @owb_new_shipment = 1;

COMMIT;

SELECT tracking_number, current_status
FROM shipments
WHERE BINARY tracking_number = BINARY 'OWB-UG-260927-001';
