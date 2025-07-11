@echo off
echo Iniciando o servidor Laravel...
echo.
echo Verificando se o arquivo .env existe...
if not exist .env (
    echo Copiando .env.example para .env...
    copy .env.example .env
    echo.
    echo Gerando chave da aplicação...
    php artisan key:generate
    echo.
)

echo Executando migrações...
php artisan migrate

echo.
echo Iniciando servidor em http://localhost:8000
echo.
php artisan serve --host=0.0.0.0 --port=8000
pause
