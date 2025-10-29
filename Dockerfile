FROM php:8.2-apache

# PHP + Apache
RUN a2enmod rewrite

# Uygulamayı kopyala
COPY . /var/www/html

# İzinler (data klasörü persist edilecek ama ilk kurulumda da yazılabilir olsun)
RUN chown -R www-data:www-data /var/www/html \
  && find /var/www/html -type d -exec chmod 775 {} \; \
  && find /var/www/html -type f -exec chmod 664 {} \;

# Render Docker’da $PORT değişkenine dinlemek gerekir
COPY docker-apache.sh /usr/local/bin/docker-apache.sh
RUN chmod +x /usr/local/bin/docker-apache.sh

CMD ["/usr/local/bin/docker-apache.sh"]
