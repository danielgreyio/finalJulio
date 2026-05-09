-- Migration 012: Add tax_amount column to orders table
ALTER TABLE orders ADD COLUMN IF NOT EXISTS tax_amount DECIMAL(10,2) DEFAULT 0.00 AFTER shipping_cost;
