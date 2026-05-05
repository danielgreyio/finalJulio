-- Migration 008: Add carrier, service, and destination postal to orders table
-- Required by Phase 2 shipping integration (Estafeta + DHL Mexico)

ALTER TABLE orders
    ADD COLUMN shipping_carrier    VARCHAR(50)  NULL AFTER shipping_cost,
    ADD COLUMN shipping_service    VARCHAR(100) NULL AFTER shipping_carrier,
    ADD COLUMN destination_postal  VARCHAR(10)  NULL AFTER shipping_service;
