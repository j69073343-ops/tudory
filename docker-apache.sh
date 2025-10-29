#!/bin/sh
# Render PORT’u veriyor; Apache’yi ona dinleteceğiz
sed -ri "s/Listen 80/Listen ${PORT:-8080}/" /etc/apache2/ports.conf
sed -ri "s!<VirtualHost \*:80>!<VirtualHost \*:${PORT:-8080}>!" /etc/apache2/sites-available/000-default.conf
exec apache2-foreground
