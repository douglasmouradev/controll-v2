-- Adicionar coluna support_response na tabela tickets se não existir
ALTER TABLE tickets 
ADD COLUMN IF NOT EXISTS support_response TEXT NULL 
AFTER status;

