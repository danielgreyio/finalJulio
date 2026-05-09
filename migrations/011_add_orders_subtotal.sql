-- Migration 011: Add subtotal column to orders table
ALTER TABLE orders ADD COLUMN IF NOT EXISTS subtotal DECIMAL(10,2) DEFAULT 0.00 AFTER merchant_id;
