@echo off
set PGPASSWORD=itu16
:loop
psql -U postgres -d crypto -h localhost -c "SELECT update_crypto_variation();"
timeout /t 10
goto loop