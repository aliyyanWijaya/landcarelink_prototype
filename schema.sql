-- LandcareLink Prototype — SQLite schema + seed data
-- Apply with: sqlite3 database/database.sqlite < schema.sql

DROP TABLE IF EXISTS `groups`;

CREATE TABLE `groups` (
  `id`            INTEGER PRIMARY KEY AUTOINCREMENT,
  `name`          TEXT NOT NULL,
  `type`          TEXT NOT NULL,
  `region`        TEXT NOT NULL,
  `contact_email` TEXT NOT NULL,
  `latitude`      REAL NOT NULL,
  `longitude`     REAL NOT NULL,
  `created_at`    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Sample seed data (10 rows, mix of Waikato and Bay of Plenty regions)
INSERT INTO `groups` (`name`, `type`, `region`, `contact_email`, `latitude`, `longitude`) VALUES
  ('Hamilton Halo Restoration',        'environmental_group',    'Waikato',        'contact@hamiltonhalo.org.nz',   -37.7870000, 175.2793000),
  ('Waikato River Care Collective',    'catchment_collective',   'Waikato',        'info@waikatorivercare.org.nz',  -37.6500000, 175.1700000),
  ('Pukemokemoke Bush Trust',          'environmental_group',    'Waikato',        'trust@pukemokemoke.org.nz',     -37.5300000, 175.4200000),
  ('Cambridge Catchment Group',        'catchment_group',        'Waikato',        'hello@cambridgecatchment.nz',   -37.8870000, 175.4670000),
  ('Maungatautari Ecological Island',  'environmental_group',    'Waikato',        'admin@sanctuarymountain.co.nz', -37.9990000, 175.5640000),
  ('Tauranga Moana Restoration',       'environmental_group',    'Bay of Plenty',  'kaitiaki@taurangamoana.org.nz', -37.6878000, 176.1651000),
  ('Kaituna Catchment Collective',     'catchment_collective',   'Bay of Plenty',  'info@kaitunacatchment.org.nz',  -37.7600000, 176.3500000),
  ('Rotorua Lakes Catchment Group',    'catchment_group',        'Bay of Plenty',  'lakes@rotoruacatchment.org.nz', -38.1100000, 176.2500000),
  ('Whakatane Kiwi Trust',             'environmental_group',    'Bay of Plenty',  'kiwi@whakatanekiwi.org.nz',     -37.9590000, 176.9850000),
  ('Te Puke Stream Care Group',        'catchment_group',        'Bay of Plenty',  'streams@tepukecare.org.nz',     -37.7830000, 176.3300000);
