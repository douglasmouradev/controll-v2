-- Índices para filtros frequentes na listagem de chamados
CREATE INDEX IF NOT EXISTS idx_tickets_unit ON tickets (unit(20));
CREATE INDEX IF NOT EXISTS idx_tickets_city ON tickets (city(30));
CREATE INDEX IF NOT EXISTS idx_tickets_user_id ON tickets (user_id);
CREATE INDEX IF NOT EXISTS idx_tickets_created_at ON tickets (created_at);
