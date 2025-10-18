# PHP 8.2 CLI
FROM php:8.2-cli

WORKDIR /app

# Projeyi kopyala
COPY . .

# Uygulama portu (göstermek opsiyonel)
EXPOSE 10000

# Not: Render trafiği $PORT'a yönlendirir. $PORT yoksa 10000'e düşer.
CMD bash -lc "mkdir -p data && php -S 0.0.0.0:${PORT:-10000} router.php"
