-- Índices para acelerar resumos e extratos de credit_history
CREATE INDEX IF NOT EXISTS idx_credit_history_type_user ON credit_history (type, user_id);
CREATE INDEX IF NOT EXISTS idx_credit_history_user_created ON credit_history (user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_credit_history_type_created ON credit_history (type, created_at);
