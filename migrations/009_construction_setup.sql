-- Migration 009: Construction marketplace setup
-- Replaces generic categories with Mexico construction verticals,
-- adds unit_of_measure to products, creates bulk pricing tiers.

-- ── 1. Replace generic categories ────────────────────────────────────────────

-- Deactivate old generic categories (preserve IDs to avoid FK issues)
UPDATE categories SET is_active = FALSE
WHERE slug IN ('electronics','clothing','home-garden','sports','books');

-- Insert construction categories (skip if already present)
INSERT INTO categories (name, slug, description, parent_id, is_active, sort_order) VALUES
('Construcción',       'construccion',      'Cemento, varilla, tabique, block, arena, grava y materiales estructurales', NULL, TRUE, 10),
('Herramientas',       'herramientas',      'Herramientas eléctricas, manuales e instrumentos de medición',             NULL, TRUE, 20),
('Eléctrico',          'electrico',         'Cables, tableros, luminarias, contactos e instalaciones eléctricas',       NULL, TRUE, 30),
('Plomería',           'plomeria',          'Tubería, llaves, conexiones, bombas y sistemas hidráulicos',                NULL, TRUE, 40),
('Seguridad Industrial','seguridad-industrial','EPP, señalización, extintores y equipo de seguridad',                  NULL, TRUE, 50),
('Acabados',           'acabados',          'Pintura, pisos, azulejo, impermeabilizantes y recubrimientos',             NULL, TRUE, 60),
('Ferretería General', 'ferreteria-general','Tornillos, anclas, adhesivos y consumibles de ferretería',                NULL, TRUE, 70)
ON DUPLICATE KEY UPDATE is_active = TRUE, sort_order = VALUES(sort_order);

-- ── 2. Add unit_of_measure to products ───────────────────────────────────────

ALTER TABLE products
    ADD COLUMN IF NOT EXISTS unit_of_measure VARCHAR(40) NOT NULL DEFAULT 'pieza' AFTER price;

-- ── 3. Create bulk pricing tiers table ───────────────────────────────────────

CREATE TABLE IF NOT EXISTS product_pricing_tiers (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id      INT          NOT NULL,
    min_quantity    INT UNSIGNED NOT NULL DEFAULT 1,
    price_per_unit  DECIMAL(10,2) NOT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_tiers_product (product_id),
    UNIQUE KEY uq_product_min_qty (product_id, min_quantity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
