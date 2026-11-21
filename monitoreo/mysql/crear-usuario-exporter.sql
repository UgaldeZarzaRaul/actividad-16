-- Usuario de solo lectura para mysqld-exporter
CREATE USER IF NOT EXISTS 'exporter'@'%' IDENTIFIED BY 'Exporter123!' WITH MAX_USER_CONNECTIONS 3;
CREATE USER IF NOT EXISTS 'exporter'@'localhost' IDENTIFIED BY 'Exporter123!' WITH MAX_USER_CONNECTIONS 3;
GRANT PROCESS, REPLICATION CLIENT, SELECT ON *.* TO 'exporter'@'%';
GRANT PROCESS, REPLICATION CLIENT, SELECT ON *.* TO 'exporter'@'localhost';
FLUSH PRIVILEGES;

-- Registro de consultas lentas (más de 1 segundo) para el panel "Consultas lentas de MySQL"
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1;
