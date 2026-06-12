USE penjualan_online;

ALTER TABLE orders
    MODIFY status ENUM('pending', 'shipped', 'completed', 'refund_requested', 'refunded', 'cancelled') NOT NULL DEFAULT 'pending',
    ADD COLUMN cancellation_reason TEXT NULL AFTER status,
    ADD COLUMN refund_reason TEXT NULL AFTER cancellation_reason,
    ADD COLUMN shipped_at TIMESTAMP NULL DEFAULT NULL AFTER refund_reason,
    ADD COLUMN completed_at TIMESTAMP NULL DEFAULT NULL AFTER shipped_at,
    ADD COLUMN refunded_at TIMESTAMP NULL DEFAULT NULL AFTER completed_at,
    ADD COLUMN cancelled_at TIMESTAMP NULL DEFAULT NULL AFTER refunded_at;

UPDATE orders
SET status = 'completed', completed_at = COALESCE(completed_at, created_at)
WHERE status NOT IN ('cancelled', 'refunded');
