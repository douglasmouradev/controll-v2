-- Adiciona status Agendado (execute uma vez no banco de produção)
INSERT INTO ticket_statuses (name, slug, color, is_final)
SELECT 'Agendado', 'agendado', '#8b5cf6', 0
WHERE NOT EXISTS (
    SELECT 1 FROM ticket_statuses WHERE name = 'Agendado' OR slug = 'agendado'
);
