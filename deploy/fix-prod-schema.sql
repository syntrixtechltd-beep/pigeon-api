-- Brings a database created from pigeon-dashboard/deploy/schema.sql up to what pigeon-api and its two workers need.
-- Safe to run more than once: every change is IF NOT EXISTS or a MODIFY to the same definition. Needs MariaDB 10.3+.
-- Usage on the VM:  sudo mariadb pigeon_gateway < deploy/fix-prod-schema.sql
-- Back up first:    sudo mariadb-dump pigeon_gateway > ~/pigeon_gateway-before-fix.sql

-- 1. Payment methods: joined by every status / list query and by the disbursement webhook query.
CREATE TABLE IF NOT EXISTS payment_methods (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug CHAR(50) NOT NULL,
  name VARCHAR(100) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 0,
  created_ TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_ TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
INSERT IGNORE INTO payment_methods (id, slug, name, active) VALUES (1, 'mm', 'Mobile Money', 1);
ALTER TABLE payment_providers ADD COLUMN IF NOT EXISTS paymentMethod INT NOT NULL DEFAULT 1;

-- 2. Transactions: columns the MNO worker writes and the status/list/webhook queries read.
ALTER TABLE transactions
  ADD COLUMN IF NOT EXISTS transactionMobileOperatorTransactionId VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS transactionMobileOperatorResultCode VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS transactionMobileOperatorResultMessage TEXT NULL,
  ADD COLUMN IF NOT EXISTS callbackRequested TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS callbackProcessed TINYINT(1) NOT NULL DEFAULT 0;

-- The API inserts transactions without these, relying on defaults. Without them:
--   initiationDate NULL  -> the MNO worker never picks the transaction up (it filters on initiationDate);
--   completionDate NULL  -> the transaction is missing from GET /collections and GET /disbursements;
--   designation/general type NULL -> status checks return 404 (they filter/join on both).
-- Columns stay nullable because the dashboard inserts its own values, sometimes NULL.
ALTER TABLE transactions
  MODIFY initiationDate DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  MODIFY completionDate DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  MODIFY transactionDesignationId INT NULL DEFAULT 1,
  MODIFY transactionGeneralTransactionTypeId INT NULL DEFAULT 1;

-- Backfill rows the API already created, so status checks and lists find them.
UPDATE transactions SET transactionDesignationId = 1 WHERE transactionDesignationId IS NULL;
UPDATE transactions SET transactionGeneralTransactionTypeId = IF(transactionSpecificTransactionTypeId IN (2, 4), 2, 1) WHERE transactionGeneralTransactionTypeId IS NULL;
UPDATE transactions SET completionDate = initiationDate WHERE completionDate IS NULL AND initiationDate IS NOT NULL;

-- The API detects duplicate requests by this unique key (it derives the reference from the request fields).
-- Dashboard-created rows leave transactionReference NULL, which a unique key allows.
-- If this fails with "Duplicate entry", see the query at the bottom of this file.
ALTER TABLE transactions ADD UNIQUE INDEX IF NOT EXISTS transactionReference (transactionReference);

-- 3. MTN/Airtel access tokens: the MNO worker saves them with these columns and upserts on (provider_id, product_id).
-- The table is only a cache; the worker fetches new tokens on its next run, so clearing it is safe.
ALTER TABLE pp_access_tokens
  ADD COLUMN IF NOT EXISTS validity INT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS updated_ TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
DELETE FROM pp_access_tokens;
ALTER TABLE pp_access_tokens ADD UNIQUE INDEX IF NOT EXISTS token_paymentProductId (provider_id, product_id);

-- 4. Check: lists any table/column the API still cannot find. An empty result means everything is in place.
SELECT need.tbl AS missing_table, need.col AS missing_column
FROM (
            SELECT 'transactions' tbl, 'transactionMobileOperatorTransactionId' col
  UNION ALL SELECT 'transactions', 'transactionMobileOperatorResultMessage'
  UNION ALL SELECT 'transactions', 'callbackRequested'
  UNION ALL SELECT 'transactions', 'callbackProcessed'
  UNION ALL SELECT 'transactions', 'transactionMobileOperatorRef'
  UNION ALL SELECT 'transactions', 'transactionOriginatorIdentification'
  UNION ALL SELECT 'transactions', 'transactionOriginatorDescription'
  UNION ALL SELECT 'transactions', 'privateTransactionReference'
  UNION ALL SELECT 'payment_providers', 'paymentMethod'
  UNION ALL SELECT 'payment_methods', 'name'
  UNION ALL SELECT 'payment_provider_currencies', 'paymentProviderId'
  UNION ALL SELECT 'pp_access_tokens', 'validity'
  UNION ALL SELECT 'pp_access_tokens', 'updated_'
  UNION ALL SELECT 'api_users', 'whitelisted_ips'
  UNION ALL SELECT 'api_users', 'permissions'
  UNION ALL SELECT 'api_user_preferences', 'webHookUrl'
  UNION ALL SELECT 'api_user_preferences', 'webHookSecret'
  UNION ALL SELECT 'account_balances', 'balance'
  UNION ALL SELECT 'transactions_status', 'name'
  UNION ALL SELECT 'transactions_OriginatorChannels', 'name'
  UNION ALL SELECT 'transactions_SpecificTransactionTypes', 'name'
  UNION ALL SELECT 'transactions_GeneralTransactionTypes', 'name'
  UNION ALL SELECT 'transactions_transactionDesignation', 'name'
) need
LEFT JOIN information_schema.COLUMNS c
  ON c.TABLE_SCHEMA = DATABASE() AND c.TABLE_NAME = need.tbl AND c.COLUMN_NAME = need.col
WHERE c.COLUMN_NAME IS NULL;

-- If the unique key in step 2 failed, this shows the duplicated references to sort out by hand:
-- SELECT transactionReference, COUNT(*) FROM transactions WHERE transactionReference IS NOT NULL GROUP BY transactionReference HAVING COUNT(*) > 1;
