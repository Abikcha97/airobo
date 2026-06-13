-- V1__create_all_tables.sql
-- Kiosk Cash Withdrawal Service — Full DB Schema
-- All monetary values in BIGINT (tiyin/so'm)

-- ============================================================
-- AGENTS
-- ============================================================
CREATE TABLE agents (
    id              BIGSERIAL PRIMARY KEY,
    name            VARCHAR(255)    NOT NULL,
    legal_name      VARCHAR(255)    NOT NULL,
    phone           VARCHAR(20)     NOT NULL,
    email           VARCHAR(255)    NOT NULL UNIQUE,
    inn             VARCHAR(20)     NOT NULL UNIQUE,
    tier            VARCHAR(20)     NOT NULL DEFAULT 'STANDARD',  -- STANDARD|SILVER|GOLD
    reward_rate     DECIMAL(8,5)    NOT NULL DEFAULT 0,
    is_active       BOOLEAN         NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE users (
    id                      BIGSERIAL PRIMARY KEY,
    username                VARCHAR(100)    NOT NULL UNIQUE,
    password_hash           VARCHAR(255)    NOT NULL,
    role                    VARCHAR(20)     NOT NULL,             -- ADMIN|AGENT|KIOSK
    agent_id                BIGINT          REFERENCES agents(id) ON DELETE SET NULL,
    is_active               BOOLEAN         NOT NULL DEFAULT TRUE,
    failed_login_attempts   SMALLINT        NOT NULL DEFAULT 0,
    locked_until            TIMESTAMPTZ,
    created_at              TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at              TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

-- ============================================================
-- KIOSKS
-- ============================================================
CREATE TABLE kiosks (
    id              BIGSERIAL PRIMARY KEY,
    agent_id        BIGINT          NOT NULL REFERENCES agents(id) ON DELETE CASCADE,
    serial_number   VARCHAR(100)    NOT NULL UNIQUE,
    location_name   VARCHAR(255)    NOT NULL,
    address         TEXT,
    status          VARCHAR(20)     NOT NULL DEFAULT 'ACTIVE',    -- ACTIVE|INACTIVE|MAINTENANCE
    dispenser_model VARCHAR(100),
    last_seen_at    TIMESTAMPTZ,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_kiosks_agent_status ON kiosks(agent_id, status);

-- ============================================================
-- TRANSACTIONS
-- ============================================================
CREATE TABLE transactions (
    id                          BIGSERIAL PRIMARY KEY,
    agent_transaction_id        VARCHAR(100)    NOT NULL UNIQUE,
    provider_transaction_id     BIGINT,
    check_transaction_id        BIGINT,
    kiosk_id                    BIGINT          NOT NULL REFERENCES kiosks(id),
    agent_id                    BIGINT          NOT NULL REFERENCES agents(id),
    service_id                  INTEGER         NOT NULL,
    card_account                VARCHAR(20)     NOT NULL,         -- ALWAYS masked: 860014******5346
    card_expire                 VARCHAR(10)     NOT NULL,         -- MM/YY
    requestor_phone             VARCHAR(20),
    amount                      BIGINT          NOT NULL,
    commission                  BIGINT          NOT NULL DEFAULT 0,
    currency_id                 SMALLINT        NOT NULL DEFAULT 0,
    status                      VARCHAR(30)     NOT NULL,
    provider_name               VARCHAR(20),                      -- OSON|PAYNET
    provider_check_response     JSONB,
    provider_pay_response       JSONB,
    failure_reason              VARCHAR(255),
    otp_attempts                SMALLINT        NOT NULL DEFAULT 0,
    otp_resend_count            SMALLINT        NOT NULL DEFAULT 0,
    otp_expires_at              TIMESTAMPTZ,
    created_at                  TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at                  TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_transactions_status_created ON transactions(status, created_at);
CREATE INDEX idx_transactions_kiosk_agent    ON transactions(kiosk_id, agent_id);

-- ============================================================
-- TRANSACTION LOGS
-- ============================================================
CREATE TABLE transaction_logs (
    id              BIGSERIAL PRIMARY KEY,
    transaction_id  BIGINT          NOT NULL REFERENCES transactions(id) ON DELETE CASCADE,
    from_status     VARCHAR(30),
    to_status       VARCHAR(30)     NOT NULL,
    note            TEXT,
    metadata        JSONB,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_txlogs_transaction_created ON transaction_logs(transaction_id, created_at);

-- ============================================================
-- AGENT DEPOSITS
-- ============================================================
CREATE TABLE agent_deposits (
    id              BIGSERIAL PRIMARY KEY,
    agent_id        BIGINT          NOT NULL UNIQUE REFERENCES agents(id) ON DELETE CASCADE,
    balance         BIGINT          NOT NULL DEFAULT 0,
    total_credited  BIGINT          NOT NULL DEFAULT 0,
    total_debited   BIGINT          NOT NULL DEFAULT 0,
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

-- ============================================================
-- DEPOSIT MOVEMENTS
-- ============================================================
CREATE TABLE deposit_movements (
    id                  BIGSERIAL PRIMARY KEY,
    agent_deposit_id    BIGINT          NOT NULL REFERENCES agent_deposits(id) ON DELETE CASCADE,
    transaction_id      BIGINT          REFERENCES transactions(id) ON DELETE SET NULL,
    type                VARCHAR(10)     NOT NULL,                 -- CREDIT|DEBIT
    amount              BIGINT          NOT NULL,
    balance_before      BIGINT          NOT NULL,
    balance_after       BIGINT          NOT NULL,
    description         VARCHAR(255),
    created_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_depmov_deposit_created ON deposit_movements(agent_deposit_id, created_at);

-- ============================================================
-- DISPENSER INVENTORY
-- ============================================================
CREATE TABLE dispenser_inventory (
    id              BIGSERIAL PRIMARY KEY,
    kiosk_id        BIGINT          NOT NULL REFERENCES kiosks(id) ON DELETE CASCADE,
    denomination    INTEGER         NOT NULL,
    quantity        INTEGER         NOT NULL DEFAULT 0,
    min_threshold   INTEGER         NOT NULL DEFAULT 20,
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    UNIQUE (kiosk_id, denomination)
);

-- ============================================================
-- DISPENSER FILL HISTORY
-- ============================================================
CREATE TABLE dispenser_fill_history (
    id              BIGSERIAL PRIMARY KEY,
    kiosk_id        BIGINT          NOT NULL REFERENCES kiosks(id) ON DELETE CASCADE,
    denomination    INTEGER         NOT NULL,
    quantity_added  INTEGER         NOT NULL,
    filled_by       BIGINT          NOT NULL REFERENCES users(id),
    filled_at       TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    notes           TEXT
);

CREATE INDEX idx_fillhist_kiosk_filled ON dispenser_fill_history(kiosk_id, filled_at);

-- ============================================================
-- AGENT REWARDS
-- ============================================================
CREATE TABLE agent_rewards (
    id              BIGSERIAL PRIMARY KEY,
    agent_id        BIGINT          NOT NULL REFERENCES agents(id) ON DELETE CASCADE,
    year            SMALLINT        NOT NULL,
    month           SMALLINT        NOT NULL,
    total_turnover  BIGINT          NOT NULL DEFAULT 0,
    reward_amount   BIGINT          NOT NULL DEFAULT 0,
    reward_rate     DECIMAL(8,5)    NOT NULL,
    status          VARCHAR(20)     NOT NULL DEFAULT 'CALCULATED', -- CALCULATED|CREDITED
    calculated_at   TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    credited_at     TIMESTAMPTZ,
    UNIQUE (agent_id, year, month)
);

CREATE INDEX idx_rewards_agent_year_month ON agent_rewards(agent_id, year, month);

-- ============================================================
-- RECONCILIATION REPORTS
-- ============================================================
CREATE TABLE reconciliation_reports (
    id                  BIGSERIAL PRIMARY KEY,
    agent_id            BIGINT          NOT NULL REFERENCES agents(id) ON DELETE CASCADE,
    report_date         DATE            NOT NULL,
    total_transactions  INTEGER         NOT NULL DEFAULT 0,
    total_amount        BIGINT          NOT NULL DEFAULT 0,
    total_commission    BIGINT          NOT NULL DEFAULT 0,
    success_count       INTEGER         NOT NULL DEFAULT 0,
    failed_count        INTEGER         NOT NULL DEFAULT 0,
    discrepancy_count   INTEGER         NOT NULL DEFAULT 0,
    discrepancies       JSONB,
    file_path_pdf       VARCHAR(500),
    file_path_csv       VARCHAR(500),
    generated_at        TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    UNIQUE (agent_id, report_date)
);

CREATE INDEX idx_reconciliation_agent_date ON reconciliation_reports(agent_id, report_date);

-- ============================================================
-- AUDIT LOGS
-- ============================================================
CREATE TABLE audit_logs (
    id              BIGSERIAL PRIMARY KEY,
    user_id         BIGINT          REFERENCES users(id) ON DELETE SET NULL,
    action          VARCHAR(100)    NOT NULL,
    entity_type     VARCHAR(100)    NOT NULL,
    entity_id       BIGINT,
    old_value       JSONB,
    new_value       JSONB,
    ip_address      VARCHAR(45),
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_auditlogs_user_created ON audit_logs(user_id, created_at);
CREATE INDEX idx_auditlogs_entity       ON audit_logs(entity_type, entity_id);
