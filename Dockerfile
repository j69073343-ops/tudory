# Basit PHP 8.2 ortamı (Render uyumlu)
FROM php:8.2-cli

# Çalışma dizini
WORKDIR /app

# Kodları kopyala
COPY . .

# Uygulama portu
EXPOSE 10000

# Router ile başlat: data klasörünü garanti oluştur + built-in server
CMD bash -c "mkdir -p data && php -S 0.0.0.0:10000 router.php"
