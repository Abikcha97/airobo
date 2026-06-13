# Implementation Plan: Kiosk Cash Withdrawal Service

## Overview

This plan breaks down the Kiosk Cash Withdrawal Service into discrete, incremental coding tasks.
Each task builds on the previous ones and wires up PHP (Laravel-style) and Java (Spring Boot)
implementations in parallel. The plan follows the flow: database → auth → core transaction API →
payment adapter → financial engine → dispenser → scheduled jobs → admin/agent APIs → error handling.

All monetary values are stored as `BIGINT` (tiyin). All timestamps use UTC+5 (Uzbekistan time).

---

## Tasks

- [ ] 1. Database migrations and core model layer
  - [ ] 1.1 Create all database migration files for the full schema
    - Write migration files for all 12 tables: `users`, `agents`, `kiosks`, `transactions`,
      `transaction_logs`, `agent_deposits`, `deposit_movements`, `dispenser_inventory`,
      `dispenser_fill_history`, `agent_rewards`, `reconciliation_reports`, `audit_logs`
    - Apply correct column types (`BIGINT` for all monetary fields, `JSONB` for provider responses,
      `VARCHAR` for `status` enum columns)
    - Add all foreign key constraints and indexes on `status`, `agent_id`, `kiosk_id`, `created_at`
    - _Requirements: 6.3, 7.4, 10.2_

  - [ ] 1.2 Implement Eloquent/JPA model classes
    - PHP: Create Eloquent models `Transaction`, `Agent`, `Kiosk`, `AgentDeposit`,
      `DepositMovement`, `DispenserInventory`, `TransactionLog`, `User`
    - Java: Create JPA `@Entity` classes for the same tables with proper annotations
    - Add card masking logic in `Transaction` model: full `card_account` is never stored —
      only the masked form `860014******5346`
    - _Requirements: 1.6, 6.3_

- [ ] 2. JWT authentication and RBAC middleware
  - [ ] 2.1 Generate RSA key pair and implement JWT token service
    - PHP: Implement `JwtService` that signs/verifies tokens with RS256 using the private/public key
      paths from `JWT_PRIVATE_KEY_PATH` / `JWT_PUBLIC_KEY_PATH`
    - Java: Configure `SecurityConfig` with RS256 JWT decoder; implement `JwtTokenProvider`
    - Token payload must include: `sub`, `username`, `role`, `agent_id`, `iat`, `exp`, `jti`
    - Access token TTL: 3600s; Refresh token TTL: 30 days
    - _Requirements: 13.1, 13.5_

  - [ ] 2.2 Implement login, refresh, and logout endpoints
    - PHP: `AuthController@login`, `AuthController@refresh`, `AuthController@logout`
    - Java: `AuthController` with `/api/v1/auth/login`, `/api/v1/auth/refresh`, `/api/v1/auth/logout`
    - On login failure: increment `failed_login_attempts`; when ≥ 5 set `locked_until = NOW() + 30 min`
    - On login: reset `failed_login_attempts = 0`
    - Return `{ access_token, refresh_token, expires_in, role }` on success
    - Return `401 INVALID_CREDENTIALS` on failure; `423 ACCOUNT_LOCKED` when locked
    - _Requirements: 13.1, 13.4_

  - [ ] 2.3 Implement RBAC middleware and route guards
    - PHP: `JwtAuthMiddleware` — verify Bearer token, inject authenticated user/role into request context
    - PHP: `RoleMiddleware` — check `role` claim against allowed roles per route group
    - Java: `JwtAuthFilter` — extract and validate JWT, populate `SecurityContext`
    - Java: `@PreAuthorize` annotations on controller methods per RBAC table in design §9.2
    - Rate limiting per design §9.3: use Redis counters with TTL keys
    - _Requirements: 13.2, 13.3, 13.6_

  - [ ]* 2.4 Write unit tests for JWT service and RBAC middleware
    - Test token generation, expiry, and invalid signature rejection
    - Test account lockout after 5 failed attempts
    - Test role enforcement — KIOSK cannot call refund, AGENT cannot call admin endpoints
    - _Requirements: 13.1, 13.2, 13.3, 13.4_

- [ ] 3. Card validation and transaction initiation
  - [ ] 3.1 Implement Luhn algorithm and card validation utilities
    - PHP: `CardValidator::luhn(string $cardNumber): bool` and `CardValidator::maskCard(string $card): string`
    - Java: `CardValidator.luhn(String cardNumber)` and `CardValidator.maskCard(String card)`
    - Validate 16-digit format and Luhn checksum
    - Validate `card_expire` in `MM/YY` format and ensure it is not in the past
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.6_

  - [ ] 3.2 Implement `POST /api/v1/transactions/initiate` endpoint
    - PHP: `TransactionController@initiate` → `TransactionService@initiate`
    - Java: `TransactionController.initiate()` → `TransactionService.initiate()`
    - Validate request fields (`kiosk_id`, `card_account`, `card_expire`, `service_id`)
    - Run Luhn check; reject with `422 INVALID_CARD` on failure
    - Reject expired cards with `422 CARD_EXPIRED`
    - Store masked card only; generate `agent_transaction_id` = `KSK-{id}-{YYYYMMDD}`
    - Create transaction record with status `INITIATED`; write `transaction_logs` entry
    - Return `{ transaction_id, agent_transaction_id, status, masked_card }`
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 6.1, 6.3_

  - [ ]* 3.3 Write unit tests for card validation and initiation
    - Test Luhn pass/fail cases including edge numbers
    - Test expired card rejection
    - Test that full card number is never persisted (only masked form)
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.6_

- [ ] 4. OTP service (send, verify, Redis TTL)
  - [ ] 4.1 Implement OTP generation, storage, and send endpoint
    - PHP: `OtpService@generate()` — 6-digit random code stored in Redis key
      `otp:{transaction_id}` with TTL = 180s
    - Java: `OtpService.generate()` — same logic using `StringRedisTemplate`
    - PHP: `TransactionController@sendOtp` → `POST /api/v1/transactions/{id}/send-otp`
    - Java: matching Spring controller method
    - Check `otp_resend_count < 3`; reject with `429 OTP_RESEND_LIMIT` if exceeded
    - Increment `otp_resend_count`; update status to `OTP_SENT`; log state transition
    - Call SMS Gateway (Eskiz / Play Mobile) with OTP code to `requestorPhone`
    - On SMS failure return `503 SMS_SEND_FAILED`
    - Return `{ status, phone_masked, expires_in: 180 }`
    - _Requirements: 2.1, 2.2, 2.7, 2.8_

  - [ ] 4.2 Implement OTP verification endpoint
    - PHP: `TransactionController@verifyOtp` → `POST /api/v1/transactions/{id}/verify-otp`
    - Java: matching Spring controller method
    - Fetch OTP from Redis; compare with submitted code (constant-time compare)
    - On match: delete Redis key, set status `OTP_VERIFIED`, log transition,
      return `{ status: OTP_VERIFIED }`
    - On mismatch: increment `otp_attempts`; return `400 INVALID_OTP` with `attempts_left`
    - When `otp_attempts >= 3`: set status `FAILED`, return `423 TRANSACTION_BLOCKED` with
      `retry_after: 900`
    - When OTP key missing (expired): return `400 OTP_EXPIRED`
    - _Requirements: 2.3, 2.4, 2.5, 2.6_

  - [ ]* 4.3 Write unit tests for OTP service
    - Test 180s TTL enforcement (mock Redis clock)
    - Test 3-attempt lockout sets transaction to FAILED
    - Test resend limit of 3 per transaction
    - _Requirements: 2.2, 2.5, 2.7_

- [ ] 5. Amount validation and Check Request
  - [ ] 5.1 Implement amount validation logic
    - PHP: `AmountValidator::validate(int $amount): void` — throws typed exceptions
    - Java: `AmountValidator.validate(long amount)` — throws typed exceptions
    - Min: 10 000 UZS; Max: 5 000 000 UZS; must be a multiple of 1 000
    - Errors: `422 AMOUNT_TOO_LOW`, `422 AMOUNT_TOO_HIGH`, `422 AMOUNT_NOT_MULTIPLE`
    - _Requirements: 3.3, 3.4, 3.5, 3.6, 3.7_

  - [ ] 5.2 Implement dispenser availability check
    - PHP: `DispenserService@canDispense(int $kioskId, int $amount): bool`
    - Java: `DispenserService.canDispense(long kioskId, long amount)`
    - Query `dispenser_inventory` for the kiosk; determine if denominations sum to `amount`
    - Return available denominations list if possible; throw `INSUFFICIENT_DISPENSER` otherwise
    - _Requirements: 4.5, 10.3_

  - [ ] 5.3 Implement `POST /api/v1/transactions/{id}/check` endpoint
    - PHP: `TransactionController@check` → `TransactionService@check`
    - Java: matching controller and service
    - Validate amount via `AmountValidator`; check dispenser via `DispenserService`
    - Build `CheckRequest` DTO with: `amount`, `service_id`, `agent_transaction_id`,
      `params.card_expire`, `params.requestorPhone`, `account` (masked card)
    - Call `PaymentProviderInterface::check()` with 30s timeout
    - On success: save `check_transaction_id`, set status `PAY_PENDING`, log transition,
      return `{ status, check_transaction_id, available_denominations }`
    - On provider failure: set status `CHECK_FAILED` → `FAILED`; return `422`/`402` per error type
    - On timeout: return `504 PROVIDER_TIMEOUT`
    - Log full request and response to `transaction_logs` (Req 4.7)
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 4.8_

  - [ ]* 5.4 Write unit tests for amount validation and check flow
    - Test all boundary values: 9 999, 10 000, 5 000 000, 5 000 001, 15 500 (not multiple)
    - Test dispenser insufficient scenario
    - Test provider timeout triggers correct status transition
    - _Requirements: 3.3, 3.5, 3.6, 3.7, 4.3, 4.5_

- [ ] 6. Payment Provider Adapter layer
  - [ ] 6.1 Define `PaymentProviderInterface` / `PaymentProviderPort` and DTOs
    - PHP: `PaymentProviderInterface` with `check()`, `pay()`, `refund()`, `getStatus()` in
      `app/Payment/Contracts/`
    - Java: `PaymentProviderPort` interface in `payment/port/`
    - PHP & Java: Define DTO classes `CheckRequest`, `CheckResponse`, `PayRequest`, `PayResponse`,
      `RefundRequest`, `RefundResponse`, `TransactionStatusResponse`
    - _Requirements: 14.1, 14.4_

  - [ ] 6.2 Implement `OsonAdapter` and `PaynetAdapter`
    - PHP: `OsonAdapter` and `PaynetAdapter` implementing `PaymentProviderInterface`
    - Java: `OsonAdapter` and `PaynetAdapter` implementing `PaymentProviderPort` (Spring `@Component`)
    - Map provider-specific response fields → `InternalCheckResponse` / `InternalPayResponse`
    - Read config from `.env`/`application.yml`: `PAYMENT_PROVIDER`, `OSON_API_KEY`,
      `PAYNET_MERCHANT_ID`, etc.
    - `sandbox_mode: true` must prevent real transactions
    - _Requirements: 14.1, 14.4, 14.5_

  - [ ] 6.3 Implement `PaymentProviderFactory` / Spring `@Configuration` selector
    - PHP: `PaymentProviderFactory::make(): PaymentProviderInterface` — reads `PAYMENT_PROVIDER`
      env var and returns `OsonAdapter` or `PaynetAdapter`
    - Java: `PaymentProviderConfig` `@Bean` method that conditionally provides the correct adapter
    - Switching providers must require only a config change, no code change
    - _Requirements: 14.1_

  - [ ] 6.4 Implement exponential backoff retry and full request/response logging
    - PHP: `RetryableHttpClient` wrapper — 3 retries with delays 0s, 1s, 2s, 4s
    - Java: Use Resilience4j `Retry` with exponential backoff: same delay sequence
    - Log every request and response (`method`, `url`, `request_body`, `response_body`,
      `http_status`, `duration_ms`) to `transaction_logs` with `type=PROVIDER_CALL`
    - _Requirements: 14.2, 14.3_

  - [ ]* 6.5 Write unit tests for payment adapters
    - Mock HTTP client; test `OsonAdapter` maps response fields correctly to internal DTO
    - Mock HTTP client; test `PaynetAdapter` maps response fields correctly
    - Test retry fires exactly 3 times on network error then throws
    - Test `sandbox_mode=true` blocks real calls
    - _Requirements: 14.3, 14.4, 14.5_

- [ ] 7. Checkpoint — verify core transaction flow end-to-end
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 8. Pay Request, Dispenser control, and transaction completion
  - [ ] 8.1 Implement `POST /api/v1/transactions/{id}/pay` endpoint
    - PHP: `TransactionController@pay` → `TransactionService@pay`
    - Java: matching controller and service
    - Verify transaction is in `PAY_PENDING` state; reject otherwise
    - Build `PayRequest` DTO: `transaction_id`, `currencyId`, `checkTransactionId`, `params.sms_code`
    - Call `PaymentProviderInterface::pay()` with 30s timeout
    - On pay success: set status `DISPENSING`, save `provider_transaction_id`, log transition
    - On provider rejection: set status `FAILED`; return `402 PAYMENT_DECLINED`
    - On timeout: set status `FAILED`; return `504`
    - Log full Pay_Request and response (Req 5.8)
    - _Requirements: 5.1, 5.2, 5.5, 5.6, 5.8_

  - [ ] 8.2 Implement Dispenser TCP client and cash dispensing
    - PHP: `DispenserClient@dispense(int $kioskId, int $amount): DispenserResponse`
      — TCP connection to `DISPENSER_TCP_HOST:DISPENSER_TCP_PORT` with 60s timeout
    - Java: `DispenserClient.dispense(long kioskId, long amount)` — same TCP logic
    - On dispenser success: set status `COMPLETED`; decrement `dispenser_inventory.quantity`
      for the dispensed denominations; log transition
    - On dispenser error: set status `DISPENSE_FAILED`; trigger auto-refund job
    - On 60s timeout: set status `DISPENSE_TIMEOUT`; send admin notification
    - _Requirements: 5.3, 5.4, 5.7, 10.3, 15.1, 15.5_

  - [ ] 8.3 Implement double-entry financial recording on COMPLETED
    - PHP: `FinancialService@recordCompletion(Transaction $tx)` — wrapped in DB transaction
    - Java: `FinancialService.recordCompletion(Transaction tx)` — wrapped in `@Transactional`
    - Compute `commission = ROUND(amount × commission_rate / 100)`
    - Compute `net_credit = amount - commission`
    - Insert `deposit_movements` CREDIT record for `net_credit`; update `agent_deposits.balance`
    - Insert `deposit_movements` DEBIT record for `commission` to commission pool
    - All 4 DB writes in one atomic transaction; rollback to `FAILED` on any failure
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5_

  - [ ]* 8.4 Write unit tests for pay, dispenser, and financial recording
    - Test COMPLETED path: correct status, inventory decremented, deposit credited
    - Test DISPENSE_FAILED path: status set, refund job enqueued
    - Test DISPENSE_TIMEOUT: status set, admin notified
    - Test financial rollback: if deposit write fails, transaction → FAILED
    - _Requirements: 5.3, 5.4, 7.4, 7.5, 15.1, 15.5_

- [ ] 9. Transaction status API and GET endpoints
  - [ ] 9.1 Implement `GET /api/v1/transactions/{id}/status` and search endpoint
    - PHP: `TransactionController@status` and `TransactionController@index`
    - Java: matching controller methods
    - `GET /status`: return full status object per design §5.2
    - `GET /transactions`: support query params `agent_transaction_id`, `status`, `from`, `to`;
      paginate results
    - Both ADMIN, AGENT (own), KIOSK (own session) can call status endpoint
    - _Requirements: 6.4, 6.5_

  - [ ] 9.2 Implement audit logging for all mutations
    - PHP: `AuditLogger@log(User $user, string $action, string $entityType, int $entityId,
      array $oldValue, array $newValue, string $ip)` — writes to `audit_logs`
    - Java: `AuditLogService.log(...)` — same fields
    - Hook into: login, logout, refund initiation, dispenser fill, reward crediting
    - _Requirements: 13.7_

- [ ] 10. Refund flow (ADMIN only)
  - [ ] 10.1 Implement `POST /api/v1/transactions/{id}/refund` endpoint
    - PHP: `TransactionController@refund` → `TransactionService@refund` — ADMIN role only
    - Java: matching with `@PreAuthorize("hasRole('ADMIN')")`
    - Validate: transaction must be `COMPLETED` or `DISPENSE_FAILED`; reject others with `409`
    - Reject with `409 ALREADY_REFUNDED` if already in `REFUNDED` state
    - Call `PaymentProviderInterface::refund()` with confirmation to provider
    - On provider success: reverse deposit movement (DEBIT from agent deposit),
      set status `REFUNDED`, write `transaction_logs` refund entry, write audit log
    - Log provider refund request and response
    - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5, 11.6, 11.7, 11.8_

  - [ ] 10.2 Implement auto-refund job for DISPENSE_FAILED
    - PHP: `ProcessRefundJob` — dispatched by `DispenserClient` on `DISPENSE_FAILED`
    - Java: `@Async` `ProcessRefundService.autoRefund(Long transactionId)` — triggered from
      `DispenserClient`
    - Same refund logic as manual refund; mark `failure_reason = AUTO_REFUND_DISPENSE_FAILED`
    - Send SMS + system notification to admin and agent (Req 15.2)
    - _Requirements: 15.1, 15.2_

  - [ ]* 10.3 Write unit tests for refund flow
    - Test manual ADMIN refund: provider called, deposit debited, status REFUNDED
    - Test double-refund rejection: 409 ALREADY_REFUNDED
    - Test auto-refund on DISPENSE_FAILED: job enqueued, notification sent
    - _Requirements: 11.5, 11.6, 15.1, 15.2_

- [ ] 11. Circuit breaker implementation
  - [ ] 11.1 Implement Circuit Breaker for Payment Provider and Dispenser calls
    - PHP: `CircuitBreaker` class using Redis to track error counts and state
      (`CLOSED` / `OPEN` / `HALF_OPEN`); wrap `PaymentProviderInterface` and `DispenserClient`
    - Java: Configure Resilience4j `CircuitBreaker` beans in `CircuitBreakerConfig.java`
      for `PaymentProviderPort` and `DispenserClient`
    - CLOSED → OPEN: error rate > 50% with ≥ 5 errors in 60s window
    - OPEN → HALF_OPEN: after 30s; single probe request; success → CLOSED, failure → OPEN
    - Return `503 SERVICE_UNAVAILABLE` immediately when circuit is OPEN
    - _Requirements: 15.4_

  - [ ] 11.2 Implement system restart recovery for stuck transactions
    - PHP: `RecoveryService@recoverPendingTransactions()` — called at application boot
    - Java: `@EventListener(ApplicationReadyEvent.class)` in `RecoveryService`
    - Query all transactions in `PAY_PENDING` or `DISPENSING` states
    - Call `PaymentProviderInterface::getStatus()` for each; reconcile actual state
    - Transition each to `COMPLETED`, `FAILED`, or `REFUNDED` based on provider response
    - _Requirements: 15.3_

  - [ ]* 11.3 Write unit tests for circuit breaker
    - Test CLOSED → OPEN transition after 5+ errors in 60s
    - Test OPEN returns 503 without calling provider
    - Test HALF_OPEN probe success transitions to CLOSED
    - _Requirements: 15.4_

- [ ] 12. Checkpoint — verify payment, dispenser, refund, and error handling
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 13. Dispenser inventory management API
  - [ ] 13.1 Implement `POST /api/v1/kiosks/{id}/dispenser/fill` endpoint
    - PHP: `DispenserController@fill` → `DispenserService@fill` — AGENT or ADMIN role
    - Java: matching controller and service
    - Accept `fills[]` array of `{ denomination, quantity }` pairs plus `notes`
    - Upsert `dispenser_inventory` rows; insert `dispenser_fill_history` record
    - Write audit log entry
    - Return updated inventory totals per design §5.4
    - _Requirements: 10.1, 10.2_

  - [ ] 13.2 Implement `GET /api/v1/kiosks/{id}/dispenser/inventory` and `/history`
    - PHP & Java: read-only controller methods
    - Inventory: return all denominations with `quantity`, `total`, and `status`
      (`OK` | `LOW` when quantity < `min_threshold`)
    - History: paginated fill history with `from`/`to`/`page`/`per_page` query params
    - _Requirements: 10.5, 10.6_

  - [ ] 13.3 Implement low-inventory alert notification
    - PHP: `LowDispenserAlert` notification dispatched when any denomination quantity
      drops below `min_threshold` (default 20) — after each successful dispense or fill
    - Java: `NotificationService.sendLowDispenserAlert(kioskId, denomination, quantity)`
    - Send alert to both agent (owner of kiosk) and all ADMIN users via SMS gateway
    - _Requirements: 10.4_

- [ ] 14. Agent financial APIs (deposit balance, movements, rewards)
  - [ ] 14.1 Implement `GET /api/v1/agents/{id}/deposit/balance` and `/movements`
    - PHP: `AgentController@depositBalance` and `AgentController@depositMovements`
    - Java: matching controller methods
    - AGENT role: can only access own `agent_id` (validate `agent_id` from JWT claim matches path)
    - ADMIN: can access any agent
    - Movements: paginated, filterable by `type` (CREDIT/DEBIT), `from`, `to`
    - _Requirements: 7.6, 12.2, 12.3_

  - [ ] 14.2 Implement `GET /api/v1/agents/{id}/rewards`
    - PHP: `AgentController@rewards`; Java: matching controller
    - Return list of `agent_rewards` rows for the agent
    - Include `year`, `month`, `total_turnover`, `reward_rate`, `reward_amount`, `status`
    - _Requirements: 9.5, 12.2_

- [ ] 15. Scheduled jobs: daily reconciliation and monthly rewards
  - [ ] 15.1 Implement `DailyReconciliationJob` / `DailyReconciliationScheduler`
    - PHP: `DailyReconciliationJob` (Laravel Queue + Cron `59 23 * * *`)
    - Java: `DailyReconciliationScheduler` with `@Scheduled(cron = "59 23 * * *", zone = "Asia/Tashkent")`
    - For each active agent: aggregate all `COMPLETED` transactions for the day
    - Compute totals: `total_transactions`, `total_amount`, `total_commission`,
      `success_count`, `failed_count`
    - Compare with `deposit_movements` to detect discrepancies; populate `discrepancies` JSON
    - Generate PDF and CSV files; save paths to `reconciliation_reports`
    - _Requirements: 8.1, 8.2, 8.3, 8.4_

  - [ ] 15.2 Implement `MonthlyRewardCalculationJob` / `MonthlyRewardScheduler`
    - PHP: `MonthlyRewardCalculationJob` (Cron `59 23 L * *`)
    - Java: `MonthlyRewardScheduler` with `@Scheduled(cron = "59 23 28-31 * *")` + last-day check
    - For each active agent: `monthly_turnover = SUM(amount) WHERE status=COMPLETED AND month=current`
    - `reward = ROUND(monthly_turnover × agent.reward_rate)`
    - Insert `agent_rewards` record; insert `deposit_movements` CREDIT for reward amount;
      update `agent_deposits.balance`
    - Send notification to agent (SMS + system)
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.6_

  - [ ]* 15.3 Write unit tests for scheduled jobs
    - Test reconciliation correctly detects discrepancy when deposit movement total ≠ transaction total
    - Test reward formula: `ROUND(500_000_000 × 0.005) = 2_500_000`
    - Test reward rate is taken from `agents.reward_rate`, not a global constant
    - _Requirements: 8.4, 9.2, 9.6_

- [ ] 16. Reconciliation and report download API
  - [ ] 16.1 Implement reconciliation query and download endpoints
    - PHP: `AgentController@reconciliation` → `GET /api/v1/agents/{id}/reconciliation?date=`
    - Java: matching controller method
    - Return reconciliation summary + download URLs for PDF and CSV
    - Serve actual files via `GET /api/v1/agents/{id}/reconciliation/download?date=&format=pdf|csv`
    - _Requirements: 8.3, 8.5_

  - [ ] 16.2 Implement general report export endpoint
    - PHP & Java: `GET /api/v1/agents/{id}/report?from=&to=&format=xlsx|csv`
    - For requests > 10 000 records: dispatch background job, return `202 Accepted` with a
      `job_id`; provide `GET /api/v1/reports/{job_id}/download` when complete
    - For ≤ 10 000 records: stream file inline
    - _Requirements: 12.3, 12.4, 12.6_

- [ ] 17. Admin APIs
  - [ ] 17.1 Implement admin transaction and kiosk/agent list endpoints
    - PHP & Java: `GET /api/v1/admin/transactions` with full filter set:
      `agent_id`, `kiosk_id`, `status`, `from`, `to`, `page`, `per_page` (max 100)
    - `GET /api/v1/admin/kiosks` — list all kiosks with latest status and inventory summary
    - `GET /api/v1/admin/agents` — list all agents with deposit balance and reward summary
    - ADMIN role only
    - _Requirements: 12.1, 12.3_

  - [ ] 17.2 Implement `GET /api/v1/admin/reports/summary`
    - Support `period=daily|weekly|monthly` and `date` query params
    - Return: total transactions, total amount, success/fail counts, total commission,
      per-kiosk and per-agent breakdown
    - _Requirements: 12.1, 12.5_

- [ ] 18. Agent Cabinet dashboard and reward preview
  - [ ] 18.1 Implement real-time dashboard summary endpoint for agents
    - PHP & Java: `GET /api/v1/agents/{id}/dashboard` (or include in existing agent endpoint)
    - Return current month: `total_amount`, `transaction_count`, `estimated_reward`
      = `ROUND(total_amount × agent.reward_rate)`
    - Agent can only see own data
    - _Requirements: 12.5_

- [ ] 19. OpenAPI documentation
  - [ ] 19.1 Generate/write OpenAPI 3.0 specification for all endpoints
    - PHP: Add Swagger/OpenAPI annotations to all controllers (use `l5-swagger` or equivalent)
    - Java: Add `@Operation`, `@ApiResponse`, `@Schema` annotations to all controllers
      and DTOs (SpringDoc OpenAPI)
    - Cover all 21 endpoints listed in design §5; include request/response schemas,
      error codes, and security schemes (Bearer JWT)
    - _Requirements: Technical Constraints (OpenAPI 3.0)_

- [ ] 20. Final checkpoint — full test suite and integration verification
  - Ensure all tests pass, ask the user if questions arise.

---

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP delivery
- PHP and Java implementations are parallel — tasks describe both; pick the stack in use or implement both
- All monetary arithmetic must use integer arithmetic (`BIGINT`); no floating-point for financial values
- Card masking must be enforced at the model/service layer — never store or log the raw 16-digit PAN
- The Dispenser TCP client must enforce a hard 60-second read timeout (Req 15.5)
- Circuit breaker state is shared across instances via Redis (not in-memory only)
- Reconciliation PDF/CSV generation should use a headless library (e.g., TCPDF for PHP, iText/Apache PDFBox for Java)
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation at key milestones

---

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1"] },
    { "id": 1, "tasks": ["1.2", "2.1"] },
    { "id": 2, "tasks": ["2.2", "2.3", "6.1"] },
    { "id": 3, "tasks": ["2.4", "3.1", "6.2"] },
    { "id": 4, "tasks": ["3.2", "6.3", "6.4"] },
    { "id": 5, "tasks": ["3.3", "6.5", "4.1"] },
    { "id": 6, "tasks": ["4.2", "5.1", "5.2"] },
    { "id": 7, "tasks": ["4.3", "5.3"] },
    { "id": 8, "tasks": ["5.4", "8.1", "9.2"] },
    { "id": 9, "tasks": ["8.2", "8.3", "9.1"] },
    { "id": 10, "tasks": ["8.4", "10.1", "11.1"] },
    { "id": 11, "tasks": ["10.2", "11.2", "13.1"] },
    { "id": 12, "tasks": ["10.3", "11.3", "13.2", "13.3"] },
    { "id": 13, "tasks": ["14.1", "14.2", "15.1"] },
    { "id": 14, "tasks": ["15.2", "16.1"] },
    { "id": 15, "tasks": ["15.3", "16.2", "17.1"] },
    { "id": 16, "tasks": ["17.2", "18.1"] },
    { "id": 17, "tasks": ["19.1"] }
  ]
}
```
