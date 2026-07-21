ALTER TABLE webhook_tokens
    ADD COLUMN token_hash VARCHAR(64) NULL AFTER token_tag,
    ADD KEY idx_webhook_tokens_token_hash (token_hash);

-- Backfill existing tokens is handled once in application code
-- (WebhookTokenRepository::backfillMissingTokenHashes) because plaintext
-- hashes cannot be derived in SQL from encrypted token payloads.
