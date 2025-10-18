# Basit PHP ortamı (Render uyumlu)
FROM php:8.2-cli

# Çalışma dizini
WORKDIR /app

# Kodları kopyala
COPY . .

# Gerekli portu expose et
EXPOSE 10000

# Uygulamayı başlat
CMD bash -c "mkdir -p data && php -S 0.0.0.0:10000 -t ."
