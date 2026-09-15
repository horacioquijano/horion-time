FROM php:8.2-apache

# Librerías de sistema necesarias para compilar la extensión gd
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      zlib1g-dev \
      libpng-dev \
      libjpeg62-turbo-dev \
      libfreetype6-dev \
 && rm -rf /var/lib/apt/lists/*

# Extensiones PHP que usa HORION TIME
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install pdo_mysql mysqli gd

# Rewrite + permitir .htaccess
RUN a2enmod rewrite \
 && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Límites para fotos y PDFs
RUN echo "upload_max_filesize=20M\npost_max_size=25M\nmemory_limit=256M\nmax_execution_time=120" \
    > /usr/local/etc/php/conf.d/horion.ini

# DocumentRoot = raíz del repo → la app vive en /horion-time/public/ igual que en XAMPP
COPY . /var/www/html/

# Carpetas de uploads con permisos
RUN mkdir -p /var/www/html/horion-time/public/uploads/marcaciones \
             /var/www/html/horion-time/public/uploads/novedades \
             /var/www/html/horion-time/public/uploads/horas_extras \
             /var/www/html/horion-time/public/uploads/empresas \
 && chown -R www-data:www-data /var/www/html/horion-time/public/uploads \
 && chmod -R 775 /var/www/html/horion-time/public/uploads

EXPOSE 80
CMD ["apache2-foreground"]